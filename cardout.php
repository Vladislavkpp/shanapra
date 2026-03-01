<?php
/**
 * Сторінка детальної інформації про поховання
 */
require_once $_SERVER['DOCUMENT_ROOT'] . "/function.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/classes/lenta.php";

$dblink = DbConnect();
$lenta = new Lenta($dblink);

function DateFormat(?string $date): string
{
    if (empty($date) || $date === '0000-00-00') return '';
    $ts = strtotime($date);
    if (!$ts) return '';
    return date('d.m.Y', $ts);
}


// AJAX для редактирования карточек
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $getInt = fn($k) => isset($_POST[$k]) ? (int)$_POST[$k] : 0;

    if ($_POST['action'] === 'get_list') {
        $type = $_POST['type'] ?? '';
        $resp = ['status'=>'ok','data'=>[]];

        if ($type === 'regions') {
            $res = mysqli_query($dblink, "SELECT idx,title FROM region ORDER BY title");
            while($row=mysqli_fetch_assoc($res)) $resp['data'][]=$row;
            echo json_encode($resp); exit;
        }
        if ($type === 'districts') {
            $region_id = $getInt('region_id');
            $res = mysqli_query($dblink, "SELECT idx,title FROM district " . ($region_id ? "WHERE region=$region_id" : "") . " ORDER BY title");
            while($row=mysqli_fetch_assoc($res)) $resp['data'][]=$row;
            echo json_encode($resp); exit;
        }
        if ($type === 'towns') {
            $district_id = $getInt('district_id');
            $res = mysqli_query($dblink, "SELECT idx,title FROM misto " . ($district_id ? "WHERE idxdistrict=$district_id" : "") . " ORDER BY title");
            while($row=mysqli_fetch_assoc($res)) $resp['data'][]=$row;
            echo json_encode($resp); exit;
        }
        if ($type === 'cemeteries') {
            $district_id = $getInt('district_id');
            $res = mysqli_query($dblink, "SELECT idx,title FROM cemetery " . ($district_id ? "WHERE district=$district_id" : "") . " ORDER BY title");
            while($row=mysqli_fetch_assoc($res)) $resp['data'][]=$row;
            echo json_encode($resp); exit;
        }

        echo json_encode(['status'=>'error','msg'=>'unknown type']); exit;
    }

    if ($_POST['action'] === 'save_card') {
        $idx = $getInt('idx');
        if (!$idx) { echo json_encode(['status'=>'error','msg'=>'Invalid idx']); exit; }

        $res = mysqli_query($dblink,"SELECT idxadd FROM grave WHERE idx=$idx LIMIT 1");
        if (!$res || mysqli_num_rows($res)==0) { echo json_encode(['status'=>'error','msg'=>'Record not found']); exit; }
        $owner = (int)mysqli_fetch_assoc($res)['idxadd'];
        $sess = $_SESSION['uzver'] ?? 0;
        if ((int)$owner !== (int)$sess) { echo json_encode(['status'=>'error','msg'=>'No permission']); exit; }

        // Текстовые поля
        $lname = trim($_POST['lname'] ?? '');
        $fname = trim($_POST['fname'] ?? '');
        $mname = trim($_POST['mname'] ?? '');
        $dt1 = trim($_POST['dt1'] ?? '');
        $dt2 = trim($_POST['dt2'] ?? '');
        $pos1 = trim($_POST['pos1'] ?? '');
        $pos2 = trim($_POST['pos2'] ?? '');
        $pos3 = trim($_POST['pos3'] ?? '');
        $idxkladb = $getInt('idxkladb');

        $normalizeDate = fn($d) => (preg_match('/^\d{4}-\d{2}-\d{2}$/',$d)?$d:'0000-00-00');
        $dt1 = $normalizeDate($dt1); $dt2 = $normalizeDate($dt2);

        $qup = "UPDATE grave SET 
            lname='".mysqli_real_escape_string($dblink,$lname)."',
            fname='".mysqli_real_escape_string($dblink,$fname)."',
            mname='".mysqli_real_escape_string($dblink,$mname)."',
            dt1='$dt1',
            dt2='$dt2',
            pos1='".mysqli_real_escape_string($dblink,$pos1)."',
            pos2='".mysqli_real_escape_string($dblink,$pos2)."',
            pos3='".mysqli_real_escape_string($dblink,$pos3)."',
            idxkladb=".($idxkladb ? $idxkladb : "NULL")."
            WHERE idx=$idx LIMIT 1";
        mysqli_query($dblink,$qup);

        if (!empty($_FILES['photo']['name'][0])) {
            $uploadDir = __DIR__ . "/graves/$idx";
            if (!is_dir($uploadDir)) mkdir($uploadDir,0777,true);

            $uploads = [];
            for ($i=0; $i<count($_FILES['photo']['name']) && $i<3; $i++) {
                if ($_FILES['photo']['error'][$i] !== UPLOAD_ERR_OK) continue;
                $ext = strtolower(pathinfo($_FILES['photo']['name'][$i], PATHINFO_EXTENSION));
                if (!in_array($ext,['jpg','jpeg','png','gif'])) continue;

                $newName = 'photo'.($i+1).'.'.$ext;
                $target = "$uploadDir/$newName";

                if (function_exists('compressCard')) {
                    compressCard($_FILES['photo']['tmp_name'][$i], $target, 75, 300);
                } else {
                    move_uploaded_file($_FILES['photo']['tmp_name'][$i], $target);
                }

                if (file_exists($target)) {
                    $uploads[$i] = "/graves/$idx/$newName";
                }
            }

            if ($uploads) {
                $cols = [];
                foreach ($uploads as $k=>$path) $cols[] = "photo".($k+1)."='".mysqli_real_escape_string($dblink,$path)."'";
                $sql = "UPDATE grave SET ".implode(',',$cols)." WHERE idx=$idx LIMIT 1";
                mysqli_query($dblink,$sql);
            }
        }

        echo json_encode(['status'=>'ok']);
        exit;
    }

    if ($_POST['action'] === 'get_photos') {
        $id = $getInt('id');
        $res = mysqli_query($dblink, "SELECT photo1, photo2, photo3 FROM grave WHERE idx=$id LIMIT 1");
        if (!$res) { echo json_encode(['status'=>'error','msg'=>'Record not found']); exit; }
        $row = mysqli_fetch_assoc($res);

        $photos = [];
        foreach (['photo1','photo2','photo3'] as $ph) {
            $photos[$ph] = !empty($row[$ph]) ? $row[$ph] : null;
        }

        echo json_encode(['status'=>'ok','photos'=>$photos]);
        exit;
    }

    if ($_POST['action'] === 'update_photo') {
        $id = $getInt('id');
        $field = $_POST['field'] ?? '';
        if (!$id || !in_array($field, ['photo1','photo2','photo3'])) {
            echo json_encode(['status'=>'error','msg'=>'Invalid request']); exit;
        }

        $res = mysqli_query($dblink, "SELECT idxadd FROM grave WHERE idx=$id LIMIT 1");
        if (!$res || mysqli_num_rows($res)==0) { echo json_encode(['status'=>'error','msg'=>'Record not found']); exit; }
        $owner = (int)mysqli_fetch_assoc($res)['idxadd'];
        $sess = $_SESSION['uzver'] ?? 0;
        if ((int)$owner !== (int)$sess) { echo json_encode(['status'=>'error','msg'=>'No permission']); exit; }

        if (empty($_FILES['photo']['name'])) {
            echo json_encode(['status'=>'error','msg'=>'No file']); exit;
        }

        $uploadDir = __DIR__ . "/graves/$id";
        if (!is_dir($uploadDir)) mkdir($uploadDir,0777,true);

        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','gif'])) {
            echo json_encode(['status'=>'error','msg'=>'Invalid file type']); exit;
        }

        $newName = $field.'_'.time().'.'.$ext;
        $target = "$uploadDir/$newName";

        $resOld = mysqli_query($dblink, "SELECT `$field` FROM grave WHERE idx=$id LIMIT 1");
        $oldPath = '';
        if ($resOld && $r = mysqli_fetch_assoc($resOld)) $oldPath = $r[$field];

        if (function_exists('compressCard')) {
            compressCard($_FILES['photo']['tmp_name'], $target, 75, 300);
        } else {
            move_uploaded_file($_FILES['photo']['tmp_name'], $target);
        }

        if (!file_exists($target)) {
            echo json_encode(['status'=>'error','msg'=>'Upload failed']); exit;
        }

        $pathRel = "/graves/$id/$newName";
        mysqli_query($dblink, "UPDATE grave SET `$field`='".mysqli_real_escape_string($dblink,$pathRel)."' WHERE idx=$id LIMIT 1");

        if ($oldPath && $oldPath !== $pathRel) {
            $oldFile = __DIR__ . $oldPath;
            if (file_exists($oldFile)) unlink($oldFile);
        }

        echo json_encode(['status'=>'ok','url'=>$pathRel]);
        exit;
    }

    if ($_POST['action'] === 'save_photos') {
        $idx = $getInt('idx');
        if (!$idx) {
            echo json_encode(['status'=>'error','msg'=>'Invalid idx']);
            exit;
        }

        $res = mysqli_query($dblink, "SELECT idxadd FROM grave WHERE idx=$idx LIMIT 1");
        if (!$res || mysqli_num_rows($res)==0) {
            echo json_encode(['status'=>'error','msg'=>'Record not found']);
            exit;
        }
        $owner = (int)mysqli_fetch_assoc($res)['idxadd'];
        $sess = $_SESSION['uzver'] ?? 0;
        if ((int)$owner !== (int)$sess) {
            echo json_encode(['status'=>'error','msg'=>'No permission']);
            exit;
        }

        $uploadDir = __DIR__ . "/graves/$idx";
        if (!is_dir($uploadDir)) mkdir($uploadDir,0777,true);

        $updates = [];
        for ($i = 1; $i <= 3; $i++) {
            if (!empty($_FILES["photo$i"]['name'])) {
                $ext = strtolower(pathinfo($_FILES["photo$i"]['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg','jpeg','png','gif'])) continue;

                $newName = "photo{$i}_" . time() . ".$ext";
                $target = "$uploadDir/$newName";

                if (function_exists('compressCard')) {
                    compressCard($_FILES["photo$i"]['tmp_name'], $target, 75, 300);
                } else {
                    move_uploaded_file($_FILES["photo$i"]['tmp_name'], $target);
                }

                if (file_exists($target)) {
                    $pathRel = "/graves/$idx/$newName";
                    $updates[] = "photo$i='".mysqli_real_escape_string($dblink,$pathRel)."'";
                }
            }
        }

        if ($updates) {
            mysqli_query($dblink, "UPDATE grave SET " . implode(',', $updates) . " WHERE idx=$idx LIMIT 1");
        }

        $usedPhotos = [];
        $res = mysqli_query($dblink, "SELECT photo1, photo2, photo3 FROM grave WHERE idx=$idx LIMIT 1");
        if ($res && $row = mysqli_fetch_assoc($res)) {
            foreach ($row as $p) if ($p) $usedPhotos[] = basename($p);
        }

        $allFiles = glob("$uploadDir/*");
        foreach ($allFiles as $f) {
            $base = basename($f);
            if (!in_array($base, $usedPhotos)) {
                unlink($f);
            }
        }


        echo json_encode(['status'=>'ok']);
        exit;
    }

    echo json_encode(['status'=>'error','msg'=>'unknown action']);
    exit;
}


