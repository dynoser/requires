<?php
$myPkgDir   = \dirname(__FILE__, 2);
$rootDir    = \defined('ROOT_DIR')   ? ROOT_DIR    : \dirname($myPkgDir, 2);
$rootDir    = \trim(strtr($rootDir, '\\', '/'), '/');
$vendorDir  = \defined('VENDOR_DIR') ? VENDOR_DIR  : $rootDir . '/vendor';
$classesDir = \defined('CLASSES_DIR')? CLASSES_DIR : $rootDir . '/includes/classes';
$extDir     = \defined('EXT_FS_DIR') ? EXT_FS_DIR  : $rootDir . '/ext';
require_once __DIR__ . "/AutoLoadSetup.php";

(new \dynoser\autoload\AutoLoadSetup($rootDir, $vendorDir, $classesDir, $extDir));
