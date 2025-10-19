<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/function.php";

$showMessage = false;
$messageType = '';
$messageText = '';

if (!isset($_SESSION['uzver']) || empty($_SESSION['uzver'])) {
    $showMessage = true;
    $messageType = 'alert-error';
    $messageText = 'Для доступу до сторінки потрібно авторизуватися';
}
$dblink = DbConnect();
$cemeteryId = isset($_GET['idx']) ? intval($_GET['idx']) : 0;
$cemetery = null;

if ($cemeteryId > 0) {
    $res = mysqli_query($dblink, "SELECT * FROM cemetery WHERE idx={$cemeteryId}");
    if ($res && mysqli_num_rows($res) > 0) {
        $cemetery = mysqli_fetch_assoc($res);
    } else {
        $showMessage = true;
        $messageType = 'alert-error';
        $messageText = 'Кладовище не знайдено';
    }
}


$idx = isset($_GET['idx']) ? intval($_GET['idx']) : 0;
$cemetery = [];
if ($idx > 0) {
    $res = mysqli_query($dblink, "SELECT * FROM cemetery WHERE idx=$idx LIMIT 1");
    $cemetery = mysqli_fetch_assoc($res);
}


if (!$showMessage && isset($_POST['md']) && $_POST['md'] === 'cemetery_update') {


    $title = mysqli_real_escape_string($dblink, $_POST['title']);
    $adress = mysqli_real_escape_string($dblink, $_POST['adress-cemetery']);
    $gpsx = mysqli_real_escape_string($dblink, $_POST['gpsx']);
    $gpsy = mysqli_real_escape_string($dblink, $_POST['gpsy']);

    $sql = "UPDATE cemetery SET 
        title='$title',
        adress='$adress',
        gpsx='$gpsx',
        gpsy='$gpsy'
        WHERE idx=$idx";

    $res = mysqli_query($dblink, $sql);


    if ($res && isset($_FILES['scheme']) && $_FILES['scheme']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . "/cemeteries/" . $idx;
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $ext = strtolower(pathinfo($_FILES['scheme']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','gif'])) {
            $safeName = "scheme.".$ext;
            $targetPath = $uploadDir . "/" . $safeName;
            $success = kladbcompress($_FILES['scheme']['tmp_name'], $targetPath, 75, 300);
            if ($success && file_exists($targetPath)) {
                mysqli_query($dblink, "UPDATE cemetery SET scheme='/cemeteries/$idx/$safeName' WHERE idx=$idx");
            }
        }
    }

    if ($res) {
        $showMessage = true;
        $messageType = 'alert-success';
        $messageText = 'Дані кладовища оновлено успішно!';

        $res2 = mysqli_query($dblink, "SELECT * FROM cemetery WHERE idx=$idx LIMIT 1");
        $cemetery = mysqli_fetch_assoc($res2);
    } else {
        $showMessage = true;
        $messageType = 'alert-error';
        $messageText = 'Помилка: ' . mysqli_error($dblink);
    }
}


function formcemetery_update($cemetery) {
    $dblink = DbConnect();
    $regionId = $cemetery['region_id'] ?? '';


    $districtOptions = '<option value="">Спочатку виберіть район</option>';
    if ($regionId) {
        $res = mysqli_query($dblink, "SELECT * FROM district WHERE region_id=".intval($regionId)." ORDER BY title");
        while ($row = mysqli_fetch_assoc($res)) {
            $sel = ($row['idx'] == $cemetery['district']) ? 'selected' : '';
            $districtOptions .= '<option value="'.$row['idx'].'" '.$sel.'>'.htmlspecialchars($row['title']).'</option>';
        }
    }

    $settlementOptions = '<option value="">Виберіть населений пункт</option>';
    if ($regionId && $cemetery['district']) {
        $res = mysqli_query($dblink, "SELECT * FROM misto WHERE region_id=".intval($regionId)." AND district_id=".intval($cemetery['district'])." ORDER BY title");
        while ($row = mysqli_fetch_assoc($res)) {
            $sel = ($row['idx'] == $cemetery['town']) ? 'selected' : '';
            $settlementOptions .= '<option value="'.$row['idx'].'" '.$sel.'>'.htmlspecialchars($row['title']).'</option>';
        }
    }


    $out = '
    <div class="cemetery-form-kladb">
        <h2 class="cemetery-form-title-kladb">Редагувати кладовище</h2>
        <form action="/kladbupdate.php?idx='.$cemetery['idx'].'" method="post" enctype="multipart/form-data">
            <input type="hidden" name="md" value="cemetery_update">


<h3 class="form-subtitle-kladb">
  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="subtitle-icon" viewBox="0 0 16 16">
    <path d="M14.5 3a.5.5 0 0 1 .5.5v9a.5.5 0 0 1-.5.5h-13a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5zm-13-1A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h13a1.5 1.5 0 0 0 1.5-1.5v-9A1.5 1.5 0 0 0 14.5 2z"/>
    <path d="M7 5.5a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5m-1.496-.854a.5.5 0 0 1 0 .708l-1.5 1.5a.5.5 0 0 1-.708 0l-.5-.5a.5.5 0 1 1 .708-.708l.146.147 1.146-1.147a.5.5 0 0 1 .708 0M7 9.5a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5m-1.496-.854a.5.5 0 0 1 0 .708l-1.5 1.5a.5.5 0 0 1-.708 0l-.5-.5a.5.5 0 0 1 .708-.708l.146.147 1.146-1.147a.5.5 0 0 1 .708 0"/>
  </svg>
  Інформація про кладовище
</h3>

            <div class="form-row-kladb two-cols">
                <div class="form-group-kladb">
                    <input type="text" name="title" class="form-input-kladb" placeholder=" " value="'.htmlspecialchars($cemetery['title']).'" required>
                    <label>Назва кладовища *</label>
                </div>
                <div class="form-group-kladb">
                    <input type="text" name="adress-cemetery" class="form-input-kladb" placeholder=" " value="'.htmlspecialchars($cemetery['adress']).'">
                    <label>Адреса</label>
                </div>
            </div>
            
            <h3 class="form-subtitle-kladb">
  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="subtitle-icon" viewBox="0 0 16 16">
    <path fill-rule="evenodd" d="M15.817.113A.5.5 0 0 1 16 .5v14a.5.5 0 0 1-.402.49l-5 1a.5.5 0 0 1-.196 0L5.5 15.01l-4.902.98A.5.5 0 0 1 0 15.5v-14a.5.5 0 0 1 .402-.49l5-1a.5.5 0 0 1 .196 0L10.5.99l4.902-.98a.5.5 0 0 1 .415.103M10 1.91l-4-.8v12.98l4 .8zm1 12.98 4-.8V1.11l-4 .8zm-6-.8V1.11l-4 .8v12.98z"/>
  </svg>
  Координати
</h3>

            <div class="form-row-kladb two-cols">
                <div class="form-group-kladb">
                    <input type="text" name="gpsx" class="form-input-kladb" placeholder=" " value="'.htmlspecialchars($cemetery['gpsx']).'">
                    <label>GPS X</label>
                </div>
                <div class="form-group-kladb">
                    <input type="text" name="gpsy" class="form-input-kladb" placeholder=" " value="'.htmlspecialchars($cemetery['gpsy']).'">
                    <label>GPS Y</label>
                </div>
            </div>

<h3 class="form-subtitle-kladb">
  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="subtitle-icon" viewBox="0 0 16 16">
    <path d="M6.002 5.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0"/>
    <path d="M2.002 1a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V3a2 2 0 0 0-2-2zm12 1a1 1 0 0 1 1 1v6.5l-3.777-1.947a.5.5 0 0 0-.577.093l-3.71 3.71-2.66-1.772a.5.5 0 0 0-.63.062L1.002 12V3a1 1 0 0 1 1-1z"/>
  </svg>
  Фотографія
</h3>

            <div class="form-row-kladb one-col">                                     
                <div class="form-group-kladb">
                    <input type="file" name="scheme" class="form-input-kladb">
                    <label>Схема кладовища (оновити)</label>
                </div>
            </div>

            <div class="form-actions-kladb">
                <button type="submit" class="btn-add-kladb" style="flex: 3;">Оновити</button>
                <button type="button" class="btn-cancel-kladb" style="flex: 1;" onclick="window.location.href=\'/kladbupdate.php?idx=\'">Скасувати</button>
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

    function loadDistricts(regionId, selectedDistrict=""){
        if(!districtSel) return;
        districtSel.innerHTML = `<option value="">Завантаження...</option>`;
        setSettlementDisabled(true);
        if(settlementSel) settlementSel.innerHTML = `<option value="">Виберіть населений пункт</option>`;
        var xhr = new XMLHttpRequest();
        xhr.open("GET", "/kladbupdate.php?ajax_districts=1&region_id=" + encodeURIComponent(regionId), true);
        xhr.onload = function(){
            if(xhr.status === 200){
                districtSel.innerHTML = xhr.responseText.trim();
                if(selectedDistrict) districtSel.value = selectedDistrict;
                loadSettlements(regionId, districtSel.value, "'.$cemetery['town'].'");
            }
        };
        xhr.send();
    }

    function loadSettlements(regionId, districtId, selectedTown=""){
        if(!settlementSel) return;
        settlementSel.innerHTML = `<option value="">Завантаження...</option>`;
        var xhr = new XMLHttpRequest();
        xhr.open("GET", "/kladbupdate.php?ajax_settlements=1&region_id=" + encodeURIComponent(regionId) + "&district_id=" + encodeURIComponent(districtId), true);
        xhr.onload = function(){
            if(xhr.status === 200){
                settlementSel.innerHTML = xhr.responseText.trim();
                if(selectedTown){
                    settlementSel.value = selectedTown;
                }
                setSettlementDisabled(false);
            }
        };
        xhr.send();
    }

    if(regionSel){
        regionSel.addEventListener("change", function(){
            if(this.value){
                loadDistricts(this.value);
            } else {
                if(districtSel) districtSel.innerHTML = `<option value="">Спочатку виберіть область</option>`;
                if(settlementSel) settlementSel.innerHTML = `<option value="">Виберіть населений пункт</option>`;
                setSettlementDisabled(true);
            }
        });
    }

    if(districtSel){
        districtSel.addEventListener("change", function(){
            if(this.value){
                loadSettlements(regionSel.value, this.value);
            } else {
                if(settlementSel) settlementSel.innerHTML = `<option value="">Виберіть населений пункт</option>`;
                setSettlementDisabled(true);
            }
        });
    }

    if(addBtn){
        addBtn.addEventListener("click", function(){
            if(!this.disabled){
                popup.style.display = "flex";
                if(newInput) newInput.focus();
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
            if(!regionId || !districtId){ alert("Виберіть область та район!"); return; }
            if(!name){ alert("Вкажіть назву населеного пункту!"); return; }

            var xhr = new XMLHttpRequest();
            xhr.open("POST", "/graveadd.php", true);
            xhr.setRequestHeader("Content-type","application/x-www-form-urlencoded; charset=UTF-8");
            xhr.onload = function(){
                if(xhr.status === 200 && xhr.responseText.trim().toUpperCase().indexOf("OK") === 0){
                    popup.style.display="none";
                    newInput.value="";
                    loadSettlements(regionId, districtId, xhr.responseText.trim());
                } else {
                    alert("Помилка додавання: " + xhr.responseText);
                }
            };
            xhr.onerror = function(){ alert("Помилка мережі при додаванні"); };
            xhr.send("region_id="+encodeURIComponent(regionId)+"&district_id="+encodeURIComponent(districtId)+"&name="+encodeURIComponent(name));
        });
    }

    if(popup){
        popup.addEventListener("click", function(e){
            if(e.target===popup) popup.style.display="none";
        });
    }

    if(regionSel && regionSel.value){
        loadDistricts(regionSel.value, "'.$cemetery['district'].'");
    } else {
        setSettlementDisabled(true);
    }
});
</script>';


    return $out;
}

// Вывод страницы
View_Clear();
View_Add(Page_Up());
View_Add(Menu_Up());
View_Add('<div class="out-cem">');

if ($showMessage) {
    View_Add('<div class="alert '.$messageType.'">'.$messageText.'</div>');
}

if ($cemetery) {
    View_Add(formcemetery_update($cemetery));
}

View_Add('</div>');
View_Add(Page_Down());
View_Out();
View_Clear();
