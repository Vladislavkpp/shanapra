<?php

/**
 * @var $md
 * @var $buf
 */
//require_once $_SERVER['DOCUMENT_ROOT'] .'/vendor/autoload.php';
require_once $_SERVER['DOCUMENT_ROOT'] ."/function.php";

// ===== проверка авторизации =====
$showMessage = false;
$messageType = '';
$messageText = '';

if (!isset($_SESSION['uzver']) || empty($_SESSION['uzver'])) {
    $showMessage = true;
    $messageType = 'alert-error';
    $messageText = 'Для доступу до сторінки потрібно авторизуватися';
}

// добавление кладбища =====
if (!$showMessage && isset($_POST['md']) && $_POST['md'] === 'cemetery') {
    $dblink = DbConnect();

    $sql = 'INSERT INTO cemetery (district, town, title, adress, gpsx, gpsy, idxadd, dtadd) VALUES ("' .
        $_POST['district'] . '","' .
        $_POST['town'] . '","' .
        $_POST['title'] . '","' .
        $_POST['adress-cemetery'] . '","' .
        $_POST['gpsx'] . '","' .
        $_POST['gpsy'] . '","' .
        intval($_SESSION['uzver']) . '", NOW());';


    $res = mysqli_query($dblink, $sql);

    if ($res) {
        $newId = mysqli_insert_id($dblink);

        $uploadDir = __DIR__ . "/cemeteries/" . $newId;
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $photos = [];
        foreach (['photo1', 'scheme'] as $field) {
            if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                    error_log("Неподдерживаемое расширение у {$field}: {$ext}");
                    continue;
                }

                $safeName = $field . "." . $ext;
                $targetPath = $uploadDir . "/" . $safeName;

                $success = kladbcompress($_FILES[$field]['tmp_name'], $targetPath, 75, 300);

                if ($success && file_exists($targetPath)) {
                    $photos[$field] = "/cemeteries/$newId/$safeName";
                    error_log("Фото успешно сохранено: {$targetPath}");
                } else {
                    error_log("Ошибка при сжатии или сохранении {$field}");
                }
            } elseif (isset($_FILES[$field])) {
                error_log("Ошибка загрузки {$field}: " . $_FILES[$field]['error']);
            }
        }

        if (!empty($photos)) {
            $updates = [];
            foreach ($photos as $col => $path) {
                $updates[] = "$col='" . mysqli_real_escape_string($dblink, $path) . "'";
            }
            $sqlUpdate = "UPDATE cemetery SET " . implode(",", $updates) . " WHERE idx=" . intval($newId);
            if (!mysqli_query($dblink, $sqlUpdate)) {
                die("Ошибка обновления путей к фото: " . mysqli_error($dblink));
            }
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

// Форма добавления кладбища
function formcemetery() {
    $out = '
    <div class="cemetery-form-kladb">
        <h2 class="cemetery-form-title-kladb">Додати кладовище</h2>
        <form action="/kladbadd.php" method="post" enctype="multipart/form-data">
            <input type="hidden" name="md" value="cemetery">

            <!-- Первый ряд -->
            <h3 class="form-subtitle-kladb">
  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="subtitle-icon" viewBox="0 0 16 16">
    <path d="M12.166 8.94c-.524 1.062-1.234 2.12-1.96 3.07A32 32 0 0 1 8 14.58a32 32 0 0 1-2.206-2.57c-.726-.95-1.436-2.008-1.96-3.07C3.304 7.867 3 6.862 3 6a5 5 0 0 1 10 0c0 .862-.305 1.867-.834 2.94M8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10"/>
    <path d="M8 8a2 2 0 1 1 0-4 2 2 0 0 1 0 4m0 1a3 3 0 1 0 0-6 3 3 0 0 0 0 6"/>
  </svg>
  Розташування
</h3>
            <div class="form-row-kladb three-cols">
                <div class="form-group-kladb">
                    ' . RegionForCem("", "form-input-kladb") . '
                    <label>Область *</label>
                </div>
                <div class="form-group-kladb">
                    <select name="district" id="district" class="form-input-kladb" required>
                        <option value="">Спочатку виберіть область</option>
                    </select>
                    <label>Район *</label>
                </div>

                <div class="form-group-kladb">
                    <select name="town" id="settlement" class="form-input-kladb" required>
                        <option value="">Виберіть район</option>
                    </select>
                    <label>Місто *</label>
                    <button type="button" id="add-settlement-btn" class="add-region-btn add-settlement-btn" disabled>+</button>
                </div>
            </div>

            <!-- Попап для додавання населеного пункту -->
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

            <!-- Второй ряд -->
            <h3 class="form-subtitle-kladb">
  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="subtitle-icon" viewBox="0 0 16 16">
    <path d="M14.5 3a.5.5 0 0 1 .5.5v9a.5.5 0 0 1-.5.5h-13a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5zm-13-1A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h13a1.5 1.5 0 0 0 1.5-1.5v-9A1.5 1.5 0 0 0 14.5 2z"/>
    <path d="M7 5.5a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5m-1.496-.854a.5.5 0 0 1 0 .708l-1.5 1.5a.5.5 0 0 1-.708 0l-.5-.5a.5.5 0 1 1 .708-.708l.146.147 1.146-1.147a.5.5 0 0 1 .708 0M7 9.5a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5m-1.496-.854a.5.5 0 0 1 0 .708l-1.5 1.5a.5.5 0 0 1-.708 0l-.5-.5a.5.5 0 0 1 .708-.708l.146.147 1.146-1.147a.5.5 0 0 1 .708 0"/>
  </svg>
  Інформація про кладовище
</h3>

            <div class="form-row-kladb two-cols">
                <div class="form-group-kladb">
                    <input type="text" name="title" class="form-input-kladb" placeholder=" " required>
                    <label>Назва кладовища *</label>
                </div>
                <div class="form-group-kladb">
                    <input type="text" name="adress-cemetery" class="form-input-kladb" placeholder=" " autocomplete="off">
                    <label>Адреса</label>
                </div>
            </div>

            <!-- Третий ряд -->
            <h3 class="form-subtitle-kladb">
  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="subtitle-icon" viewBox="0 0 16 16">
    <path fill-rule="evenodd" d="M15.817.113A.5.5 0 0 1 16 .5v14a.5.5 0 0 1-.402.49l-5 1a.5.5 0 0 1-.196 0L5.5 15.01l-4.902.98A.5.5 0 0 1 0 15.5v-14a.5.5 0 0 1 .402-.49l5-1a.5.5 0 0 1 .196 0L10.5.99l4.902-.98a.5.5 0 0 1 .415.103M10 1.91l-4-.8v12.98l4 .8zm1 12.98 4-.8V1.11l-4 .8zm-6-.8V1.11l-4 .8v12.98z"/>
  </svg>
  Координати
</h3>
            <div class="form-row-kladb two-cols">
                <div class="form-group-kladb">
                    <input type="text" name="gpsx" class="form-input-kladb" placeholder=" ">
                    <label>GPS X</label>
                </div>
                <div class="form-group-kladb">
                    <input type="text" name="gpsy" class="form-input-kladb" placeholder=" ">
                    <label>GPS Y</label>
                </div>
            </div>

            <!-- Четвертый ряд -->
            <h3 class="form-subtitle-kladb">
  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="subtitle-icon" viewBox="0 0 16 16">
    <path d="M6.002 5.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0"/>
    <path d="M2.002 1a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V3a2 2 0 0 0-2-2zm12 1a1 1 0 0 1 1 1v6.5l-3.777-1.947a.5.5 0 0 0-.577.093l-3.71 3.71-2.66-1.772a.5.5 0 0 0-.63.062L1.002 12V3a1 1 0 0 1 1-1z"/>
  </svg>
  Фотографія
</h3>
            
            <div class="form-row-kladb one-col">                                     
                <div class="form-group-kladb" id="kladbfile2">
                    <input type="file" name="scheme" class="form-input-kladb">
                    <label>Схема кладовища</label>
                </div>
            </div>

            <!-- Кнопки -->
            <div class="form-actions-kladb">
                <button type="submit" class="btn-add-kladb" style="flex: 3;">Додати</button>
                <button type="button" class="btn-cancel-kladb" style="flex: 1;" onclick="window.location.href=\'kladbadd.php\'">Скасувати</button>
            </div>
        </form>
          </div>';


    $out .= '
    <script>
    document.addEventListener("DOMContentLoaded", function(){
        var regionSel = document.getElementById("region");
        var districtSel = document.getElementById("district");
        var settlementSel = document.getElementById("settlement");
        var addBtn = document.getElementById("add-settlement-btn");
        var popup = document.getElementById("settlement-popup");
        var closePopupBtn = document.getElementById("close-popup");
        var submitNewBtn = document.getElementById("submit-new-settlement");
        var newInput = document.getElementById("new-settlement-input");

        function setSettlementDisabled(state){
            if(settlementSel) settlementSel.disabled = state;
            if(addBtn) addBtn.disabled = state;
        }

        function loadDistricts(regionId){
            if(!districtSel) return;
            districtSel.innerHTML = `<option value="">Завантаження...</option>`;
            setSettlementDisabled(true);
            if(settlementSel) settlementSel.innerHTML = `<option value="">Виберіть район</option>`;
            var xhr = new XMLHttpRequest();
            xhr.open("GET", "/kladbadd.php?ajax_districts=1&region_id=" + encodeURIComponent(regionId), true);
            xhr.onload = function(){
                districtSel.innerHTML = (xhr.status === 200 ? xhr.responseText.trim() : `<option value="">Помилка завантаження</option>`);
                setSettlementDisabled(true);
                if(addBtn) addBtn.disabled = !(regionSel.value && districtSel.value);
            };
            xhr.onerror = function(){
                districtSel.innerHTML = `<option value="">Помилка мережі</option>`;
                setSettlementDisabled(true);
            };
            xhr.send();
        }

        function loadSettlements(regionId, districtId){
            if(!settlementSel) return;
            settlementSel.innerHTML = `<option value="">Завантаження...</option>`;
            var xhr = new XMLHttpRequest();
            xhr.open("GET", "/kladbadd.php?ajax_settlements=1&region_id=" + encodeURIComponent(regionId) + "&district_id=" + encodeURIComponent(districtId), true);
            xhr.onload = function(){
                settlementSel.innerHTML = (xhr.status === 200 ? xhr.responseText.trim() : `<option value="">Помилка завантаження</option>`);
                setSettlementDisabled(false);
                if(addBtn) addBtn.disabled = !(regionSel.value && districtSel.value);
            };
            xhr.onerror = function(){
                settlementSel.innerHTML = `<option value="">Помилка мережі</option>`;
                setSettlementDisabled(false);
            };
            xhr.send();
        }

        if(regionSel){
            regionSel.addEventListener("change", function(){
                if(this.value) loadDistricts(this.value);
                else {
                    if(districtSel) districtSel.innerHTML = `<option value="">Спочатку виберіть область</option>`;
                    if(settlementSel) settlementSel.innerHTML = `<option value="">Виберіть район</option>`;
                    setSettlementDisabled(true);
                }
                if(addBtn) addBtn.disabled = !(regionSel.value && districtSel && districtSel.value);
            });
        }

        if(districtSel){
            districtSel.addEventListener("change", function(){
                if(this.value){
                    loadSettlements(regionSel.value, this.value);
                } else {
                    if(settlementSel) settlementSel.innerHTML = `<option value="">Виберіть район</option>`;
                    setSettlementDisabled(true);
                }
                if(addBtn) addBtn.disabled = !(regionSel.value && districtSel.value);
            });
        }

        if(addBtn){
            addBtn.addEventListener("click", function(){
                if(!this.disabled){
                    popup.style.display = "flex";
                    setTimeout(function(){ if(newInput) newInput.focus(); },50);
                }
            });
        }

        if(closePopupBtn){
            closePopupBtn.addEventListener("click", function(){ popup.style.display = "none"; });
        }

        if(submitNewBtn){
            submitNewBtn.addEventListener("click", function(){
                var regionId = regionSel ? regionSel.value : "";
                var districtId = districtSel ? districtSel.value : "";
                var name = newInput ? newInput.value.trim() : "";

                if(!regionId || !districtId){ alert("Виберіть область та район!"); return; }
                if(!name){ alert("Вкажіть назву населеного пункту!"); return; }

                var xhr = new XMLHttpRequest();
                xhr.open("POST", "/graveadd.php", true);
                xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded; charset=UTF-8");
                xhr.onload = function(){
                    if(xhr.status === 200 && xhr.responseText.trim().toUpperCase().indexOf("OK") === 0){
                        popup.style.display = "none";
                        if(newInput) newInput.value = "";
                        loadSettlements(regionId, districtId);
                    } else {
                        alert("Помилка додавання: " + xhr.responseText);
                    }
                };
                xhr.onerror = function(){ alert("Помилка мережі при додаванні"); };
                xhr.send("region_id=" + encodeURIComponent(regionId) + "&district_id=" + encodeURIComponent(districtId) + "&name=" + encodeURIComponent(name));
            });
        }

        if(popup){
            popup.addEventListener("click", function(e){
                if(e.target === popup) popup.style.display = "none";
            });
        }

        setSettlementDisabled(true);
    });
    </script>';

    return $out;
}




//вывод страницы
View_Clear();
View_Add(Page_Up());
View_Add(Menu_Up());
View_Add('<div class="out-cem">');

if ($showMessage) {
    if ($messageType === 'alert-success') {
        // Всплывающее уведомление
        View_Add('<div id="success-overlay" class="success-overlay">'.$messageText.'</div>');
        View_Add(formcemetery());
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
            <div class="alert '.$messageType.'">'.$messageText.'</div>
            <p class="back-link"><a href="/auth.php">Увійти</a></p>
          </div>');
    }
} else {
    View_Add(formcemetery());
}

View_Add('</div>');
View_Add(Page_Down());
View_Out();
View_Clear();
