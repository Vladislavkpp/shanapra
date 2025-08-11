<?php
require_once $_SERVER['DOCUMENT_ROOT'] .'/vendor/autoload.php';

//use vendor\vodovra\Page;
//use vendor\vodovra\View;
//View::Add(''); //
?>
Шановні відвідувачі !

- Через те, що призвища не перекладають, та низку інших причин, в базі
зберігаються призвища імена та по-батькові в тому вигляді та тією мовою,
як вони були введені до бази

-

function rus2lat($string)
{
    $rus = array('ё', 'ж', 'ц', 'ч', 'ш', 'щ', 'ю', 'я', 'Ё', 'Ж', 'Ц', 'Ч', 'Ш', 'Щ', 'Ю', 'Я', 'Ъ', 'Ь', 'ъ', 'ь');
    $lat = array('e', 'zh', 'c', 'ch', 'sh', 'sh', 'ju', 'ja', 'E', 'ZH', 'C', 'CH', 'SH', 'SH', 'JU', 'JA', '', '', '', '');
    $string = str_replace($rus, $lat, $string);
    return strtr($string, "АБВГДЕЗИЙКЛМНОПРСТУФХЫЭабвгдезийклмнопрстуфхыэ ", "ABVGDEZIJKLMNOPRSTUFHIEabvgdezijklmnoprstufhie_");
}

function getmicrotime()
{
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);