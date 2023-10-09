<?php
namespace dynoser\autoload;

class DynoImporter {
    
    public $dynoArr = []; // [namespace] => sourcepath (see $classesArr in AutoLoader)
    public $dynoArrChanged = false; // if the dynoArr differs from what is saved in the file
    
    public function __construct(string $vendorDir) {
        if (DYNO_FILE) {
            if (\file_exists(DYNO_FILE)) {
                $this->dynoArr = (require DYNO_FILE);
            }
            if ($this->dynoArr && \is_array($this->dynoArr)) {
                AutoLoader::$classesArr = \array_merge(AutoLoader::$classesArr, $this->dynoArr);
            } else {
                $this->importComposersPSR4($vendorDir);
                $this->saveDynoFile($vendorDir);
            }
        }
    }
    
    public function updateFromComposer(string $vendorDir) {
        if (DYNO_FILE) {
            // reload last version of dynoFile
            if (($this->dynoArrChanged || empty($this->dynoArr)) && \file_exists(DYNO_FILE)) {
                $this->dynoArr = (require DYNO_FILE);
                $this->dynoArrChanged = false;
            }
            $changed = $this->importComposersPSR4($vendorDir);
            $this->saveDynoFile($vendorDir);
        }
        return $this->dynoArrChanged;
    }
    
    public function saveDynoFile(string $vendorDir) {
        $dynoStr = '<' . "?php\n" . 'return ';
        $dynoStr .= \var_export($this->dynoArr, true) . ";\n";
        $chkDir = \dirname(DYNO_FILE);
        if (!\is_dir($chkDir)) {
            if (\is_dir($vendorDir) && \dirname($chkDir, 2) === \dirname($vendorDir)) {
                \mkdir($chkDir,0777, true);
            }
            if (!\is_dir($chkDir)) {
                throw new \Exception("Not found folder to storage DYNO_FILE=" . DYNO_FILE);
            }
        }
        $wb = \file_put_contents(DYNO_FILE, $dynoStr);
        if (!$wb) {
            throw new \Exception("Can't write dyno-file (psr4-namespaces imported from composer)\nFile: " . DYNO_FILE);
        }
        $this->dynoArrChanged = false;
    }


    public function convertComposersPSR4toDynoArr(string $vendorDir): ?array {
        $composersPSR4file = $vendorDir . '/composer/autoload_psr4.php';
        if (!\is_file($composersPSR4file)) {
            return null;
        }
        $composerPSR4arr = (require $composersPSR4file);
        if (!\is_array($composerPSR4arr)) {
            return null;
        }
        $dynoArr = [];
        foreach($composerPSR4arr as $nameSpace => $srcFoldersArr) {
            foreach($srcFoldersArr as $n => $path) {
                $srcFoldersArr[$n] = '*' . \strtr($path, '\\', '/') . '/*';
            }
            $nameSpace = \trim(\strtr($nameSpace, '\\', '/'), "/ \n\r\v\t");
            if (\is_array($srcFoldersArr) && \count($srcFoldersArr) === 1) {
                $dynoArr[$nameSpace] = \reset($srcFoldersArr);
            } else {
                $dynoArr[$nameSpace] = $srcFoldersArr;
            }
        }
        return $dynoArr;
    }
    function importComposersPSR4(string $vendorDir): bool {
        if (!is_array($this->dynoArr)) {
            $this->dynoArr = [];
        }
        $dynoArr = $this->convertComposersPSR4toDynoArr($vendorDir);
        if ($dynoArr) {
            foreach($dynoArr as $nameSpace => $srcFoldersArr) {
                if (!\array_key_exists($nameSpace, $this->dynoArr) || $this->dynoArr[$nameSpace] !== $srcFoldersArr) {
                    $this->dynoArr[$nameSpace] = $srcFoldersArr;
                    $this->dynoArrChanged = true;
                }
                if (\is_string($srcFoldersArr)) {
                    $srcFoldersArr = [$srcFoldersArr];
                }
                AutoLoader::addNameSpaceBase($nameSpace, $srcFoldersArr, false);
            }
        }
        return $this->dynoArrChanged;
    }
    
    public function getAliases(): array {
        $aliasesArr = []; // [aliasTO] => [classFROM]
        foreach($this->dynoArr as $nameSpace => $pathes) {
            if (\is_string($pathes)) {
                $pathes = [$pathes];
            }
            if (\is_array($pathes)) {
                foreach($pathes as $path) {
                    if (\is_string($path) && \substr($path, 0, 1) === '?') {
                        $aliasesArr[$nameSpace] = \substr($path, 1);
                        break;
                    }
                }
            }
        }
        return $aliasesArr;
    }
}