function CardInfo(mysqli $dblink, int $idx): string
{
    $idx = (int)$idx;
    $sql = "
    SELECT 
        g.*, 
        c.idx AS cemetery_idx,
        c.title AS cemetery_title,
        d.idx AS district_idx,
        d.title AS district_title,
        r.idx AS region_idx,
        r.title AS region_title,
        m.idx AS town_idx,
        m.title AS town_title,
        CONCAT(u.lname, ' ', u.fname) AS added_by
    FROM grave g
    LEFT JOIN cemetery c ON g.idxkladb = c.idx
    LEFT JOIN district d ON c.district = d.idx
    LEFT JOIN region r ON d.region = r.idx
    LEFT JOIN misto m ON c.town = m.idx
    LEFT JOIN users u ON g.idxadd = u.idx
    WHERE g.idx = $idx
    LIMIT 1
    ";

    $res = mysqli_query($dblink, $sql);
    if (!$res || mysqli_num_rows($res) == 0) {
        return '<div class="cardout"><p>Запис не знайдено.</p></div>';
    }

    $r = mysqli_fetch_assoc($res);

    $fio = trim(($r['lname'] ?? '') . ' ' . ($r['fname'] ?? '') . ' ' . ($r['mname'] ?? ''));
    $d1  = ($r['dt1'] && $r['dt1'] !== '0000-00-00') ? DateFormat($r['dt1']) : 'Дата не вказана';
    $d2  = ($r['dt2'] && $r['dt2'] !== '0000-00-00') ? DateFormat($r['dt2']) : 'Дата не вказана';

    // Фото
    $photos = [];
    foreach (['photo1', 'photo2', 'photo3'] as $p) {
        if (!empty($r[$p]) && is_file($_SERVER['DOCUMENT_ROOT'] . $r[$p])) {
            $photos[] = $r[$p];
        }
    }
    if (empty($photos)) {
        $photos[] = '/graves/noimage.jpg';
    }

// Слайдер
    $photoHtml = '<div class="cardout-photo-slider">';
    foreach ($photos as $i => $photoPath) {
        $photoHtml .= '
        <img 
            data-photo="' . ($i + 1) . '" 
            src="' . htmlspecialchars($photoPath) . '" 
            alt="' . htmlspecialchars($fio) . '" 
            class="card-photo" 
            style="' . ($i === 0 ? '' : 'display:none;') . '">';
    }
    if (count($photos) > 1) {
        $photoHtml .= '
        <button class="photo-prev" onclick="changePhoto(-1)">&#10094;</button>
        <button class="photo-next" onclick="changePhoto(1)">&#10095;</button>';
    }
    $photoHtml .= '</div>';

    $photoJs = '';
    if (count($photos) > 1) {
        $photoJs = '
    <script>
        let photos = ' . json_encode(array_values($photos)) . ';
        let currentPhoto = 0;
        const imgElements = document.querySelectorAll(".card-photo");
        const updateDisplay = () => {
            imgElements.forEach((img, i) => img.style.display = (i === currentPhoto ? "block" : "none"));
            document.querySelector(".photo-prev").disabled = (currentPhoto === 0);
            document.querySelector(".photo-next").disabled = (currentPhoto === photos.length - 1);
        };
        function changePhoto(dir) {
            const newIndex = currentPhoto + dir;
            if (newIndex < 0 || newIndex >= photos.length) return;
            currentPhoto = newIndex;
            updateDisplay();
        }
        updateDisplay();
    </script>';
    }


    $canEdit = (isset($_SESSION['uzver']) && (int)$_SESSION['uzver'] === (int)$r['idxadd']);

    $out = '
<div class="cardout" id="cardout-' . $idx . '" 
     data-card-idx="' . $idx . '"
     data-region="' . ((int)($r['region_idx'] ?? 0)) . '"
     data-district="' . ((int)($r['district_idx'] ?? 0)) . '"
     data-town="' . ((int)($r['town_idx'] ?? 0)) . '"
     data-cemetery="' . ((int)($r['cemetery_idx'] ?? 0)) . '"
>
    <div class="cardout-top">
        <div class="cardout-photo">' . $photoHtml . '</div>
        <div class="cardout-data">
            <div class="cardout-actions">';

    if ($canEdit) {
        $out .= '
        <button id="edit-btn" class="edit-btn" onclick="enableEditMode();">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-edit">
                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                <path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1"/>
                <path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415z"/>
                <path d="M16 5l3 3"/>
            </svg>
            <span>Редагувати</span>
        </button>';
    }


    $out .= '</div><h3 class="cardout-subtitle one">Детальна інформація</h3>
            <h2 class="display-fio">' . htmlspecialchars($fio) . '</h2>

            <div class="cardout-dates">
                <div class="date-block">
                    <div class="date-label">Дата народження</div>
                    <div class="date-value display-dt1">' . $d1 . '</div>
                </div>
                <div class="date-block">
                    <div class="date-label">Дата смерті</div>
                    <div class="date-value display-dt2">' . $d2 . '</div>
                </div>
            </div>

           
        </div>
    </div>

    <div class="cemeteryinfo">
     <h3 class="cardout-subtitle">Місце розташування</h3>
           <div class="location-data display-location">
  ' . (
        !empty($r['region_title']) || !empty($r['district_title']) || !empty($r['town_title'])
            ? '
        <div class="location-card">
          <div class="loc-row">
            <div class="loc-field">
              <label>Область</label>
              <div class="loc-value">' . (!empty($r['region_title']) ? htmlspecialchars($r['region_title']) : '-') . '</div>
            </div>
            <div class="loc-field">
              <label>Район</label>
              <div class="loc-value">' . (!empty($r['district_title']) ? htmlspecialchars($r['district_title']) : '-') . '</div>
            </div>
            <div class="loc-field">
              <label>Населений пункт</label>
              <div class="loc-value">' . (!empty($r['town_title']) ? htmlspecialchars($r['town_title']) : '-') . '</div>
            </div>
          </div>
        </div>
      '
            : '<p><div class="location-card">Інформація про місце розташування не вказана</div></p>'
        ) . '
