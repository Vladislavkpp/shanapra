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
    $out = '<div class="content">' .
        '<div class="login-formContainer">' .
        '<div class="form-title">Пошук інформації про померлих</div>' .
        '<form class="formindex" action="/searchx.php" method="get" lang="uk">' .
        '<input type="hidden" name="page" value="1">' .

        '<div class="form-row-desktop">' .

        // Блок ФИО
        '<div class="form-group">' .
        '<div class="input-container">' .
        '<input type="text" name="surname" class="login-Input" placeholder=" " autocomplete="off" 
                        value="' . htmlspecialchars($_GET['surname'] ?? '') . '">' .
        '<label>Прізвище</label>' .
        '</div>' .
        '<div class="input-container">' .
        '<input type="text" name="name" class="login-Input" placeholder=" " autocomplete="off" 
                        value="' . htmlspecialchars($_GET['name'] ?? '') . '">' .
        '<label>Ім\'я</label>' .
        '</div>' .
        '<div class="input-container">' .
        '<input type="text" name="patronymic" class="login-Input" placeholder=" " autocomplete="off" 
                        value="' . htmlspecialchars($_GET['patronymic'] ?? '') . '">' .
        '<label>По-батькові</label>' .
        '</div>' .
        '</div>' .

        // Разделитель
        '<div class="form-divider"></div>' .

        // Блок даты
        '<div class="form-group">' .
        '<div class="input-container">' .
        '<input type="date" name="birthdate" placeholder=" " lang="uk" 
                        value="' . htmlspecialchars($_GET['birthdate'] ?? '') . '">' .
        '<label>Дата народження</label>' .
        '</div>' .
        '<div class="input-container">' .
        '<input type="date" name="deathdate" placeholder=" " lang="uk" 
                        value="' . htmlspecialchars($_GET['deathdate'] ?? '') . '">' .
        '<label>Дата смерті</label>' .
        '</div>' .
        '</div>' .

        // Кнопка
        '<div class="button-container">' .
        '<button type="submit" class="sub-btn">
        <img src="assets/images/searchi.png" alt="search" class="icon">
        Знайти
    </button>' .
        '</div>'.



        '</div>' .

        '</form>' .
        '</div>' .
        '</div>';

    return $out;
}

function SearchCemetery(): string
{
    $out = '<div class="content">
        <div class="login-formContainer">
            <div class="form-title">Пошук кладовищ</div>
            <form class="formindex" action="/kladbsearch.php" method="get" lang="uk">
                <input type="hidden" name="page" value="1">

                <div class="form-row-desktop">

                    <div class="form-group">
                        <div class="input-container">
                            ' . RegionForKladb("region", "region") . '
                            <label>Область</label>
                        </div>

                        <div class="input-container">
                            <select name="district" id="district">
                                <option value="">Спочатку виберіть область</option>
                            </select>
                            <label>Район</label>
                        </div>

                        <div class="input-container">
                            <input type="text" name="title" class="login-Input" placeholder=" " autocomplete="off"
                                   value="' . htmlspecialchars($_GET['title'] ?? '') . '">
                            <label>Назва кладовища</label>
                        </div>
                    </div>
                           
                <div class="button-container">
        <button type="submit" class="sub-btn">
        <img src="assets/images/searchi.png" alt="search" class="icon">
Знайти
    </button>
        </div>

            </form>
        </div>
    </div>
    </div>';

    $out .= '
    <script>
    document.addEventListener("DOMContentLoaded", function(){
        var regionSel = document.getElementById("region");
        var districtSel = document.getElementById("district");

        function loadDistricts(regionId){
            districtSel.innerHTML = "<option value=\'\'>Завантаження...</option>";
            var xhr = new XMLHttpRequest();
            xhr.open("GET", "?ajax_districts=1&region_id=" + encodeURIComponent(regionId), true);
            xhr.onload = function(){
                districtSel.innerHTML = (xhr.status === 200 ? xhr.responseText.trim() : "<option value=\'\'>Помилка завантаження</option>");
            };
            xhr.onerror = function(){
                districtSel.innerHTML = "<option value=\'\'>Помилка мережі</option>";
            };
            xhr.send();
        }

        if(regionSel){
            regionSel.addEventListener("change", function(){
                if(this.value) loadDistricts(this.value);
                else districtSel.innerHTML = "<option value=\'\'>Спочатку виберіть область</option>";
            });
        }
    });
    </script>';

    return $out;
}

function InfoBlock(): string
{
    $out = '<div class="info-block block">' .
        '<div class="titleinfo">ІПС «Шана» – це інформаційно-пошукова система пам’яті.
На першому етапі це соціальна мережа, де об’єкт уваги – поховання, а не користувач.
У перспективі – повноцінна робоча платформа з маркетплейсом, рекламою та іншими можливостями.
Наша мета – зберігати пам`ять, допомагати навіть на відстані та робити цей процес доступним для кожного.</div>' .

        '</div>';


    return $out;
}


function StatsBlock(): string
{

    date_default_timezone_set('Europe/Kiev');

    $dblink = DbConnect();


    $res = mysqli_query($dblink, "SELECT COUNT(*) as cnt FROM grave");
    $row = $res ? mysqli_fetch_assoc($res) : ['cnt' => 0];
    $graveCount = $row['cnt'];


    $res2 = mysqli_query($dblink, "SELECT COUNT(*) as cnt FROM cemetery");
    $row2 = $res2 ? mysqli_fetch_assoc($res2) : ['cnt' => 0];
    $cemetryCount = $row2['cnt'];

    mysqli_close($dblink);

    $updated = date("d.m.Y");

    $out = '
<div class="stats-block block">
    
    <div class="stats-row">
    <div class="form-title-stats">Статистика</div>
        <div class="stats-index">
            <div class="stats-title">Поховань у базі:</div>
            <div class="stats-value">'.$graveCount.'</div>
        </div>
        <div class="stats-index">
            <div class="stats-title">Кладовищ у базі:</div>
            <div class="stats-value">'.$cemetryCount.'</div>
        </div>
        <div class="stats-index">
            <div class="stats-title">Оновлено:</div>
            <div class="stats-value">'.$updated.'</div>
        </div>
    </div>
</div>';

    $out .= '
<div class="stats-mobile block">
    <div class="form-title-statsm">Статистика</div>
    <div class="stats-rowm">
    
        <div class="stats-indexm">
            <div class="stats-titlem">Поховань у базі:</div>
            <div class="stats-valuem">'.$graveCount.'</div>
        </div>
        <div class="stats-indexm">
            <div class="stats-titlem">Кладовищ у базі:</div>
            <div class="stats-valuem">'.$cemetryCount.'</div>
        </div>
        <div class="stats-indexm">
            <div class="stats-titlem">Оновлено:</div>
            <div class="stats-valuem">'.$updated.'</div>
        </div>
    </div>
</div>';

    return $out;
}




// === Вывод страницы ===
View_Clear();
View_Add(Page_Up('Головна'));
View_Add(Menu_Up());

View_Add('<div class="out-index">');


View_Add(StatsBlock());
//View_Add(InfoBlock());
View_Add(Content());
View_Add(SearchCemetery());

View_Add('</div>');

View_Add(Page_Down());
View_Out();
View_Clear();