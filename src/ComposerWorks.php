<?php
namespace dynoser\requires;

use dynoser\autoload\AutoLoadSetup;

trait ComposerWorks {
    public bool $composerChanged = false;
    public bool $composerAutoLoaderLoaded = false;
    public string $composerPharFile = '';
    
    public string $pkgForComposerInit = 'solomono/autoinit';

    public $composerWorkDir = '';
    
    public function composerWorksInit() {
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
                    throw new \Exception("Can't install composer in workDir=$composerWorkDir \nOutput: $output");
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
        $command = 'php -dxdebug.mode=off "' . $this->getComposerPharFile() . '" -n ' . $command . ' --working-dir=' . $workDir;
        $output = [];
        \exec($command, $output, $exitCode);

        if (!\is_array($output)) {
            $output = [];
        }

        if (!$exitCode) {
            AutoLoadSetup::loadComposerAutoLoader();
        }

        return compact('output', 'exitCode');
    }
    
    public function getComposerPharFile() {
        if (!$this->composerPharFile) {
            $this->composerPharFile = self::whereComposerPhar();
        }
        return $this->composerPharFile;
    }
    
    public static function whereComposerPhar() {
        $composerFiles = \preg_split('/\r\n|\r|\n/', \shell_exec('where composer'));

        $foundComposerPharFiles = [];

        foreach ($composerFiles as $composerFile) {
            $composerFile = \trim($composerFile);
            if (!$composerFile) continue;
            $i = \strrpos($composerFile, '.');
            if (!$i) {
                $i = \strlen($composerFile);
            }
            $composerPharFile = \substr($composerFile, 0, $i) . '.phar';
            if (\file_exists($composerPharFile)) {
                $foundComposerPharFiles[] = $composerPharFile;
            }
        }

        return \reset($foundComposerPharFiles);
    }
}
