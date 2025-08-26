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

// === AJAX: загрузка районов по области ===
if (isset($_GET['ajax_districts']) && !empty($_GET['region_id'])) {
    echo getDistricts((int)$_GET['region_id']);
    exit;
}

// === AJAX: загрузка населённых пунктов по области и району ===
if (isset($_GET['ajax_settlements']) && !empty($_GET['region_id']) && !empty($_GET['district_id'])) {
    echo getSettlements((int)$_GET['region_id'], (int)$_GET['district_id']);
    exit;
}

// === AJAX: добавление нового населённого пункта ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'], $_POST['region_id'], $_POST['district_id'])) {
    $region_id = (int)$_POST['region_id'];
    $district_id = (int)$_POST['district_id'];
    $name = trim($_POST['name']);

    if ($region_id && $district_id && $name) {
        echo addSettlement($region_id, $district_id, $name);
    } else {
        echo "Помилка: не всі дані заповнені";
    }
    exit;
}

// ==== Форма захоронения ====
function Contentgrave() {
    $out  = '<div class="contentgrave-form">' .
        '<form action="/graveadd.php" method="post" enctype="multipart/form-data">' .
        '<input type="hidden" name="md" value="grave">' .

        '<div class="form-header-wrap">' .
        '<div class="form-header">' .
        '<h2><i class="icon-person"></i> Форма реєстрації даних про померлого</h2>' .
        '<p>Заповніть всі необхідні поля для створення запису</p>' .
        '</div>' .
        '</div>' .

        '<div class="form-content" style="padding:24px;padding-top:15px;padding-bottom:15px;">' .

        '<div class="section-header">' .
        '<h3><img src="/assets/images/fuser.png" class="section-icon">Особисті дані</h3>' .
        '</div>' .

        '<div class="form-row-grave fio">' .
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

        '<div class="form-row-grave dates">' .
        '<div class="input-container-grave">' .
        '<input type="date" name="birthdate" required>' .
        '<label>Дата народження *</label>' .
        '</div>' .
        '<div class="input-container-grave">' .
        '<input type="date" name="deathdate" required>' .
        '<label>Дата смерті *</label>' .
        '</div>' .
        '</div>' .

        '<div class="section-header">' .
        '<h3><img src="/assets/images/flocation.png" class="section-icon">Місце поховання</h3>' .
        '</div>' .

        '<div class="form-row-grave location">' .
        '<div class="input-container-grave">' . RegionSelect("region", "city-select") .
        '<label>Область *</label>' .
        '</div>' .
        '<div class="input-container-grave">' .
        '<select name="district" id="district" required>' .
        '<option value="">Спочатку виберіть область</option>' .
        '</select>' .
        '<label>Район *</label>' .
        '</div>' .
        '<div class="input-container-grave">' .
        '<select name="settlement" id="settlement" required disabled>' .
        '<option value="">Спочатку виберіть район</option>' .
        '</select>' .
        '<label>Населений пункт</label>' .
        '<button type="button" id="add-settlement-btn" class="add-region-btn add-settlement-btn" disabled>+</button>' .
        '</div>' .
        '</div>' .

        '<div id="settlement-popup" class="popup" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);justify-content:center;align-items:center;z-index:9999;">' .
        '<div class="popup-content" style="background:#fff;padding:20px;border-radius:12px;min-width:320px;max-width:90vw;box-shadow:0 2px 12px rgba(0,0,0,0.3);">' .
        '<h3 style="margin-top:0;">Додати населений пункт</h3>' .
        '<div id="add-settlement-form">' .
        '<div class="input-container-grave">' .
        '<input type="text" name="new_settlement" id="new-settlement-input" placeholder=" " required>' .
        '<label for="new-settlement-input">Введіть назву населеного пункту</label>' .
        '</div>' .
        '<div class="popup-actions" style="margin-top:15px;display:flex;gap:8px;justify-content:flex-end;">' .
        '<button type="button" id="submit-new-settlement" class="sub-btn">Додати</button>' .
        '<button type="button" id="close-settlement-popup" class="cancel-btn">Скасувати</button>' .
        '</div>' .
        '</div>' .
        '</div>' .
        '</div>' .

        '<div class="form-row-grave cemetery">' .
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

        '</div>' .
        '</form>' .
        '</div>';

// ===== JS =====
$out .= '
<script>
document.addEventListener("DOMContentLoaded", function(){
    var regionSel = document.getElementById("region");
    var districtSel = document.getElementById("district");
    var settlementSel = document.getElementById("settlement");
    var addBtn = document.getElementById("add-settlement-btn");
    var popup = document.getElementById("settlement-popup");
    var closePopupBtn = document.getElementById("close-settlement-popup");
    var submitNewBtn = document.getElementById("submit-new-settlement");
    var newInput = document.getElementById("new-settlement-input");

    function setSettlementDisabled(state){
        settlementSel.disabled = state;
        addBtn.disabled = state;
    }

    function loadDistricts(regionId){
        districtSel.innerHTML = "<option value=\'\'>Завантаження...</option>";
        setSettlementDisabled(true);
        settlementSel.innerHTML = "<option value=\'\'>Спочатку виберіть район</option>";

        var xhr = new XMLHttpRequest();
        xhr.open("GET", "?ajax_districts=1&region_id=" + encodeURIComponent(regionId), true);
        xhr.onload = function(){
            districtSel.innerHTML = (xhr.status === 200 ? xhr.responseText.trim() : "<option value=\'\'>Помилка завантаження</option>");
            setSettlementDisabled(true);
            addBtn.disabled = !(regionSel.value && districtSel.value);
        };
        xhr.onerror = function(){
            districtSel.innerHTML = "<option value=\'\'>Помилка мережі</option>";
            setSettlementDisabled(true);
        };
        xhr.send();
    }

    function loadSettlements(districtId){
        settlementSel.innerHTML = "<option value=\'\'>Завантаження...</option>";
        var xhr = new XMLHttpRequest();
        xhr.open("GET", "?ajax_settlements=1&region_id=" + encodeURIComponent(regionSel.value) + "&district_id=" + encodeURIComponent(districtId), true);
        xhr.onload = function(){
            settlementSel.innerHTML = (xhr.status === 200 ? xhr.responseText.trim() : "<option value=\'\'>Помилка завантаження</option>");
            setSettlementDisabled(false);
        };
        xhr.onerror = function(){
            settlementSel.innerHTML = "<option value=\'\'>Помилка мережі</option>";
            setSettlementDisabled(false);
        };
        xhr.send();
    }

    if(regionSel){
        regionSel.addEventListener("change", function(){
            if(this.value) loadDistricts(this.value);
            else {
                districtSel.innerHTML = "<option value=\'\'>Спочатку виберіть область</option>";
                settlementSel.innerHTML = "<option value=\'\'>Спочатку виберіть район</option>";
                setSettlementDisabled(true);
            }
            addBtn.disabled = !(regionSel.value && districtSel.value);
        });
    }

    if(districtSel){
        districtSel.addEventListener("change", function(){
            if(this.value) loadSettlements(this.value);
            else setSettlementDisabled(true);
            addBtn.disabled = !(regionSel.value && districtSel.value);
        });
    }

    if(addBtn){
        addBtn.addEventListener("click", function(){
            if(!this.disabled){
                popup.style.display="flex";
                setTimeout(function(){ newInput.focus(); },50);
            }
        });
    }

    if(closePopupBtn){
        closePopupBtn.addEventListener("click", function(){ popup.style.display="none"; });
    }

    if(submitNewBtn){
        submitNewBtn.addEventListener("click", function(){
            var regionId = regionSel.value;
            var districtId = districtSel.value;
            var name = newInput.value.trim();

            if(!regionId || !districtId){ alert("Спочатку виберіть область та район!"); return; }
            if(!name){ alert("Вкажіть назву населеного пункту"); return; }

            var xhr = new XMLHttpRequest();
            xhr.open("POST","/graveadd.php",true);
            xhr.setRequestHeader("Content-type","application/x-www-form-urlencoded; charset=UTF-8");
            xhr.onload=function(){
                if(xhr.status===200 && xhr.responseText.trim().toUpperCase().indexOf("OK")===0){
                    popup.style.display="none";
                    loadSettlements(districtId);
                    newInput.value="";
                } else { alert("Помилка додавання: "+xhr.responseText); }
            };
            xhr.onerror=function(){ alert("Помилка мережі при додаванні"); };
            xhr.send("region_id="+encodeURIComponent(regionId)+"&district_id="+encodeURIComponent(districtId)+"&name="+encodeURIComponent(name));
        });
    }

    popup.addEventListener("click", function(e){
        if(e.target === popup) popup.style.display="none";
    });

    setSettlementDisabled(true);
});
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