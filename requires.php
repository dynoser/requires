<?php
(function ($classShortName) {
    $reqManClass = 'dynoser\\requires\\' . $classShortName;

    if (!\class_exists($reqManClass, false)) {
        global $argc, $argv;
        if (!\defined('ROOT_DIR')) {
            if (!empty($argc) && ($argc > 2 && $argv[1] === 'ROOT_DIR')) {
                \define('ROOT_DIR', $argv[2]);
                if (!\is_dir(ROOT_DIR)) {
                    throw new \Exception("Bad ROOT_DIR specified: " . ROOT_DIR);
                }
            } else {
                $rootDir = \getcwd();
                if (\is_dir($rootDir . '/vendor')) {
                    \define('ROOT_DIR', $rootDir);
                }
            }
        }
        require_once __DIR__ . '/autoload/autoload.php';
        
        // update DYNO_FILE from Composer
        if (\dynoser\autoload\AutoLoadSetup::updateFromComposer()) {
            echo "NameSpaces Successful Updated from Composer\n";
        }
        
        $mySrcDir = __DIR__ . '/src';
        // Load own classes and traits
        require_once $mySrcDir . '/ComposerWorks.php';
        require_once $mySrcDir . '/DownLoader.php';
        $fullClassFile = \strtr($mySrcDir, '\\', '/') . '/' . $classShortName . '.php';
        require_once $fullClassFile;
        
        // AutoLoader diagnostic
        $ourAutoLoadClass = $reqManClass::OUR_AUTO_LOAD_CLASS;
        if (!\class_exists($ourAutoLoadClass, false)) {
            throw new \Exception("Autoloader diagnostic error: NO autoload class $ourAutoLoadClass");
        }
        $chkFile = $ourAutoLoadClass::autoLoad($reqManClass, null);
        if ($chkFile && (!\is_string($chkFile) || \strtr($chkFile, '\\', '/') !== $fullClassFile)) {
            throw new \Exception("Autoloader diagnostic error:\n $chkFile \n $fullClassFile \n");
        }

        if (!\class_exists($reqManClass, true)) {
            throw new \Exception("Class $reqManClass was not loaded, required");
        }
    }

    $obj = new $reqManClass();
    $obj->run();
})('RequireManager');
