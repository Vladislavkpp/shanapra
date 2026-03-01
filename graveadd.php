<?php

/**
 * @var $md
 * @var $buf
 */
//require_once $_SERVER['DOCUMENT_ROOT'] .'/vendor/autoload.php';
require_once $_SERVER['DOCUMENT_ROOT'] . "/function.php";

// ===== проверка авторизации =====
$showMessage = false;
$messageType = '';
$messageText = '';

if (!isset($_SESSION['uzver']) || empty($_SESSION['uzver'])) {
    $showMessage = true;
    $messageType = 'alert-error';
    $messageText = 'Для доступу до сторінки потрібно авторизуватися';
}
// ===== обработка формы добавления =====
if (!$showMessage && isset($_POST['md']) && $_POST['md'] === 'grave') {
    $dblink = DbConnect();
    $sql = 'INSERT INTO grave (fname, lname, mname, dt1, dt2, idtadd, idxadd, idxkladb, pos1, pos2, pos3) VALUES ("' .
        $_POST['fname'] . '","' .
        $_POST['lname'] . '","' .
        $_POST['mname'] . '","' .
        $_POST['dt1'] . '","' .
        $_POST['dt2'] . '", NOW(), "' .
        intval($_SESSION['uzver']) . '","' .
        $_POST['idxkladb'] . '","' .
        $_POST['pos1'] . '","' .
        $_POST['pos2'] . '","' .
        $_POST['pos3'] . '");';

    $res = mysqli_query($dblink, $sql);

    if (!$res) {
        die("SQL error: " . mysqli_error($dblink) . "<br>Запрос: " . $sql);
    }

    $newId = mysqli_insert_id($dblink);
    $uploadDir = __DIR__ . "/graves/" . $newId;


    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $photos = [];
    foreach (['photo1', 'photo2'] as $field) {
        if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {

            $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                error_log("Неподдерживаемое расширение у {$field}: {$ext}");
                continue;
            }

            $safeName = $field . "." . $ext;
            $targetPath = $uploadDir . "/" . $safeName;

            error_log("Загрузка фото {$field}: " . $_FILES[$field]['name']);


            $success = gravecompress(
                $_FILES[$field]['tmp_name'],
                $targetPath,
                75,
                300
            );

            if ($success && file_exists($targetPath)) {
                $photos[$field] = "/graves/$newId/$safeName";
                error_log("Успешно сохранено: {$targetPath}");
            } else {
                error_log("Ошибка при сжатии или сохранении {$field}");
            }
        } elseif (isset($_FILES[$field])) {
            // Логируем ошибку загрузки
            error_log("Ошибка загрузки {$field}: " . $_FILES[$field]['error']);
        }
    }


    if (!empty($photos)) {
        $updates = [];
        foreach ($photos as $col => $path) {
            $updates[] = "$col='" . mysqli_real_escape_string($dblink, $path) . "'";
        }

        $sqlUpdate = "UPDATE grave SET " . implode(",", $updates) . " WHERE idx=" . intval($newId);

        if (!mysqli_query($dblink, $sqlUpdate)) {
            error_log("Ошибка обновления путей к фото: " . mysqli_error($dblink));
            die("Ошибка обновления путей к фото: " . mysqli_error($dblink));
        } else {
            error_log("Фото обновлены в базе для ID $newId");
        }
    }


    if ($res) {
        $showMessage = true;
        $messageType = 'alert-success';
        $messageText = 'Запис додано успішно!';
    } else {
        $showMessage = true;
        $messageType = 'alert-error';
        $messageText = 'Помилка: ' . mysqli_error($dblink);
    }
}

// загрузка районов по области
if (isset($_GET['ajax_districts']) && !empty($_GET['region_id'])) {
    echo getDistricts((int)$_GET['region_id']);
    exit;
}

