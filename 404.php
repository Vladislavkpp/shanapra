<?php
/**
 * @var $md
 * @var $buf
 */
//require_once $_SERVER['DOCUMENT_ROOT'] .'/vendor/autoload.php';
require_once $_SERVER['DOCUMENT_ROOT'] ."/function.php";

if (isset($_GET['exit']) && $_GET['exit'] == 1) {
    session_destroy();
    header("Location: /");
    exit;
}

function Content(): string
{
    $out = '<br><div class="content">' .
        '<div class="login-formContainer">' .
        '<br><div class="form-title">Увага !</div><br>' .
'<h1> Помилка 404. Сторінку за адресою '.$_SERVER['SCRIPT_URI'].' не знайдено !!!</h1><br><br>'.
        '</div>' .
        '</div>';

    return $out;
}

// === Вывод страницы ===
View_Clear();
View_Add(Page_Up('Сторінка не знайдена. Помилка 404'));
View_Add(Menu_Up());
View_Add('<div class="out-index">');

View_Add(Content());

View_Add('</div>');
View_Add(Page_Down());
View_Out();
View_Clear();