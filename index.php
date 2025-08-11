<?php
/**
 * @var $md
 * @var $buf
 */
//require_once $_SERVER['DOCUMENT_ROOT'] .'/vendor/autoload.php';
require_once "function.php";
function Content(): string
{


    $dblink = DbConnect();

    $res = mysqli_query($dblink, "SELECT idx FROM grave");
    $count = 0;
    if ($res) {
        $count = mysqli_num_rows($res);
    }
    mysqli_close($dblink);

    $out = '<div class="content">' .
        '<div class="login-formContainer">' .
        '<div class="form-title">Пошук по базі</div>' .
        '<div>Усього в базі ' . $count . ' поховань</div><br>' .
        '<form class="formindex" action="/searchout.php" method="get" lang="uk">' .
        '<input type="hidden" name="page" value="1">' .
        '<input type="hidden" name="s" value="1">' .

        // Первый ряд ФИО
        '<div class="form-row form-vertical" lang="uk">' .

        '<div class="input-container">' .
        '<input type="text" name="surname" class="login-Input" placeholder=" " autocomplete="off">' .
        '<label>Прізвище</label>' .
        '</div>' .

        '<div class="input-container">' .
        '<input type="text" name="name" class="login-Input" placeholder=" " autocomplete="off">' .
        '<label>Ім\'я</label>' .
        '</div>' .

        '<div class="input-container">' .
        '<input type="text" name="patronymic" class="login-Input" placeholder=" " autocomplete="off">' .
        '<label>По-батькові</label>' .
        '</div>' .

        '<hr class="form-separator">' .
        '</div>' .

        // Второй ряд — даты
        '<div class="form-row" lang="uk">' .
        '<div class="input-container">' .
        '<input type="date" name="birthdate" placeholder=" " lang="uk">' .
        '<label>Дата народження</label>' .
        '</div>' .

        '<div class="input-container">' .
        '<input type="date" name="deathdate" placeholder=" " lang="uk">' .
        '<label>Дата смерті</label>' .
        '</div>' .
        '</div>' .

        '<div class="form-vertical">' .
        '<input type="submit" class="sub-btn" value="Знайти">' .
        '</div>' .
        '</form>' .
        '</div>' .
        '</div>';

    return $out;
}


View_Clear();
View_Add(Page_Up());
View_Add(Menu_Up());
View_Add('<div class="out">');

//Основной екран

//View_Add(Menu_Left());
View_Add(Content());

View_Add('</div>');
View_Add(Page_Down());
View_Out();
View_Clear();