// загрузка населённых пунктов по області і району
if (isset($_GET['ajax_settlements']) && !empty($_GET['region_id']) && !empty($_GET['district_id'])) {
    echo getSettlements((int)$_GET['region_id'], (int)$_GET['district_id']);
    exit;
}

// добавление нового населённого пункта
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

if (isset($_GET['ajax_cemeteries']) && !empty($_GET['district_id'])) {
    echo CemeterySelect((int)$_GET['district_id']);
    exit;
}


//Форма поховання
function Contentgrave()
{
    $out = '<div class="contentgrave-form">
        <form action="/graveadd.php" method="post" enctype="multipart/form-data">
            <input type="hidden" name="md" value="grave">

            <div class="form-header-wrap">
                <div class="form-header">
                    <h2><i class="icon-person"></i> Форма реєстрації даних про померлого</h2>
                    <p>Заповніть всі необхідні поля для створення запису</p>
                </div>
            </div>

            <div class="form-content" style="padding:24px;padding-top:10px;padding-bottom:15px;">
                <div class="section-header">
                    <h3>
  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="section-icon" viewBox="0 0 16 16">
    <path d="M5 8a2 2 0 1 0 0-4 2 2 0 0 0 0 4m4-2.5a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 0 1h-4a.5.5 0 0 1-.5-.5M9 8a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 0 1h-4A.5.5 0 0 1 9 8m1 2.5a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 0 1h-3a.5.5 0 0 1-.5-.5"/>
    <path d="M2 2a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2zM1 4a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H8.96q.04-.245.04-.5C9 10.567 7.21 9 5 9c-2.086 0-3.8 1.398-3.984 3.181A1 1 0 0 1 1 12z"/>
  </svg>
  Особисті дані
</h3>
                </div>

                <div class="form-row-grave fio">
                    <div class="input-container-grave">
                        <input type="text" name="lname" required placeholder=" ">
                        <label>Прізвище *</label>
                    </div>
                    <div class="input-container-grave">
                        <input type="text" name="fname" required placeholder=" ">
                        <label>Ім’я *</label>
                    </div>
                    <div class="input-container-grave">
                        <input type="text" name="mname" placeholder=" ">
                        <label>По батькові</label>
                    </div>
                </div>

                <div class="form-row-grave dates">
                    <div class="input-container-grave">
                        <input type="date" name="dt1">
                        <label>Дата народження *</label>
                    </div>
                    <div class="input-container-grave">
                        <input type="date" name="dt2">
                        <label>Дата смерті *</label>
                    </div>
                </div>

                <div class="section-header">
                    <h3>
  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="section-icon" viewBox="0 0 16 16">
    <path d="M12.166 8.94c-.524 1.062-1.234 2.12-1.96 3.07A32 32 0 0 1 8 14.58a32 32 0 0 1-2.206-2.57c-.726-.95-1.436-2.008-1.96-3.07C3.304 7.867 3 6.862 3 6a5 5 0 0 1 10 0c0 .862-.305 1.867-.834 2.94M8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10"/>
    <path d="M8 8a2 2 0 1 1 0-4 2 2 0 0 1 0 4m0 1a3 3 0 1 0 0-6 3 3 0 0 0 0 6"/>
  </svg>
  Місце поховання
</h3>
                </div>

                <div class="form-row-grave location">
                    <div class="input-container-grave">' .
        RegionSelect("region", "city-select") .
        '<label>Область *</label>
                    </div>

                    <div class="input-container-grave">
                        <select name="district" id="district" required>
                            <option value="">Спочатку виберіть область</option>
                        </select>
                        <label>Район *</label>
                    </div>

                    <div class="input-container-grave">
                        <select name="settlement" id="settlement" required>
                            <option value="">Виберіть район</option>
                        </select>
                        <label>Населений пункт *</label>
                        <button type="button" id="add-settlement-btn" class="add-region-btn add-settlement-btn" disabled>+</button>
                    </div>
                </div>

                <div id="settlement-popup" class="popup" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);justify-content:center;align-items:center;z-index:9999;">
                    <div class="popup-content" style="background:#fff;padding:20px;border-radius:12px;min-width:360px;max-width:90vw;box-shadow:0 2px 12px rgba(0,0,0,0.3);">
                        <h3 style="margin:0; padding-right:30px; position:relative;">Додати населений пункт
             <button id="close-popup" class="close-pop" type="button">&times;</button>
        </h3>
                        <div id="add-settlement-form">
                            <div class="input-container-grave">
                                <input type="text" name="new_settlement" id="new-settlement-input" placeholder=" ">
                                <label for="new-settlement-input">Введіть назву населеного пункту</label>
                                <button type="button" id="submit-new-settlement" class="sub-btn">Додати</button>
                                
                            </div>
                           

                        </div>
                        <div id="custom-alert-container"></div>
                    </div>
                </div>

                <div class="form-row-grave cemetery">
                    <div class="input-container-grave">
                        <select name="idxkladb" id="cemetery" required>
                         <option value="">Виберіть район</option>
                        </select>
                        <label>Кладовище *</label>
                        <a href="/kladbadd.php" id="add-settlement-btn" class="add-region-btn add-settlement-btn">+</a>
                    </div>


                    <div class="input-container-md">
                        <input type="text" name="pos1" required placeholder=" ">
                        <label>Квартал</label>
                    </div>
                    <div class="input-container-md">
                        <input type="text" name="pos2" required placeholder=" ">
                        <label>Ряд</label>
                    </div>
                    <div class="input-container-md">
                        <input type="text" name="pos3" required placeholder=" ">
                        <label>Місце</label>
                    </div>
                </div>

                <div class="section-header">
                   <h3>
  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="section-icon" viewBox="0 0 16 16">
    <path d="M6.002 5.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0"/>
    <path d="M2.002 1a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V3a2 2 0 0 0-2-2zm12 1a1 1 0 0 1 1 1v6.5l-3.777-1.947a.5.5 0 0 0-.577.093l-3.71 3.71-2.66-1.772a.5.5 0 0 0-.63.062L1.002 12V3a1 1 0 0 1 1-1z"/>
  </svg>
  Фотографії
