<?php
/**
 * @var $lok
 */
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] .'/libraries/init.php';
const xbr = "\n";

spl_autoload_register(function ($class) {
    $class = str_replace("\\", '/', $class);
//    $found = false;
    if (is_file($_SERVER['DOCUMENT_ROOT'] . "/$class.php")) {
        require_once $_SERVER['DOCUMENT_ROOT'] . "/$class.php";
//        $found = true;
    }
//    if (is_file($_SERVER['DOCUMENT_ROOT'] . "/classes/$class.php")) {
//        require_once $_SERVER['DOCUMENT_RO0OT'] . "/classes/$class.php";
//        $found = true;
//    }
//    if (!$found) {
//        echo 'Class "' . $class . '" not found in Project !<br>' . xbr;
//    }
});

use vendor\Vodovra\Utils;
use vendor\Vodovra\Config;

Config::$start = Utils::GetMicroTime();
$md = $_GET['md'] ?? '';
$md = $_POST['md'] ?? $md;
$lok=true;