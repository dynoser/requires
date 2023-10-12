<?php
namespace dynoser\requires;

use dynoser\autoload\DynoImporter;

class RequiresFiles {
    public static $i = null; // last instance of self ($this)
    
    public string $cacheBaseDir = '';
    public string $requiresCacheDir = '';

    public bool $changed = false;
    
    const INT_SUB_DIR = '/requires';
    const REQ_FILES_EXT = '-req.json';

    const REQUIRES_FILE_SET  = 'requires_file_set';
    const REQUIRES_FILE_BASE = 'requires_file_base';
    const REQUIRES_FILE_HASH = 'requires_file_hash';
    
    public function __construct($cacheDir = null) {
        if (!$cacheDir) {
            if (!\defined('DYNO_FILE')) {
                throw new \Exception("DYNO_FILE constant required");
            }
            $cacheDir = \dirname(DYNO_FILE);
        }
        // check for cacheDir
        $realCacheDir = \realpath($cacheDir);
        if (!$realCacheDir) {
            throw new \Exception("Not found cacheDir: $cacheDir");
        }

        $realCacheDir = \strtr($realCacheDir, '\\', '/');

        // prepare $intRequiresCacheDir by $realCacheDir
        if (\substr($realCacheDir, -\strlen(self::INT_SUB_DIR)) !== self::INT_SUB_DIR) {
            $this->cacheBaseDir = $realCacheDir;
            $intRequiresCacheDir = $realCacheDir . self::INT_SUB_DIR;
            if (!\is_dir($intRequiresCacheDir) && !\mkdir($intRequiresCacheDir)) {
                throw new \Exception("Can't create dir: $intRequiresCacheDir");
            }
        } else {
            $this->cacheBaseDir = \dirname($realCacheDir);
            $intRequiresCacheDir = $realCacheDir;
        }
        $this->requiresCacheDir = $intRequiresCacheDir;

        self::$i = $this;
    }
    
    /**
     * Return:
     *   array of full-path all requires-cached-files
     * 
     * @return array
     * @throws \Exception
     */
    public function getRequiresCachedFiles(): array {
        $requiresCachedFilesArr = \glob($this->requiresCacheDir . '/*', \GLOB_NOSORT);
        if (!\is_array($requiresCachedFilesArr)) {
            throw new \Exception("Can't get list of requires Cached Files");
        }
        return $requiresCachedFilesArr;
    }
    
    /**
     * Delete all cached requires-files
     * Return:
     *  =0 - no changes (already empty dir)
     *  >0 - how many files deleted
     * 
     * @return bool
     * @throws \Exception
     */
    public function clearAllRequiresCachedFiles(): int {
        $removedCnt = 0;
        $requiresCachedFilesArr = $this->getRequiresCachedFiles();
        if ($requiresCachedFilesArr) {
            $dir_s = $this->requiresCacheDir;
            $dir_l = \strlen($dir_s);
            $ext_s = self::REQ_FILES_EXT;
            $ext_l = \strlen($ext_s);
            foreach($requiresCachedFilesArr as $fullFile) {
                // check file name before delete
                if (\substr($fullFile, 0, $dir_l) !== $dir_s) {
                    throw new \Exception("Critical code error");
                }
                if (\substr($fullFile, -$ext_l) === $ext_s) {
                    \unlink($fullFile);
                    $removedCnt++;
                }
            }
        }
        return $removedCnt;
    }
    
    public function removeCachedFile(string $fromFileOrPath) {
        $cacheFileName = $this->calcCacheFileName($fromFileOrPath);
        if (!\is_file($cacheFileName)) {
            return false;
        }
        return \unlink($cacheFileName);
    }
    
    public function calcCacheFileName(string $fromFileOrPath): string {
        $hashHex = \hash('sha256', $fromFileOrPath, false);
        $cacheFileName = $this->requiresCacheDir . '/' . \strtolower(\substr($hashHex, 0, 16)) . self::REQ_FILES_EXT;
        return $cacheFileName;
    }
    
    public function saveToCache(string $fromFileOrPath, array & $requiresArr, string $fileHashHex = null, string $setName = null) {
        $requiresArr[self::REQUIRES_FILE_BASE] = $fromFileOrPath;
        if ($fileHashHex) {
            $requiresArr[self::REQUIRES_FILE_HASH] = $fileHashHex;
        } elseif (!\array_key_exists(self::REQUIRES_FILE_HASH, $requiresArr)) {
            throw new \Exception("Code error: no hash key=" . self::REQUIRES_FILE_HASH);
        }
        $requiresArr[self::REQUIRES_FILE_SET] = $setName ? $setName : 'unknown';
        $cacheFileName = $this->calcCacheFileName($fromFileOrPath);
        $dataStr = \json_encode($requiresArr, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE);
        if (\is_file($cacheFileName)) {
            $oldData = \file_get_contents($cacheFileName);
        }
        if ($oldData !== $dataStr) {
            $wb = \file_put_contents($cacheFileName, $dataStr);
            if (!$wb) {
                throw new \Exception("Can't save requires-cache file: $cacheFileName");
            }
            $this->changed = true;
        }
        return $cacheFileName;
    }
    
    public function loadFromCache(string $fromFileOrPath): ?array {
        $cacheFileName = $this->calcCacheFileName($fromFileOrPath);
        if (!\is_file($cacheFileName)) {
            return null;
        }
        $dataStr = \file_get_contents($cacheFileName);
        if (!$dataStr) {
            throw new \Exception("Can't read data from file: $cacheFileName");
        }
        $requireArr = \json_decode($dataStr, true);
        if (!\is_array($requireArr)) {
            throw new \Exception("Can't unpack data from file: $cacheFileName");
        }
        return $requireArr;
    }
}
