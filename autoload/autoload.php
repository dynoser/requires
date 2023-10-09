<?php
(function($file) {
    if ($file) {
        $rootDir = \strtr($file, '\\', '/');
        $i = \strpos($rootDir, '/vendor/composer');
        $rootDir = \defined('ROOT_DIR') ? ROOT_DIR : \substr($rootDir, 0, $i ? $i : 0);
    } else {
        $rootDir = \defined('ROOT_DIR') ? ROOT_DIR : \dirname(__FILE__, 5);
    }
    $rootDir    = \trim(strtr($rootDir, '\\', '/'), '/');
    $vendorDir  = \defined('VENDOR_DIR') ? VENDOR_DIR  : $rootDir . '/vendor';
    $classesDir = \defined('CLASSES_DIR')? CLASSES_DIR : $rootDir . '/includes/classes';
    $extDir     = \defined('EXT_FS_DIR') ? EXT_FS_DIR  : $rootDir . '/ext';
    require_once __DIR__ . "/AutoLoadSetup.php";

    (new \dynoser\autoload\AutoLoadSetup($rootDir, $vendorDir, $classesDir, $extDir));
})($file ?? '');// $file is Composer value
