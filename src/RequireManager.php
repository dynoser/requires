<?php
namespace dynoser\requires;

use dynoser\autoload\AutoLoader;
use dynoser\autoload\AutoLoadSetup;
use dynoser\autoload\DynoImporter;
use dynoser\requires\RequiresFiles;

class RequireManager {
    use \dynoser\requires\ComposerWorks;
    use \dynoser\requires\DownLoader;

    public bool $echoOn = false;

    public const OUR_AUTO_LOADER_CLASS = '\dynoser\autoload\AutoLoader';
    public const HELML_CLASS = '\dynoser\HELML\HELML';

    public string $vendorDir;
    public string $extDir = '';
    public bool $extChanged = true;
    public array $extListArr = []; // [n] => chortExtSubFolder

    public string $classesDir;
    public array $classesFoldersArr = []; // [n] => fullClassesSubFolder
    public bool $classesChanged = true;

    public array $otherFoldersArr = []; // [n] => fullOtherFolderPath
    
    public array $aliasesArr = []; // [aliasTO] => classFROM
    public bool $aliasesChanged = false;
    
    public RequiresFiles $reqFilesObj;

    public array $successMsgArr = [];
    public array $errorsMsgArr = [];
    public string $fullClassName = ''; // only for messages
    public string $fullFileName = ''; // only for messages
    
    const REQUIRE_FILE_NAME_NO_EXT = 'requires';
    
    const ADD_BASE_URLS = 'urlbases';
    const REQ_NAMESPACES = 'namespaces';
    const REQ_ALIASES = 'aliases';
    
    const URL_SPEC = 'url';

    const DO_NOT_AUTO_LOAD = 'dontautoload';
    const DO_NOT_UPDATE = 'donotupdate';
    const LOAD_FROM_PATH = 'frompath';
    const LOAD_BY_COMPOSER = 'composer';
    const LOAD_REQ_FROM = 'requirefrom';
    const LOAD_FILES = 'files';
    const TARGET_FOLDER = 'target';
    const  TARGET_VENDOR = 'vendor';
    const  TARGET_MODULE = 'ext';
    const  TARGET_CLASSES = 'classes';
    const  TARGET_CURRENT = '.';
    const CHECK_FILES = 'checkfiles';
    const CLASS_FOR_ALIAS = 'alias';
    
    const REQUIRES_FILE_SET = 'requires_file_set';
    const REQUIRES_FILE_BASE = 'requires_file_base';

    public $valuesArr = [];
    
    public array $requireExtArr = [];
    
    public array $requireResolvedArr = []; // [require.* file full path] => true or false, true = resolved
    
    public function errorPush(string $msg, string $addAfter = null, bool $isFatal = false, $errorCodeArr = null) {
        if (\is_null($addAfter) && $this->fullClassName) {
            $addAfter = ", class " . $this->fullClassName;
        }
        $msg .= $addAfter;
        $this->errorsMsgArr[] = $msg;
        if ($isFatal) {
            if ($errorCodeArr) {
                $msg .= "Error in file: {$this->fullFileName} \nError Code: " . print_r($errorCodeArr, true);
            }
            throw new \Exception("FATAL ERROR: $msg \n". "Other errors:" . \print_r($this->errorMsgArr, true));
        }
    }
    
    public function successPush(string $msg, string $addAfter = null) {
        if (\is_null($addAfter) && $this->fullClassName) {
            $addAfter = ", class " . $this->fullClassName;
        }
        $this->successMsgArr[] = $msg . $addAfter;
    }
    
