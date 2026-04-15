<?php
require_once "function.php";
require_once "function_vra.php";

$view = strtolower(trim((string)($_GET['view'] ?? '')));
if ($view === 'add') {
$isAuthorized = isset($_SESSION['uzver']) && !empty($_SESSION['uzver']);
$showMessage = false;
$messageType = '';
$messageText = '';

$formData = [
    'region' => '',
    'district' => '',
    'town' => '',
    'title' => '',
    'adress-cemetery' => '',
    'gpsx' => '',
    'gpsy' => '',
];

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

if ($isAuthorized && isset($_POST['md']) && $_POST['md'] === 'cemetery') {
    $postedAddress = trim((string)($_POST['cemetery-adr'] ?? ($_POST['cem_addr'] ?? ($_POST['adress-cemetery'] ?? ''))));
    $formData['region'] = trim((string)($_POST['region'] ?? ''));
    $formData['district'] = trim((string)($_POST['district'] ?? ''));
    $formData['town'] = trim((string)($_POST['town'] ?? ''));
    $formData['title'] = trim((string)($_POST['title'] ?? ''));
    $formData['adress-cemetery'] = $postedAddress;
    $formData['gpsx'] = trim((string)($_POST['gpsx'] ?? ''));
    $formData['gpsy'] = trim((string)($_POST['gpsy'] ?? ''));

    $district = (int)$formData['district'];
    $town = (int)$formData['town'];
    $title = $formData['title'];
    $address = $formData['adress-cemetery'];
    $gpsx = $formData['gpsx'];
    $gpsy = $formData['gpsy'];
    $authorId = (int)$_SESSION['uzver'];

    if ((int)$formData['region'] <= 0 || $district <= 0 || $town <= 0 || $title === '') {
        $showMessage = true;
        $messageType = 'error';
        $messageText = 'Заповніть обов`язкові поля: область, район, населений пункт і назва кладовища.';
    } else {
        $dblink = DbConnect();
        $stmt = mysqli_prepare(
            $dblink,
            'INSERT INTO cemetery (district, town, title, adress, gpsx, gpsy, idxadd, dtadd) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
        );

        if (!$stmt) {
            $showMessage = true;
            $messageType = 'error';
            $messageText = 'Помилка підготовки запиту: ' . mysqli_error($dblink);
        } else {
            mysqli_stmt_bind_param($stmt, 'iissssi', $district, $town, $title, $address, $gpsx, $gpsy, $authorId);
            $saved = mysqli_stmt_execute($stmt);

            if (!$saved) {
                $showMessage = true;
                $messageType = 'error';
                $messageText = 'Помилка збереження: ' . mysqli_error($dblink);
            } else {
                $newId = (int)mysqli_insert_id($dblink);
                $uploadDir = __DIR__ . '/cemeteries/' . $newId;
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $uploaded = [];
                foreach (['scheme'] as $field) {
                    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
                        continue;
                    }

                    $ext = strtolower((string)pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
                    if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
                        continue;
                    }

                    $safeName = $field . '.' . $ext;
                    $targetPath = $uploadDir . '/' . $safeName;
                    $ok = kladbcompress($_FILES[$field]['tmp_name'], $targetPath);

                    if ($ok && file_exists($targetPath)) {
                        $uploaded[$field] = '/cemeteries/' . $newId . '/' . $safeName;
                    }
                }

                if (!empty($uploaded)) {
                    $updates = [];
                    foreach ($uploaded as $column => $path) {
                        $updates[] = $column . "='" . mysqli_real_escape_string($dblink, $path) . "'";
                    }
                    mysqli_query($dblink, 'UPDATE cemetery SET ' . implode(', ', $updates) . ' WHERE idx=' . $newId);
                }

                $showMessage = true;
                $messageType = 'success';
                $messageText = 'Кладовище додано успішно!';
                $formData = [
                    'region' => '',
                    'district' => '',
                    'town' => '',
                    'title' => '',
                    'adress-cemetery' => '',
                    'gpsx' => '',
                    'gpsy' => '',
                ];
            }
            mysqli_stmt_close($stmt);
        }
        mysqli_close($dblink);
    }
}

function addCemeteryEsc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function addCemeteryRegionOptions(string $selectedValue): string
{
    $dblink = DbConnect();
    $res = mysqli_query($dblink, 'SELECT idx, title FROM region ORDER BY title');
    $out = '<option value="">Оберіть область</option>';

    while ($res && $row = mysqli_fetch_assoc($res)) {
        $value = (string)$row['idx'];
        $selected = ($value === $selectedValue) ? ' selected' : '';
        $out .= '<option value="' . addCemeteryEsc($value) . '"' . $selected . '>' . addCemeteryEsc($row['title']) . '</option>';
    }

    mysqli_close($dblink);
    return $out;
}

function addCemeteryDistrictOptions(string $regionValue, string $selectedValue): string
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
        $out .= '<option value="' . addCemeteryEsc($value) . '"' . $selected . '>' . addCemeteryEsc($row['title']) . '</option>';
    }

    mysqli_close($dblink);
    return $out;
}

function addCemeterySettlementOptions(string $regionValue, string $districtValue, string $selectedValue): string
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
        $out .= '<option value="' . addCemeteryEsc($value) . '"' . $selected . '>' . addCemeteryEsc($row['title']) . '</option>';
    }

    mysqli_close($dblink);
    return $out;
}

View_Clear();
View_Add(Page_Up('Нове кладовище'));
View_Add(Menu_Up());

