<?php
require_once __DIR__ . '/vendor/autoload.php';

var_dump(file_exists(__DIR__ . '/vendor/phpmailer/phpmailer/src/PHPMailer.php'));
var_dump(class_exists('PHPMailer\PHPMailer\PHPMailer'));

use PHPMailer\PHPMailer\PHPMailer;
$mail = new PHPMailer();
echo "PHPMailer работает";