</div>
        <h3 class="cardout-subtitle-3">Місце поховання</h3>

        <div class="location-data display-location">';

    $out .= (!empty($r['cemetery_title']) || !empty($r['pos1']) || !empty($r['pos2']) || !empty($r['pos3'])
        ? '<div class="grave-location-card location-card">' .
        '<div class="loc-row">' .
        '<div class="loc-field">' .
        '<label>Кладовище</label>' .
        '<div class="loc-value display-cemetery">' .
        (!empty($r['cemetery_title']) ? htmlspecialchars($r['cemetery_title']) : '<span class="nocem">Інформація не вказана</span>') .
        '</div>' .
        '</div>' .
        '<div class="grave-locations">' .
        (!empty($r['pos1']) ? '<div class="grave-item"><span>Квартал</span><strong class="display-pos1">' . htmlspecialchars($r['pos1']) . '</strong></div>' : '') .
        (!empty($r['pos2']) ? '<div class="grave-item"><span>Ряд</span><strong class="display-pos2">' . htmlspecialchars($r['pos2']) . '</strong></div>' : '') .
        (!empty($r['pos3']) ? '<div class="grave-item"><span>Місце</span><strong class="display-pos3">' . htmlspecialchars($r['pos3']) . '</strong></div>' : '') .
        '</div>' .
        '</div>' .
        '</div>'
        : '<p>Інформація про місце поховання не вказана</p>'
    );

    $out .= '</div>
    </div>
