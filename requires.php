<?php
(function ($classShortName) {
    $reqManClass = 'dynoser\\requires\\' . $classShortName;

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
        }

        // setup autoloader and vars in AutoLoadSetup:: $vendorDir, $rootDir, $classesDir, $extDir
        require_once __DIR__ . '/autoload/autoload.php';
        
        // update DYNO_FILE from Composer (current classesArr + all psr4 from composer)
        if (\dynoser\autoload\AutoLoadSetup::updateFromComposer()) {
            echo "NameSpaces Successful Updated from Composer\n";
        }

        // Since this namespace may not be in DYNO_FILE now, add it
        // Also, we want to use this version and not the one the composer might give
        \dynoser\autoload\AutoLoader::addNameSpaceBase('dynoser/requires', __DIR__ . '/src', false);
        \dynoser\autoload\AutoLoader::addNameSpaceBase('dynoser/autoload', __DIR__ . '/autoload', false);

        if (!\class_exists($reqManClass, true)) {
            throw new \Exception("Class $reqManClass was not loaded, required");
        }
    }

    $obj = new $reqManClass();
    $obj->echoOn = true;
    $obj->run();
})('RequireManager');
