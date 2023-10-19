<?php
use dynoser\autoload\AutoLoader;
use dynoser\autoload\AutoLoadSetup;

(function ($classShortName) {
    $reqManClass = 'dynoser\\requires\\' . $classShortName;
    $AutoLoadSetup = 'dynoser\\autoload\\AutoLoadSetup';

    if (!\class_exists($reqManClass, false)) {
        // check command line arguments, like ROOT_DIR "path"
        global $argc, $argv;
        if (!\defined('ROOT_DIR')) {
            if (!empty($argc) && ($argc > 2 && $argv[1] === 'ROOT_DIR')) {
                \define('ROOT_DIR', $argv[2]);
                if (!\is_dir(ROOT_DIR)) {
                    throw new \Exception("Bad ROOT_DIR specified: " . ROOT_DIR);
                }
            } else {
                $rootDir =\strtr(\getcwd(), '\\', '/') . '/vendor';
                while($i = \strrpos($rootDir, '/vendor')) {
                    $rootDir = \substr($rootDir, 0, $i);
                    if (\is_dir($rootDir . '/vendor')) {
                        \define('ROOT_DIR', $rootDir);
                        break;
                    }
                }
            }
            if (!\defined('ROOT_DIR') && \class_exists($AutoLoadSetup, false)) {
                $rootDir = AutoLoadSetup::$rootDir;
                \define('ROOT_DIR', $rootDir);
            }
        }

        if (!\class_exists($AutoLoadSetup, false)) {
            // setup autoloader and vars in AutoLoadSetup:: $vendorDir, $rootDir, $classesDir, $extDir
            $autoLoadFile = $rootDir . '/vendor/autoload.php';
            if (\is_file($autoLoadFile)) {
                require_once $autoLoadFile;
            }
            if (!\class_exists($AutoLoadSetup, false)) {
                throw new \Exception("run it: 'composer require dynoser/autoload'");
            }
        }

        $setOwnNameSpaces = function() {
            // Since this namespace may not be in DYNO_FILE now, add it
            // Also, we want to use this version and not the one the composer might give
            AutoLoader::addNameSpaceBase('dynoser/requires', '*' . __DIR__ . '/src', false);
        };
        $setOwnNameSpaces();
        
        // update DYNO_FILE from Composer (current classesArr + all psr4 from composer)
        if (AutoLoadSetup::updateFromComposer()) {
            echo "NameSpaces Successful Updated from Composer\n";
        }

        if (!\defined('DONT_RESET_NS')) {
            $setOwnNameSpaces();
        }

        if (!\class_exists($reqManClass, true)) {
            throw new \Exception("Class $reqManClass was not loaded, required");
        }
    }

    $obj = new $reqManClass();
    $obj->echoOn = true;
    $obj->run();
})('RequireManager');
