<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/function.php";

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
                    $ok = kladbcompress($_FILES[$field]['tmp_name'], $targetPath, 75, 300);

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

    View_Add('<link rel="stylesheet" href="/assets/css/grave.css">');
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
<div class="out">
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

                <form id="acm-form" class="acm-form" action="/addcemetery.php" method="post" enctype="multipart/form-data" novalidate autocomplete="off" data-submit-success="' . (($showMessage && $messageType === 'success') ? '1' : '0') . '">
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
        const variants = [
            { lat: xVal, lon: yVal },
            { lat: yVal, lon: xVal }
        ];
        let best = null;
        let bestScore = -999;
        variants.forEach(function (v) {
            let score = 0;
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

        fetch("/addcemetery.php?ajax_districts=1&region_id=" + encodeURIComponent(regionId), { credentials: "same-origin" })
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
        fetch("/addcemetery.php?ajax_settlements=1&region_id=" + encodeURIComponent(regionId) + "&district_id=" + encodeURIComponent(districtId), { credentials: "same-origin" })
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

        fetch("/addcemetery.php", {
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
