<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/function.php";

function kladbUpdateEsc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function kladbUpdateRegionOptions(string $selectedValue): string
{
    $dblink = DbConnect();
    $res = mysqli_query($dblink, 'SELECT idx, title FROM region ORDER BY title');
    $out = '<option value="">Оберіть область</option>';

    while ($res && $row = mysqli_fetch_assoc($res)) {
        $value = (string)$row['idx'];
        $selected = ($value === $selectedValue) ? ' selected' : '';
        $out .= '<option value="' . kladbUpdateEsc($value) . '"' . $selected . '>' . kladbUpdateEsc($row['title']) . '</option>';
    }

    mysqli_close($dblink);
    return $out;
}

function kladbUpdateDistrictOptions(string $regionValue, string $selectedValue): string
{
    if ((int)$regionValue <= 0) {
        return '<option value="">Спочатку оберіть область</option>';
    }

    $dblink = DbConnect();
    $regionId = (int)$regionValue;
    $res = mysqli_query($dblink, 'SELECT idx, title FROM district WHERE region = ' . $regionId . ' ORDER BY title');
    $out = '<option value="">Оберіть район</option>';

    while ($res && $row = mysqli_fetch_assoc($res)) {
        $value = (string)$row['idx'];
        $selected = ($value === $selectedValue) ? ' selected' : '';
        $out .= '<option value="' . kladbUpdateEsc($value) . '"' . $selected . '>' . kladbUpdateEsc($row['title']) . '</option>';
    }

    mysqli_close($dblink);
    return $out;
}

function kladbUpdateTownOptions(string $regionValue, string $districtValue, string $selectedValue): string
{
    if ((int)$regionValue <= 0 || (int)$districtValue <= 0) {
        return '<option value="">Оберіть район</option>';
    }

    $dblink = DbConnect();
    $regionId = (int)$regionValue;
    $districtId = (int)$districtValue;
    $res = mysqli_query(
        $dblink,
        'SELECT idx, title FROM misto WHERE idxregion = ' . $regionId . ' AND idxdistrict = ' . $districtId . ' ORDER BY title'
    );
    $out = '<option value="">Оберіть населений пункт</option>';

    while ($res && $row = mysqli_fetch_assoc($res)) {
        $value = (string)$row['idx'];
        $selected = ($value === $selectedValue) ? ' selected' : '';
        $out .= '<option value="' . kladbUpdateEsc($value) . '"' . $selected . '>' . kladbUpdateEsc($row['title']) . '</option>';
    }

    mysqli_close($dblink);
    return $out;
}

function kladbUpdateLoadCemetery(mysqli $dblink, int $cemeteryId): ?array
{
    if ($cemeteryId <= 0) {
        return null;
    }

    $query = "
        SELECT c.*,
               d.region AS region_id,
               d.title AS district_name,
               m.title AS town_name,
               r.title AS region_name
        FROM cemetery c
        LEFT JOIN district d ON c.district = d.idx
        LEFT JOIN misto m ON c.town = m.idx
        LEFT JOIN region r ON d.region = r.idx
        WHERE c.idx = $cemeteryId
        LIMIT 1
    ";
    $res = mysqli_query($dblink, $query);
    if (!$res) {
        return null;
    }

    return mysqli_fetch_assoc($res) ?: null;
}

function kladbUpdateModerationResetSql(): string
{
    return implode(",\n                    ", [
        "moderation_status = 'pending'",
        "moderation_submitted_at = NOW()",
        "moderation_reviewed_at = NULL",
        "moderation_reviewed_by = NULL",
        "moderation_note = NULL",
        "moderation_reject_reason = NULL",
    ]);
}

$isAuthorized = isset($_SESSION['uzver']) && !empty($_SESSION['uzver']);
$currentUserId = $isAuthorized ? (int)$_SESSION['uzver'] : 0;
$cemeteryId = (int)($_GET['idx'] ?? 0);

if (isset($_GET['ajax_districts']) && !empty($_GET['region_id'])) {
    echo getDistricts((int)$_GET['region_id']);
    exit;
}