    public function __construct(string $vendorDir = null, string $classesDir = null, string $extDir = null) {        
        // vendorDir resolve
        $vendorDir = $vendorDir ? $vendorDir : AutoLoadSetup::$vendorDir;
        $this->vendorDir = \realpath($vendorDir);
        if (!$this->vendorDir) {
            if (!\mkdir($vendorDir)) {
                throw new \Exception("Not found vendor dir: $vendorDir , can't auto-create");
            }
            $this->vendorDir = \realpath($vendorDir);
        }
        $this->vendorDir = \strtr($this->vendorDir, '\\', '/');
        
        // check AutoLoader
        $ourAutoLoaderClass = self::OUR_AUTO_LOADER_CLASS;
        if (!\class_exists($ourAutoLoaderClass, false)) {
            throw new \Exception("$ourAutoLoaderClass required");
        }
        if (!\defined('DYNO_FILE')) {
            throw new \Exception("$ourAutoLoaderClass incorrect, DYNO_FILE constant required");
        }
        
        // classesDir resolve
        $classesDir = $classesDir ? $classesDir : AutoLoadSetup::$classesDir;
        if (!\is_string($classesDir)) {
            throw new \Exception('Classes dir must be specified');
        }
        $this->classesDir = \realpath($classesDir);
        if (!$this->classesDir) {
            $classesDir = \strtr($classesDir, '\\', '/');
            if (\is_dir($vendorDir) && \dirname($this->vendorDir) === \dirname($classesDir, 2)) {
                \mkdir($classesDir, 0777, true);
            }
            if (!\is_dir($classesDir)) {
                throw new \Exception('Classes dir not found, it is required: $classesDir');
            }
            $this->classesDir = $classesDir;
        }
        $this->classesDir = \strtr($this->classesDir, '\\', '/');
        
        // extDir resolve
        $extDir = $extDir ? $extDir : AutoLoadSetup::$extDir;
        $this->extDir = (\is_string($extDir) && $extDir) ? \strtr($extDir, '\\', '/') : '';

        $this->reqFilesObj = new RequiresFiles();
        
        $this->requireExtArr['.json'] = [$this, 'loadJSONfile'];
    }

    public static function loadJSONfile($fullFileJSON) {
        $dataStr = \file_get_contents($fullFileJSON);
        if ($dataStr) {
            try {
                return \json_decode($dataStr, \JSON_THROW_ON_ERROR || \JSON_OBJECT_AS_ARRAY);
            } catch (\Exception $e) {
                return null;
            }
        }
        return null;
    }
    
    public static function loadHELMLfile($fullFileHELML) {
        $dataStr = \file_get_contents($fullFileHELML);
        if ($dataStr) {
            try {
                return \dynoser\HELML\HELML::decode($dataStr);
            } catch (\Exception $e) {
                return null;
            }
        }
        return null;
    }
    
    public function run() {
        $this->composerWorksInit();
        $this->downLoaderInit();
        
        // check helml and install if need
        if (!\class_exists('dynoser\\HELML\\HELML')) {
            $this->composerUpdate();
        }
        if (\class_exists(self::HELML_CLASS)) {
            if (!\array_key_exists('.helml', $this->requireExtArr)) {
                $this->requireExtArr['.helml'] = [$this, 'loadHELMLFile'];
            }
        } else {
            throw new \Exception("Not found " . self::HELML_CLASS . " class, required");
        }
        
        $this->reqFilesObj->clearAllRequiresCachedFiles();
        
        // folders for scan requires-files
        $this->otherFoldersArr = [
            AutoLoadSetup::$rootDir,
            AutoLoadSetup::$classesDir,
            AutoLoadSetup::$extDir,
        ];
        
        $this->requireResolvedArr = [];
        $this->aliasesArr = AutoLoadSetup::$dynoObj->getAliases();
        do {
            if ($this->composerChanged) {
                $this->composerUpdate();
                $this->composerChanged = false;
            }
            do {
                [$totalDepChangesMaked, $totalDepNeedReCheck] = $this->walkAllResolve();
                
                // out success messages
                if ($this->successMsgArr) {
                    foreach($this->successMsgArr as $msg) {
                        echo "$msg \n";
                    }
                    $this->successMsgArr = [];
                }
            } while ($totalDepChangesMaked || $totalDepNeedReCheck);
            if ($this->errorsMsgArr) {
                echo "ERRORS: \n";
                foreach($this->errorsMsgArr as $msg) {
                    echo "$msg \n";
                }
            }
        } while ($this->composerChanged);
        return false;
    }
    