</h3>
                </div>

                <div class="form-vertical-grave">
    <div class="input-container-grave upload">
        <input type="file" name="photo1">
        <label>Фото поховання</label>
       
    </div>
    <div class="input-container-grave upload">
        <input type="file" name="photo2">
        <label>Фото лиця</label>
       
    </div>
</div>

                <div class="form-row-gravebutton form-actions-grave">
                    <button type="submit" class="sub-btngrave">Додати запис</button>
                    <button type="reset" class="cancel-btn">Скасувати</button>
                </div>

            </div>
        </form>
    </div>';

    $out .= '<script>
document.addEventListener("DOMContentLoaded", function() {
    var closeBtn = document.getElementById("close-popup");
    var popup = document.getElementById("settlement-popup");

    closeBtn.addEventListener("click", function(e) {
    e.preventDefault(); 
    popup.style.display = "none";
});

});
</script>';


    $out .= '
    <script>
document.addEventListener("DOMContentLoaded", function(){
    var regionSel = document.getElementById("region");
    var districtSel = document.getElementById("district");
    var settlementSel = document.getElementById("settlement");
    var cemeterySel = document.getElementById("cemetery");
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
        settlementSel.innerHTML = "<option value=\'\'>Виберіть район</option>";
        cemeterySel.innerHTML = "<option value=\'\'>Виберіть район</option>";

        var xhr = new XMLHttpRequest();
        xhr.open("GET", "/graveadd.php?ajax_districts=1&region_id=" + encodeURIComponent(regionId), true);
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
        xhr.open("GET", "/graveadd.php?ajax_settlements=1&region_id=" + encodeURIComponent(regionSel.value) + "&district_id=" + encodeURIComponent(districtId), true);
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

    function loadCemeteries(districtId){
    cemeterySel.innerHTML = "<option>Завантаження...</option>";
    var xhr = new XMLHttpRequest();
    xhr.open("GET", "/graveadd.php?ajax_cemeteries=1&district_id=" + encodeURIComponent(districtId), true);
    xhr.onload = function(){
        var response = xhr.responseText.trim();
        if(xhr.status === 200 && response !== ""){
            cemeterySel.innerHTML = response;
        } else {
           
            cemeterySel.innerHTML = "<option value=\'\'>Немає кладовищ</option>";
        }
    };
    xhr.onerror = function(){
        cemeterySel.innerHTML = "<option value=\'\'>Помилка мережі</option>";
    };
    xhr.send();
}

    if(regionSel){
        regionSel.addEventListener("change", function(){
            if(this.value) loadDistricts(this.value);
            else {
                districtSel.innerHTML = "<option value=\'\'>Спочатку виберіть область</option>";
                settlementSel.innerHTML = "<option value=\'\'>Виберіть район</option>";
                cemeterySel.innerHTML = "<option value=\'\'>Виберіть район</option>";
                setSettlementDisabled(true);
            }
            addBtn.disabled = !(regionSel.value && districtSel.value);
        });
    }

    if(districtSel){
        districtSel.addEventListener("change", function(){
            if(this.value){
                loadSettlements(this.value);
                loadCemeteries(this.value); // подгрузка кладбищ
            } else {
                setSettlementDisabled(true);
                cemeterySel.innerHTML = "<option>Виберіть район</option>";
            }
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

    function showAlert(message, type = "error", duration = 3000) {
    var container = document.getElementById("custom-alert-container");
    if(!container) return;

    var alert = document.createElement("div");
    alert.className = "alert-js " + (type === "success" ? "alert-js-success" : "alert-js-error");
    alert.textContent = message;

    container.appendChild(alert);

    
    setTimeout(() => {
        alert.style.opacity = "0";
        setTimeout(() => alert.remove(), 400); 
    }, duration);
}

    
    if(submitNewBtn){
        submitNewBtn.addEventListener("click", function(){
            var regionId = regionSel.value;
            var districtId = districtSel.value;
            var name = newInput.value.trim();
            if(!regionId || !districtId){ alert("Виберіть область та район!"); return; }
            if(!name){ showAlert("Вкажіть назву населеного пункту", "error"); 
    return; 
}

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

//вывод страницы
View_Clear();
View_Add(Page_Up('Додати поховання'));
View_Add(Menu_Up());
View_Add('<div class="out"><div class="graveadd-container">');

if ($showMessage) {
    if ($messageType === 'alert-success') {
        // Всплывающее уведомление
        View_Add('<div id="success-overlay" class="success-overlay">' . $messageText . '</div>');
        View_Add(Contentgrave());
        View_Add('
<script>
document.addEventListener("DOMContentLoaded", function(){
    var overlay = document.getElementById("success-overlay");
    if (overlay) {
       
        overlay.classList.remove("show");
        overlay.classList.remove("hide");

        
        requestAnimationFrame(function(){
            overlay.classList.add("show");
        });

      
        setTimeout(function(){
            overlay.classList.remove("show");
            overlay.classList.add("hide");
        }, 5000);

       
        overlay.addEventListener("transitionend", function(){
            if (overlay.classList.contains("hide")) {
                overlay.classList.remove("hide");
                overlay.style.opacity = 0;
            }
        });
    }
});
</script>
');


    } else {
        // сообщение об ошибке
        View_Add('<div class="graveadd has-message">
            <div class="alert ' . $messageType . '">' . $messageText . '</div>
            <p class="back-link"><a href="/auth.php">Увійти</a></p>
          </div>');
    }
} else {

    View_Add(Contentgrave());
}

View_Add('</div></div>');
View_Add(Page_Down());
View_Out();
View_Clear();