if (isset($_GET['ajax_settlements']) && !empty($_GET['region_id']) && !empty($_GET['district_id'])) {
    echo getSettlements((int)$_GET['region_id'], (int)$_GET['district_id']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_add_settlement'])) {
    $regionId = (int)($_POST['region_id'] ?? 0);
    $districtId = (int)($_POST['district_id'] ?? 0);
    $name = trim((string)($_POST['name'] ?? ''));

    if ($regionId > 0 && $districtId > 0 && $name !== '') {
        echo addSettlement($regionId, $districtId, $name);
    } else {
        echo 'Помилка: некоректні дані';
    }
    exit;
}

$showMessage = false;
$messageType = '';
$messageText = '';

$dblink = DbConnect();
$cemetery = kladbUpdateLoadCemetery($dblink, $cemeteryId);

$formData = [
    'region' => (string)($cemetery['region_id'] ?? ''),
    'district' => (string)($cemetery['district'] ?? ''),
    'town' => (string)($cemetery['town'] ?? ''),
    'title' => (string)($cemetery['title'] ?? ''),
    'adress-cemetery' => (string)($cemetery['adress'] ?? ''),
    'gpsx' => (string)($cemetery['gpsx'] ?? ''),
    'gpsy' => (string)($cemetery['gpsy'] ?? ''),
];

$isOwner = $cemetery && $currentUserId > 0 && (int)($cemetery['idxadd'] ?? 0) === $currentUserId;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['md'] ?? '') === 'cemetery_update') {
    $postedAddress = trim((string)($_POST['cemetery-adr'] ?? ($_POST['cem_addr'] ?? ($_POST['adress-cemetery'] ?? ''))));
    $formData['region'] = trim((string)($_POST['region'] ?? ''));
    $formData['district'] = trim((string)($_POST['district'] ?? ''));
    $formData['town'] = trim((string)($_POST['town'] ?? ''));
    $formData['title'] = trim((string)($_POST['title'] ?? ''));
    $formData['adress-cemetery'] = $postedAddress;
    $formData['gpsx'] = trim((string)($_POST['gpsx'] ?? ''));
    $formData['gpsy'] = trim((string)($_POST['gpsy'] ?? ''));

    if (!$isAuthorized) {
        $showMessage = true;
        $messageType = 'error';
        $messageText = 'Для редагування потрібно авторизуватися.';
    } elseif (!$cemetery) {
        $showMessage = true;
        $messageType = 'error';
        $messageText = 'Кладовище не знайдено.';
    } elseif (!$isOwner) {
        $showMessage = true;
        $messageType = 'error';
        $messageText = 'Редагувати може лише користувач, який додав це кладовище.';
    } else {
        $region = (int)$formData['region'];
        $district = (int)$formData['district'];
        $town = (int)$formData['town'];
        $title = $formData['title'];
        $address = $formData['adress-cemetery'];
        $gpsx = $formData['gpsx'];
        $gpsy = $formData['gpsy'];

        if ($region <= 0 || $district <= 0 || $town <= 0 || $title === '') {
            $showMessage = true;
            $messageType = 'error';
            $messageText = 'Заповніть обов`язкові поля: область, район, населений пункт і назва кладовища.';
        } else {
            $stmt = mysqli_prepare(
                $dblink,
                'UPDATE cemetery SET district = ?, town = ?, title = ?, adress = ?, gpsx = ?, gpsy = ?, ' . kladbUpdateModerationResetSql() . ' WHERE idx = ? AND idxadd = ? LIMIT 1'
            );

            if (!$stmt) {
                $showMessage = true;
                $messageType = 'error';
                $messageText = 'Помилка підготовки запиту: ' . mysqli_error($dblink);
            } else {
                mysqli_stmt_bind_param($stmt, 'iissssii', $district, $town, $title, $address, $gpsx, $gpsy, $cemeteryId, $currentUserId);
                $updated = mysqli_stmt_execute($stmt);
                $stmtError = mysqli_stmt_error($stmt);
                mysqli_stmt_close($stmt);

                if (!$updated) {
                    $showMessage = true;
                    $messageType = 'error';
                    $messageText = 'Помилка оновлення: ' . ($stmtError !== '' ? $stmtError : mysqli_error($dblink));
                } else {
                    if (isset($_FILES['scheme']) && $_FILES['scheme']['error'] === UPLOAD_ERR_OK) {
                        $uploadDir = __DIR__ . '/cemeteries/' . $cemeteryId;
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0777, true);
                        }

                        $ext = strtolower((string)pathinfo($_FILES['scheme']['name'], PATHINFO_EXTENSION));
                        if (in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
                            $safeName = 'scheme.' . $ext;
                            $targetPath = $uploadDir . '/' . $safeName;
                            $ok = kladbcompress($_FILES['scheme']['tmp_name'], $targetPath, 75, 300);
                            if ($ok && file_exists($targetPath)) {
                                $schemePath = '/cemeteries/' . $cemeteryId . '/' . $safeName;
                                $schemeEscaped = mysqli_real_escape_string($dblink, $schemePath);
                                mysqli_query(
                                    $dblink,
                                    "UPDATE cemetery SET scheme = '$schemeEscaped' WHERE idx = $cemeteryId AND idxadd = $currentUserId LIMIT 1"
                                );
                            }
                        }
                    }

                    $showMessage = true;
                    $messageType = 'success';
                    $messageText = 'Зміни успішно збережено.';
                    $cemetery = kladbUpdateLoadCemetery($dblink, $cemeteryId);
                    $isOwner = $cemetery && $currentUserId > 0 && (int)($cemetery['idxadd'] ?? 0) === $currentUserId;
                    if ($cemetery) {
                        $formData = [
                            'region' => (string)($cemetery['region_id'] ?? ''),
                            'district' => (string)($cemetery['district'] ?? ''),
                            'town' => (string)($cemetery['town'] ?? ''),
                            'title' => (string)($cemetery['title'] ?? ''),
                            'adress-cemetery' => (string)($cemetery['adress'] ?? ''),
                            'gpsx' => (string)($cemetery['gpsx'] ?? ''),
                            'gpsy' => (string)($cemetery['gpsy'] ?? ''),
                        ];
                    }
                }
            }
        }
    }
}

