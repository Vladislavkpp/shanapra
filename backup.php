<?php
/**
 * @var $md
 * @var $buf
 */
require_once $_SERVER['DOCUMENT_ROOT'] . "/function.php";

View_Clear();
View_Add(Page_Up());
View_Add(Menu_Up());
View_Add('<div class="out">');
View_Add(Menu_Left());

//Основной екран

$b = array('index.php', 'function.php');

$dc = $_SERVER["DOCUMENT_ROOT"];

$title = 'IPS Shanapra, Backup';
if (!extension_loaded('zip')) {
    View_Add('PHP - extension ZIP are not loaded !!!<hr>Backup impossible');
} else {
    $zip = new ZipArchive();
    $dt = date('Y-m-d_H-i-s');
    $zip_name = 'ips_shanapro_' . $dt . '.zip';
    if ($zip->open($dc . '/ziip/' . $zip_name, ZipArchive::CREATE) !== TRUE) {
        View_Add("Cannot create <$zip_name>\n");
    } else {
        $fl = file($dc . '/ziip/backup.xml');
        foreach ($fl as $f) {
            $zip->addFile(trim($dc . '/' . $f), trim($f));
        }
        View_Add('Files added: ' . $zip->numFiles . '<hr><a href="/ziip/' . $zip_name . '"> [Link for download]</a>');
        $zip->close();
    }
}

View_Add('</div>');
View_Add(Page_Down());
View_Out();
View_Clear();
