<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/function.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/classes/lenta.php";

$dblink = DbConnect();
$lenta = new Lenta($dblink);

echo '<link rel="stylesheet" href="/assets/css/lenta.css">';

// Контент
$out  = '<div class="lenta-container">';
$out .= $lenta->FormMassageAdd();
$out .= $lenta->showForm();
$out .= $lenta->showMessages();
$out .= '</div>';

// Вывод страницы
View_Add(Page_Up("Лента повідомлень"));
View_Add(Menu_Up());
View_Add('<div class="out">');
View_Add($out);
View_Add('</div>');
View_Add(Page_Down());
View_Out();