mysqli_close($dblink);

View_Clear();
View_Add(Page_Up('Редагування кладовища'));
View_Add(Menu_Up());

if (!$isAuthorized) {
    View_Add('<link rel="stylesheet" href="/assets/css/in-dev.css">');
    View_Add('<div class="out-index out-index--404">');
    View_Add(AuthRequired_Content('/auth.php', 'Увійти'));
    View_Add('</div>');
} else {
    $alertHtml = '';
    if ($showMessage) {
        $alertClass = $messageType === 'success' ? 'acm-alert--success' : 'acm-alert--error';
        $alertHtml = '<div class="acm-alert ' . $alertClass . '">' . kladbUpdateEsc($messageText) . '</div>';
    }

    View_Add('<link rel="stylesheet" href="/assets/css/grave.css?v=1">');
    View_Add('<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">');
    View_Add('<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>');
    View_Add('
<style>
.acm-current-scheme{display:flex;align-items:center;gap:12px;padding:10px;border:1px solid #d8e2ef;border-radius:12px;background:#f8fbff}
.acm-current-scheme img{width:96px;height:96px;object-fit:cover;border-radius:10px;border:1px solid #d5dfeb;background:#edf2f8}
.acm-current-scheme small{display:block;color:#607189;font-size:12px}
.acm-current-scheme--empty{padding:14px 12px;border-style:dashed;color:#51647d;font-weight:700}
.acm-denied{max-width:760px;margin:26px auto;background:#fff;border:1px solid #d8e2ef;border-radius:16px;padding:24px}
.acm-denied h2{margin:0 0 8px;font-size:26px;line-height:1.2}
.acm-denied p{margin:0;color:#53657d;line-height:1.5}
.acm-actions .acm-btn{display:inline-flex;align-items:center;justify-content:center;text-decoration:none}
.acm-map-picker-row{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.acm-map-picker-row small{font-size:12px;color:#57708a}
.acm-btn--map{min-width:170px}
#acm-map-canvas{width:100%;height:360px;border:1px solid #d3dfec;border-radius:12px;overflow:hidden}
.acm-map-modal-card{width:min(94vw,780px)!important;box-sizing:border-box;overflow-x:hidden}
.acm-map-hint{margin-top:8px;min-height:20px;color:#476787;font-size:12px}
@media (max-width:760px){#acm-map-canvas{height:300px}}
</style>
<div class="out">
    <main class="acm-page">');

    if (!$cemetery) {
        View_Add('
        <section class="acm-denied">
            <h2>Кладовище не знайдено</h2>
            <p>Запис за вказаним ідентифікатором відсутній. Перевірте посилання та спробуйте ще раз.</p>
        </section>');
    } elseif (!$isOwner) {
        View_Add('
        <section class="acm-denied">
            <h2>Доступ до редагування обмежено</h2>
            <p>Редагувати може лише користувач, який додав це кладовище.</p>
        </section>');
    } else {
        $safeRegion = kladbUpdateEsc($formData['region']);
        $safeDistrict = kladbUpdateEsc($formData['district']);
        $safeTown = kladbUpdateEsc($formData['town']);
        $safeTitle = kladbUpdateEsc($formData['title']);
        $safeAddress = kladbUpdateEsc($formData['adress-cemetery']);
        $safeGpsx = kladbUpdateEsc($formData['gpsx']);
        $safeGpsy = kladbUpdateEsc($formData['gpsy']);
        $safeCemeteryId = (int)$cemeteryId;

        $scheme = (string)($cemetery['scheme'] ?? '');
        $hasScheme = ($scheme !== '' && is_file($_SERVER['DOCUMENT_ROOT'] . $scheme));
        $safeScheme = $hasScheme ? kladbUpdateEsc($scheme) : '';
        $currentSchemeHtml = $hasScheme
            ? '<div class="acm-current-scheme"><img src="' . $safeScheme . '" alt="Поточна схема"><div><b>Поточне зображення</b><small>Якщо потрібно, оберіть новий файл нижче.</small></div></div>'
            : '<div class="acm-current-scheme acm-current-scheme--empty">Поточне зображення не встановлено</div>';

        View_Add('
        <section class="acm-layout">
            <aside class="acm-aside">
                <div class="acm-badge">Форма редагування кладовища</div>
                <h1 class="acm-heading">Оновлення даних кладовища</h1>
                <p>Змініть потрібні поля та збережіть оновлення. Редагування доступне лише автору запису.</p>
                <ul class="acm-tips">
                    <li>Ви можете змінити область, район і населений пункт.</li>
                    <li>Назва кладовища є обов`язковою для збереження.</li>
                    <li>Схему можна оновити новим файлом JPG/PNG.</li>
                </ul>
            </aside>

            <section class="acm-form-card">
                <h2 class="acm-form-title">Редагувати кладовище</h2>
                <p class="acm-form-subtitle">Оновіть поля нижче та натисніть «Зберегти зміни».</p>
                ' . $alertHtml . '

                <form id="acm-update-form" class="acm-form" action="/kladbupdate.php?idx=' . $safeCemeteryId . '" method="post" enctype="multipart/form-data" novalidate autocomplete="off">
                    <input type="hidden" name="md" value="cemetery_update">

                    <fieldset class="acm-section">
                        <p class="acm-section-title">Розташування</p>
                        <div class="acm-row acm-row--three">
                            <div class="acm-field">
                                <label for="acm-region">Область *</label>
                                <select id="acm-region" name="region" required>
                                    ' . kladbUpdateRegionOptions($formData['region']) . '
                                </select>
                            </div>
                            <div class="acm-field">
                                <label for="acm-district">Район *</label>
                                <select id="acm-district" name="district" data-selected="' . $safeDistrict . '" required>
                                    ' . kladbUpdateDistrictOptions($formData['region'], $formData['district']) . '
                                </select>
                            </div>
                            <div class="acm-field">
                                <label for="acm-town">Населений пункт *</label>
                                <div class="acm-settlement-wrap">
                                    <select id="acm-town" name="town" data-selected="' . $safeTown . '" required>
                                        ' . kladbUpdateTownOptions($formData['region'], $formData['district'], $formData['town']) . '
                                    </select>
                                    <button type="button" id="acm-open-settlement" class="acm-add-settlement-btn" data-tooltip="Додати населений пункт" aria-label="Додати населений пункт">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-plus" aria-hidden="true" focusable="false">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                            <path d="M12 5l0 14"></path>
                                            <path d="M5 12l14 0"></path>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="acm-section">
                        <p class="acm-section-title">Основні дані</p>
                        <div class="acm-row acm-row--two">
                            <div class="acm-field">
                                <label for="acm-title">Назва кладовища *</label>
                                <input id="acm-title" type="text" name="title" value="' . $safeTitle . '" required>
                            </div>
                            <div class="acm-field">
                                <label for="acm-cemetery-adr">Адреса</label>
                                <input id="acm-cemetery-adr" type="text" name="cemetery-adr" value="' . $safeAddress . '" autocomplete="off" autocapitalize="off" spellcheck="false">
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="acm-section">
                        <p class="acm-section-title">Координати</p>
                        <div class="acm-row acm-row--two acm-row--coords">
                            <div class="acm-field">
                                <label for="acm-gpsx">GPS X</label>
                                <input id="acm-gpsx" type="text" name="gpsx" value="' . $safeGpsx . '">
                            </div>
                            <div class="acm-field">
                                <label for="acm-gpsy">GPS Y</label>
                                <input id="acm-gpsy" type="text" name="gpsy" value="' . $safeGpsy . '">
                            </div>
                        </div>
                        <div class="acm-map-picker-row">
                            <button type="button" id="acm-open-map" class="acm-btn acm-btn--ghost acm-btn--map">Вказати на карті</button>
                            <small>Поставте мітку на карті, і координати заповняться автоматично.</small>
                        </div>
                    </fieldset>

                    <fieldset class="acm-section">
                        <p class="acm-section-title">Схема кладовища</p>
                        <div class="acm-file-grid">
                            ' . $currentSchemeHtml . '
                            <label class="acm-file">
                                <span class="acm-file-title">Нова схема (необов`язково)</span>
                                <input id="acm-scheme" class="acm-file-input" type="file" name="scheme" accept=".jpg,.jpeg,.png">
                                <div class="acm-file-control">
                                    <span class="acm-file-btn">Вибрати файл</span>
                                    <span id="acm-scheme-name" class="acm-file-name">Файл не обрано</span>
                                </div>
                                <small>PNG / JPG</small>
                            </label>
                        </div>
                    </fieldset>

                    <div class="acm-actions">
                        <a href="/cemetery.php?idx=' . $safeCemeteryId . '" class="acm-btn acm-btn--ghost">Скасувати</a>
                        <button type="submit" class="acm-btn acm-btn--primary">Зберегти зміни</button>
                    </div>
                </form>
            </section>
        </section>

<div class="acm-modal" id="acm-settlement-modal" aria-hidden="true">
    <div class="acm-modal__backdrop" data-acm-close-modal></div>
    <div class="acm-modal__card" role="dialog" aria-modal="true" aria-labelledby="acm-settlement-title">
        <h3 id="acm-settlement-title" class="acm-modal__title">Додати населений пункт</h3>
        <p class="acm-modal__text">Нова назва буде додана до обраного району та області.</p>
        <div class="acm-field">
            <label for="acm-new-settlement">Назва населеного пункту</label>
            <input id="acm-new-settlement" type="text" autocomplete="off">
        </div>
        <div id="acm-settlement-hint" class="acm-modal-hint"></div>
        <div class="acm-modal__actions">
            <button type="button" class="acm-btn acm-btn--ghost" data-acm-close-modal>Скасувати</button>
            <button type="button" id="acm-save-settlement" class="acm-btn acm-btn--primary">Додати</button>
        </div>
    </div>
</div>

<div class="acm-modal" id="acm-map-modal" aria-hidden="true">
    <div class="acm-modal__backdrop" data-acm-close-map></div>
    <div class="acm-modal__card acm-map-modal-card" role="dialog" aria-modal="true" aria-labelledby="acm-map-title">
        <h3 id="acm-map-title" class="acm-modal__title">Вибір координат на карті</h3>
        <p class="acm-modal__text">Клікніть на карті, щоб поставити мітку. Після цього натисніть «Застосувати координати».</p>
        <div id="acm-map-canvas"></div>
        <div id="acm-map-hint" class="acm-map-hint"></div>
        <div class="acm-modal__actions">
            <button type="button" class="acm-btn acm-btn--ghost" data-acm-close-map>Скасувати</button>
            <button type="button" id="acm-apply-map" class="acm-btn acm-btn--primary">Застосувати координати</button>
        </div>
    </div>
</div>

<script>
(function () {
    var regionSel = document.getElementById("acm-region");
    var districtSel = document.getElementById("acm-district");
    var townSel = document.getElementById("acm-town");
    var openSettlementBtn = document.getElementById("acm-open-settlement");
    var settlementModal = document.getElementById("acm-settlement-modal");
    var newSettlementInput = document.getElementById("acm-new-settlement");
    var saveSettlementBtn = document.getElementById("acm-save-settlement");
    var settlementHint = document.getElementById("acm-settlement-hint");
    var closeSettlementNodes = settlementModal ? settlementModal.querySelectorAll("[data-acm-close-modal]") : [];
    var gpsxInput = document.getElementById("acm-gpsx");
    var gpsyInput = document.getElementById("acm-gpsy");
    var schemeInput = document.getElementById("acm-scheme");
    var schemeName = document.getElementById("acm-scheme-name");
    var openMapBtn = document.getElementById("acm-open-map");
    var mapModal = document.getElementById("acm-map-modal");
    var mapCanvas = document.getElementById("acm-map-canvas");
    var mapHint = document.getElementById("acm-map-hint");
    var applyMapBtn = document.getElementById("acm-apply-map");
    var closeMapNodes = mapModal ? mapModal.querySelectorAll("[data-acm-close-map]") : [];
    var placeholderById = {
        "acm-region": "Оберіть область",
        "acm-district": "Оберіть район",
        "acm-town": "Оберіть населений пункт"
    };
    if (!regionSel || !districtSel || !townSel) return;

    function closeAllCustomSelects(exceptWrapper) {
        document.querySelectorAll(".acm-field .custom-select-wrapper.open").forEach(function (wrapper) {
            if (exceptWrapper && wrapper === exceptWrapper) return;
            wrapper.classList.remove("open");
            var optionsBox = wrapper.querySelector(".custom-options");
            if (optionsBox) optionsBox.style.display = "none";
        });
    }

    function getCustomWrapper(selectEl) {
        return selectEl.parentNode.querySelector(".custom-select-wrapper[data-select-id=\"" + selectEl.id + "\"]");
    }

    function ensureCustomSelect(selectEl) {
        var wrapper = getCustomWrapper(selectEl);
        if (wrapper) return wrapper;

        wrapper = document.createElement("div");
        wrapper.className = "custom-select-wrapper";
        wrapper.dataset.selectId = selectEl.id;

        var trigger = document.createElement("div");
        trigger.className = "custom-select-trigger";

        var optionsBox = document.createElement("div");
        optionsBox.className = "custom-options";

        trigger.addEventListener("click", function () {
            if (selectEl.disabled) return;
            var willOpen = !wrapper.classList.contains("open");
            closeAllCustomSelects(wrapper);
            wrapper.classList.toggle("open", willOpen);
            optionsBox.style.display = willOpen ? "flex" : "none";
        });

        wrapper.appendChild(trigger);
        wrapper.appendChild(optionsBox);
        selectEl.style.display = "none";
        if (selectEl.nextSibling) {
            selectEl.parentNode.insertBefore(wrapper, selectEl.nextSibling);
        } else {
            selectEl.parentNode.appendChild(wrapper);
        }
        return wrapper;
    }

    function syncCustomSelect(selectEl) {
        var wrapper = ensureCustomSelect(selectEl);
        var trigger = wrapper.querySelector(".custom-select-trigger");
        var optionsBox = wrapper.querySelector(".custom-options");
        var options = Array.from(selectEl.options || []);
        var placeholder = placeholderById[selectEl.id] || "Оберіть";

        optionsBox.innerHTML = "";

        var selectedOption = options.find(function (opt) {
            return opt.value !== "" && opt.value === selectEl.value;
        });

        var triggerText = placeholder;
        if (selectedOption) triggerText = selectedOption.textContent;
        else if (options[0] && options[0].textContent) triggerText = options[0].textContent;
        trigger.textContent = triggerText;

        options.forEach(function (opt) {
            var optionNode = document.createElement("span");
            optionNode.textContent = opt.textContent;
            if (!opt.value) {
                optionNode.className = "custom-option disabled";
                optionsBox.appendChild(optionNode);
                return;
            }

            optionNode.className = "custom-option";
            if (opt.value === selectEl.value) optionNode.classList.add("is-selected");
            optionNode.addEventListener("click", function () {
                if (selectEl.disabled) return;
                selectEl.value = opt.value;
                syncCustomSelect(selectEl);
                closeAllCustomSelects();
                selectEl.dispatchEvent(new Event("change", { bubbles: true }));
            });
            optionsBox.appendChild(optionNode);
        });

        wrapper.classList.toggle("disabled", !!selectEl.disabled);
    }

    function resetTown() {
        townSel.innerHTML = "<option value=\"\">Оберіть район</option>";
        townSel.disabled = true;
        syncCustomSelect(townSel);
        toggleSettlementButton();
    }

    function setSettlementHint(text, isError) {
        if (!settlementHint) return;
        settlementHint.textContent = text || "";
        settlementHint.style.color = isError ? "#8b2330" : "#285c89";
    }

    function toggleSettlementButton() {
        if (!openSettlementBtn) return;
        openSettlementBtn.disabled = !(regionSel.value && districtSel.value);
    }

    function openSettlementModal() {
        if (!settlementModal || !openSettlementBtn || openSettlementBtn.disabled) return;
        settlementModal.classList.add("is-open");
        settlementModal.setAttribute("aria-hidden", "false");
        setSettlementHint("", false);
        setTimeout(function () {
            if (newSettlementInput) newSettlementInput.focus();
        }, 0);
    }

    function closeSettlementModal() {
        if (!settlementModal) return;
        settlementModal.classList.remove("is-open");
        settlementModal.setAttribute("aria-hidden", "true");
        if (newSettlementInput) newSettlementInput.value = "";
        setSettlementHint("", false);
    }

    function loadDistricts(regionId, selectedDistrict, selectedTown) {
        districtSel.disabled = true;
        districtSel.innerHTML = "<option value=\"\">Завантаження...</option>";
        syncCustomSelect(districtSel);
        resetTown();

        fetch("/kladbupdate.php?ajax_districts=1&region_id=" + encodeURIComponent(regionId), { credentials: "same-origin" })
            .then(function (res) { return res.text(); })
            .then(function (html) {
                districtSel.innerHTML = html;
                districtSel.disabled = false;
                if (selectedDistrict) districtSel.value = selectedDistrict;
                syncCustomSelect(districtSel);
                if (districtSel.value) {
                    loadTowns(regionId, districtSel.value, selectedTown || "");
                }
                toggleSettlementButton();
            })
            .catch(function () {
                districtSel.innerHTML = "<option value=\"\">Помилка завантаження</option>";
                districtSel.disabled = true;
                syncCustomSelect(districtSel);
                toggleSettlementButton();
            });
    }

    function loadTowns(regionId, districtId, selectedTown, selectedTownTitle) {
        townSel.disabled = true;
        townSel.innerHTML = "<option value=\"\">Завантаження...</option>";
        syncCustomSelect(townSel);

        fetch("/kladbupdate.php?ajax_settlements=1&region_id=" + encodeURIComponent(regionId) + "&district_id=" + encodeURIComponent(districtId), { credentials: "same-origin" })
            .then(function (res) { return res.text(); })
            .then(function (html) {
                townSel.innerHTML = html;
                townSel.disabled = false;
                if (selectedTown) townSel.value = selectedTown;
                else if (selectedTownTitle) {
                    var expected = selectedTownTitle.trim().toLowerCase();
                    var option = Array.from(townSel.options).find(function (item) {
                        return item.textContent.trim().toLowerCase() === expected;
                    });
                    if (option) townSel.value = option.value;
                }
                syncCustomSelect(townSel);
                toggleSettlementButton();
            })
            .catch(function () {
                townSel.innerHTML = "<option value=\"\">Помилка завантаження</option>";
                townSel.disabled = true;
                syncCustomSelect(townSel);
                toggleSettlementButton();
            });
    }

    regionSel.addEventListener("change", function () {
        if (!this.value) {
            districtSel.innerHTML = "<option value=\"\">Спочатку оберіть область</option>";
            districtSel.disabled = true;
            syncCustomSelect(districtSel);
            resetTown();
            return;
        }
        loadDistricts(this.value, "", "");
    });

    districtSel.addEventListener("change", function () {
        if (!regionSel.value || !this.value) {
            resetTown();
            return;
        }
        loadTowns(regionSel.value, this.value, "");
    });

    document.addEventListener("click", function (event) {
        if (!event.target.closest(".acm-field .custom-select-wrapper")) {
            closeAllCustomSelects();
        }
    });

    if (schemeInput && schemeName) {
        schemeInput.addEventListener("change", function () {
            if (schemeInput.files && schemeInput.files[0]) {
                schemeName.textContent = schemeInput.files[0].name;
            } else {
                schemeName.textContent = "Файл не обрано";
            }
        });
    }

    function parseCoord(value) {
        var normalized = String(value || "").replace(",", ".").trim();
        if (!normalized) return null;
        var num = Number(normalized);
        return Number.isFinite(num) ? num : null;
    }

    function formatCoord(value) {
        return Number(value).toFixed(7).replace(/\.?0+$/, "");
    }

    function resolveLatLonFromFields(xVal, yVal) {
        if (xVal === null || yVal === null) return null;
        var variants = [
            { lat: xVal, lon: yVal },
            { lat: yVal, lon: xVal }
        ];
        var best = null;
        var bestScore = -999;
        variants.forEach(function (v) {
            var score = 0;
            if (v.lat < -90 || v.lat > 90 || v.lon < -180 || v.lon > 180) {
                score = -999;
            } else {
                score += 2;
                if (v.lat >= 44 && v.lat <= 53 && v.lon >= 22 && v.lon <= 41) score += 3;
                else if (v.lat >= 35 && v.lat <= 60 && v.lon >= 10 && v.lon <= 60) score += 1;
            }
            if (score > bestScore) {
                bestScore = score;
                best = v;
            }
        });
        return bestScore < 0 ? null : best;
    }

    var map = null;
    var mapMarker = null;
    var mapSelected = null;

    function setMapHint(text, isError) {
        if (!mapHint) return;
        mapHint.textContent = text || "";
        mapHint.style.color = isError ? "#8b2330" : "#476787";
    }

    function setMarker(lat, lon, moveMap) {
        if (!map || !window.L) return;
        mapSelected = { lat: lat, lon: lon };
        if (!mapMarker) {
            mapMarker = window.L.marker([lat, lon], { draggable: true }).addTo(map);
            mapMarker.on("dragend", function () {
                var p = mapMarker.getLatLng();
                setMarker(p.lat, p.lng, false);
            });
        } else {
            mapMarker.setLatLng([lat, lon]);
        }
        if (moveMap) map.setView([lat, lon], Math.max(map.getZoom(), 15));
        setMapHint("Обрано: Lat " + formatCoord(lat) + ", Lon " + formatCoord(lon), false);
    }

    function ensureMap() {
        if (map || !window.L || !mapCanvas) return;
        map = window.L.map("acm-map-canvas", { zoomControl: true }).setView([48.5, 31.2], 6);
        window.L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
            maxZoom: 19,
            attribution: "&copy; OpenStreetMap"
        }).addTo(map);
        map.on("click", function (e) {
            setMarker(e.latlng.lat, e.latlng.lng, false);
        });
    }

    function openMapModal() {
        if (!mapModal) return;
        mapModal.classList.add("is-open");
        mapModal.setAttribute("aria-hidden", "false");
        setMapHint("", false);

        if (!window.L) {
            setMapHint("Не вдалося завантажити карту. Перевірте підключення до інтернету.", true);
            return;
        }

        ensureMap();
        if (!map) return;

        var xVal = parseCoord(gpsxInput ? gpsxInput.value : "");
        var yVal = parseCoord(gpsyInput ? gpsyInput.value : "");
        var resolved = resolveLatLonFromFields(xVal, yVal);
        if (resolved) setMarker(resolved.lat, resolved.lon, true);
        else if (mapSelected) setMarker(mapSelected.lat, mapSelected.lon, true);
        else setMapHint("Клікніть на карті, щоб поставити мітку.", false);

        setTimeout(function () { map.invalidateSize(); }, 60);
    }

    function closeMapModal() {
        if (!mapModal) return;
        mapModal.classList.remove("is-open");
        mapModal.setAttribute("aria-hidden", "true");
    }

    if (openMapBtn) openMapBtn.addEventListener("click", openMapModal);
    if (openSettlementBtn) openSettlementBtn.addEventListener("click", openSettlementModal);
    closeSettlementNodes.forEach(function (node) { node.addEventListener("click", closeSettlementModal); });
    closeMapNodes.forEach(function (node) { node.addEventListener("click", closeMapModal); });

    if (saveSettlementBtn) {
        saveSettlementBtn.addEventListener("click", function () {
            var name = newSettlementInput ? newSettlementInput.value.trim() : "";
            if (!regionSel.value || !districtSel.value) {
                setSettlementHint("Оберіть область і район перед додаванням населеного пункту.", true);
                return;
            }
            if (!name) {
                setSettlementHint("Вкажіть назву населеного пункту.", true);
                return;
            }

            saveSettlementBtn.disabled = true;
            setSettlementHint("Додаємо населений пункт...", false);

            var payload = new URLSearchParams();
            payload.set("ajax_add_settlement", "1");
            payload.set("region_id", regionSel.value);
            payload.set("district_id", districtSel.value);
            payload.set("name", name);

            fetch("/kladbupdate.php?idx=' . $safeCemeteryId . '", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
                credentials: "same-origin",
                body: payload.toString()
            })
                .then(function (response) { return response.text(); })
                .then(function (text) {
                    if (text.trim().toUpperCase().indexOf("OK") === 0) {
                        setSettlementHint("Населений пункт додано. Оновлюємо список...", false);
                        loadTowns(regionSel.value, districtSel.value, "", name);
                        setTimeout(closeSettlementModal, 240);
                        return;
                    }
                    setSettlementHint(text || "Не вдалося додати населений пункт.", true);
                })
                .catch(function () {
                    setSettlementHint("Помилка мережі при додаванні населеного пункту.", true);
                })
                .finally(function () {
                    saveSettlementBtn.disabled = false;
                });
        });
    }
    if (applyMapBtn) {
        applyMapBtn.addEventListener("click", function () {
            if (!mapSelected || !gpsxInput || !gpsyInput) {
                setMapHint("Спочатку оберіть точку на карті.", true);
                return;
            }
            // GPS X = Longitude, GPS Y = Latitude
            gpsxInput.value = formatCoord(mapSelected.lon);
            gpsyInput.value = formatCoord(mapSelected.lat);
            closeMapModal();
        });
    }
    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape" && settlementModal && settlementModal.classList.contains("is-open")) {
            closeSettlementModal();
        }
        if (event.key === "Escape" && mapModal && mapModal.classList.contains("is-open")) {
            closeMapModal();
        }
    });

    var initialDistrict = districtSel.dataset.selected || "";
    var initialTown = townSel.dataset.selected || "";
    syncCustomSelect(regionSel);
    syncCustomSelect(districtSel);
    syncCustomSelect(townSel);
    toggleSettlementButton();
    if (regionSel.value) {
        loadDistricts(regionSel.value, initialDistrict, initialTown);
    } else {
        districtSel.disabled = true;
        syncCustomSelect(districtSel);
        resetTown();
    }
})();
</script>');
    }

    View_Add('</main></div>');
}

View_Add(Page_Down());
View_Out();
View_Clear();
