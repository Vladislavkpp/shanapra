<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

use vendor\vodovra\Page;
use vendor\vodovra\View;
use vendor\vodovra\Dbs;
use vendor\vodovra\Region;

// работа с базой
// $res=Dbs::Select('* from grobki where kladb=12') - вернется ассоциативный массив или ложь
// $res=Dbs::Insert() - вернется истина или ложь
// $res=Dbs::Update() - вернется истина или ложь
// $res=Dbs::Delete() - вернется истина или ложь

Page::SetTitle('Agency');
View::Add(Page::OutTop());

$schetchik = 8;
$imya_agenstva = "";
$gorod_agenstva = "";
$rayoni_goroda_agenstva = "";
$opis_agenstva = "Введите описание агенства";
$uslugi_agenstva = "Услуги оказываемые агенством(например: кремация, погребение, подготовка документов)";
$fio_agenstva = "";
$mail_agenstva = "";

$res = Dbs::Select('* from agenstva');
//View::add(print_r($res,true));
//View::add('<hr>');
View::add('<table><tr><th>Название агенства</th><th>Город</th><th>Районы города</th><th>Рейтинг</th></tr>');
foreach ($res as $resex) {
    View::add('<tr><td><b>' . $resex['imya_agenstva'] . '</b></td><td>' . $resex['gorod_agenstva'] . '</td><td>' . $resex['rayoni_goroda_agenstva'] . '</td><td>' . $resex['reiting'] . '</td></tr>');
}

View::add('</table>');
//Proverka formi na zapolnenie
if (isset($_POST['insert_agenstvo'])) {
    $schetchik = 0;
    $imya_agenstva = $_POST['imya_agenstva'];
    if (strlen($imya_agenstva) > 3) {
        $schetchik++;
    } else {
        $imya_agenstva = "Название агенства не введено";
    }

    $gorod_agenstva = $_POST['gorod_agenstva'];
    if (strlen($gorod_agenstva) > 3) {
        $schetchik++;
    } else {
        $gorod_agenstva = "Название города не введено";
    }

    $rayoni_goroda_agenstva = $_POST['rayoni_goroda_agenstva'];
    if (strlen($rayoni_goroda_agenstva) > 3) {
        $schetchik++;
    } else {
        $rayoni_goroda_agenstva = "Название города не введено";
    }

    $opis_agenstva = $_POST['opis_agenstva'];
    if (strlen($opis_agenstva) > 3) {
        $schetchik++;
    } else {
        $opis_agenstva = "Описание агенства не введено";
    }

    $uslugi_agenstva = $_POST['uslugi_agenstva'];
    if (strlen($uslugi_agenstva) > 3) {
        $schetchik++;
    } else {
        $uslugi_agenstva = "Описание услуг агенства не введено";
    }

    $fio_agenstva = $_POST['fio_agenstva'];
    if (strlen($fio_agenstva) > 3) {
        $schetchik++;
    } else {
        $fio_agenstva = "ФИО ответственного не введено";
    }

    $mail_agenstva = $_POST['mail_agenstva'];
    if (strlen($mail_agenstva) > 3) {
        $schetchik++;
    } else {
        $mail_agenstva = "e-mail агенства не введено";
    }
}
if ($schetchik < 7) {
    View::add('<br>Добавление нового ритуального агенства');
    View::add('<form method="post" action="agency.php">');
    View::add('<input type="text" size="38" name="imya_agenstva" value="' . $imya_agenstva . '" placeholder="Введите название агенства" required><br>');
    View::add('<input type="text" size="38" name="gorod_agenstva" value="' . $gorod_agenstva . '" placeholder="Введите название города где работает агенство" required><br>');
    View::add('<input type="text" size="38" name="rayoni_goroda_agenstva" value="' . $rayoni_goroda_agenstva . '" placeholder="Районы города где работает агенство"><br>');
    View::add('<textarea rows="6" cols="40" name="opis_agenstva" placeholder="' . $opis_agenstva . '" required></textarea><br>');
    View::add('<textarea rows="6" cols="40" name="uslugi_agenstva" placeholder="' . $uslugi_agenstva . '"></textarea><br>');
    View::add('<input type="text" size="38" name="fio_agenstva" value="' . $fio_agenstva . '" placeholder="Введите ФИО"><br>');
    View::add('<input type="email" size="38" name="mail_agenstva" value="' . $mail_agenstva . '" placeholder="Введите e-mail" required><br>');
    View::add('<input type="hidden" name="insert_agenstvo" value="1">');
    View::add('<input type="submit" value="Добавить">');
    View::add('</form>');
}
if ($schetchik == 7) {
    $res = Dbs::Insert("* INTO agenstva(imya_agenstva, gorod_agenstva, rayoni_goroda_agenstva, opis_agenstva, uslugi_agenstva, fio_agenstva, mail_agenstva, data_vvoda_agenstva) VALUES ('$imya_agenstva','$gorod_agenstva','$rayoni_goroda_agenstva','$opis_agenstva', '$uslugi_agenstva', '$fio_agenstva', '$mail_agenstva', '" . date("Y-m-d") . "')");
    if ($res == true) {
        View::add('Данные успешно внесены, после проверки модератором и подтверждения e-mail будут доступны другим пользователям');
    }
    View::add('Данные успешно внесены, после проверки модератором и подтверждения e-mail будут доступны другим пользователям');
}

View::Add(Page::OutBottom());
View::out();