<?php
namespace dynoser\requires;

use dynoser\autoload\AutoLoadSetup;

trait ComposerWorks {
    public bool $composerChanged = false;
    public bool $composerAutoLoaderLoaded = false;
    public string $composerPharFile = '';
    public static string $phpBin = '';
    public static string $phpIni = '';
    
    public string $pkgForComposerInit = 'solomono/autoinit';

    public $composerWorkDir = '';
    
    public function composerWorksInit() {
        if (!\extension_loaded('openssl')) {
            throw new Exception("The openssl extension is required for SSL/TLS protection but is not available.");
        }
        AutoLoadSetup::loadComposerAutoLoader();
        $composerWorkDir = $this->getComposerWorkDir();
        $composerPharFile = $this->getComposerPharFile();
        if (!$composerPharFile) {
            throw new \Exception("Composer must be installed");
        }
        $result = AutoLoadSetup::updateFromComposer();
        if (\is_null($result)) {
            throw new \Exception("AutoLoader incorrect DYNO-import");
        }
    }

    public function getComposerRootJSONfile(): string {
        $composerWorkDir = $this->composerWorkDir ? $this->composerWorkDir : $this->getComposerWorkDir();
        return $composerWorkDir . '/composer.json';
    }

    public function getComposerWorkDir() {
        if (!$this->composerWorkDir) {
            $this->composerWorkDir = $composerWorkDir = \dirname($this->vendorDir);
            if (!\is_dir($composerWorkDir)) {
                throw new \Exception("Not found composer directory");
            }
            $composerJSONfile = $this->getComposerRootJSONfile();
            if (!\is_file($composerJSONfile)) {
                // composer init
                $result = $this->composerRun('init --name="' . $this->pkgForComposerInit . '"', $composerWorkDir);
                if ($this->echoOn) {
                    echo \implode("\n", $result['output']) ."\n";
                }
                if (!\is_file($composerJSONfile)) {
                    throw new \Exception("Can't install composer in workDir=$composerWorkDir \nOutput: " . $result['output']);
                }
                $dataJSON = <<<HERE
                {
                    "name": "{$this->pkgForComposerInit}",
                    "minimum-stability": "dev",
                    "require": {
                        "dynoser/requires": "*"
                    },
                    "config": {
                        "optimize-autoloader": true,
                        "sort-packages": true
                    }
                }
                HERE;
                $wb = \file_put_contents($composerJSONfile, $dataJSON);
                if (!$wb) {
                    throw new \Exception("Can't modify composer.json file: $composerJSONfile");
                }
                $this->composerChanged = true;
            }
        }
        return $this->composerWorkDir;
    }

    public function composerUpdate() {
        $result = $this->composerRun('update');
        if ($this->echoOn) {
            echo \implode("\n", $result['output']) ."\n";
        }
        return !$result['exitCode'];
    }
    
    public function composerRun(string $command, string $workDir = null) {
        if (!$workDir) {
            $workDir = $this->getComposerWorkDir();
        }
        $phpRun = self::getPHP();
        $composerPhar = $this->getComposerPharFile();
        $command = $phpRun . ' -d xdebug.mode=off -f "' . $composerPhar . '" -n ' . $command . ' --working-dir="' . $workDir . '"';
        $output = [];
        \exec($command, $output, $exitCode);

        if (!\is_array($output)) {
            $output = [];
        }

        if (!$exitCode) {
            AutoLoadSetup::$composerAutoLoaderLoaded = false;
            AutoLoadSetup::loadComposerAutoLoader();
        }

        return compact('output', 'exitCode');
    }
    
    public function getComposerPharFile() {
        $composerWorkDir = $this->composerWorkDir ? $this->composerWorkDir : \dirname($this->vendorDir);
        if (!$this->composerPharFile) {
            $this->composerPharFile = self::whereComposerPhar($composerWorkDir);
        }
        if (!$this->composerPharFile) {
            $this->composerPharFile = self::composerLoadAndInstall($composerWorkDir);
        }
        return $this->composerPharFile;
    }
    