if (!$isAuthorized) {
    View_Add('<link rel="stylesheet" href="/assets/css/in-dev.css">');
    View_Add('<div class="out-index out-index--404">');
    View_Add(AuthRequired_Content('/auth.php', 'Увійти'));
    View_Add('</div>');
} else {
    $safeRegion = addCemeteryEsc($formData['region']);
    $safeDistrict = addCemeteryEsc($formData['district']);
    $safeTown = addCemeteryEsc($formData['town']);
    $safeTitle = addCemeteryEsc($formData['title']);
    $safeAddress = addCemeteryEsc($formData['adress-cemetery']);
    $safeGpsx = addCemeteryEsc($formData['gpsx']);
    $safeGpsy = addCemeteryEsc($formData['gpsy']);

    $alertHtml = '';
    if ($showMessage) {
        $alertClass = $messageType === 'success' ? 'acm-alert--success' : 'acm-alert--error';
        $alertHtml = '<div class="acm-alert ' . $alertClass . '">' . addCemeteryEsc($messageText) . '</div>';
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
.acm-map-picker-row{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.acm-map-picker-row small{font-size:12px;color:#57708a}
.acm-btn--map{min-width:170px}
#acm-map-canvas{width:100%;height:360px;border:1px solid #d3dfec;border-radius:12px;overflow:hidden}
.acm-map-modal-card{width:min(94vw,780px)!important;box-sizing:border-box;overflow-x:hidden}
.acm-map-hint{margin-top:8px;min-height:20px;color:#476787;font-size:12px}
@media (max-width:760px){#acm-map-canvas{height:300px}}
</style>
<div class="out acm-out">
    <main class="acm-page">
        <section class="acm-layout">
            <aside class="acm-aside">
                <div class="acm-badge">Форма додавання кладовища</div>
                <h1 class="acm-heading">Додайте нове кладовище до системи</h1>
                <p>Форма призначена для структурованого внесення інформації до бази даних кладовищ Інформаційно-пошукової системи «Shana».</p>
                <ul class="acm-tips">
                    <li>Спочатку оберіть область, район і населений пункт.</li>
                    <li>Назва кладовища обов`язкова для збереження.</li>
                    <li>Схему можна додати одразу або пізніше.</li>
                </ul>
            </aside>

            <section class="acm-form-card">
                <h2 class="acm-form-title">Нове кладовище</h2>
                <p class="acm-form-subtitle">Заповніть поля нижче та натисніть «Додати кладовище».</p>
                ' . $alertHtml . '

                <form id="acm-form" class="acm-form" action="/searchcem/addcemetery" method="post" enctype="multipart/form-data" novalidate autocomplete="off" data-submit-success="' . (($showMessage && $messageType === 'success') ? '1' : '0') . '">
                    <input type="hidden" name="md" value="cemetery">

                    <fieldset class="acm-section">
                        <p class="acm-section-title">Розташування</p>
                        <div class="acm-row acm-row--three">
                            <div class="acm-field">
                                <label for="acm-region">Область *</label>
                                <select id="acm-region" name="region" required>
                                    ' . addCemeteryRegionOptions($formData['region']) . '
                                </select>
                            </div>
                            <div class="acm-field">
                                <label for="acm-district">Район *</label>
                                <select id="acm-district" name="district" data-selected="' . $safeDistrict . '" required>
                                    ' . addCemeteryDistrictOptions($formData['region'], $formData['district']) . '
                                </select>
                            </div>
                            <div class="acm-field">
                                <label for="acm-town">Населений пункт *</label>
                                <div class="acm-settlement-wrap">
                                    <select id="acm-town" name="town" data-selected="' . $safeTown . '" required>
                                        ' . addCemeterySettlementOptions($formData['region'], $formData['district'], $formData['town']) . '
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
                                <input id="acm-cemetery-adr" type="text" name="cemetery-adr" value="' . $safeAddress . '" autocomplete="nope" autocapitalize="off" spellcheck="false">
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
                        <p class="acm-section-title">Файли</p>
                        <div class="acm-file-grid">
                            <label class="acm-file">
                                <span class="acm-file-title">Схема кладовища</span>
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
                        <button type="button" id="acm-clear-form" class="acm-btn acm-btn--ghost">Очистити</button>
                        <button type="submit" class="acm-btn acm-btn--primary">Додати кладовище</button>
                    </div>
                </form>
            </section>
        </section>

        <section class="acm-after-form">
            <h3 class="acm-after-form-title">Що далі після додавання</h3>
            <div class="acm-after-form-grid">
                <article class="acm-after-form-item">
                    <h4>Перевірка даних</h4>
                    <p>Перевірте назву, координати та район перед збереженням. Це спростить пошук кладовища в каталозі.</p>
                </article>
                <article class="acm-after-form-item">
                    <h4>Додаткові матеріали</h4>
                    <p>Якщо зараз немає схеми, збережіть кладовище та додайте схему пізніше через картку кладовища.</p>
                </article>
                <article class="acm-after-form-item">
                    <h4>Потрібна допомога</h4>
                    <p>Якщо населений пункт або район відсутній у списку, скористайтесь кнопкою «+» або зверніться до підтримки.</p>
                </article>
            </div>
        </section>
    </main>
</div>

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
    const regionSel = document.getElementById("acm-region");
    const districtSel = document.getElementById("acm-district");
    const townSel = document.getElementById("acm-town");
    const openSettlementBtn = document.getElementById("acm-open-settlement");
    const settlementModal = document.getElementById("acm-settlement-modal");
    const newSettlementInput = document.getElementById("acm-new-settlement");
    const saveSettlementBtn = document.getElementById("acm-save-settlement");
    const settlementHint = document.getElementById("acm-settlement-hint");
    const schemeInput = document.getElementById("acm-scheme");
    const schemeName = document.getElementById("acm-scheme-name");
    const formEl = document.getElementById("acm-form");
    const clearFormBtn = document.getElementById("acm-clear-form");
    const titleInput = document.getElementById("acm-title");
    const addressInput = document.getElementById("acm-cemetery-adr");
    const gpsxInput = document.getElementById("acm-gpsx");
    const gpsyInput = document.getElementById("acm-gpsy");
    const openMapBtn = document.getElementById("acm-open-map");
    const mapModal = document.getElementById("acm-map-modal");
    const mapCanvas = document.getElementById("acm-map-canvas");
    const mapHint = document.getElementById("acm-map-hint");
    const applyMapBtn = document.getElementById("acm-apply-map");
    const closeMapNodes = mapModal ? mapModal.querySelectorAll("[data-acm-close-map]") : [];
    const closeModalNodes = settlementModal ? settlementModal.querySelectorAll("[data-acm-close-modal]") : [];
    const draftStorageKey = "acm.addcemetery.draft.v1";
    const draftTtlMs = 1000 * 60 * 60 * 6;
    const isSubmitSuccess = formEl ? formEl.dataset.submitSuccess === "1" : false;
    const placeholderById = {
        "acm-region": "Оберіть область",
        "acm-district": "Оберіть район",
        "acm-town": "Оберіть населений пункт"
    };

    if (!regionSel || !districtSel || !townSel || !openSettlementBtn || !settlementModal || !newSettlementInput || !saveSettlementBtn) {
        return;
    }

    function loadDraft() {
        try {
            const raw = window.localStorage.getItem(draftStorageKey);
            if (!raw) {
                return null;
            }
            const parsed = JSON.parse(raw);
            if (!parsed || typeof parsed !== "object") {
                clearDraft();
                return null;
            }
            const savedAt = typeof parsed.savedAt === "number" ? parsed.savedAt : 0;
            if (!savedAt || (Date.now() - savedAt) > draftTtlMs) {
                clearDraft();
                return null;
            }
            return parsed;
        } catch (error) {
            return null;
        }
    }

    function saveDraft() {
        try {
            const draft = {
                region: regionSel.value || "",
                district: districtSel.value || "",
                town: townSel.value || "",
                title: titleInput ? titleInput.value : "",
                address: addressInput ? addressInput.value : "",
                gpsx: gpsxInput ? gpsxInput.value : "",
                gpsy: gpsyInput ? gpsyInput.value : "",
                savedAt: Date.now()
            };
            window.localStorage.setItem(draftStorageKey, JSON.stringify(draft));
        } catch (error) {
            // ignore storage errors
        }
    }

    function clearDraft() {
        try {
            window.localStorage.removeItem(draftStorageKey);
        } catch (error) {
            // ignore storage errors
        }
    }

    function applyDraft(draft) {
        if (!draft || typeof draft !== "object") {
            return;
        }

        if (typeof draft.region === "string") {
            regionSel.value = draft.region;
        }
        if (typeof draft.district === "string") {
            districtSel.dataset.selected = draft.district;
        }
        if (typeof draft.town === "string") {
            townSel.dataset.selected = draft.town;
        }
        if (titleInput && typeof draft.title === "string") {
            titleInput.value = draft.title;
        }
        if (addressInput && typeof draft.address === "string") {
            addressInput.value = draft.address;
        }
        if (gpsxInput && typeof draft.gpsx === "string") {
            gpsxInput.value = draft.gpsx;
        }
        if (gpsyInput && typeof draft.gpsy === "string") {
            gpsyInput.value = draft.gpsy;
        }
    }

    function clearFormFields() {
        if (formEl) {
            formEl.reset();
        }
        regionSel.value = "";
        districtSel.dataset.selected = "";
        townSel.dataset.selected = "";
        districtSel.disabled = true;
        townSel.disabled = true;
        districtSel.innerHTML = "<option value=\"\">Спочатку оберіть область</option>";
        townSel.innerHTML = "<option value=\"\">Оберіть район</option>";

        if (titleInput) {
            titleInput.value = "";
        }
        if (addressInput) {
            addressInput.value = "";
        }
        if (gpsxInput) {
            gpsxInput.value = "";
        }
        if (gpsyInput) {
            gpsyInput.value = "";
        }
        if (schemeInput) {
            schemeInput.value = "";
        }
        if (schemeName) {
            schemeName.textContent = "Файл не обрано";
        }

        syncCustomSelect(regionSel);
        syncCustomSelect(districtSel);
        syncCustomSelect(townSel);
        closeAllCustomSelects();
        closeModal();
        toggleSettlementButton();
        clearDraft();
    }

    function closeAllCustomSelects(exceptWrapper) {
        document.querySelectorAll(".acm-field .custom-select-wrapper.open").forEach(function (wrapper) {
            if (exceptWrapper && wrapper === exceptWrapper) {
                return;
            }
            wrapper.classList.remove("open");
            const optionsBox = wrapper.querySelector(".custom-options");
            if (optionsBox) {
                optionsBox.style.display = "none";
            }
        });
    }

    function getCustomWrapper(selectEl) {
        return selectEl.parentNode.querySelector(".custom-select-wrapper[data-select-id=\"" + selectEl.id + "\"]");
    }

    function ensureCustomSelect(selectEl) {
        let wrapper = getCustomWrapper(selectEl);
        if (wrapper) {
            return wrapper;
        }

        wrapper = document.createElement("div");
        wrapper.className = "custom-select-wrapper";
        wrapper.dataset.selectId = selectEl.id;

        const trigger = document.createElement("div");
        trigger.className = "custom-select-trigger";

        const optionsBox = document.createElement("div");
        optionsBox.className = "custom-options";

        trigger.addEventListener("click", function () {
            if (selectEl.disabled) {
                return;
            }
            const willOpen = !wrapper.classList.contains("open");
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
        const wrapper = ensureCustomSelect(selectEl);
        const trigger = wrapper.querySelector(".custom-select-trigger");
        const optionsBox = wrapper.querySelector(".custom-options");
        const options = Array.from(selectEl.options || []);
        const placeholder = placeholderById[selectEl.id] || "Оберіть";

        optionsBox.innerHTML = "";

        const selectedOption = options.find(function (opt) {
            return opt.value !== "" && opt.value === selectEl.value;
        });

        let triggerText = placeholder;
        if (selectedOption) {
            triggerText = selectedOption.textContent;
        } else if (options[0] && options[0].textContent) {
            triggerText = options[0].textContent;
        }
        trigger.textContent = triggerText;

        options.forEach(function (opt) {
            const optionNode = document.createElement("span");
            optionNode.textContent = opt.textContent;

            if (!opt.value) {
                optionNode.className = "custom-option disabled";
                optionsBox.appendChild(optionNode);
                return;
            }

            optionNode.className = "custom-option";
            if (opt.value === selectEl.value) {
                optionNode.classList.add("is-selected");
            }

            optionNode.addEventListener("click", function () {
                if (selectEl.disabled) {
                    return;
                }
                selectEl.value = opt.value;
                syncCustomSelect(selectEl);
                closeAllCustomSelects();
                selectEl.dispatchEvent(new Event("change", { bubbles: true }));
            });
            optionsBox.appendChild(optionNode);
        });

        wrapper.classList.toggle("disabled", !!selectEl.disabled);
    }

    function setHint(text, isError) {
        settlementHint.textContent = text || "";
        settlementHint.style.color = isError ? "#8b2330" : "#285c89";
    }

    function toggleSettlementButton() {
        openSettlementBtn.disabled = !(regionSel.value && districtSel.value);
    }

    function openModal() {
        if (openSettlementBtn.disabled) {
            return;
        }
        settlementModal.classList.add("is-open");
        settlementModal.setAttribute("aria-hidden", "false");
        setHint("", false);
        setTimeout(function () {
            newSettlementInput.focus();
        }, 0);
    }

    function closeModal() {
        settlementModal.classList.remove("is-open");
        settlementModal.setAttribute("aria-hidden", "true");
        newSettlementInput.value = "";
        setHint("", false);
    }

    function parseCoord(value) {
        const normalized = String(value || "").replace(",", ".").trim();
        if (!normalized) return null;
        const num = Number(normalized);
        return Number.isFinite(num) ? num : null;
    }

    function formatCoord(value) {
        return Number(value).toFixed(7).replace(/\.?0+$/, "");
    }

    function resolveLatLonFromFields(xVal, yVal) {
        if (xVal === null || yVal === null) return null;
        const preferred = { lat: yVal, lon: xVal };
        if (preferred.lat >= -90 && preferred.lat <= 90 && preferred.lon >= -180 && preferred.lon <= 180) {
            return preferred;
        }

        const legacy = { lat: xVal, lon: yVal };
        if (legacy.lat >= -90 && legacy.lat <= 90 && legacy.lon >= -180 && legacy.lon <= 180) {
            return legacy;
        }

        return null;
    }

    let map = null;
    let mapMarker = null;
    let mapSelected = null;

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
                const p = mapMarker.getLatLng();
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

        const xVal = parseCoord(gpsxInput ? gpsxInput.value : "");
        const yVal = parseCoord(gpsyInput ? gpsyInput.value : "");
        const resolved = resolveLatLonFromFields(xVal, yVal);
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

    function loadDistricts(regionId, selectedDistrict, selectedTown) {
        districtSel.disabled = true;
        townSel.disabled = true;
        districtSel.innerHTML = "<option value=\"\">Завантаження...</option>";
        townSel.innerHTML = "<option value=\"\">Оберіть район</option>";
        syncCustomSelect(districtSel);
        syncCustomSelect(townSel);
        toggleSettlementButton();

        fetch("/searchcem/addcemetery?ajax_districts=1&region_id=" + encodeURIComponent(regionId), { credentials: "same-origin" })
            .then(function (response) { return response.text(); })
            .then(function (html) {
                districtSel.innerHTML = html;
                districtSel.disabled = false;
                if (selectedDistrict) {
                    districtSel.value = selectedDistrict;
                }
                syncCustomSelect(districtSel);
                if (districtSel.value) {
                    loadSettlements(regionId, districtSel.value, selectedTown);
                } else {
                    townSel.disabled = true;
                    townSel.innerHTML = "<option value=\"\">Оберіть район</option>";
                    syncCustomSelect(townSel);
                }
                toggleSettlementButton();
                saveDraft();
            })
            .catch(function () {
                districtSel.innerHTML = "<option value=\"\">Помилка завантаження</option>";
                districtSel.disabled = true;
                syncCustomSelect(districtSel);
                townSel.disabled = true;
                townSel.innerHTML = "<option value=\"\">Оберіть район</option>";
                syncCustomSelect(townSel);
                toggleSettlementButton();
                saveDraft();
            });
    }

    function loadSettlements(regionId, districtId, selectedTown, selectedTownTitle) {
        townSel.disabled = true;
        townSel.innerHTML = "<option value=\"\">Завантаження...</option>";
        syncCustomSelect(townSel);
        fetch("/searchcem/addcemetery?ajax_settlements=1&region_id=" + encodeURIComponent(regionId) + "&district_id=" + encodeURIComponent(districtId), { credentials: "same-origin" })
            .then(function (response) { return response.text(); })
            .then(function (html) {
                townSel.innerHTML = html;
                if (selectedTown) {
                    townSel.value = selectedTown;
                } else if (selectedTownTitle) {
                    const expected = selectedTownTitle.trim().toLowerCase();
                    const option = Array.from(townSel.options).find(function (item) {
                        return item.textContent.trim().toLowerCase() === expected;
                    });
                    if (option) {
                        townSel.value = option.value;
                    }
                }
                townSel.disabled = false;
                syncCustomSelect(townSel);
                toggleSettlementButton();
                saveDraft();
            })
            .catch(function () {
                townSel.innerHTML = "<option value=\"\">Помилка завантаження</option>";
                townSel.disabled = true;
                syncCustomSelect(townSel);
                toggleSettlementButton();
                saveDraft();
            });
    }

    regionSel.addEventListener("change", function () {
        const regionId = regionSel.value;
        if (!regionId) {
            districtSel.disabled = true;
            districtSel.innerHTML = "<option value=\"\">Спочатку оберіть область</option>";
            syncCustomSelect(districtSel);
            townSel.disabled = true;
            townSel.innerHTML = "<option value=\"\">Оберіть район</option>";
            syncCustomSelect(townSel);
            toggleSettlementButton();
            saveDraft();
            return;
        }
        loadDistricts(regionId, "", "");
        saveDraft();
    });

    districtSel.addEventListener("change", function () {
        if (!regionSel.value || !districtSel.value) {
            townSel.disabled = true;
            townSel.innerHTML = "<option value=\"\">Оберіть район</option>";
            syncCustomSelect(townSel);
            toggleSettlementButton();
            saveDraft();
            return;
        }
        loadSettlements(regionSel.value, districtSel.value, "", "");
        saveDraft();
    });

    openSettlementBtn.addEventListener("click", openModal);
    closeModalNodes.forEach(function (node) {
        node.addEventListener("click", closeModal);
    });
    if (openMapBtn) {
        openMapBtn.addEventListener("click", openMapModal);
    }
    closeMapNodes.forEach(function (node) {
        node.addEventListener("click", closeMapModal);
    });

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape" && settlementModal.classList.contains("is-open")) {
            closeModal();
        }
        if (event.key === "Escape" && mapModal && mapModal.classList.contains("is-open")) {
            closeMapModal();
        }
    });

    document.addEventListener("click", function (event) {
        if (!event.target.closest(".acm-field .custom-select-wrapper")) {
            closeAllCustomSelects();
        }
    });

    saveSettlementBtn.addEventListener("click", function () {
        const name = newSettlementInput.value.trim();
        if (!regionSel.value || !districtSel.value) {
            setHint("Оберіть область і район перед додаванням населеного пункту.", true);
            return;
        }
        if (!name) {
            setHint("Вкажіть назву населеного пункту.", true);
            return;
        }

        saveSettlementBtn.disabled = true;
        setHint("Додаємо населений пункт...", false);

        const payload = new URLSearchParams();
        payload.set("ajax_add_settlement", "1");
        payload.set("region_id", regionSel.value);
        payload.set("district_id", districtSel.value);
        payload.set("name", name);

        fetch("/searchcem/addcemetery", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
            credentials: "same-origin",
            body: payload.toString()
        })
            .then(function (response) { return response.text(); })
            .then(function (text) {
                if (text.trim().toUpperCase().indexOf("OK") === 0) {
                    setHint("Населений пункт додано. Оновлюємо список...", false);
                    loadSettlements(regionSel.value, districtSel.value, "", name);
                    saveDraft();
                    setTimeout(closeModal, 240);
                    return;
                }
                setHint(text || "Не вдалося додати населений пункт.", true);
            })
            .catch(function () {
                setHint("Помилка мережі при додаванні населеного пункту.", true);
            })
            .finally(function () {
                saveSettlementBtn.disabled = false;
            });
    });

    if (schemeInput && schemeName) {
        schemeInput.addEventListener("change", function () {
            if (schemeInput.files && schemeInput.files[0]) {
                schemeName.textContent = schemeInput.files[0].name;
            } else {
                schemeName.textContent = "Файл не обрано";
            }
            saveDraft();
        });
    }

    [titleInput, addressInput, gpsxInput, gpsyInput].forEach(function (inputEl) {
        if (!inputEl) {
            return;
        }
        inputEl.addEventListener("input", saveDraft);
        inputEl.addEventListener("change", saveDraft);
    });

    if (clearFormBtn) {
        clearFormBtn.addEventListener("click", clearFormFields);
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
            saveDraft();
            closeMapModal();
        });
    }

    if (isSubmitSuccess) {
        clearDraft();
    } else {
        const draft = loadDraft();
        if (draft) {
            applyDraft(draft);
        }
    }

    const initialRegion = regionSel.value;
    const initialDistrict = districtSel.dataset.selected || "";
    const initialTown = townSel.dataset.selected || "";

    if (!initialRegion) {
        districtSel.disabled = true;
        townSel.disabled = true;
    }
    syncCustomSelect(regionSel);
    syncCustomSelect(districtSel);
    syncCustomSelect(townSel);

    if (initialRegion) {
        loadDistricts(initialRegion, initialDistrict, initialTown);
    } else {
        toggleSettlementButton();
        saveDraft();
    }
})();
</script>
');
}

View_Add(Page_Down());
View_Out();
View_Clear();

    return;
}

function RenderKSearchCard(array $c): string
{
    $idx = (int)($c['idx'] ?? 0);
    $title = htmlspecialchars((string)($c['title'] ?? ''), ENT_QUOTES, 'UTF-8');
    $address = htmlspecialchars((string)($c['adress'] ?? ''), ENT_QUOTES, 'UTF-8');
    $moderationStatus = strtolower(trim((string)($c['moderation_status'] ?? '')));
    $scheme = (string)($c['scheme'] ?? '');
    $isSchemeMissing = ($scheme === '' || !is_file($_SERVER['DOCUMENT_ROOT'] . $scheme));

    if ($isSchemeMissing) {
        $scheme = '/cemeteries/noscheme.png';
    }

    $town = trim((string)($c['town_name'] ?? ''));
    $district = trim((string)($c['district_name'] ?? ''));
    $region = trim((string)($c['region_name'] ?? ''));

    $safeTown = htmlspecialchars($town, ENT_QUOTES, 'UTF-8');
    $safeDistrict = htmlspecialchars($district, ENT_QUOTES, 'UTF-8');
    $safeRegion = htmlspecialchars($region, ENT_QUOTES, 'UTF-8');

    $locationParts = [];
    if ($district !== '') {
        $locationParts[] = $safeDistrict . ' район';
    }
    if ($region !== '') {
        $locationParts[] = $safeRegion . ' область';
    }
    $location = !empty($locationParts) ? implode(', ', $locationParts) : 'Локація не вказана';

    $link = '/cemetery.php?idx=' . $idx;
    $moderationBadgeHtml = '';
    if ($moderationStatus === 'pending') {
        $moderationBadgeHtml = '<span class="kcard-moderation kcard-moderation--pending">На модерації</span>';
    } elseif ($moderationStatus === 'approved') {
        $moderationBadgeHtml = '<span class="kcard-moderation kcard-moderation--approved" aria-label="Перевірено модерацією"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-circle-check"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 12a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" /><path d="M9 12l2 2l4 -4" /></svg><span class="kcard-moderation-text">Перевірено</span></span>';
    }

    $out = '<article class="kcard">';
    $out .= '<div class="kcard-media' . ($isSchemeMissing ? ' kcard-media--empty' : '') . '">';
    $out .= '<img src="' . htmlspecialchars($scheme, ENT_QUOTES, 'UTF-8') . '" alt="' . $title . '">';
    $out .= $moderationBadgeHtml;
    if ($isSchemeMissing) {
        $out .= '<span class="kcard-media-note">Фотографію не встановлено</span>';
    }
    $out .= '</div>';
    $out .= '<div class="kcard-body">';
    $out .= '<h3 class="kcard-title">' . ($title !== '' ? $title : 'Без назви') . '</h3>';
    $out .= '<div class="kcard-meta">';
    if ($town !== '') {
        $out .= '<span class="kcard-chip">' . $safeTown . '</span>';
    }
    if ($district !== '') {
        $out .= '<span class="kcard-chip">' . $safeDistrict . ' р-н</span>';
    }
    if ($region !== '') {
        $out .= '<span class="kcard-chip">' . $safeRegion . ' обл.</span>';
    }
    $out .= '</div>';
    $out .= '<p class="kcard-location">' . $location . '</p>';
    if ($address !== '') {
        $out .= '<p class="kcard-address"><b>Адреса:</b> ' . $address . '</p>';
    }
    $out .= '</div>';
    $out .= '<div class="kcard-footer">';
    $out .= '<span class="kcard-id">ID #' . $idx . '</span>';
    $out .= '<a href="' . $link . '" class="kcard-link"><span>Деталі</span>';
    $out .= '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-arrow-narrow-right">';
    $out .= '<path stroke="none" d="M0 0h24v24H0z" fill="none"/>';
    $out .= '<path d="M5 12l14 0" />';
    $out .= '<path d="M15 16l4 -4" />';
    $out .= '<path d="M15 8l4 4" />';
    $out .= '</svg></a>';
    $out .= '</div>';
    $out .= '</article>';

    return $out;
}

$cp = (int)($_GET['page'] ?? 1);
if ($cp < 1) {
    $cp = 1;
}
$perpage = 6;

$title = trim($_GET['title'] ?? '');
$region = $_GET['region'] ?? '';
$district = $_GET['district'] ?? '';

$dblink = DbConnect();
$rows = [];

$sql = "
SELECT c.*,
       m.title AS town_name,
       d.title AS district_name,
       r.title AS region_name
FROM cemetery c
LEFT JOIN misto m ON c.town = m.idx
LEFT JOIN district d ON c.district = d.idx
LEFT JOIN region r ON d.region = r.idx
WHERE 1 = 1
  AND LOWER(COALESCE(c.moderation_status, 'pending')) <> 'rejected'
";

if ($title !== '') {
    $safeTitle = mysqli_real_escape_string($dblink, $title);
    $sql .= ' AND c.title LIKE "%' . $safeTitle . '%"';
}

if ($region !== '') {
    $sql .= ' AND d.region = ' . (int)$region;
}

if ($district !== '') {
    $sql .= ' AND c.district = ' . (int)$district;
}

$sql .= " ORDER BY c.idx ASC";

$res = mysqli_query($dblink, $sql);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $rows[] = $row;
    }
}

$cout = count($rows);

$regionOptions = '<option value="">Виберіть область</option>';
$regionCustomOptions = '';
$regionTriggerText = 'Виберіть область';
$resRegions = mysqli_query($dblink, "SELECT idx, title FROM region ORDER BY title");
if ($resRegions) {
    while ($r = mysqli_fetch_assoc($resRegions)) {
        $id = (int)$r['idx'];
        $label = htmlspecialchars($r['title'], ENT_QUOTES, 'UTF-8');
        $selected = ($region !== '' && (int)$region === $id) ? ' selected' : '';
        $regionOptions .= '<option value="' . $id . '"' . $selected . '>' . $label . '</option>';
        $regionCustomOptions .= '<span class="custom-option" data-value="' . $id . '">' . $label . '</span>';
        if ($selected !== '') {
            $regionTriggerText = $label;
        }
    }
}

$districtOptions = '<option value="">Виберіть область</option>';
$districtCustomOptions = '';
$districtTriggerText = 'Виберіть область';
if ($region !== '') {
    $resDistricts = mysqli_query(
        $dblink,
        "SELECT idx, title FROM district WHERE region = " . (int)$region . " ORDER BY title"
    );
    if ($resDistricts && mysqli_num_rows($resDistricts) > 0) {
        $districtOptions = '<option value="">Виберіть район</option>';
        $districtTriggerText = 'Виберіть район';
        while ($d = mysqli_fetch_assoc($resDistricts)) {
            $id = (int)$d['idx'];
            $label = htmlspecialchars($d['title'], ENT_QUOTES, 'UTF-8');
            $selected = ($district !== '' && (int)$district === $id) ? ' selected' : '';
            $districtOptions .= '<option value="' . $id . '"' . $selected . '>' . $label . '</option>';
            $districtCustomOptions .= '<span class="custom-option" data-value="' . $id . '">' . $label . '</span>';
            if ($selected !== '') {
                $districtTriggerText = $label;
            }
        }
    }
}

$searchParts = [];
if ($title !== '') {
    $searchParts[] = 'Назва: ' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
}
if ($region !== '') {
    $searchParts[] = 'Область: ' . htmlspecialchars($regionTriggerText, ENT_QUOTES, 'UTF-8');
}
if ($district !== '') {
    $searchParts[] = 'Район: ' . htmlspecialchars($districtTriggerText, ENT_QUOTES, 'UTF-8');
}
$searchLine = !empty($searchParts) ? implode('; ', $searchParts) : '—';
$searchParamsMobileClass = $searchLine === '—' ? ' search-badge--mobile-hidden' : '';

mysqli_close($dblink);

View_Clear();
View_Add(Page_Up('Результати пошуку кладовищ'));
View_Add(Menu_Up());
View_Add('<link rel="stylesheet" href="/assets/css/cemetery-style.css?v=1">');

View_Add('<div class="out-xsearch">');
View_Add('<div class="search-container">');
View_Add('<div class="search-out search-toolbar">');

View_Add('<div class="search-badges">');
View_Add('<div class="search-badge search-badge--params' . $searchParamsMobileClass . '">Пошук за параметрами: ' . $searchLine . '</div>');
View_Add('<div class="search-badge">Всього кладовищ: <strong>' . $cout . '</strong></div>');
View_Add('</div>');

View_Add('<a href="/searchcem/addcemetery" class="searchklb-btn"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="btn-add-icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14" /><path d="M5 12l14 0" /></svg>Додати кладовище</a>');

View_Add('<div class="search-toolbar-right">');
View_Add('<form class="search-form" action="/searchcem" method="get">');
if ($region !== '') {
    View_Add('<input type="hidden" name="region" value="' . (int)$region . '">');
}
if ($district !== '') {
    View_Add('<input type="hidden" name="district" value="' . (int)$district . '">');
}
View_Add('<input type="hidden" name="page" value="1">');
View_Add('<div class="search-input-wrap">');
View_Add('<input type="text" name="title" value="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '" placeholder="Пошук за назвою кладовища" class="search-inputx" autocomplete="off">');
View_Add('<button type="submit" class="search-submit-btn" aria-label="Пошук"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg></button>');
View_Add('</div>');
View_Add('</form>');

View_Add('<div class="filter-dropdown">');
View_Add('<button type="button" class="filter-btnx" id="filterToggle"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="filter-btn-icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 4h16v2.172a2 2 0 0 1 -.586 1.414l-4.414 4.414v7l-6 2v-8.5l-4.48 -4.928a2 2 0 0 1 -.52 -1.345v-2.227" /></svg>Фільтр</button>');

View_Add('
<div class="filter-panel" id="filterPanel" aria-hidden="true">
    <form class="filter-form" action="/searchcem" method="get">
        <input type="hidden" name="page" value="1">
        ' . ($title !== '' ? '<input type="hidden" name="title" value="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '">' : '') . '

        <div class="filter-header">
            <div class="filter-title">Розширений фільтр</div>
            <button type="button" class="filter-close" aria-label="Закрити фільтр">&times;</button>
        </div>

        <div class="filter-grid">
            <div class="filter-field">
                <div class="input-container">
                    <select name="region" id="filter-region" class="login-Input" style="display:none;">
                        ' . $regionOptions . '
                    </select>
                    <div class="custom-select-wrapper" id="region-wrapper">
                        <div class="custom-select-trigger">' . htmlspecialchars($regionTriggerText, ENT_QUOTES, 'UTF-8') . '</div>
                        <div class="custom-options">' . $regionCustomOptions . '</div>
                    </div>
                    <label class="label-active">Область</label>
                </div>
            </div>

            <div class="filter-field">
                <div class="input-container">
                    <select name="district" id="filter-district" class="login-Input" style="display:none;">
                        ' . $districtOptions . '
                    </select>
                    <div class="custom-select-wrapper" id="district-wrapper">
                        <div class="custom-select-trigger">' . htmlspecialchars($districtTriggerText, ENT_QUOTES, 'UTF-8') . '</div>
                        <div class="custom-options">' . $districtCustomOptions . '</div>
                    </div>
                    <label class="label-active">Район</label>
                </div>
            </div>
        </div>

        <div class="filter-actions">
            <button type="submit" class="filter-apply">Застосувати</button>
            <button type="reset" class="filter-reset">Скинути</button>
        </div>
    </form>
</div>');

View_Add('</div>');
View_Add('</div>');
View_Add('</div>');

View_Add('<div class="cardk-out">');

if ($cout === 0) {
    View_Add('<div class="no-results-wrap"><div class="no-results">За вашим запитом нічого не знайдено</div></div>');
} else {
    $offset = ($cp - 1) * $perpage;
    $rowsPage = array_slice($rows, $offset, $perpage);

    foreach ($rowsPage as $c) {
        View_Add(RenderKSearchCard($c));
    }
}

View_Add('</div>');
View_Add('</div>');

if ($cout > 0) {
    View_Add('<div class="paginator-out">');
    View_Add(Paginatex::Showx($cp, $cout, $perpage));
    View_Add('</div>');
}

View_Add('</div>');

View_Add('
<script>
document.addEventListener("DOMContentLoaded", function () {
    var filterToggle = document.getElementById("filterToggle");
    var filterPanel = document.getElementById("filterPanel");
    if (!filterToggle || !filterPanel) return;

    var closeBtn = filterPanel.querySelector(".filter-close");
    var resetBtn = filterPanel.querySelector(".filter-reset");
    var filterForm = filterPanel.querySelector(".filter-form");

    var regionSelect = document.getElementById("filter-region");
    var districtSelect = document.getElementById("filter-district");
    var regionWrapper = document.getElementById("region-wrapper");
    var districtWrapper = document.getElementById("district-wrapper");
    var mobileFilterMedia = window.matchMedia("(max-width: 768px)");

    function getCustomOptions(wrapper) {
        if (!wrapper) return null;
        if (wrapper._portalOptions && wrapper._portalOptions.isConnected) {
            return wrapper._portalOptions;
        }

        var options = wrapper.querySelector(".custom-options");
        if (options) {
            wrapper._portalOptions = options;
        }

        return options;
    }

    function restoreCustomOptions(wrapper) {
        var options = getCustomOptions(wrapper);
        if (!wrapper || !options) return null;

        if (options.parentNode !== wrapper) {
            wrapper.appendChild(options);
        }

        options.classList.remove("custom-options--portal");
        wrapper._portalOptions = options;
        return options;
    }

    function isCustomSelectTarget(target) {
        return !!(target && (target.closest(".custom-select-wrapper") || target.closest(".custom-options--portal")));
    }

    function cleanupCustomOptions(wrapper) {
        if (!wrapper) return;
        var options = getCustomOptions(wrapper);
        if (!options) return;
        wrapper.classList.remove("open-upward");
        wrapper.classList.remove("custom-select-wrapper--floating");
        options.classList.remove("custom-options--portal");
        options.style.position = "";
        options.style.left = "";
        options.style.right = "";
        options.style.top = "";
        options.style.bottom = "";
        options.style.width = "";
        options.style.maxHeight = "";
        options.style.zIndex = "";
    }

    function closeCustomSelect(wrapper) {
        if (!wrapper) return;
        var options = getCustomOptions(wrapper);
        wrapper.classList.remove("open");
        cleanupCustomOptions(wrapper);
        if (options) {
            options.style.display = "none";
            restoreCustomOptions(wrapper);
        }
    }

    function closeAllCustomSelects(exceptWrapper) {
        document.querySelectorAll(".custom-select-wrapper").forEach(function (wrapper) {
            if (wrapper !== exceptWrapper) {
                closeCustomSelect(wrapper);
            }
        });
    }

    function positionCustomOptions(wrapper) {
        if (!wrapper) return;
        var options = getCustomOptions(wrapper);
        var trigger = wrapper.querySelector(".custom-select-trigger");
        if (!options || !trigger) return;

        if (!mobileFilterMedia.matches) {
            restoreCustomOptions(wrapper);
            cleanupCustomOptions(wrapper);
            return;
        }

        var triggerRect = trigger.getBoundingClientRect();
        var viewportHeight = window.innerHeight || document.documentElement.clientHeight;
        var viewportWidth = window.innerWidth || document.documentElement.clientWidth;
        var sideGap = 12;
        var bottomReserve = 92;
        var availableBelow = viewportHeight - triggerRect.bottom - bottomReserve;
        var availableAbove = triggerRect.top - 18;
        var openUpward = availableBelow < 180 && availableAbove > availableBelow;
        var maxHeight = Math.max(132, Math.min(240, openUpward ? (availableAbove - 8) : (availableBelow - 8)));
        var width = Math.min(triggerRect.width, viewportWidth - sideGap * 2);
        var left = Math.min(Math.max(sideGap, triggerRect.left), viewportWidth - sideGap - width);

        if (options.parentNode !== document.body) {
            document.body.appendChild(options);
        }

        options.classList.add("custom-options--portal");

        options.style.position = "fixed";
        options.style.left = left + "px";
        options.style.right = "auto";
        options.style.width = width + "px";
        options.style.maxHeight = maxHeight + "px";
        options.style.zIndex = "10050";

        if (openUpward) {
            options.style.top = "auto";
            options.style.bottom = Math.max(12, viewportHeight - triggerRect.top + 6) + "px";
            wrapper.classList.add("open-upward");
        } else {
            options.style.bottom = "auto";
            options.style.top = Math.max(12, triggerRect.bottom + 6) + "px";
            wrapper.classList.remove("open-upward");
        }

        wrapper.classList.add("custom-select-wrapper--floating");
    }

    function refreshOpenCustomOptions() {
        document.querySelectorAll(".custom-select-wrapper.open").forEach(function (wrapper) {
            positionCustomOptions(wrapper);
        });
    }

    function openPanel() {
        filterPanel.classList.add("open");
        filterPanel.setAttribute("aria-hidden", "false");
        filterToggle.classList.add("active");
    }

    function closePanel() {
        closeAllCustomSelects();
        filterPanel.classList.remove("open");
        filterPanel.setAttribute("aria-hidden", "true");
        filterToggle.classList.remove("active");
    }

    filterToggle.addEventListener("click", function (e) {
        e.stopPropagation();
        if (filterPanel.classList.contains("open")) {
            closePanel();
        } else {
            openPanel();
        }
    });

    if (closeBtn) {
        closeBtn.addEventListener("click", function (e) {
            e.preventDefault();
            closePanel();
        });
    }

    document.addEventListener("click", function (e) {
        if (!filterPanel.contains(e.target) && !filterToggle.contains(e.target)) {
            if (!isCustomSelectTarget(e.target)) {
                closePanel();
            }
        }
    });

    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape") {
            closePanel();
        }
    });

    function initCustomSelect(wrapper) {
        if (!wrapper) return;
        var trigger = wrapper.querySelector(".custom-select-trigger");
        var options = getCustomOptions(wrapper);
        var select = wrapper.previousElementSibling;

        if (!trigger || !options || !select) return;
        wrapper._portalOptions = options;

        trigger.addEventListener("click", function (e) {
            e.stopPropagation();

            closeAllCustomSelects(wrapper);

            wrapper.classList.toggle("open");
            if (wrapper.classList.contains("open")) {
                options.style.display = "flex";
                positionCustomOptions(wrapper);
            } else {
                closeCustomSelect(wrapper);
            }
        });

        function bindOptions() {
            options.querySelectorAll(".custom-option").forEach(function (opt) {
                opt.onclick = function (e) {
                    e.stopPropagation();
                    trigger.textContent = opt.textContent;
                    select.value = opt.dataset.value || "";
                    select.dispatchEvent(new Event("change", { bubbles: true }));
                    closeCustomSelect(wrapper);
                };
            });
        }

        bindOptions();
        wrapper._bindOptions = bindOptions;
    }

    function resetDistrictsUi() {
        if (!districtSelect) return;

        districtSelect.innerHTML = "";
        var opt = document.createElement("option");
        opt.value = "";
        opt.textContent = "Виберіть область";
        districtSelect.appendChild(opt);

        if (districtWrapper) {
            var trigger = districtWrapper.querySelector(".custom-select-trigger");
            var options = districtWrapper.querySelector(".custom-options");
            if (trigger) trigger.textContent = "Виберіть область";
            if (options) options.innerHTML = "";
            if (districtWrapper._bindOptions) districtWrapper._bindOptions();
        }
    }

    function loadDistricts(regionId, selected) {
        if (!districtSelect) return;

        districtSelect.innerHTML = "";
        var loadingOpt = document.createElement("option");
        loadingOpt.value = "";
        loadingOpt.textContent = "Завантаження...";
        districtSelect.appendChild(loadingOpt);

        if (districtWrapper) {
            var loadingTrigger = districtWrapper.querySelector(".custom-select-trigger");
            if (loadingTrigger) loadingTrigger.textContent = "Завантаження...";
        }

        if (!regionId) {
            resetDistrictsUi();
            return;
        }

        fetch("?ajax_districts=1&region_id=" + encodeURIComponent(regionId))
            .then(function (res) { return res.text(); })
            .then(function (html) {
                var tmp = document.createElement("select");
                tmp.innerHTML = html;

                districtSelect.innerHTML = "";
                var placeholderOpt = document.createElement("option");
                placeholderOpt.value = "";
                placeholderOpt.textContent = "Виберіть район";
                districtSelect.appendChild(placeholderOpt);

                if (districtWrapper) {
                    var options = districtWrapper.querySelector(".custom-options");
                    if (options) options.innerHTML = "";
                }

                tmp.querySelectorAll("option").forEach(function (opt) {
                    if (!opt.value) return;

                    var optionNode = document.createElement("option");
                    optionNode.value = opt.value;
                    optionNode.textContent = opt.textContent;
                    if (selected && selected == opt.value) {
                        optionNode.selected = true;
                    }
                    districtSelect.appendChild(optionNode);

                    if (districtWrapper) {
                        var customNode = document.createElement("span");
                        customNode.className = "custom-option";
                        customNode.dataset.value = opt.value;
                        customNode.textContent = opt.textContent;
                        var customOptions = districtWrapper.querySelector(".custom-options");
                        if (customOptions) customOptions.appendChild(customNode);
                    }
                });

                if (districtWrapper) {
                    var trigger = districtWrapper.querySelector(".custom-select-trigger");
                    if (trigger) {
                        var selectedText = "Виберіть район";
                        if (selected) {
                            var selectedNode = tmp.querySelector("option[value=\"" + selected + "\"]");
                            if (selectedNode) {
                                selectedText = selectedNode.textContent;
                            }
                        }
                        trigger.textContent = selectedText;
                    }
                    if (districtWrapper._bindOptions) districtWrapper._bindOptions();
                }
            })
            .catch(function () {
                districtSelect.innerHTML = "";
                var errOpt = document.createElement("option");
                errOpt.value = "";
                errOpt.textContent = "Помилка завантаження";
                districtSelect.appendChild(errOpt);

                if (districtWrapper) {
                    var trigger = districtWrapper.querySelector(".custom-select-trigger");
                    if (trigger) trigger.textContent = "Помилка завантаження";
                }
            });
    }

    if (regionWrapper) initCustomSelect(regionWrapper);
    if (districtWrapper) initCustomSelect(districtWrapper);

    document.addEventListener("click", function (e) {
        if (!isCustomSelectTarget(e.target)) {
            closeAllCustomSelects();
        }
    });

    window.addEventListener("resize", refreshOpenCustomOptions);
    window.addEventListener("scroll", refreshOpenCustomOptions, { passive: true });
    if (filterForm) {
        filterForm.addEventListener("scroll", refreshOpenCustomOptions, { passive: true });
    }

    if (regionSelect && districtSelect) {
        if (regionSelect.value) {
            loadDistricts(regionSelect.value, districtSelect.value || null);
        } else {
            resetDistrictsUi();
        }

        regionSelect.addEventListener("change", function () {
            loadDistricts(this.value, null);
        });
    }

    if (resetBtn) {
        resetBtn.addEventListener("click", function (e) {
            e.preventDefault();
            window.location.href = "/searchcem";
        });
    }
});
</script>
');

View_Add(Page_Down());

View_Out();
View_Clear();
