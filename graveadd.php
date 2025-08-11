<?php

/**
 * @var $md
 * @var $buf
 */
//require_once $_SERVER['DOCUMENT_ROOT'] .'/vendor/autoload.php';
require_once "function.php";
if ($md == "grave") {
    $dblink = DbConnect();
    $sql = 'INSERT INTO grave (fname, lname, mname, dt1, dt2, idtadd, idxadd) VALUES ("' .
        $_POST['fname'] . '","' .
        $_POST['lname'] . '","' .
        $_POST['mname'] . '","' .
        $_POST['birthdate'] . '","' .
        $_POST['deathdate'] . '", NOW(), "' .
        intval($_SESSION['uzver']) . '" );';

    echo $sql;
    $res = mysqli_query($dblink, $sql);
}



function Contentgrave()
{
    $out =
        ' <div class="contentgrave-form"> ' .
        ' <form action="/graveadd.php" method="post"> ' .
        ' <input type="hidden" name="md" value="grave"> ' .


        ' <div class="form-header-wrap"> ' .
        ' <div class="form-header"> ' .
        ' <h2><i class="icon-person"></i> Форма реєстрації даних про померлого</h2> ' .
        ' <p>Заповніть всі необхідні поля для створення запису</p> ' .
        ' </div> ' .
        ' </div> ' .


        ' <div class="form-content" style="padding: 24px; padding-top: 15px; padding-bottom: 15px; "> ' .
        ' <div class="section-header"> ' .
        ' <h3><img src="/assets/images/fuser.png" class="section-icon">Особисті дані</h3> ' .
        ' </div> ' .
        '<div class="form-row"> ' .
        ' <div class="input-container"> ' .
        ' <input type="text" name="lname" required placeholder=" "> ' .
        ' <label>Прізвище *</label> ' .
        ' </div> ' .
        ' <div class="input-container"> ' .
        ' <input type="text" name="fname" required placeholder=" "> ' .
        ' <label>Ім’я *</label> ' .
        ' </div> ' .
        ' <div class="input-container"> ' .
        ' <input type="text" name="mname" placeholder=" "> ' .
        ' <label>По батькові</label> ' .
        ' </div> ' .
        '</div> ' .

        '<div class="form-row"> ' .
        '<div class="input-container"> ' .
        '<input type="date" name="birthdate" required placeholder="дд.мм.рррр" style="lang: uk;"> ' .
        '<label>Дата народження *</label> ' .
        ' </div> ' .
        ' <div class="input-container"> ' .
        ' <input type="date" name="deathdate" required placeholder=" "> ' .
        '<label>Дата смерті *</label> ' .
        ' </div>' .
        ' </div> ' .


        '<div class="section-header"> ' .
        '<h3><img src="/assets/images/flocation.png" class="section-icon">Місце поховання</h3> ' .
        '</div> ' .
        '<div class="form-row"> ' .
        '<div class="input-container"> ' .
        RegionSelect("city","city-select").
        ' <label>Місто *</label>' .
        ' </div>' .
        '<div class="input-container">' .
        ' <input type="text" name="cemetery" required placeholder=" ">' .
        '<label>Кладовище *</label>' .
        ' </div>' .
        '</div>' .

        '<div class="form-row">' .
        '<div class="input-container">' .
        '<input type="text" name="pos1" required placeholder=" ">' .
        '<label>Квартал</label>' .
        '</div>' .
        ' <div class="input-container">' .
        ' <input type="text" name="pos2" required placeholder=" ">' .
        ' <label>Ряд</label>' .
        ' </div>' .
        ' <div class="input-container">' .
        '<input type="text" name="pos3" required placeholder=" ">' .
        '<label>Місце поховання</label>' .
        ' </div>' .
        '</div>' .


        '<div class="section-header">' .
        ' <h3><img src="/assets/images/fcamera.png" class="section-icon">Фотографії</h3>' .
        '</div>' .
        '<div class="form-vertical-grave">' .
        '<div class="input-container upload">' .
        '<input type="file" name="photo1">' .
        '<label>Фото поховання</label>' .
        ' </div>' .
        ' <div class="input-container upload">' .
        ' <input type="file" name="photo2">' .
        ' <label>Фото лиця</label>' .
        '</div>' .
        '</div>' .


        '<div class="form-row form-actions">' .
        '<button type="submit" class="sub-btn">Зберегти запис</button>' .
        ' <button type="reset" class="cancel-btn">Скасувати</button>' .
        '</div>' .

        '</form></div></div>';

    return $out;
}


View_Clear();
View_Add(Page_Up());
View_Add(Menu_Up());
View_Add('<div class="out">');

//Основной екран

//View_Add(Menu_Left());
View_Add(Contentgrave());

View_Add('</div>');
View_Add(Page_Down());
View_Out();
View_Clear();