' . $photoJs . '
</div>';

    $self = basename($_SERVER['PHP_SELF']);
    $out .= <<<JS
<script>
(function(){
    const card = document.getElementById("cardout-{$idx}");
    if (!card) return;
    const cardIdx = card.dataset.cardIdx;
    let editing = false;

function ajaxPost(data) {
    return fetch(window.location.href, {
        method: "POST",
        body: data
    }).then(r => {
        if (!r.ok) throw new Error("Network response not ok: " + r.status);
        return r.json();
    });
}


    function loadList(type, params = {}) {
        const fd = new FormData();
        fd.append("action", "get_list");
        fd.append("type", type);
        for (const k in params) fd.append(k, params[k] ?? "");
        return ajaxPost(fd).then(resp => {
            if (!resp || resp.status === "error") {
                console.error("loadList error:", resp && resp.msg);
                return [];
            }
            return resp.data || [];
        }).catch(err => {
            console.error("loadList network/error:", err);
            return [];
        });
    }
    
function openPhotoModal(currentPhotos = [], cardIdx, ajaxPost) {
    let modal = document.getElementById("photoEditModal");
    if (modal) modal.remove();

    modal = document.createElement("div");
    modal.id = "photoEditModal";
    modal.className = "photo-modal";

    const photoGridHTML = [1, 2, 3].map(i => `
        <div class="photo-slot">
            <img src="\${currentPhotos[i - 1] || '/graves/noimage.jpg'}"
                 class="photo-slot-img"
                 id="photo-prev-\${i}">
            <label class="photo-slot-label">
                <input type="file" accept="image/*" id="photo-file-\${i}" data-slot="\${i}">
                <div class="label-content">
                    <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round"
                         class="label-icon">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                        <path d="M15 8h.01" />
                        <path d="M12.5 21h-6.5a3 3 0 0 1 -3 -3v-12a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v6.5" />
                        <path d="M3 16l5 -5c.928 -.893 2.072 -.893 3 0l4 4" />
                        <path d="M14 14l1 -1c.67 -.644 1.45 -.824 2.182 -.54" />
                        <path d="M16 19h6" />
                        <path d="M19 16v6" />
                    </svg>
                    <span>Змінити</span>
                </div>
            </label>
        </div>
    `).join("");

    modal.innerHTML = `
        <div class="photo-modal-backdrop"></div>
        <div class="photo-modal-content">
            <div class="photo-modal-title-container">
                <span>Редагування фотографій</span>
                <span class="photo-modal-close">
                    <img src="assets/images/closemodal.png" alt="Закрити" class="close-icon">
                </span>
            </div>

            <div class="photo-modal-message" id="photo-modal-message" style="display:none;">
                Фотографії успішно оновлені!
            </div>

            <div class="photo-modal-grid">
                \${photoGridHTML}
            </div>

            <div class="photo-modal-actions">
                <button id="photoSaveBtn">Зберегти</button>
            </div>
        </div>
    `;

    document.body.appendChild(modal);
    setTimeout(() => modal.classList.add("show"), 10);

    [1, 2, 3].forEach(i => {
        const fileInput = modal.querySelector(`#photo-file-\${i}`);
        const imgPrev = modal.querySelector(`#photo-prev-\${i}`);

        fileInput.addEventListener("change", e => {
            const file = e.target.files[0];
            if (!file) return;
            imgPrev.src = URL.createObjectURL(file);

            e.target.blur();
        });
    });

    const closeModal = () => { 
        modal.classList.remove("show"); 
        setTimeout(() => modal.remove(), 250); 
    };
    modal.querySelector(".photo-modal-backdrop").addEventListener("click", closeModal);
    modal.querySelector(".photo-modal-close").addEventListener("click", closeModal);

    modal.querySelector("#photoSaveBtn").addEventListener("click", () => {
        const fd = new FormData();
        fd.append("action", "save_photos");
        fd.append("idx", cardIdx);

        [1, 2, 3].forEach(i => {
            const fileInput = modal.querySelector(`#photo-file-\${i}`);
            if (fileInput.files[0]) fd.append("photo" + i, fileInput.files[0]);
        });

        ajaxPost(fd).then(resp => {
            if (resp.status === "ok") {
                const msg = modal.querySelector("#photo-modal-message");
                msg.style.display = "block";
                msg.classList.add("show");

                [1, 2, 3].forEach(i => {
                    const cardImg = document.querySelector(`.card-photo[data-photo='\${i}']`);
                    if (cardImg && resp.files && resp.files[i]) {
                        cardImg.src = resp.files[i] + "?t=" + Date.now();
                    }
                });

                setTimeout(() => {
                    msg.classList.remove("show");
                    setTimeout(() => msg.style.display = "none", 400);
                }, 3000);
            } else {
                alert("Помилка: " + (resp.msg || "невідома"));
            }
        }).catch(err => {
            console.error(err);
            alert("Помилка мережі");
        });
    });
}



    window.enableEditMode = function() {
        if (editing) return;
        editing = true;

        const editBtn = document.getElementById("edit-btn");
        if (editBtn) {
            editBtn.innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                    <path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1"/>
                    <path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415z"/>
                    <path d="M16 5l3 3"/>
                </svg>
                <span>Редагування</span>
            `;
            editBtn.disabled = true;
            editBtn.classList.add("editing-active");
        }

        card.classList.add("editing");

        const photoContainer = card.querySelector(".cardout-photo-slider");
        if (photoContainer) {
            const changeBtn = document.createElement("button");
            changeBtn.textContent = "Змінити";
            changeBtn.className = "photo-change-btn";
            changeBtn.addEventListener("click", () => {
                const currentPhotos = [1,2,3].map(i => {
                    const img = photoContainer.querySelector(`[data-photo="\${i}"]`);
                    return img ? img.src : null;
                });
                openPhotoModal(currentPhotos, cardIdx, ajaxPost);
            });
            photoContainer.appendChild(changeBtn);
        }

        // ФИО
        const fioEl = card.querySelector(".display-fio");
        const full = fioEl ? fioEl.textContent.trim() : "";
        const parts = full.split(" ").filter(Boolean);
        const lname = parts[0] || "";
        const fname = parts[1] || "";
        const mname = parts.slice(2).join(" ") || "";

        const fioWrap = document.createElement("div");
        fioWrap.className = "editfio-container";
        fioWrap.innerHTML = `
            <div class="editfio-field">
                <label for="lname">Прізвище</label>
                <input type="text" id="lname" class="editfio-input" name="lname" value="\${escapeHtml(lname)}" data-field="lname" />
            </div>
            <div class="editfio-field">
                <label for="fname">Ім’я</label>
                <input type="text" id="fname" class="editfio-input" name="fname" value="\${escapeHtml(fname)}" data-field="fname" />
            </div>
            <div class="editfio-field">
                <label for="mname">По батькові</label>
                <input type="text" id="mname" class="editfio-input" name="mname" value="\${escapeHtml(mname)}" data-field="mname" />
            </div>
        `;
        fioEl.replaceWith(fioWrap);

        fioWrap.querySelectorAll(".editfio-input").forEach(input => {
            const updateWidth = () => {
                const tmp = document.createElement("span");
                tmp.style.visibility = "hidden";
                tmp.style.position = "absolute";
                tmp.style.whiteSpace = "pre";
                tmp.style.font = getComputedStyle(input).font;
                tmp.textContent = input.value || input.placeholder;
                document.body.appendChild(tmp);
                input.style.width = (tmp.offsetWidth + 20) + "px";
                tmp.remove();
            };
            input.addEventListener("input", updateWidth);
            updateWidth();
        });

        // Даты
        const dt1El = card.querySelector(".display-dt1");
        const dt2El = card.querySelector(".display-dt2");
        const rawDt1 = dt1El ? (dt1El.textContent.trim() === "Дата не вказана" ? "" : toISO(dt1El.textContent.trim())) : "";
        const rawDt2 = dt2El ? (dt2El.textContent.trim() === "Дата не вказана" ? "" : toISO(dt2El.textContent.trim())) : "";

        const datesWrap = document.createElement("div");
        datesWrap.className = "edit-dates";
        datesWrap.innerHTML = `
            <div class="date-block">
                <div class="date-label">Дата народження</div>
                <input type="date" name="dt1" value="\${escapeHtml(rawDt1)}" data-field="dt1" />
            </div>
            <div class="date-block">
                <div class="date-label">Дата смерті</div>
                <input type="date" name="dt2" value="\${escapeHtml(rawDt2)}" data-field="dt2" />
            </div>
        `;
        if (dt1El && dt1El.parentNode) dt1El.parentNode.parentNode.replaceWith(datesWrap);

     
        const locationPara = card.querySelector(".display-location");
        const currentRegion = parseInt(card.dataset.region) || 0;
        const currentDistrict = parseInt(card.dataset.district) || 0;
        const currentTown = parseInt(card.dataset.town) || 0;
        const currentCemetery = parseInt(card.dataset.cemetery) || 0;

        const locWrap = document.createElement("div");
        locWrap.className = "location-data display-location";
        locWrap.innerHTML = `
          <div class="location-card">
            <div class="loc-row">
              <div class="loc-field">
                <label for="sel-region">Область</label>
                <select name="region" data-field="region" id="sel-region">
                  <option value="" disabled selected>-- Оберіть область --</option>
                </select>
              </div>
              <div class="loc-field">
                <label for="sel-district">Район</label>
                <select name="district" data-field="district" id="sel-district">
                  <option value="" disabled selected>-- Оберіть район --</option>
                </select>
              </div>
              <div class="loc-field">
                <label for="sel-town">Населений пункт</label>
                <select name="town" data-field="town" id="sel-town">
                  <option value="" disabled selected>-- Оберіть нас. пункт --</option>
                </select>
              </div>
            </div>
          </div>
        `;
        if (locationPara) locationPara.replaceWith(locWrap);

     
        const pos1El = card.querySelector(".display-pos1");
        const pos2El = card.querySelector(".display-pos2");
        const pos3El = card.querySelector(".display-pos3");

        let posWrap = document.createElement("div");
        posWrap.className = "grave-locations";

        if (pos1El && pos2El && pos3El) {
            [pos1El, pos2El, pos3El].forEach((el, i) => {
                const field = ["pos1", "pos2", "pos3"][i];
                const label = ["Квартал", "Ряд", "Місце"][i];
                const oldVal = el.textContent.trim() || "";

                const itemDiv = document.createElement("div");
                itemDiv.className = "grave-item editable";

                const labelSpan = document.createElement("span");
                labelSpan.textContent = label;

                const numInput = document.createElement("input");
                numInput.type = "number";
                numInput.min = "1";
                numInput.max = "999";
                numInput.value = oldVal;
                numInput.setAttribute("data-field", field);

                numInput.addEventListener("input", function () {
                    if (this.value === "0" || this.value.startsWith("0")) this.value = "";
                });

                itemDiv.appendChild(labelSpan);
                itemDiv.appendChild(numInput);
                posWrap.appendChild(itemDiv);
            });

            const oldWrap = card.querySelector(".grave-locations");
            if (oldWrap) oldWrap.replaceWith(posWrap);
        }

     
        const cemeterySpan = card.querySelector(".display-cemetery") || card.querySelector(".nocem");
        if (cemeterySpan) {
            const cemSelectWrap = document.createElement("div");
            cemSelectWrap.className = "cem-select-wrap";
            cemSelectWrap.innerHTML = `
                <div class="loc-field"><label>Кладовище:</label>
                    <select name="idxkladb" data-field="idxkladb" id="sel-cemetery">
                        <option value="" disabled selected>-- Оберіть кладовище --</option>
                    </select>
                </div>
            `;
            cemeterySpan.parentNode.replaceWith(cemSelectWrap);
        }

   
        const actions = document.createElement("div");
        actions.className = "edit-actions";
        actions.innerHTML = `
            <button id="save-btn">Зберегти</button>
            <button id="cancel-btn">Скасувати</button>
        `;
        const topActionsWrap = card.querySelector(".cardout-actions");
        if (topActionsWrap) topActionsWrap.appendChild(actions);

    
        const selRegion = document.getElementById("sel-region");
        const selDistrict = document.getElementById("sel-district");
        const selTown = document.getElementById("sel-town");
        const selCemetery = document.getElementById("sel-cemetery");

        function resetSelect(sel, placeholderText) {
            if (!sel) return;
            sel.innerHTML = `<option value="" disabled selected>\${placeholderText}</option>`;
        }

        function loadRegions() {
            resetSelect(selRegion, "-- Оберіть область --");
            resetSelect(selDistrict, "-- Оберіть район --");
            resetSelect(selTown, "-- Оберіть нас. пункт --");
            if (selCemetery) resetSelect(selCemetery, "-- Оберіть кладовище --");

            loadList("regions").then(regs => {
                regs.forEach(r => {
                    const opt = document.createElement("option");
                    opt.value = r.idx;
                    opt.textContent = r.title;
                    if (parseInt(r.idx,10) === currentRegion) opt.selected = true;
                    selRegion.appendChild(opt);
                });
                if (currentRegion) loadDistricts(currentRegion);
            });
        }

        function loadDistricts(regionId) {
            const rid = parseInt(regionId, 10) || 0;
            resetSelect(selDistrict, "-- Оберіть район --");
            resetSelect(selTown, "-- Оберіть нас. пункт --");
            if (selCemetery) resetSelect(selCemetery, "-- Оберіть кладовище --");
            if (!rid) return;

            loadList("districts", { region_id: rid }).then(dists => {
                dists.forEach(d => {
                    const opt = document.createElement("option");
                    opt.value = d.idx;
                    opt.textContent = d.title;
                    if (parseInt(d.idx,10) === currentDistrict) opt.selected = true;
                    selDistrict.appendChild(opt);
                });
                if (currentDistrict) loadTowns(currentDistrict);
            });
        }

        function loadTowns(districtId) {
            const did = parseInt(districtId, 10) || 0;
            resetSelect(selTown, "-- Оберіть нас. пункт --");
            if (selCemetery) resetSelect(selCemetery, "-- Оберіть кладовище --");
            if (!did) return;

            loadList("towns", { district_id: did }).then(towns => {
                towns.forEach(t => {
                    const opt = document.createElement("option");
                    opt.value = t.idx;
                    opt.textContent = t.title;
                    if (parseInt(t.idx,10) === currentTown) opt.selected = true;
                    selTown.appendChild(opt);
                });
                if (did) loadCemeteries(did);
            });
        }

        function loadCemeteries(districtId) {
            const did = parseInt(districtId, 10) || 0;
            resetSelect(selCemetery, "-- Оберіть кладовище --");
            if (!did) return;

            loadList("cemeteries", { district_id: did }).then(cems => {
                cems.forEach(c => {
                    const opt = document.createElement("option");
                    opt.value = c.idx;
                    opt.textContent = c.title;
                    if (parseInt(c.idx,10) === currentCemetery) opt.selected = true;
                    selCemetery.appendChild(opt);
                });
            });
        }

        selRegion.addEventListener("change", function(){
            const rid = parseInt(this.value, 10) || 0;
            resetSelect(selDistrict, "-- Оберіть район --");
            resetSelect(selTown, "-- Оберіть нас. пункт --");
            if (selCemetery) resetSelect(selCemetery, "-- Оберіть кладовище --");
            if (rid) loadDistricts(rid);
        });

        selDistrict.addEventListener("change", function(){
            const did = parseInt(this.value, 10) || 0;
            resetSelect(selTown, "-- Оберіть нас. пункт --");
            if (selCemetery) resetSelect(selCemetery, "-- Оберіть кладовище --");
            if (did) loadTowns(did);
        });

        loadRegions();

        document.getElementById("cancel-btn").addEventListener("click", function(e){
            e.preventDefault();
            location.reload();
        });

        document.getElementById("save-btn").addEventListener("click", function(e){
            e.preventDefault();
            const fd = new FormData();
            fd.append("action", "save_card");
            fd.append("idx", cardIdx);

            fioWrap.querySelectorAll("input[data-field]").forEach(inp => fd.append(inp.getAttribute("data-field"), inp.value.trim()));
            datesWrap.querySelectorAll("input[data-field]").forEach(inp => fd.append(inp.getAttribute("data-field"), inp.value));
            if (selCemetery) fd.append("idxkladb", selCemetery.value || 0);
            posWrap.querySelectorAll("input[data-field]").forEach(inp => fd.append(inp.getAttribute("data-field"), inp.value.trim()));

            fd.append("region", selRegion ? selRegion.value : 0);
            fd.append("district", selDistrict ? selDistrict.value : 0);
            fd.append("town", selTown ? selTown.value : 0);

            ajaxPost(fd).then(resp => {
                if (resp && resp.status === "ok") location.reload();
                else alert("Помилка: " + (resp && resp.msg ? resp.msg : "unknown"));
            }).catch(err => {
                alert("Помилка мережі");
                console.error(err);
            });
        });
    };

    function escapeHtml(s) {
        if (!s) return "";
        return String(s)
            .replace(/&/g,"&amp;")
            .replace(/</g,"&lt;")
            .replace(/>/g,"&gt;")
            .replace(/\"/g,"&quot;");
    }

    function toISO(human) {
        if (!human) return "";
        const m = human.match(/(\d{1,4})[^0-9]+(\d{1,2})[^0-9]+(\d{1,4})/);
        if (!m) return "";
        if (m[1].length === 4) return m[1] + "-" + pad(m[2]) + "-" + pad(m[3]);
        if (m[3].length === 4) return m[3] + "-" + pad(m[2]) + "-" + pad(m[1]);
        return "";
    }

    function pad(n){ return (n*1<10? "0"+(n*1):n); }

})();
</script>
JS;



    return $out;
}


function advertising(){
    $out ='<div class="adv">
<h3>Рекламий блок</h3>
    <p>Тут може бути ваша реклама</p>
    <a href="#" class="button">Дізнатись більше</a>

</div>';
    return $out;
}
$idx = (int)$_GET['idx'];

$lpage = '<div class="lenta-container">';
$lpage .= $lenta->FormMassageAdd($idx);
$lpage .= $lenta->showForm();
$lpage .= $lenta->showMessages($idx);
$lpage .= '</div>';

// вывод страницы
if (!isset($_GET['idx']) || !is_numeric($_GET['idx'])) {
    die('Некоректний запит');
}
$idx = (int)$_GET['idx'];

View_Clear();
View_Add(Page_Up('Деталі поховання'));
View_Add('<link rel="stylesheet" href="/assets/css/cardout.css">');
View_Add('<link rel="stylesheet" href="/assets/css/lenta.css">');
View_Add(Menu_Up());

$out = '
<div class="outpage">
    <div class="cardout-wrapper">
        ' . CardInfo($dblink, $idx) . '
        ' . advertising() . '

    </div>
    ' . $lpage . '
</div>';


View_Add($out);
View_Add(Page_Down());
View_Out();
View_Clear();
