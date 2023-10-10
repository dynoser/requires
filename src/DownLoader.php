<?php
namespace dynoser\requires;

trait DownLoader {
    public array $urlBases = []; // [url] => parameters
    
    public string $knownUrlBasesFile;

    public array $cachedDownLoadedArr = []; // [url] => content
    
    public function downLoaderInit() {
        return $this->loadKnownUrlBasesFile();
    }
    
    public function loadKnownUrlBasesFile(): bool {
        if (!\defined('DYNO_FILE')) {
            throw new \Exception("DYNO_FILE constant requried");
        }
        $changed = false;
        $kfile = $this->knownUrlBaseFile = \dirname(DYNO_FILE) . '/knownurlbases.php';
        if (\is_file($kfile)) {
            $arr = (include $kfile);
            if (!\is_array($arr)) {
                $this->errorPush("Illegal format file: $kfile , please remove this file and try again", null, true);
            }
            foreach($arr as $urlBase => $parameters) {
                if ($this->addURLBase($urlBase, $parameters, false)) {
                    $changed = true;
                }
            }
        }
        return $changed;
    }
    
    public function saveKnownUrlBaseFile() {
        $kfile = $this->knownUrlBaseFile;
        $dataStr = '<' . "?php\nreturn " . \var_export($this->urlBases, true) . ";\n";
        $wb = \file_put_contents($kfile, $dataStr);
        if (!$wb) {
            $this->errorPush("Can't write file: $kfile", null, true);
        }
    }
    
    public function addURLBase(string $urlBase, $parameters, bool $saveToFile = true): bool {
        if (\is_numeric($urlBase) && \is_string($parameters) && \filter_var($parameters, \FILTER_VALIDATE_URL)) {
            $urlBase = $parameters;
            $parameters = true;
        }
        if (\array_key_exists($urlBase, $this->urlBases) && $this->urlBases[$urlBase] === $parameters) {
            return false;
        }
        $this->urlBases[$urlBase] = $parameters;
        if ($saveToFile) {
            $this->saveKnownUrlBaseFile();
        }
        return true;
    }
    
    /**
     * Rerurn:
     *   true = changed
     *   false = no changes
     *   null = error
     *   string = downloaded data (only if $oneDownLoadMode=true)
     * 
     * @param string $basePathSrc
     * @param array $filesArr
     * @param bool $doNotUpdate
     * @param array $urlSpecArr
     * @param int $minBasePathLen
     * @param bool $oneDownLoadMode
     * @return null|bool|string
     * @throws \Exception
     */
    public function LoadFiles(
        string $basePathSrc,
        array $filesArr, // short pathes
        bool $doNotUpdate = false,
        array $urlSpecArr = [],
        int $minBasePathLen = 0,
        bool $oneDownLoadMode = false
    ) {
        $urlBasesArr = $this->urlBases;
        $urlShortArr = [];
        foreach($urlSpecArr as $urlSpec => $parameters) {
            if (\substr($urlSpec, 0, 1) === '/') {
                // url short add-path specified
                $urlShortArr[] = \substr($urlSpec, 1);
            } else {
                // global url specified
                $urlBasesArr[$urlSpec] = $parameters;
            }
        }
        // check urlBases
        if (!$urlBasesArr) {
            $this->errorPush("No URL bases specified");
            return null;
        }
        if (!$urlShortArr) {
            $urlShortArr = [''];
        }
        
        // prepare basePath
        $basePath = \realpath($basePathSrc);
        if (!$basePath) {
            if (!\mkdir($basePathSrc)) {
                $this->errorPush("Not found basePath=$basePath, can't created (target for download)", null, true);
            }
            $basePath = \realpath($basePathSrc);
            $doNotUpdate = false;
        }
        $basePath = \strtr($basePath, '\\', '/');

        // check files if not-upgrade mode
        if ($doNotUpdate) {
            $noFilesCnt = $this->checkNoFilesCnt($basePath, $filesArr);
            if (!$noFilesCnt) {
               return false; // no changes
            }
        }

        $successDownloadedCnt = 0;
        
        // prepare toArr
        $toArr = [];
        foreach($filesArr as $path) {
            $toArr[$path] = false;
        }

        foreach($urlShortArr as $urlShortAdd) {
            $success = false;
            foreach($toArr as $pathSrc => $alreadyOk) {
                if ($alreadyOk) continue;
                $fromArr = [];
                foreach($this->urlBases as $urlBase => $parameters) {
                    $path = $pathSrc;
                    $currUrlShortAdd = (\substr($urlShortAdd, -1) !== '/') ? $urlShortAdd : \substr($urlShortAdd, 0, -1);
                    $currBasePath = \strtr($basePath, '\\' , '/');
                    while(\substr($path, 0, 3) === '../') {
                        $path = \substr($path, 3);
                        $i = \strrpos($currUrlShortAdd, '/');
                        if ($i) {
                            $j = \strrpos($currBasePath, '/');
                            if ($j) {
                                if ($minBasePathLen && ($minBasePathLen < $j)) {
                                    break;
                                }
                                $currBasePath = \substr($currBasePath, 0, $j);
                            }
                            $currUrlShortAdd = \substr($currUrlShortAdd, 0, $i);
                        } else {
                            $currUrlShortAdd = '';
                            break;
                        }
                    }
                    $fromArr[] = $urlBase . $currUrlShortAdd . '/' . $path;
                }
                if ($fromArr) {
                    $targetFile = $this->getFromArr($fromArr, $oneDownLoadMode ? null : $currBasePath);
                    if (\is_string($targetFile)) {
                        if ($oneDownLoadMode) {
                            return $targetFile; // Not file name, it is data string
                        }
                        $successDownloadedCnt++;
                    }
                    if ($targetFile) {
                        $toArr[$path] = true; // alreadyOk
                    }
                }
            }
        }

        return $successDownloadedCnt ? true : false;
    }
    