    public static function whereComposerPhar(string $composerWorkDir = null) {
        $whereFiles = self::getWhereArr('composer');

        $foundComposerPharFiles = [];

        foreach ($whereFiles as $composerFile) {
            $i = \strrpos($composerFile, '.');
            if (!$i) {
                $i = \strlen($composerFile);
            }
            $composerPharFile = \substr($composerFile, 0, $i) . '.phar';
            if (\file_exists($composerPharFile)) {
                $foundComposerPharFiles[] = $composerPharFile;
            }
        }
        if (!$foundComposerPharFiles && $composerWorkDir) {
            $chkVendorPhar = $composerWorkDir . '/composer.phar';
            if (\is_file($chkVendorPhar)) {
                return $chkVendorPhar;
            }
        }

        return \reset($foundComposerPharFiles);
    }

    public static function getWhereArr($progName) {
        return self::getShellNonEmptyRows('where ' . $progName);
    }

    public static function getShellNonEmptyRows($cmd) {
        $shellRows = \preg_split('/\r\n|\r|\n/', \shell_exec($cmd));

        $rowsArr = [];

        foreach ($shellRows as $row) {
            $row = \trim($row);
            if (!$row) continue;
            $rowsArr[] = $row;
        }

        return $rowsArr;
    }

    public static function getPHP($addPhpIni = true) {
        if (!self::$phpBin) {
            $whereFiles = self::getWhereArr('php');
            self::$phpBin = $whereFiles ? 'php' : PHP_BINARY;
        }
        $phpRun = self::$phpBin;
        if ($addPhpIni) {
            $phpIni = self::$phpIni ? self::$phpIni : \php_ini_loaded_file();
            if (!$phpIni) {
                $phpIni = self::getPHPini();
            }
            self::$phpIni = $phpIni ? $phpIni : '';
            if (self::$phpIni) {
                $phpRun .= ' -c "' . self::$phpIni . '"';
            }
        }
        return $phpRun;
    }

    public static function getPHPini() {
        if (!self::$phpIni) {
            $phpRun = self::getPHP(false);
            $rows = self::getShellNonEmptyRows($phpRun . ' --ini');
            if ($rows) {
                foreach($rows as $row) {
                    $i = strpos($row, 'ile:');
                    if ($i) {
                        self::$phpIni = \trim(\substr($row, $i+5));
                        break;
                    }
                }
            }
        }
        return self::$phpIni;
    }

    public static function composerLoadAndInstall($composerWorkDir) {
        $composerPhar = self::whereComposerPhar($composerWorkDir);
        if ($composerPhar) {
            throw new \Exception("Composer already installed");
        }

        if (!\is_dir($composerWorkDir)) {
            throw new \Exception("Not found composer directory");
        }

        $phpRun = self::getPHP();

        $oldcwd = \getcwd();
        \chdir($composerWorkDir);

        $composerSetupName = 'composer-setup.php';
        if (!\file_exists($composerSetupName)) {
            $composerFromURL = 'https://getcomposer.org/installer';
            \copy($composerFromURL, $composerSetupName);
            if (!\file_exists($composerSetupName)) {
                throw new Exception("Can't download $composerSetupName from $composerFromURL");
            }
        }

        $signature = \file_get_contents('https://composer.github.io/installer.sig');
        if (\hash_file('SHA384', $composerSetupName) === $signature) {
            // run composer-setup.php
            $runStr = "$phpRun -d xdebug.mode=off -f \"{$composerWorkDir}/{$composerSetupName}\"";
            \exec($runStr);
        } else {
            throw new \Exception('Illegal composer signature, setup cancelled');
        }
        \unlink($composerSetupName);

        $composerPhar = self::whereComposerPhar($composerWorkDir);
        if (!$composerPhar) {
            throw new \Exception("ERROR: Composer was not installed");
        }

        // restore old work dir
        \chdir($oldcwd);

        return $composerPhar;
    }
}
