<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/function.php";

$view = strtolower(trim((string)($_GET['view'] ?? '')));
if ($view === 'edit') {
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
                            $ok = kladbcompress($_FILES['scheme']['tmp_name'], $targetPath);
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

    $graveCssVersion = (int)@filemtime(__DIR__ . '/assets/css/grave.css');
    if ($graveCssVersion <= 0) {
        $graveCssVersion = time();
    }
    View_Add('<link rel="stylesheet" href="/assets/css/grave.css?v=' . $graveCssVersion . '">');
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
<div class="out acm-out">
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

                <form id="acm-update-form" class="acm-form" action="/cemetery/editcemform?idx=' . $safeCemeteryId . '" method="post" enctype="multipart/form-data" novalidate autocomplete="off">
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
                                <label for="acm-gpsy">GPS Y (широта)</label>
                                <input id="acm-gpsy" type="text" name="gpsy" value="' . $safeGpsy . '">
                            </div>
                            <div class="acm-field">
                                <label for="acm-gpsx">GPS X (довгота)</label>
                                <input id="acm-gpsx" type="text" name="gpsx" value="' . $safeGpsx . '">
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

        fetch("/cemetery/editcemform?ajax_districts=1&region_id=" + encodeURIComponent(regionId), { credentials: "same-origin" })
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

        fetch("/cemetery/editcemform?ajax_settlements=1&region_id=" + encodeURIComponent(regionId) + "&district_id=" + encodeURIComponent(districtId), { credentials: "same-origin" })
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

        variants.forEach(function (variant) {
            var score = 0;
            if (variant.lat < -90 || variant.lat > 90 || variant.lon < -180 || variant.lon > 180) {
                score = -999;
            } else {
                score += 2;
                if (variant.lat >= 44 && variant.lat <= 53 && variant.lon >= 22 && variant.lon <= 41) {
                    score += 3;
                } else if (variant.lat >= 35 && variant.lat <= 60 && variant.lon >= 10 && variant.lon <= 60) {
                    score += 1;
                }
            }

            if (score > bestScore) {
                bestScore = score;
                best = variant;
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
        setMapHint("Обрано: Lon " + formatCoord(lon) + ", Lat " + formatCoord(lat), false);
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

            fetch("/cemetery/editcemform?idx=' . $safeCemeteryId . '", {
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

    return;
}

function cemeteryEsc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function cemeteryDateRange(string $dt1, string $dt2): string
{
    $dt1 = trim($dt1);
    $dt2 = trim($dt2);

    $format = static function (string $date): string {
        if ($date === '' || $date === '0000-00-00') {
            return '';
        }
        $ts = strtotime($date);
        if ($ts === false) {
            return '';
        }
        return date('d.m.Y', $ts);
    };

    $left = $format($dt1);
    $right = $format($dt2);

    if ($left !== '' && $right !== '') {
        return $left . ' - ' . $right;
    }
    if ($left !== '') {
        return $left . ' - ...';
    }
    if ($right !== '') {
        return '... - ' . $right;
    }

    return 'Дати не вказані';
}

function cemeteryNormalizeCoord(string $value): ?float
{
    $value = trim(str_replace(',', '.', $value));
    if ($value === '' || !is_numeric($value)) {
        return null;
    }
    return (float)$value;
}

function cemeteryResolveCoordinates(?float $gpsx, ?float $gpsy): ?array
{
    if ($gpsx === null || $gpsy === null) {
        return null;
    }
    $variants = [
        ['lat' => $gpsx, 'lon' => $gpsy, 'source' => 'xy'],
        ['lat' => $gpsy, 'lon' => $gpsx, 'source' => 'yx'],
    ];
    $best = null;
    $bestScore = -999;

    foreach ($variants as $variant) {
        $score = 0;
        if ($variant['lat'] < -90 || $variant['lat'] > 90 || $variant['lon'] < -180 || $variant['lon'] > 180) {
            $score = -999;
        } else {
            $score += 2;
            if ($variant['lat'] >= 44 && $variant['lat'] <= 53 && $variant['lon'] >= 22 && $variant['lon'] <= 41) {
                $score += 3;
            } elseif ($variant['lat'] >= 35 && $variant['lat'] <= 60 && $variant['lon'] >= 10 && $variant['lon'] <= 60) {
                $score += 1;
            }
        }

        if ($score > $bestScore) {
            $bestScore = $score;
            $best = $variant;
        }
    }

    return $bestScore < 0 ? null : $best;
}

function cemeteryFormatCoord(float $value): string
{
    return rtrim(rtrim(sprintf('%.7F', $value), '0'), '.');
}

function cemeteryVisibilityConditionSql(int $viewerId, string $tableAlias = ''): string
{
    $prefix = $tableAlias !== '' ? $tableAlias . '.' : '';
    $approvedCondition = "LOWER(COALESCE({$prefix}moderation_status, 'pending')) <> 'rejected'";

    if ($viewerId > 0) {
        return '(' . $approvedCondition . " OR {$prefix}idxadd = $viewerId)";
    }

    return '(' . $approvedCondition . ')';
}

function cemeteryCanViewPublication(?array $publication, int $currentUserId): bool
{
    if (!$publication) {
        return false;
    }

    $status = strtolower(trim((string)($publication['moderation_status'] ?? 'pending')));
    if ($status !== 'rejected') {
        return true;
    }

    return $currentUserId > 0 && $currentUserId === (int)($publication['idxadd'] ?? 0);
}

function cemeteryLoadGravesPage(mysqli $dblink, int $cemeteryId, int $page, int $perPage, int $viewerId = 0): array
{
    if ($cemeteryId <= 0 || $perPage <= 0) {
        return [];
    }

    $page = max(1, $page);
    $offset = ($page - 1) * $perPage;
    $visibilityCondition = cemeteryVisibilityConditionSql($viewerId);
    $query = "
        SELECT idx, lname, fname, mname, dt1, dt1_year, dt1_month, dt1_day, dt2, dt2_year, dt2_month, dt2_day, photo1
        FROM grave
        WHERE idxkladb = $cemeteryId
          AND $visibilityCondition
        ORDER BY idx DESC
        LIMIT $perPage OFFSET $offset
    ";
    $res = mysqli_query($dblink, $query);
    if (!$res) {
        return [];
    }

    $rows = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $rows[] = $row;
    }
    return $rows;
}

function cemeteryRenderZoomableMedia(string $photo, string $alt, bool $isPlaceholder = false, string $note = ''): string
{
    $out = '<div class="cemdet-media' . ($isPlaceholder ? ' cemdet-media--empty' : '') . '" data-gallery>';
    $imgAttrs = ' class="cemdet-media-image"';
    if (!$isPlaceholder) {
        $imgAttrs .= ' data-gallery-image="1" tabindex="0" role="button" aria-label="Відкрити фото у повному розмірі"';
    }

    $out .= '<img src="' . cemeteryEsc($photo) . '" alt="' . cemeteryEsc($alt) . '"' . $imgAttrs . '>';

    if (!$isPlaceholder) {
        $out .= '<span class="cemdet-media-zoom" aria-hidden="true">';
        $out .= '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-zoom-in"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 10a7 7 0 1 0 14 0a7 7 0 1 0 -14 0" /><path d="M7 10l6 0" /><path d="M10 7l0 6" /><path d="M21 21l-6 -6" /></svg>';
        $out .= '<span class="cemdet-media-zoom-text">Повне фото</span>';
        $out .= '</span>';
    }

    if ($isPlaceholder && $note !== '') {
        $out .= '<span class="cemdet-media-note">' . cemeteryEsc($note) . '</span>';
    }

    $out .= '</div>';
    return $out;
}

function cemeteryRenderGravesCards(array $graves): string
{
    if (empty($graves)) {
        return '<div class="cemdet-empty-inline">Для цього кладовища ще немає пов\'язаних карток поховань.</div>';
    }

    $out = '<div class="cemdet-grids">';
    foreach ($graves as $grave) {
        $graveIdx = (int)($grave['idx'] ?? 0);
        $nameParts = array_filter([
            trim((string)($grave['lname'] ?? '')),
            trim((string)($grave['fname'] ?? '')),
            trim((string)($grave['mname'] ?? '')),
        ], static fn($v) => $v !== '');
        $graveName = !empty($nameParts) ? implode(' ', $nameParts) : 'Без ПІБ';

        $photo = (string)($grave['photo1'] ?? '');
        if ($photo === '' || !is_file($_SERVER['DOCUMENT_ROOT'] . $photo)) {
            $photo = '/graves/noimage.jpg';
        }

        $out .= '<a class="cemdet-grave" href="/cardout.php?idx=' . $graveIdx . '">';
        $out .= '<span class="cemdet-grave-media"><img src="' . cemeteryEsc($photo) . '" alt="' . cemeteryEsc($graveName) . '"></span>';
        $out .= '<div class="cemdet-grave-data">';
        $out .= '<b class="cemdet-grave-title">' . cemeteryEsc($graveName) . '</b>';
        $out .= '<span class="cemdet-grave-dates">' . cemeteryEsc(graveDateFormatRangeFromRow($grave)) . '</span>';
        $out .= '</div>';
        $out .= '</a>';
    }
    $out .= '</div>';

    return $out;
}

function cemeteryRenderPagerButton(int $page, string $label, bool $active = false, bool $disabled = false, string $extraClass = ''): string
{
    $classes = 'cemdet-pager-btn';
    if ($extraClass !== '') {
        $classes .= ' ' . $extraClass;
    }
    if ($active) {
        $classes .= ' is-active';
    }
    $disabledAttr = $disabled ? ' disabled aria-disabled="true"' : '';
    $dataAttr = $disabled ? '' : ' data-page="' . $page . '"';
    return '<button type="button" class="' . $classes . '"' . $dataAttr . $disabledAttr . '>' . $label . '</button>';
}

function cemeteryRenderGravesPager(int $currentPage, int $totalPages): string
{
    if ($totalPages <= 1) {
        return '';
    }

    $currentPage = max(1, min($currentPage, $totalPages));
    $start = max(1, $currentPage - 2);
    $end = min($totalPages, $currentPage + 2);

    $out = '<nav class="cemdet-pager" aria-label="Пагінація карток поховань">';
    $out .= cemeteryRenderPagerButton(max(1, $currentPage - 1), '&lsaquo; Назад', false, $currentPage <= 1, 'cemdet-pager-btn--nav');

    if ($start > 1) {
        $out .= cemeteryRenderPagerButton(1, '1', $currentPage === 1);
        if ($start > 2) {
            $out .= '<span class="cemdet-pager-gap">…</span>';
        }
    }

    for ($page = $start; $page <= $end; $page++) {
        $out .= cemeteryRenderPagerButton($page, (string)$page, $page === $currentPage);
    }

    if ($end < $totalPages) {
        if ($end < $totalPages - 1) {
            $out .= '<span class="cemdet-pager-gap">…</span>';
        }
        $out .= cemeteryRenderPagerButton($totalPages, (string)$totalPages, $currentPage === $totalPages);
    }

    $out .= cemeteryRenderPagerButton(min($totalPages, $currentPage + 1), 'Вперед &rsaquo;', false, $currentPage >= $totalPages, 'cemdet-pager-btn--nav');
    $out .= '</nav>';

    return $out;
}

$idx = (int)($_GET['idx'] ?? 0);
$dblink = DbConnect();
$isAjaxGraves = isset($_GET['ajax_graves']) && (string)$_GET['ajax_graves'] === '1';

$cemetery = null;
$gravesCount = 0;
$latestGraves = [];
$gravesPerPage = 10;
$gravesPage = max(1, (int)($_GET['page'] ?? 1));
$gravesTotalPages = 1;
$currentUserId = (int)($_SESSION['uzver'] ?? 0);

if ($idx > 0) {
    $query = "
        SELECT c.*,
               m.title AS town_name,
               d.title AS district_name,
               r.title AS region_name
        FROM cemetery c
        LEFT JOIN misto m ON c.town = m.idx
        LEFT JOIN district d ON c.district = d.idx
        LEFT JOIN region r ON d.region = r.idx
        WHERE c.idx = $idx
        LIMIT 1
    ";
    $res = mysqli_query($dblink, $query);
    if ($res) {
        $cemetery = mysqli_fetch_assoc($res) ?: null;
    }
}

if (!cemeteryCanViewPublication($cemetery, $currentUserId)) {
    $cemetery = null;
}

if ($cemetery) {
    $countRes = mysqli_query(
        $dblink,
        "SELECT COUNT(*) AS cnt FROM grave WHERE idxkladb = $idx AND " . cemeteryVisibilityConditionSql($currentUserId)
    );
    if ($countRes && ($countRow = mysqli_fetch_assoc($countRes))) {
        $gravesCount = (int)($countRow['cnt'] ?? 0);
    }

    $gravesTotalPages = max(1, (int)ceil($gravesCount / $gravesPerPage));
    $gravesPage = min($gravesPage, $gravesTotalPages);
    $latestGraves = cemeteryLoadGravesPage($dblink, $idx, $gravesPage, $gravesPerPage, $currentUserId);
}

if ($isAjaxGraves) {
    header('Content-Type: application/json; charset=UTF-8');
    if (!$cemetery) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Кладовище не знайдено'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } else {
        echo json_encode([
            'ok' => true,
            'cards_html' => cemeteryRenderGravesCards($latestGraves),
            'pager_html' => cemeteryRenderGravesPager($gravesPage, $gravesTotalPages),
            'page' => $gravesPage,
            'total_pages' => $gravesTotalPages,
            'total_items' => $gravesCount,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    mysqli_close($dblink);
    exit;
}

mysqli_close($dblink);

$title = $cemetery ? trim((string)($cemetery['title'] ?? '')) : '';
$safeTitle = cemeteryEsc($title !== '' ? $title : 'Кладовище');
$pageTitle = $cemetery ? ($title !== '' ? $title : 'Кладовище') : 'Кладовище не знайдено';

$town = trim((string)($cemetery['town_name'] ?? ''));
$district = trim((string)($cemetery['district_name'] ?? ''));
$region = trim((string)($cemetery['region_name'] ?? ''));
$address = trim((string)($cemetery['adress'] ?? ''));
$gpsxRaw = trim((string)($cemetery['gpsx'] ?? ''));
$gpsyRaw = trim((string)($cemetery['gpsy'] ?? ''));

$scheme = (string)($cemetery['scheme'] ?? '');
$isSchemeMissing = ($scheme === '' || !is_file($_SERVER['DOCUMENT_ROOT'] . $scheme));
if ($isSchemeMissing) {
    $scheme = '/cemeteries/noscheme.png';
}

$gpsx = cemeteryNormalizeCoord($gpsxRaw);
$gpsy = cemeteryNormalizeCoord($gpsyRaw);
$coordData = cemeteryResolveCoordinates($gpsx, $gpsy);
$hasCoordinates = ($coordData !== null);
$isZeroCoordinates = false;

$mapEmbed = '';
$mapLink = '';
if ($hasCoordinates) {
    $lat = (float)$coordData['lat'];
    $lon = (float)$coordData['lon'];
    $isZeroCoordinates = (abs($lat) < 0.0000001 && abs($lon) < 0.0000001);
    if (!$isZeroCoordinates) {
        $latLon = cemeteryFormatCoord($lat) . ',' . cemeteryFormatCoord($lon);
        $mapEmbed = 'https://maps.google.com/maps?q=' . rawurlencode($latLon) . '&z=16&output=embed';
        $mapLink = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($latLon);
    }
}

$chips = [];
if ($town !== '') {
    $chips[] = '<span class="cemdet-chip">' . cemeteryEsc($town) . '</span>';
}
if ($district !== '') {
    $chips[] = '<span class="cemdet-chip">' . cemeteryEsc($district) . ' р-н</span>';
}
if ($region !== '') {
    $chips[] = '<span class="cemdet-chip">' . cemeteryEsc($region) . ' обл.</span>';
}

$locationParts = [];
if ($district !== '') {
    $locationParts[] = cemeteryEsc($district) . ' район';
}
if ($region !== '') {
    $locationParts[] = cemeteryEsc($region) . ' область';
}
$locationLine = !empty($locationParts) ? implode(', ', $locationParts) : 'Локація не вказана';
$canEdit = ($cemetery && $currentUserId > 0 && (int)($cemetery['idxadd'] ?? 0) === $currentUserId);

if (!$cemetery) {
    http_response_code(404);
}

View_Clear();
View_Add(Page_Up($pageTitle));
View_Add(Menu_Up());
View_Add('<link rel="stylesheet" href="/assets/css/cemetery-detail.css?v=6">');

View_Add('<div class="out out-cemdet">');
View_Add('<main class="cemdet-page' . (!$cemetery ? ' cemdet-page--empty' : '') . '">');

if (!$cemetery) {
    View_Add('
    <section class="cemdet-empty">
        <h1>Кладовище не знайдено</h1>
        <p>Запис за вказаним ідентифікатором не знайдено або він недоступний.</p>
        <div class="cemdet-actions cemdet-empty-actions">
            <a href="/searchcem" class="cemdet-btn cemdet-btn--dark">Повернутися до пошуку</a>
            <a href="/" class="cemdet-btn cemdet-btn--light">На головну</a>
        </div>
    </section>
    ');
} else {
    $addressHtml = $address !== '' ? cemeteryEsc($address) : 'Адреса не вказана';
    $coordsHtml = $hasCoordinates
        ? 'Lat: ' . cemeteryEsc(cemeteryFormatCoord((float)$coordData['lat'])) . ', Lon: ' . cemeteryEsc(cemeteryFormatCoord((float)$coordData['lon']))
        : 'Координати не вказані';
    $addedAt = trim((string)($cemetery['dtadd'] ?? ''));
    $addedAtHtml = $addedAt !== '' && $addedAt !== '0000-00-00' ? cemeteryEsc($addedAt) : 'Немає даних';
    $moderationStatus = strtolower(trim((string)($cemetery['moderation_status'] ?? 'pending')));
    if (!in_array($moderationStatus, ['pending', 'approved', 'rejected'], true)) {
        $moderationStatus = 'pending';
    }

    $moderationBadgeHtml = '';
    if ($moderationStatus === 'pending') {
        $moderationBadgeHtml = '<span class="cemdet-moderation cemdet-moderation--pending">На модерації</span>';
    } elseif ($moderationStatus === 'approved') {
        $moderationBadgeHtml = '<span class="cemdet-moderation cemdet-moderation--approved" aria-label="Перевірено модерацією"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-circle-check"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 12a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" /><path d="M9 12l2 2l4 -4" /></svg><span class="cemdet-moderation-text">Перевірено</span></span>';
    } else {
        $moderationBadgeHtml = '<span class="cemdet-moderation cemdet-moderation--rejected">Відхилено</span>';
    }

    View_Add('<div class="cemdet-sticky" data-cemdet-sticky>');
    View_Add('<img src="' . cemeteryEsc($scheme) . '" alt="' . $safeTitle . '">');
    View_Add('<div><strong>' . $safeTitle . '</strong><span>ID #' . $idx . '</span></div>');
    View_Add('<button type="button" class="cemdet-sticky-top" data-scroll-top aria-label="Повернутися вгору"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5"></path><path d="M5 12l7-7 7 7"></path></svg></button>');
    View_Add('</div>');

    View_Add('<section class="cemdet-hero" data-cemdet-hero>');
    View_Add($moderationBadgeHtml);
    View_Add(cemeteryRenderZoomableMedia($scheme, $title !== '' ? $title : 'Кладовище', $isSchemeMissing, 'Схему кладовища не встановлено'));

    View_Add('<div class="cemdet-main">');
    View_Add('<div class="cemdet-breadcrumbs"><a href="/">Головна</a><span>/</span><a href="/searchcem">Кладовища</a><span>/</span><b>' . $safeTitle . '</b></div>');
    View_Add('<h1 class="cemdet-title">' . $safeTitle . '</h1>');

    View_Add('<div class="cemdet-chips">' . implode('', $chips) . '</div>');

    View_Add('<div class="cemdet-facts">');
    View_Add('<div class="cemdet-fact"><span>Локація</span><b>' . $locationLine . '</b></div>');
    View_Add('<div class="cemdet-fact"><span>Адреса</span><b>' . $addressHtml . '</b></div>');
    View_Add('<div class="cemdet-fact"><span>Координати</span><b>' . $coordsHtml . '</b></div>');
    View_Add('<div class="cemdet-fact"><span>Додано</span><b>' . $addedAtHtml . '</b></div>');
    View_Add('</div>');

    View_Add('<div class="cemdet-actions">');
    View_Add('<a href="/searchcem" class="cemdet-btn cemdet-btn--light"><span class="cemdet-btn-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-list-search"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M11 15a4 4 0 1 0 8 0a4 4 0 1 0 -8 0" /><path d="M18.5 18.5l2.5 2.5" /><path d="M4 6h16" /><path d="M4 12h4" /><path d="M4 18h4" /></svg></span><span>До списку кладовищ</span></a>');
    if ($hasCoordinates && !$isZeroCoordinates) {
        View_Add('<a href="' . $mapLink . '" class="cemdet-btn cemdet-btn--dark" target="_blank" rel="noopener"><span class="cemdet-btn-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-brand-google-maps"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M9.5 9.5a2.5 2.5 0 1 0 5 0a2.5 2.5 0 1 0 -5 0" /><path d="M6.428 12.494l7.314 -9.252" /><path d="M10.002 7.935l-2.937 -2.545" /><path d="M17.693 6.593l-8.336 9.979" /><path d="M17.591 6.376c.472 .907 .715 1.914 .709 2.935a7.263 7.263 0 0 1 -.72 3.18a19.085 19.085 0 0 1 -2.089 3c-.784 .933 -1.49 1.93 -2.11 2.98c-.314 .62 -.568 1.27 -.757 1.938c-.121 .36 -.277 .591 -.622 .591c-.315 0 -.463 -.136 -.626 -.593a10.595 10.595 0 0 0 -.779 -1.978a18.18 18.18 0 0 0 -1.423 -2.091c-.877 -1.184 -2.179 -2.535 -2.853 -4.071a7.077 7.077 0 0 1 -.621 -2.967a6.226 6.226 0 0 1 1.476 -4.055a6.25 6.25 0 0 1 4.811 -2.245a6.462 6.462 0 0 1 1.918 .284a6.255 6.255 0 0 1 3.686 3.092" /></svg></span><span>Відкрити в Google Maps</span></a>');
    }
    if ($canEdit) {
        View_Add('<a href="/cemetery/editcemform?idx=' . $idx . '" class="cemdet-btn cemdet-btn--ghost"><span class="cemdet-btn-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-edit"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1" /><path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415" /><path d="M16 5l3 3" /></svg></span><span>Редагувати</span></a>');
    }
    View_Add('</div>');
    View_Add('</div>');
    View_Add('</section>');

    View_Add('<section class="cemdet-stats">');
    View_Add('<article class="cemdet-stat"><span>Карток поховань</span><b>' . $gravesCount . '</b></article>');
    View_Add('<article class="cemdet-stat"><span>Фото-схема</span><b>' . ($isSchemeMissing ? 'Відсутня' : 'Додана') . '</b></article>');
    View_Add('<article class="cemdet-stat"><span>GPS-дані</span><b>' . (($hasCoordinates && !$isZeroCoordinates) ? 'Заповнено' : 'Відсутні') . '</b></article>');
    View_Add('<article class="cemdet-stat"><span>ID кладовища</span><b>#' . $idx . '</b></article>');
    View_Add('</section>');

    View_Add('<section class="cemdet-grid">');

    View_Add('<article class="cemdet-panel">');
    View_Add('<div class="cemdet-panel-head"><h2>Карта та навігація</h2></div>');
    if ($hasCoordinates && !$isZeroCoordinates) {
        View_Add('<div class="cemdet-map-wrap"><iframe src="' . $mapEmbed . '" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Мапа кладовища"></iframe></div>');
    } elseif ($isZeroCoordinates) {
        View_Add('<div class="cemdet-map-placeholder">GPS дані відсутні.</div>');
    } else {
        View_Add('<div class="cemdet-map-placeholder">Координати не додані. Тут буде інтерактивна мапа після заповнення GPS.</div>');
    }
    View_Add('</article>');

    View_Add('<article class="cemdet-panel">');
    View_Add('<div class="cemdet-panel-head"><h2>Додаткова інформація</h2></div>');
    View_Add('<p class="cemdet-muted">У поточній формі додавання кладовища зберігаються базові дані: назва, локація, адреса, координати та схема. Розширений опис (графік, контакти, правила відвідування) поки що не заповнюється.</p>');
    View_Add('<ul class="cemdet-list">');
    View_Add('<li>Зараз доступно: адреса, координати та схема розташування.</li>');
    View_Add('<li>Після розширення форми тут можна буде показувати графік роботи.</li>');
    View_Add('<li>Також тут можна виводити контакти адміністрації та примітки.</li>');
    View_Add('</ul>');
    View_Add('</article>');

    View_Add('<article class="cemdet-panel cemdet-panel--wide">');
    View_Add('<div class="cemdet-panel-head"><h2>Картки поховань цього кладовища</h2><a href="/searchx.php" class="cemdet-inline-link">Всі картки</a></div>');
    View_Add('<div id="cemdet-graves-block" class="cemdet-graves-block" data-cemetery-id="' . $idx . '" data-current-page="' . $gravesPage . '" data-total-pages="' . $gravesTotalPages . '">');
    View_Add('<div id="cemdet-graves-list">' . cemeteryRenderGravesCards($latestGraves) . '</div>');
    View_Add('<div id="cemdet-graves-pager">' . cemeteryRenderGravesPager($gravesPage, $gravesTotalPages) . '</div>');
    View_Add('<div id="cemdet-graves-status" class="cemdet-graves-status" aria-live="polite"></div>');
    View_Add('</div>');
    View_Add('</article>');

    View_Add('</section>');

    $cemeteryScript = <<<'HTML'
<script>
(function () {
    function initGalleries(root) {
        if (!root || !root.querySelectorAll) {
            return;
        }

        var galleries = [];
        if (root.matches && root.matches("[data-gallery]")) {
            galleries.push(root);
        }
        galleries = galleries.concat(Array.prototype.slice.call(root.querySelectorAll("[data-gallery]")));
        if (!galleries.length) {
            return;
        }

        galleries.forEach(function (gallery) {
            if (gallery.getAttribute("data-gallery-ready") === "1") {
                return;
            }
            gallery.setAttribute("data-gallery-ready", "1");

            var images = Array.prototype.slice.call(gallery.querySelectorAll("[data-gallery-image]"));
            var prevBtn = gallery.querySelector("[data-gallery-prev]");
            var nextBtn = gallery.querySelector("[data-gallery-next]");
            var currentIndex = 0;
            var modal = null;
            var modalImage = null;
            var modalStage = null;
            var modalPrev = null;
            var modalNext = null;
            var modalThumbs = [];

            if (!images.length) {
                return;
            }

            function ensureModal() {
                if (modal) {
                    return;
                }

                var thumbsMarkup = images.map(function (image, index) {
                    var alt = image.alt || "";
                    return '<button type="button" class="grvdet-photo-modal__thumb" data-photo-modal-thumb="' + index + '" aria-label="Фото ' + (index + 1) + '"><img src="' + image.src + '" alt="' + alt.replace(/"/g, "&quot;") + '"></button>';
                }).join("");

                modal = document.createElement("div");
                modal.className = "grvdet-photo-modal" + (images.length <= 1 ? " is-single" : "");
                modal.innerHTML =
                    '<div class="grvdet-photo-modal__backdrop" data-photo-modal-close></div>' +
                    '<div class="grvdet-photo-modal__dialog" role="dialog" aria-modal="true" aria-label="Повнорозмірне фото">' +
                        '<button type="button" class="grvdet-close-btn grvdet-photo-modal__close" data-photo-modal-close aria-label="Закрити">&#10005;</button>' +
                        '<div class="grvdet-photo-modal__layout">' +
                            (images.length > 1 ? '<div class="grvdet-photo-modal__thumbs" data-photo-modal-thumbs>' + thumbsMarkup + '</div>' : "") +
                            '<div class="grvdet-photo-modal__stage">' +
                                '<div class="grvdet-photo-modal__frame">' +
                                    '<img src="" alt="" class="grvdet-photo-modal__image">' +
                                '</div>' +
                                (images.length > 1
                                    ? '<button type="button" class="grvdet-photo-modal__nav grvdet-photo-modal__nav--prev" data-photo-modal-prev aria-label="Попереднє фото">&#10094;</button>' +
                                      '<button type="button" class="grvdet-photo-modal__nav grvdet-photo-modal__nav--next" data-photo-modal-next aria-label="Наступне фото">&#10095;</button>'
                                    : "") +
                            '</div>' +
                        '</div>' +
                    '</div>';

                document.body.appendChild(modal);
                modalImage = modal.querySelector(".grvdet-photo-modal__image");
                modalStage = modal.querySelector(".grvdet-photo-modal__stage");
                modalPrev = modal.querySelector("[data-photo-modal-prev]");
                modalNext = modal.querySelector("[data-photo-modal-next]");
                modalThumbs = Array.prototype.slice.call(modal.querySelectorAll("[data-photo-modal-thumb]"));

                if (modalThumbs.length) {
                    modalThumbs.forEach(function (thumb) {
                        thumb.addEventListener("click", function () {
                            var index = parseInt(thumb.getAttribute("data-photo-modal-thumb") || "0", 10);
                            currentIndex = index;
                            updateModal();
                            sync();
                        });
                    });
                }

                modal.addEventListener("click", function (event) {
                    if (event.target.closest("[data-photo-modal-close]")) {
                        closeModal();
                    }
                });

                if (modalPrev) {
                    modalPrev.addEventListener("click", function () {
                        if (currentIndex > 0) {
                            currentIndex -= 1;
                            updateModal();
                            sync();
                        }
                    });
                }

                if (modalNext) {
                    modalNext.addEventListener("click", function () {
                        if (currentIndex < images.length - 1) {
                            currentIndex += 1;
                            updateModal();
                            sync();
                        }
                    });
                }

                document.addEventListener("keydown", function (event) {
                    if (!modal || !modal.classList.contains("is-open")) {
                        return;
                    }

                    if (event.key === "Escape") {
                        closeModal();
                    } else if (event.key === "ArrowLeft" && modalPrev && currentIndex > 0) {
                        currentIndex -= 1;
                        updateModal();
                        sync();
                    } else if (event.key === "ArrowRight" && modalNext && currentIndex < images.length - 1) {
                        currentIndex += 1;
                        updateModal();
                        sync();
                    }
                });
            }

            function updateModal() {
                if (!modalImage) {
                    return;
                }

                modalImage.src = images[currentIndex].src;
                modalImage.alt = images[currentIndex].alt || "";
                if (modalStage) {
                    modalStage.style.setProperty("--modal-photo", 'url("' + images[currentIndex].src + '")');
                }
                if (modalPrev) {
                    modalPrev.disabled = currentIndex === 0;
                }
                if (modalNext) {
                    modalNext.disabled = currentIndex === images.length - 1;
                }
                if (modalThumbs.length) {
                    modalThumbs.forEach(function (thumb, index) {
                        thumb.classList.toggle("is-active", index === currentIndex);
                    });
                }
            }

            function openModal(index) {
                ensureModal();
                currentIndex = index;
                updateModal();
                modal.classList.add("is-open");
                document.documentElement.classList.add("grvdet-modal-open");
                document.body.classList.add("grvdet-modal-open");
            }

            function closeModal() {
                if (!modal) {
                    return;
                }

                modal.classList.remove("is-open");
                document.documentElement.classList.remove("grvdet-modal-open");
                document.body.classList.remove("grvdet-modal-open");
            }

            function sync() {
                images.forEach(function (image, index) {
                    image.classList.toggle("is-active", index === currentIndex);
                });

                if (prevBtn) {
                    prevBtn.disabled = currentIndex === 0;
                }
                if (nextBtn) {
                    nextBtn.disabled = currentIndex === images.length - 1;
                }
            }

            images.forEach(function (image, index) {
                image.addEventListener("click", function () {
                    openModal(index);
                });

                image.addEventListener("keydown", function (event) {
                    if (event.key === "Enter" || event.key === " ") {
                        event.preventDefault();
                        openModal(index);
                    }
                });
            });

            if (prevBtn) {
                prevBtn.addEventListener("click", function () {
                    if (currentIndex > 0) {
                        currentIndex -= 1;
                        sync();
                    }
                });
            }

            if (nextBtn) {
                nextBtn.addEventListener("click", function () {
                    if (currentIndex < images.length - 1) {
                        currentIndex += 1;
                        sync();
                    }
                });
            }

            sync();
        });
    }

    initGalleries(document);

    var block = document.getElementById("cemdet-graves-block");
    var listNode = document.getElementById("cemdet-graves-list");
    var pagerNode = document.getElementById("cemdet-graves-pager");
    var statusNode = document.getElementById("cemdet-graves-status");
    var cemeteryId = block ? Number(block.getAttribute("data-cemetery-id") || "0") : 0;
    var isLoading = false;

    function setStatus(text, isError) {
        if (!statusNode) {
            return;
        }
        statusNode.textContent = text || "";
        statusNode.classList.toggle("is-error", !!isError);
    }

    function setLoading(state) {
        isLoading = state;
        if (block) {
            block.classList.toggle("is-loading", state);
        }
    }

    function loadPage(page) {
        if (isLoading || !block || !listNode || !pagerNode || !cemeteryId) {
            return;
        }

        setLoading(true);
        setStatus("", false);

        fetch("/cemetery.php?ajax_graves=1&idx=" + encodeURIComponent(String(cemeteryId)) + "&page=" + encodeURIComponent(String(page)), {
            credentials: "same-origin"
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error("network");
                }
                return response.json();
            })
            .then(function (payload) {
                if (!payload || !payload.ok) {
                    throw new Error("invalid");
                }
                listNode.innerHTML = payload.cards_html || "";
                pagerNode.innerHTML = payload.pager_html || "";
                block.setAttribute("data-current-page", String(payload.page || page));
                block.setAttribute("data-total-pages", String(payload.total_pages || 1));
                initGalleries(listNode);
            })
            .catch(function () {
                setStatus("Не вдалося завантажити сторінку карток. Спробуйте ще раз.", true);
            })
            .finally(function () {
                setLoading(false);
            });
    }

    if (block && pagerNode) {
        block.addEventListener("click", function (event) {
            var button = event.target.closest(".cemdet-pager-btn[data-page]");
            if (!button || !pagerNode.contains(button)) {
                return;
            }
            event.preventDefault();
            if (button.disabled || button.classList.contains("is-active")) {
                return;
            }
            var page = Number(button.getAttribute("data-page") || "0");
            if (!Number.isFinite(page) || page < 1) {
                return;
            }
            loadPage(page);
        });
    }

    var sticky = document.querySelector("[data-cemdet-sticky]");
    var hero = document.querySelector("[data-cemdet-hero]");
    if (sticky && hero) {
        var scrollTopBtn = sticky.querySelector("[data-scroll-top]");
        if (scrollTopBtn) {
            scrollTopBtn.addEventListener("click", function () {
                window.scrollTo({ top: 0, behavior: "smooth" });
            });
        }

        var toggleSticky = function (visible) {
            sticky.classList.toggle("is-visible", visible);
        };

        if ("IntersectionObserver" in window) {
            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    toggleSticky(!entry.isIntersecting);
                });
            }, { rootMargin: "-80px 0px 0px 0px", threshold: 0 });
            observer.observe(hero);
        } else {
            window.addEventListener("scroll", function () {
                var rect = hero.getBoundingClientRect();
                toggleSticky(rect.bottom <= 80);
            });
        }
    }
})();
</script>
HTML;
    View_Add($cemeteryScript);
}

View_Add('</main>');
View_Add('</div>');
View_Add(Page_Down());
View_Out();
View_Clear();