    public function scanNewRequiresArrayFiles(
        array $whereScanThisArrKeysArr = [
            'extListArr' => 'ext',
            'classesFoldersArr' => 'classes',
            'otherFoldersArr' => 'other'
        ]
    ): array {
        $reqFilesArr = []; // [fullFileName] => array or null
        foreach($whereScanThisArrKeysArr as $arrKey => $setName) {
            foreach($this->$arrKey as $pathItem) {
                switch ($arrKey) {
                    case 'extListArr':
                        $fullBasePath = $this->extDir . '/' . $pathItem;
                        break;
                    default:
                        $fullBasePath = $pathItem;
                }

                $fullRequireFileBase = $fullBasePath . '/' . self::REQUIRE_FILE_NAME_NO_EXT;

                foreach($this->requireExtArr as $ext => $unpacker) {
                    $fullFile = $fullRequireFileBase . $ext;
                    if (empty($this->requireResolvedArr[$fullFile]) && \is_file($fullFile)) {
                        $requiresArr = $unpacker($fullFile);
                        if ($requiresArr && \is_array($requiresArr)) {
                            $this->reqFilesObj->saveToCache($fullFile, $requiresArr, $setName);
                            $reqFilesArr[$fullFile] = $requiresArr;
                        } else {
                            $reqFilesArr[$fullFile] = null;
                        }
                    }
                }
            }
        }
        return $reqFilesArr;
    }
    
    public function walkAllResolve() {
        $this->errorsMsgArr = [];
        $totalDepChangesMaked = 0;
        $totalDepNeedReCheck = 0;

        if ($this->classesChanged) {
            $this->classesFoldersArr = DynoImporter::getFoldersArr($this->classesDir, true);
            $this->classesChanged = false;
        }

        if ($this->extChanged) {
            $this->extListArr = DynoImporter::getFoldersArr($this->extDir, false); // [n] => shortSubFolder
            $this->extChanged = false;
        }
        
        $reqFilesArr = $this->scanNewRequiresArrayFiles();
        foreach($reqFilesArr as $fullFile => $requireArr) {
            $this->fullFileName = $fullFile; // for messages
            if ($requireArr) {
                [$depChangesMaked, $depNeedReCheck] = $this->checkDepends($requireArr);
                $totalDepChangesMaked += $depChangesMaked;
                $totalDepNeedReCheck += $depNeedReCheck;
                $isResolved = empty($depChangesMaked) && empty($depNeedReCheck);
            } else {
                $this->errorPush("Incorrect requires$ext file");
                $isResolved = true;
            }
            $this->requireResolvedArr[$fullFile] = $isResolved;
            if ($isResolved) {
                $this->reqFilesObj->removeCachedFile($fullFile);
            }
            if ($this->composerChanged || $this->aliasesChanged) {
                AutoLoadSetup::updateFromComposer();
            }
        }
        return [$totalDepChangesMaked, $totalDepNeedReCheck];
    }
    
    public function checkDepends(array $requireArr): array {
        $ourAutoLoaderClass = self::OUR_AUTO_LOADER_CLASS;
        $depChangesMaked = 0;
        $depNeedReCheck = 0;
        $this->valuesArr = [];
        $this->fullClassName = '';
        $fullBasePath = $requireArr[self::REQUIRES_FILE_BASE];
        foreach($requireArr as $key => $whatCanDoArr) {
            $fullClassName = \strtr($key, '/', '\\');
            if (!\strpos($fullClassName, '\\')) {
                $value = $whatCanDoArr;
                switch($key) {
                case self::ADD_BASE_URLS:
                    if (\is_string($value)) {
                        $value = [$value => true];
                    }
                    if (!\is_array($value)) {
                        $this->pushError("Illegal " . self::ADD_BASE_URLS . " type", null, true);
                    }
                    foreach($value as $baseUrl => $parameters) {
                        if ($this->addURLBase($baseUrl, $parameters)) {
                            $depChangesMaked++;
                        }
                    }
                    break;
                case self::REQ_ALIASES:
                    foreach($whatCanDoArr as $toClassName => $fromClassName) {
                        $toClassName = \strtr($toClassName, '/', '\\');
                        $fromClassName = \strtr($fromClassName, '/', '\\');
                        if (!isset($this->aliasesArr[$toClassName]) || $this->aliasesArr[$toClassName] !== $fromClassName) {
                            $this->aliasesArr[$toClassName] = $fromClassName;
                            $this->aliasesChanged = true;
                            $fromClassName = '?' . $fromClassName;
                            if (empty(AutoLoader::$classesArr[$toClassName]) || AutoLoader::$classesArr[$toClassName] !== $fromClassName) {
                                AutoLoader::$classesArr[$toClassName] = $fromClassName;
                                AutoLoader::$changed = true;
                            }
                        }
                    }
                    $whatCanDoArr = [];
                    // not break
                case self::REQ_NAMESPACES:
                    foreach($whatCanDoArr as $namespace => $fromPath) {
                        if (\is_string($fromPath)) {
                            $fromPath = \strtr($fromPath, '\\', '/');
                        }
                        if (empty(AutoLoader::$classesArr[$namespace]) || AutoLoader::$classesArr[$namespace] !== $fromPath) {
                            AutoLoader::$classesArr[$namespace] = $fromPath;
                            AutoLoader::$changed = true;
                        }
                    }
                    if (AutoLoader::$changed) {
                        AutoLoadSetup::updateFromComposer();
                        AutoLoader::$changed = false;
                    }
                default:
                    $this->valuesArr[$key] = $value;
                }
                continue;
            }
            $this->fullClassName = $fullClassName;
            $tryAutoLoad = empty($whatCanDoArr[self::DO_NOT_AUTO_LOAD]);            
            $chkFile = $ourAutoLoaderClass::autoLoad($fullClassName, $tryAutoLoad);
//            if ($tryAutoLoad && \is_string($chkFile) && !\class_exists($fullClassName, false)) {
//                require_once $chkFile;
//            }
            if (\class_exists($fullClassName, $tryAutoLoad)) {
                continue;
            }
            // class not loaded, need to resolve dep
            $depChangesMaked += $this->depChangesMake($fullBasePath, $fullClassName, $whatCanDoArr);
            
            $depNeedReCheck++;
        }
        return [$depChangesMaked, $depNeedReCheck];
    }
    
