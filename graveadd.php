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
        '<div class="contentgrave-form">' .
        '<form action="/graveadd.php" method="post">' .
        '<input type="hidden" name="md" value="grave">' .

        '<div class="form-header-wrap">' .
        '<div class="form-header">' .
        '<h2><i class="icon-person"></i> Форма реєстрації даних про померлого</h2>' .
        '<p>Заповніть всі необхідні поля для створення запису</p>' .
        '</div>' .
        '</div>' .

        '<div class="form-content" style="padding: 24px; padding-top: 15px; padding-bottom: 15px;">' .

        '<div class="section-header">' .
        '<h3><img src="/assets/images/fuser.png" class="section-icon">Особисті дані</h3>' .
        '</div>' .

        '<div class="form-row-grave">' .
        '<div class="input-container-grave">' .
        '<input type="text" name="lname" required placeholder=" ">' .
        '<label>Прізвище *</label>' .
        '</div>' .
        '<div class="input-container-grave">' .
        '<input type="text" name="fname" required placeholder=" ">' .
        '<label>Ім’я *</label>' .
        '</div>' .
        '<div class="input-container-grave">' .
        '<input type="text" name="mname" placeholder=" ">' .
        '<label>По батькові</label>' .
        '</div>' .
        '</div>' .

        '<div class="form-row-grave">' .
        '<div class="input-container-grave">' .
        '<input type="date" name="birthdate" required placeholder="дд.мм.рррр" style="lang: uk;">' .
        '<label>Дата народження *</label>' .
        '</div>' .
        '<div class="input-container-grave">' .
        '<input type="date" name="deathdate" required placeholder=" ">' .
        '<label>Дата смерті *</label>' .
        '</div>' .
        '</div>' .

        '<div class="section-header">' .
        '<h3><img src="/assets/images/flocation.png" class="section-icon">Місце поховання</h3>' .
        '</div>' .

        '<div class="form-row-grave">' .
        '<div class="input-container-grave">' .
        RegionSelect("region","city-select") .
        '<label>Область *</label>' .

        '</div>' .

// поп-ап
        '<div id="region-popup" class="popup">' .
        '<div class="popup-content">' .
        '<h3>Додати населений пункт</h3>' .
        '<form method="post" action="">' .
        '<div class="input-container-grave">' .
        '<input type="text" name="new_region" id="new-region-input" placeholder=" " required>' .
        '<label for="new-region-input">Введіть назву населеного пункту</label>' .
        '</div>' .
        '<div class="popup-actions">' .
        '<button type="submit" class="sub-btn">Додати</button>' .
        '<button type="button" id="close-popup" class="cancel-btn">Скасувати</button>' .
        '</div>' .
        '</form>' .
        '</div>' .
        '</div>' .

        '<div class="input-container-grave">' .
        '<select name="district" id="district" required>' .
        '<option value="">Спочатку виберіть область</option>' .
        '</select>' .
        '<label>Район *</label>' .
        '</div>' .
        '<div class="input-container-grave">' .
        '<select name="settlement" id="settlement" required>' .
        '<option value="">Виберіть</option>' .
        '</select>' .
        '<label>Населений пункт</label>' .
        '<button type="button" class="add-region-btn">+</button>' .
        '</div>' .

        '</div>' .

        '<div class="form-row-grave">' .
        '<div class="input-container-grave">' .
        '<input type="text" name="cemetery" required placeholder=" ">' .
        '<label>Кладовище *</label>' .
        '</div>' .
        '<div class="input-container-md">' .
        '<input type="text" name="pos1" required placeholder=" ">' .
        '<label>Квартал</label>' .
        '</div>' .
        '<div class="input-container-md">' .
        '<input type="text" name="pos2" required placeholder=" ">' .
        '<label>Ряд</label>' .
        '</div>' .
        '<div class="input-container-md">' .
        '<input type="text" name="pos3" required placeholder=" ">' .
        '<label>Місце</label>' .
        '</div>' .
        '</div>' .

        '<div class="section-header">' .
        '<h3><img src="/assets/images/fcamera.png" class="section-icon">Фотографії</h3>' .
        '</div>' .
        '<div class="form-vertical-grave">' .
        '<div class="input-container-grave upload">' .
        '<input type="file" name="photo1">' .
        '<label>Фото поховання</label>' .
        '</div>' .
        '<div class="input-container-grave upload">' .
        '<input type="file" name="photo2">' .
        '<label>Фото лиця</label>' .
        '</div>' .
        '</div>' .

        '<div class="form-row-grave form-actions-grave">' .
        '<button type="submit" class="sub-btngrave">Зберегти запис</button>' .
        '<button type="reset" class="cancel-btn">Скасувати</button>' .
        '</div>' .

        '</form>' .
        '</div>' .
        '</div>';


    $out .= '
<script>
function loadDistricts(regionId) {
    if (!regionId) return;
    var xhr = new XMLHttpRequest();
   
    xhr.open("GET", "?ajax_districts=1&region_id=" + regionId, true);
    xhr.onload = function() {
        if (xhr.status === 200) {
            document.getElementById("district").innerHTML = xhr.responseText;
        } else {
            alert("Помилка завантаження районів");
        }
    };
    xhr.send();
}

// показать попап
document.addEventListener("click", function(e){
    if(e.target.classList.contains("add-region-btn")){
        document.getElementById("region-popup").style.display = "flex";
    }
});

// закрыть попап
document.getElementById("close-popup").onclick = function(){
    document.getElementById("region-popup").style.display = "none";
};

</script>';

    return $out;
}

function gravezone()
{
    $out = '<div class="gravezone"></div>';

    return $out;
}

View_Clear();
View_Add(Page_Up());
View_Add(Menu_Up());
View_Add('<div class="out">');

View_Add('<div class="graveadd-container">');

View_Add(Contentgrave());
//View_Add(gravezone());

View_Add('</div>');

View_Add('</div>');

View_Add(Page_Down());
View_Out();
View_Clear();