    public function checkNoFilesCnt(string $basePath, array $filesArr): int {
        $noFilesCnt = 0;
        if (!\is_dir($basePath)) {
            return \count($filesArr);
        }
        $basePath = \strtr($basePath, '\\', '/');
        if (\substr($basePath, -1) !== '/') {
            $basePath .= '/';
        }
        foreach($filesArr as $fileShort) {
            $fullFile = $basePath . $fileShort;
            if (!\is_file($fullFile)) {
                $noFilesCnt++;
            }
        }
        return $noFilesCnt;
    }

    /**
     * Return:
     * - true  - data found, but not changed (already exist)
     * - null  - Not found data (or data is empty)
     * - false - write error
     * - int   - successful data writed (bytes cnt)
     * - string - downloaded data (if $targetPath is empty) OR targetFile (if data saved successful)
     *
     * @param array $fromArr
     * @param string|null $targetPath basePath for save downloaded data
     * @param string|null $baseNameNoExt new name of file for save (without .ext)
     * @param array|null $avaExt enabled extentions list (empty = all enabled)
     * @param bool $doNotUpdate if true, return true if targetFile already exist
     * @return bool|null|true|string
     */
    public function getFromArr(
        array $fromArr,
        string $targetPath = null,
        string $baseNameNoExt = null,
        array $avaExt = null,
        bool $doNotUpdate = false,
        bool $canCache = true
    ) {
        $context = \stream_context_create([
            "ssl" => [
                "verify_peer" => false,
                "verify_peer_name" => false,
            ],
        ]);
        $data = null;
        foreach($fromArr as $fromPath) {
            $i = \strrpos($fromPath, '.');
            $ext = $i ? \substr($fromPath, $i) : '';
            if ($avaExt) {
                if (!\in_array($ext, $avaExt)) {
                    $this->errorPush("Illegal ext $ext for $fromPath");
                    continue;
                }
            }
            if ($targetPath) {
                if ($baseNameNoExt) {
                    $targetFile = $targetPath . '/' . $baseNameNoExt . $ext;
                } else {
                    $targetFile = $targetPath . '/' . \basename($fromPath);
                }
                if ($doNotUpdate && \is_file($targetFile)) {
                    return true;
                }
            }
            // convert fromPath to full (if local specified
            $fromURLs = [];
            if ($fromPath[0] === '/') {
                if (!$this->urlBases) {
                    $this->errorPush("No urlbases specified, can't download by short pathes");
                    return null;
                }
                foreach($this->urlBases as $urlBase => $properties) {
                    $fromURLs[] = $urlBase . $fromPath;
                }
            } else {
                $fromURLs = [$fromPath];
            }
            // try load
            foreach($fromURLs as $fromPath) {
                if ($canCache && isset($this->cachedDownLoadedArr['fromPath'])) {
                    $data = $this->cachedDownLoadedArr['fromPath'];
                    break 2;
                }
                $data = @\file_get_contents($fromPath, false, $context);
                if ($data) {
                    if ($canCache) {
                        $this->cachedDownLoadedArr['fromPath'] = $data;
                    }
                    break 2;
                }
            }
        }
        if (!$data) {
            return null;
        }
        if ($targetPath && $data) {
            if (!\is_dir($targetPath) && !\mkdir($targetPath)) {
                $this->errorPush("Can't create target directory: $targetPath");
            }
            if (\file_exists($targetFile)) {
                // compare with old data
                $oldData = \file_get_contents($targetFile);
                if ($oldData === $data) {
                    return true;
                } else {
                    $this->successPush("Sucessful downloaded NEW data from $fromPath for file $targetFile");
                }
            }
            $wb = \file_put_contents($targetFile, $data);
            if (\is_numeric($wb)) {
                $this->successPush("Successful data writed to file: $targetFile");
                return $targetFile;
            } else {
                $this->errorPush("Can't save downloaded data fromrequire to file: $targetFile");
            }
            return false;
        }
        return $data;
    }
}