    public function depChangesMake(string $fullBasePath, string $fullClassName, array $whatCanDoArr): int {
        $depChangesMaked = 0;
        $ourAutoLoaderClass = self::OUR_AUTO_LOADER_CLASS;
        
        //calculate $targetPath
        $targetFolder = empty($whatCanDoArr[self::TARGET_FOLDER]) ? null : $whatCanDoArr[self::TARGET_FOLDER];
        $doNotUpdate  = empty($whatCanDoArr[self::DO_NOT_UPDATE]) ? false : true;
        $tnLen = \strcspn($targetFolder, '\\/');
        $targetName = \substr($targetFolder, 0, $tnLen);
        switch ($targetName) {
            case self::TARGET_VENDOR:
                $i = \strpos($fullClassName, '\\');
                if (!$i) {
                    throw new \Exception("Illegal class name for vendor-named: $fullClassName");
                }
                $targetPath = $this->vendorDir . '/' . \substr($fullClassName, 0, $i);
                $targetPath .= \strtr(\substr($fullClassName, $i), '\\', '/');
                $targetFile = $targetPath . '.php';
                $targetPath = \dirname($targetFile);
                break;
            case self::TARGET_MODULE:
                $i = \strrpos($fullClassName, '\\');
                if (!$i) {
                    throw new \Exception("Illegal class name for module ext: $fullClassName");
                }
                $targetPath = $this->extDir . '/' . \substr($fullClassName, $i);
                break;
            case self::TARGET_CLASSES:
                $targetAdd = \substr($targetFolder, $tnLen);
                $targetPath = $this->classesDir . $targetAdd;
                if (empty($targetAdd)) {
                    $this->errorPush("Target must specified sub-folder in 'classes/sub-folders' format. Can't use classes-root folder", null, true);
                }
                break;
            case self::TARGET_CURRENT:
                $targetPath = $fullBasePath;
                break;
            default:
                $this->errorPush("Target must be specified.", null, true, $whatCanDoArr);
                $targetPath = null;
        }
        
        // Do required steps
        foreach($whatCanDoArr as $stepKey => $stepArr) {
            $changesCnt = 0;
            switch ($stepKey) {
                // let it work by default
//                case self::REQUIRES_FILE_BASE:
//                case self::REQUIRES_FILE_SET:
//                case self::CLASS_FOR_ALIAS:
//                case self::TARGET_FOLDER:
//                case self::DO_NOT_AUTO_LOAD:
//                case self::URL_SPEC;
//                    continue 2;
                case self::LOAD_FILES:
                    $urlSpecArr = $whatCanDoArr[self::URL_SPEC] ?? [];
                    if (\is_string($urlSpecArr)) {
                        $urlSpecArr = [$urlSpecArr => true];
                    }
                    if (!\is_array($urlSpecArr)) {
                        $this->errorPush("Illegal type for key " . self::URL_SPEC);
                        break;
                    }
                    $result = $this->LoadFiles($targetPath, $stepArr, $doNotUpdate, $urlSpecArr, \strlen($this->classesDir));
                    if (true === $result) {
                        $changesCnt++;
                    }
                    break;
                case self::LOAD_BY_COMPOSER:
                    $compoReq = $stepArr;
                    if (!\is_string($compoReq) || !\strpos($compoReq, '/')) {
                        throw new \Exception("Illegal name for composer package: $compoReq");
                    }
                    if (!\array_key_exists(self::CHECK_FILES, $whatCanDoArr)) {
                        throw new \Exception("Key " . self::CHECK_FILES . " is requred for composer installing mode");
                    }
                    $basePath = $this->vendorDir . '/' . $compoReq;
                    if (empty($whatCanDoArr[self::CHECK_FILES])) {
                        throw new \Exception('[' . self::CHECK_FILES . "] array can't be empty for $fullClassName (composer = $compoReq)");
                    }
                    $checkNoFilesCntBefore = $this->checkNoFilesCnt($basePath, $whatCanDoArr[self::CHECK_FILES]);
                    if ($checkNoFilesCntBefore) {
                        $output = $this->composerRun('require --ignore-platform-reqs ' . \trim($compoReq));
                        $checkNoFilesCntAfter = $this->checkNoFilesCnt($basePath, $whatCanDoArr[self::CHECK_FILES]);
                        if ($checkNoFilesCntAfter !== $checkNoFilesCntBefore) {
                            $changesCnt++;
                        } else {
                            $this->errorPush('[' . self::CHECK_FILES . "] not found after 'composer require $compoReq'\n" .
                                    \print_r($whatCanDoArr[self::CHECK_FILES], true));
                        }
                    }
                    break;
                case self::LOAD_REQ_FROM:
                    if (!\is_string($targetPath)) {
                        throw new \Exception("Target required for [" . self::LOAD_REQ_FROM . "] key, class $fullClassName");
                    }
                    if (\is_string($stepArr)) {
                        $stepArr = [$stepArr];
                    }
                    if (!\is_array($stepArr)) {
                        throw new \Exception("Illegal requirefrom type, class $fullClassName");
                    }
                    $targetFile = $this->getFromArr($stepArr, $targetPath, 'require', ['.json', '.helml'], $doNotUpdate);
                    if ($targetFile) {
                        if (true !== $targetFile) {
                            $depChangesMaked++;
                            $this->classesChanged = true;
                        }
                    } elseif (false !== $targetFile) {
                        $this->errorPush("Can't download data [fromrequire]");
                    }
                    break;
                case self::LOAD_FROM_PATH:
                    if (\is_string($stepArr)) {
                        $stepArr = [$stepArr];
                    }
                    $changesCnt = $this->tryLoadFromPath($fullClassName, $stepArr);
                    break;
                default:
                    continue 2;
            }
            if ($changesCnt) {
                $depChangesMaked += $changesCnt;
                if (\class_exists($fullClassName, false)) {
                    break;
                }
            }
        }
        
        if (isset($whatCanDoArr[self::CLASS_FOR_ALIAS])) {
            $classForAlias = $whatCanDoArr[self::CLASS_FOR_ALIAS];
            if (!\is_string($classForAlias)) {
                throw new \Exception("alias must have string type (for $fullClassName )");
            }
            $classForAlias = \strtr($classForAlias, '/', '\\');
            if (!isset($this->aliasesArr[$fullClassName]) || $this->aliasesArr[$fullClassName] !== $classForAlias) {
                $this->aliasesArr[$fullClassName] = $classForAlias;
                $this->aliasesChanged = true;
            }
            if (!\class_exists($fullClassName, false) && \class_exists($classForAlias, true)) {
                if (\class_alias($classForAlias, $fullClassName)) {
                    $depChangesMaked++;
                }
            }
        }
        
        return $depChangesMaked;
    }

    public function tryLoadFromPath(string $fullClassName, array $fromPathArr) {
//        foreach($fromPathArr as )
    }
}
