<?php
if (!defined('CEMETERY_MAP_ROUTE')) {
    define('CEMETERY_MAP_ROUTE', '/test.php');
}
if (!defined('CEMETERY_MAP_CSS')) {
    define('CEMETERY_MAP_CSS', '/test.css');
}
if (!defined('CEMETERY_MAP_DATA_ROUTE')) {
    define('CEMETERY_MAP_DATA_ROUTE', CEMETERY_MAP_ROUTE);
}
if (!defined('CEMETERY_MAP_SELECTED_ID')) {
    define('CEMETERY_MAP_SELECTED_ID', (int)($_GET['idx'] ?? ($_GET['cemetery_id'] ?? 0)));
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/function.php';

function testMapEsc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function testMapRegionOptions(): string
{
    $dblink = DbConnect();
    if (!dbTableExists($dblink, 'region')) {
        mysqli_close($dblink);
        return '<option value="">Немає даних областей</option>';
    }
    $res = mysqli_query($dblink, 'SELECT idx, title FROM region ORDER BY title');
    $out = '<option value="">Оберіть область</option>';

    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $out .= '<option value="' . (int)($row['idx'] ?? 0) . '">' . testMapEsc((string)($row['title'] ?? '')) . '</option>';
    }

    mysqli_close($dblink);
    return $out;
}

function testMapNormalizePosition(?string $value): int
{
    $value = trim((string)$value);
    if ($value === '' || $value === '0') {
        return 0;
    }

    $digits = preg_replace('/\D+/u', '', $value);
    if ($digits === null || $digits === '') {
        return 0;
    }

    return (int)$digits;
}

function testMapBuildBurialName(array $grave): string
{
    $parts = [
        trim((string)($grave['lname'] ?? '')),
        trim((string)($grave['fname'] ?? '')),
        trim((string)($grave['mname'] ?? '')),
    ];

    $parts = array_values(array_filter($parts, static fn($part) => $part !== ''));
    return $parts ? implode(' ', $parts) : 'Невідоме поховання';
}

function testMapBuildBurialShortName(array $grave): string
{
    $parts = [
        trim((string)($grave['lname'] ?? '')),
        trim((string)($grave['fname'] ?? '')),
    ];

    $parts = array_values(array_filter($parts, static fn($part) => $part !== ''));
    return $parts ? implode(' ', $parts) : 'Невідоме поховання';
}

function testMapBuildBurialYears(array $grave): string
{
    $birthYear = graveDateYearFromRow($grave, 'dt1');
    $deathYear = graveDateYearFromRow($grave, 'dt2');

    if ($birthYear !== '' && $deathYear !== '') {
        return $birthYear . '-' . $deathYear;
    }
    if ($birthYear !== '') {
        return $birthYear . ' - ...';
    }
    if ($deathYear !== '') {
        return '... - ' . $deathYear;
    }

    return '';
}

function testMapResolveBurialPhoto(?string $path, string $fallback = ''): string
{
    $path = trim((string)$path);
    if ($path === '' || !is_file($_SERVER['DOCUMENT_ROOT'] . $path)) {
        return $fallback;
    }

    return $path;
}

function testMapLoadCemeteries(mysqli $dblink): array
{
    $rows = [];
    $res = mysqli_query(
        $dblink,
        "SELECT idx, title
         FROM cemetery
         WHERE LOWER(COALESCE(moderation_status, 'pending')) <> 'rejected'
         ORDER BY title"
    );

    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $rows[] = [
            'id' => (int)($row['idx'] ?? 0),
            'title' => trim((string)($row['title'] ?? '')),
        ];
    }

    return $rows;
}

function testMapBuildQuarterPayload(mysqli $dblink, int $cemeteryId): array
{
    $payload = [
        'status' => 'ok',
        'grave_data_available' => false,
        'message' => '',
        'cemetery' => null,
        'quarters' => [],
    ];

    if ($cemeteryId <= 0) {
        $payload['status'] = 'error';
        $payload['message'] = 'Не обрано кладовище.';
        return $payload;
    }

    $cemeterySelect = [
        'c.idx',
        'c.title',
        'c.adress',
        'c.town',
        'c.district',
    ];
    $cemeteryJoin = [];

    if (dbTableExists($dblink, 'misto')) {
        $cemeterySelect[] = 'm.title AS town_name';
        $cemeteryJoin[] = 'LEFT JOIN misto m ON c.town = m.idx';
    } else {
        $cemeterySelect[] = "'' AS town_name";
    }

    if (dbTableExists($dblink, 'district')) {
        $cemeterySelect[] = 'd.title AS district_name';
        $cemeteryJoin[] = 'LEFT JOIN district d ON c.district = d.idx';

        if (dbTableExists($dblink, 'region')) {
            $cemeterySelect[] = 'r.title AS region_name';
            $cemeteryJoin[] = 'LEFT JOIN region r ON d.region = r.idx';
        } else {
            $cemeterySelect[] = "'' AS region_name";
        }
    } else {
        $cemeterySelect[] = "'' AS district_name";
        $cemeterySelect[] = "'' AS region_name";
    }

    $cemeteryRes = mysqli_query(
        $dblink,
        'SELECT ' . implode(', ', $cemeterySelect) . '
         FROM cemetery c
         ' . implode("\n", $cemeteryJoin) . '
         WHERE c.idx = ' . $cemeteryId . "
           AND LOWER(COALESCE(c.moderation_status, 'pending')) <> 'rejected'
         LIMIT 1"
    );

    $cemetery = $cemeteryRes ? mysqli_fetch_assoc($cemeteryRes) : null;
    if (!$cemetery) {
        $payload['status'] = 'error';
        $payload['message'] = 'Кладовище не знайдено.';
        return $payload;
    }

    $payload['cemetery'] = [
        'id' => (int)($cemetery['idx'] ?? 0),
        'title' => trim((string)($cemetery['title'] ?? '')),
        'address' => trim((string)($cemetery['adress'] ?? '')),
        'town' => trim((string)($cemetery['town_name'] ?? '')),
        'district' => trim((string)($cemetery['district_name'] ?? '')),
        'region' => trim((string)($cemetery['region_name'] ?? '')),
    ];

    if (!dbTableExists($dblink, 'grave')) {
        $payload['message'] = 'У поточній базі даних ще немає таблиці поховань, тому схема кварталів поки недоступна.';
        return $payload;
    }

    $payload['grave_data_available'] = true;

    $graveRes = mysqli_query(
        $dblink,
        "SELECT idx, lname, fname, mname, dt1, dt1_year, dt1_month, dt1_day, dt2, dt2_year, dt2_month, dt2_day, pos1, pos2, pos3, photo1
         FROM grave
         WHERE idxkladb = {$cemeteryId}
           AND LOWER(COALESCE(moderation_status, 'pending')) <> 'rejected'
           AND TRIM(COALESCE(pos1, '')) <> ''
           AND TRIM(COALESCE(pos2, '')) <> ''
           AND TRIM(COALESCE(pos3, '')) <> ''
         ORDER BY idx DESC"
    );

    if (!$graveRes) {
        $payload['message'] = 'Не вдалося завантажити дані про поховання для цього кладовища.';
        return $payload;
    }

    $quarters = [];
    while ($grave = mysqli_fetch_assoc($graveRes)) {
        $quarter = testMapNormalizePosition($grave['pos1'] ?? '');
        $row = testMapNormalizePosition($grave['pos2'] ?? '');
        $place = testMapNormalizePosition($grave['pos3'] ?? '');

        if ($quarter <= 0 || $row <= 0 || $place <= 0) {
            continue;
        }

        $quarterKey = (string)$quarter;
        $cellKey = $row . '-' . $place;

        if (!isset($quarters[$quarterKey])) {
            $quarters[$quarterKey] = [
                'key' => $quarterKey,
                'rows' => 0,
                'places_per_row' => 0,
                'burial_count' => 0,
                'occupied_places' => 0,
                'cells' => [],
            ];
        }

        if (!isset($quarters[$quarterKey]['cells'][$cellKey])) {
            $quarters[$quarterKey]['cells'][$cellKey] = [
                'row' => $row,
                'place' => $place,
                'burials' => [],
            ];
            $quarters[$quarterKey]['occupied_places']++;
        }

        $quarters[$quarterKey]['rows'] = max($quarters[$quarterKey]['rows'], $row);
        $quarters[$quarterKey]['places_per_row'] = max($quarters[$quarterKey]['places_per_row'], $place);
        $quarters[$quarterKey]['burial_count']++;
        $quarters[$quarterKey]['cells'][$cellKey]['burials'][] = [
            'id' => (int)($grave['idx'] ?? 0),
            'name' => testMapBuildBurialName($grave),
            'short_name' => testMapBuildBurialShortName($grave),
            'years' => testMapBuildBurialYears($grave),
            'photo' => testMapResolveBurialPhoto($grave['photo1'] ?? ''),
            'url' => '/cardout.php?idx=' . (int)($grave['idx'] ?? 0),
        ];
    }

    if (!$quarters) {
        $payload['message'] = 'Для цього кладовища поки немає поховань із заповненими кварталом, рядом і місцем.';
        return $payload;
    }

    uksort($quarters, static fn($a, $b) => ((int)$a <=> (int)$b));
    foreach ($quarters as &$quarterData) {
        uksort($quarterData['cells'], static function ($left, $right) {
            [$leftRow, $leftPlace] = array_map('intval', explode('-', $left, 2));
            [$rightRow, $rightPlace] = array_map('intval', explode('-', $right, 2));
            return [$leftRow, $leftPlace] <=> [$rightRow, $rightPlace];
        });
    }
    unset($quarterData);

    $payload['quarters'] = array_values($quarters);
    return $payload;
}

$dblink = DbConnect();

if (($_GET['action'] ?? '') === 'map_data') {
    header('Content-Type: application/json; charset=utf-8');
    $cemeteryId = (int)($_GET['cemetery_id'] ?? 0);
    echo json_encode(testMapBuildQuarterPayload($dblink, $cemeteryId), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    mysqli_close($dblink);
    exit;
}

$cemeteries = testMapLoadCemeteries($dblink);
$requestedInitialCemeteryId = (int)CEMETERY_MAP_SELECTED_ID;
$requestedInitialQuarterKey = (string)testMapNormalizePosition((string)($_GET['quarter'] ?? ''));
$initialCemeteryId = 0;
if ($requestedInitialCemeteryId > 0) {
    foreach ($cemeteries as $cemetery) {
        if ((int)($cemetery['id'] ?? 0) === $requestedInitialCemeteryId) {
            $initialCemeteryId = $requestedInitialCemeteryId;
            break;
        }
    }
}
mysqli_close($dblink);

$pageUp = Page_Up('Карта кладовища');
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'] ?? 'shanapra.com';
$cssUrl = $protocol . "://" . $host . testMapEsc((string)CEMETERY_MAP_CSS);
$pageUp = str_replace('</head>', '    <link rel="stylesheet" href="' . $cssUrl . '?v=' . filemtime($_SERVER['DOCUMENT_ROOT'] . CEMETERY_MAP_CSS) . '">' . xbr . '</head>', $pageUp);
echo $pageUp;
echo Menu_Up();
?>
<main class="test-map-page">
    <div class="test-map-shell">
        <section class="test-map-hero">
            <div class="test-map-hero__content">
                <span class="test-map-hero__eyebrow">Інтерактивна мапа</span>
                <h1>Карта кладовища</h1>
                <p>Оберіть кладовище та квартал, щоб переглянути розміщення місць, перейти до наявних поховань або швидко додати новий запис у вільну позицію.</p>
                <div class="test-map-hero__meta">
                    <span class="test-map-hero__pill">Пошук кварталів і місць</span>
                    <span class="test-map-hero__pill">Навігація по схемі</span>
                </div>
            </div>
        </section>

        <section id="cemetery-picker-section" class="test-map-picker"<?= $initialCemeteryId > 0 ? ' hidden' : '' ?>>
            <form id="cemetery-picker-form" class="test-map-picker__card">
                <div class="test-map-picker__head">
                    <div class="test-map-picker__icon-wrap">
                        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9 11a3 3 0 1 0 6 0a3 3 0 0 0 -6 0"/><path d="M17.657 16.657l-4.243 4.243a2 2 0 0 1 -2.827 0l-4.244 -4.243a8 8 0 1 1 11.314 0z"/></svg>
                    </div>
                    <div class="test-map-picker__head-content">
                        <h2>Оберіть кладовище</h2>
                        <p>Оберіть область, потім район та кладовище. Карта відкриється автоматично після вибору.</p>
                    </div>
                </div>

                <div class="test-map-picker__steps">
                    <div class="test-map-picker__step" data-step="1" data-state="active">
                        <div class="test-map-picker__step-header">
                            <span class="test-map-picker__step-num">1</span>
                            <span class="test-map-picker__step-title">Область</span>
                        </div>
                        <div class="test-map-picker__step-body">
                            <div class="test-select-host">
                                <select id="region-select" class="test-map-select"<?= !$cemeteries ? ' disabled' : '' ?>>
                                    <?= testMapRegionOptions() ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="test-map-picker__step" data-step="2" data-state="waiting">
                        <div class="test-map-picker__step-header">
                            <span class="test-map-picker__step-num">2</span>
                            <span class="test-map-picker__step-title">Район</span>
                        </div>
                        <div class="test-map-picker__step-body">
                            <div class="test-select-host">
                                <select id="district-select" class="test-map-select"<?= !$cemeteries ? ' disabled' : '' ?> disabled>
                                    <option value="">Спочатку оберіть область</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="test-map-picker__step" data-step="3" data-state="waiting">
                        <div class="test-map-picker__step-header">
                            <span class="test-map-picker__step-num">3</span>
                            <span class="test-map-picker__step-title">Кладовище</span>
                        </div>
                        <div class="test-map-picker__step-body">
                            <div class="test-select-host">
                                <select id="cemetery-select" class="test-map-select"<?= !$cemeteries ? ' disabled' : '' ?> disabled>
                                    <option value="">Спочатку оберіть район</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="test-map-picker__actions">
                        <button type="submit" id="cemetery-picker-submit" class="test-map-picker__submit"<?= !$cemeteries ? ' disabled' : '' ?>>
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 7l6 -3l6 3l6 -3v13l-6 3l-6 -3l-6 3v-13"/><path d="M9 4v13"/><path d="M15 7v13"/></svg>
                            Карта
                        </button>
                    </div>
                </div>
            </form>
        </section>

        <select id="quarter-select" class="test-map-hidden-select" disabled aria-hidden="true" tabindex="-1">
            <option value="">Спочатку оберіть кладовище</option>
        </select>

        <section id="map-summary-section" class="test-map-summary"<?= $initialCemeteryId > 0 ? '' : ' hidden' ?>>
            <button type="button" id="change-cemetery-btn" class="test-map-summary__change">Змінити кладовище</button>
            <article class="test-map-summary__item">
                <span class="test-map-summary__label">Кладовище</span>
                <strong id="summary-cemetery">-</strong>
            </article>
            <article class="test-map-summary__item">
                <span class="test-map-summary__label">Квартал</span>
                <strong id="summary-quarter">-</strong>
            </article>
            <article class="test-map-summary__item">
                <span class="test-map-summary__label">Рядів</span>
                <strong id="summary-rows">-</strong>
            </article>
            <article class="test-map-summary__item">
                <span class="test-map-summary__label">Позицій у схемі</span>
                <strong id="summary-places">-</strong>
            </article>
            <article class="test-map-summary__item">
                <span class="test-map-summary__label">Поховань у кварталі</span>
                <strong id="summary-burials">-</strong>
            </article>
        </section>

        <section id="quarter-map-section" class="test-quarter-card"<?= $initialCemeteryId > 0 ? '' : ' hidden' ?>>
            <div class="test-quarter-card__header">
                <div>
                    <span class="test-map-section-label">Рівень 1</span>
                    <h2 id="quarter-map-title">Оберіть кладовище</h2>
                </div>
                <div class="test-quarter-legend">
                    <span class="test-quarter-legend__item">
                        <i class="test-quarter-legend__dot test-quarter-legend__dot--selected"></i>
                        Обраний квартал
                    </span>
                    <span class="test-quarter-legend__item">
                        <i class="test-quarter-legend__dot test-quarter-legend__dot--occupied"></i>
                        Є поховання
                    </span>
                </div>
            </div>

            <div class="test-quarter-frame">
                <div class="test-quarter-frame__caption">Карта кварталів</div>
                <div class="test-quarter-map-scroller">
                    <div id="quarter-map-grid" class="test-quarter-map" aria-live="polite"></div>
                </div>
            </div>
        </section>

        <section id="quarter-detail-section" class="test-quarter-card"<?= $initialCemeteryId > 0 ? '' : ' hidden' ?>>
            <div class="test-quarter-card__header">
                <div>
                    <span class="test-map-section-label">Рівень 2</span>
                    <h2 id="quarter-title">Оберіть квартал</h2>
                    <p class="test-map-note">Схема показує лише координати, уже внесені на сайт. Якщо реальних місць більше, вони з'являться після заповнення даних.</p>
                </div>
                <div class="test-quarter-legend">
                    <span class="test-quarter-legend__item">
                        <i class="test-quarter-legend__dot test-quarter-legend__dot--empty"></i>
                        Вільно
                    </span>
                    <span class="test-quarter-legend__item">
                        <i class="test-quarter-legend__dot test-quarter-legend__dot--occupied"></i>
                        Є поховання
                    </span>
                </div>
            </div>

            <div class="test-quarter-frame">
                <div class="test-quarter-frame__caption">Карта місць</div>
                <div class="test-quarter-grid-scroller">
                    <div id="quarter-grid" class="test-quarter-grid" aria-live="polite"></div>
                </div>
            </div>
        </section>
    </div>
</main>

<div id="test-place-popover" class="test-place-popover" hidden>
    <div class="test-place-popover__panel" role="dialog" aria-modal="false" aria-live="polite">
        <button type="button" class="test-place-popover__close" aria-label="Закрити меню місця">×</button>
        <div class="test-place-popover__content"></div>
    </div>
</div>

<script>
const initialCemeteryId = <?= (int)$initialCemeteryId ?>;
const initialQuarterKey = <?= json_encode($requestedInitialQuarterKey, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const configuredMapDataPath = <?= json_encode((string)CEMETERY_MAP_DATA_ROUTE, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const configuredMapRoutePath = <?= json_encode((string)CEMETERY_MAP_ROUTE, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const cemeteryPickerSection = document.getElementById('cemetery-picker-section');
const cemeteryPickerForm = document.getElementById('cemetery-picker-form');
const cemeteryPickerSubmit = document.getElementById('cemetery-picker-submit');
const changeCemeteryBtn = document.getElementById('change-cemetery-btn');
const regionSelect = document.getElementById('region-select');
const districtSelect = document.getElementById('district-select');
const cemeterySelect = document.getElementById('cemetery-select');
const quarterSelect = document.getElementById('quarter-select');
const mapSummarySection = document.getElementById('map-summary-section');
const quarterMapSection = document.getElementById('quarter-map-section');
const quarterDetailSection = document.getElementById('quarter-detail-section');
const quarterMapGrid = document.getElementById('quarter-map-grid');
const quarterMapFrame = quarterMapGrid.closest('.test-quarter-frame');
const quarterMapTitle = document.getElementById('quarter-map-title');
const quarterGrid = document.getElementById('quarter-grid');
const quarterTitle = document.getElementById('quarter-title');
const summaryCemetery = document.getElementById('summary-cemetery');
const summaryQuarter = document.getElementById('summary-quarter');
const summaryRows = document.getElementById('summary-rows');
const summaryPlaces = document.getElementById('summary-places');
const summaryBurials = document.getElementById('summary-burials');
const placePopover = document.getElementById('test-place-popover');
const placePopoverPanel = placePopover ? placePopover.querySelector('.test-place-popover__panel') : null;
const placePopoverContent = placePopover ? placePopover.querySelector('.test-place-popover__content') : null;
const placePopoverClose = placePopover ? placePopover.querySelector('.test-place-popover__close') : null;

let currentPayload = null;
let activePlaceTrigger = null;
let allCemeteryOptions = [];
let customSelectEnabled = true;
const customSelectWrappers = new Map();

function closeAllCustomSelects(exceptWrapper) {
    document.querySelectorAll('.custom-select-wrapper.open').forEach((wrapper) => {
        if (exceptWrapper && wrapper === exceptWrapper) return;
        wrapper.classList.remove('open');
        wrapper.classList.remove('open-up');
        const optionsBox = wrapper.querySelector('.custom-options');
        if (optionsBox) optionsBox.style.display = 'none';
    });
}

function getCustomWrapper(selectEl) {
    if (!selectEl || !selectEl.id) return null;
    return selectEl.parentNode
        ? selectEl.parentNode.querySelector('.custom-select-wrapper[data-select-id="' + selectEl.id + '"]')
        : null;
}

function ensureCustomSelect(selectEl) {
    if (!customSelectEnabled || !selectEl || !selectEl.id) return null;
    let wrapper = getCustomWrapper(selectEl);
    if (wrapper) return wrapper;

    wrapper = document.createElement('div');
    wrapper.className = 'custom-select-wrapper';
    wrapper.dataset.selectId = selectEl.id;

    const trigger = document.createElement('div');
    trigger.className = 'custom-select-trigger';

    const optionsBox = document.createElement('div');
    optionsBox.className = 'custom-options';

    trigger.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        if (selectEl.disabled) return;

        const willOpen = !wrapper.classList.contains('open');
        closeAllCustomSelects(wrapper);
        if (willOpen) {
            wrapper.classList.remove('open-up');
            const triggerRect = trigger.getBoundingClientRect();
            const viewportSpaceBelow = window.innerHeight - triggerRect.bottom;
            const optionsHeight = Math.min(optionsBox.scrollHeight || 240, 240) + 10;
            if (viewportSpaceBelow < optionsHeight) {
                wrapper.classList.add('open-up');
            }
        } else {
            wrapper.classList.remove('open-up');
        }

        wrapper.classList.toggle('open', willOpen);
        optionsBox.style.display = willOpen ? 'flex' : 'none';
    });

    wrapper.addEventListener('mousedown', (event) => {
        event.stopPropagation();
    });

    wrapper.appendChild(trigger);
    wrapper.appendChild(optionsBox);
    selectEl.classList.add('test-select-native-hidden');

    if (selectEl.nextSibling) {
        selectEl.parentNode.insertBefore(wrapper, selectEl.nextSibling);
    } else {
        selectEl.parentNode.appendChild(wrapper);
    }

    customSelectWrappers.set(selectEl.id, wrapper);
    return wrapper;
}

function syncCustomSelect(selectEl, placeholderText = 'Оберіть') {
    if (!customSelectEnabled || !selectEl) return;
    const wrapper = ensureCustomSelect(selectEl);
    if (!wrapper) return;
    const trigger = wrapper.querySelector('.custom-select-trigger');
    const optionsBox = wrapper.querySelector('.custom-options');
    if (!trigger || !optionsBox) return;

    const options = Array.from(selectEl.options || []).filter((opt) => !opt.hidden);
    optionsBox.innerHTML = '';

    const selectedOption = options.find((opt) => opt.value !== '' && opt.value === selectEl.value);
    const triggerText = selectedOption
        ? selectedOption.textContent
        : (options[0] && options[0].textContent ? options[0].textContent : placeholderText);
    trigger.textContent = triggerText;

    options.forEach((opt) => {
        const optionNode = document.createElement('span');
        optionNode.textContent = opt.textContent;
        if (!opt.value) {
            optionNode.className = 'custom-option disabled';
            optionsBox.appendChild(optionNode);
            return;
        }

        optionNode.className = 'custom-option';
        if (opt.value === selectEl.value) optionNode.classList.add('is-selected');
        optionNode.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            if (selectEl.disabled) return;
            selectEl.value = opt.value;
            syncCustomSelect(selectEl, placeholderText);
            closeAllCustomSelects();
            selectEl.dispatchEvent(new Event('change', { bubbles: true }));
        });
        optionsBox.appendChild(optionNode);
    });

    wrapper.classList.toggle('disabled', !!selectEl.disabled);
}

function setPickerVisible(isVisible) {
    if (cemeteryPickerSection) {
        cemeteryPickerSection.hidden = !isVisible;
    }
    if (changeCemeteryBtn) {
        changeCemeteryBtn.hidden = isVisible;
    }
}

function resolveMapDataBaseUrl(path) {
    const currentPath = window.location.pathname || '';
    const normalizedPath = String(path || '').trim();

    if (/cemetery-map\.php$/i.test(currentPath)) {
        return window.location.origin + currentPath;
    }

    if (/^https?:\/\//i.test(normalizedPath)) {
        const parsed = new URL(normalizedPath);
        return window.location.origin === parsed.origin
            ? parsed.toString()
            : (window.location.origin + parsed.pathname);
    }

    return new URL(normalizedPath || currentPath, window.location.origin).toString();
}

const mapDataBaseUrl = resolveMapDataBaseUrl(configuredMapDataPath);

const placeholderById = {
    'region-select': 'Оберіть область',
    'district-select': 'Оберіть район',
    'cemetery-select': 'Оберіть кладовище',
};

function closeAllCustomSelects(exceptWrapper) {
    document.querySelectorAll('.custom-select-wrapper.open').forEach((wrapper) => {
        if (exceptWrapper && wrapper === exceptWrapper) return;
        wrapper.classList.remove('open');
        wrapper.classList.remove('open-up');
        const optionsBox = wrapper.querySelector('.custom-options');
        if (optionsBox) optionsBox.style.display = 'none';
    });
}

function getCustomWrapper(selectEl) {
    const host = selectEl && selectEl.parentNode ? selectEl.parentNode : null;
    return host ? host.querySelector('.custom-select-wrapper[data-select-id="' + selectEl.id + '"]') : null;
}

function ensureCustomSelect(selectEl) {
    if (!selectEl || !selectEl.id) return null;
    let wrapper = getCustomWrapper(selectEl);
    if (wrapper) return wrapper;

    wrapper = document.createElement('div');
    wrapper.className = 'custom-select-wrapper';
    wrapper.dataset.selectId = selectEl.id;

    const trigger = document.createElement('div');
    trigger.className = 'custom-select-trigger';

    const optionsBox = document.createElement('div');
    optionsBox.className = 'custom-options';

    trigger.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        if (selectEl.disabled) return;
        const willOpen = !wrapper.classList.contains('open');
        closeAllCustomSelects(wrapper);
        if (willOpen) {
            wrapper.classList.remove('open-up');
            const triggerRect = trigger.getBoundingClientRect();
            const viewportSpaceBelow = window.innerHeight - triggerRect.bottom;
            const optionsHeight = Math.min(optionsBox.scrollHeight || 220, 220) + 10;
            if (viewportSpaceBelow < optionsHeight) wrapper.classList.add('open-up');
        } else {
            wrapper.classList.remove('open-up');
        }
        wrapper.classList.toggle('open', willOpen);
        optionsBox.style.display = willOpen ? 'flex' : 'none';
    });

    wrapper.addEventListener('mousedown', (event) => event.stopPropagation());
    wrapper.appendChild(trigger);
    wrapper.appendChild(optionsBox);

    selectEl.classList.add('test-select-native-hidden');
    const host = selectEl.parentNode;
    if (selectEl.nextSibling) host.insertBefore(wrapper, selectEl.nextSibling);
    else host.appendChild(wrapper);
    return wrapper;
}

function syncCustomSelect(selectEl) {
    const wrapper = ensureCustomSelect(selectEl);
    if (!wrapper) return;
    const trigger = wrapper.querySelector('.custom-select-trigger');
    const optionsBox = wrapper.querySelector('.custom-options');
    const options = Array.from(selectEl.options || []);
    const placeholder = placeholderById[selectEl.id] || 'Оберіть';

    optionsBox.innerHTML = '';
    const selectedOption = options.find((opt) => opt.value !== '' && opt.value === selectEl.value) || null;
    let triggerText = placeholder;
    if (selectedOption) triggerText = selectedOption.textContent;
    else if (options[0] && options[0].textContent) triggerText = options[0].textContent;
    trigger.textContent = triggerText;

    options.forEach((opt) => {
        const optionNode = document.createElement('span');
        optionNode.textContent = opt.textContent;
        if (!opt.value) {
            optionNode.className = 'custom-option disabled';
            optionsBox.appendChild(optionNode);
            return;
        }
        optionNode.className = 'custom-option';
        if (opt.value === selectEl.value) optionNode.classList.add('is-selected');
        optionNode.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            if (selectEl.disabled) return;
            selectEl.value = opt.value;
            syncCustomSelect(selectEl);
            closeAllCustomSelects();
            selectEl.dispatchEvent(new Event('change', { bubbles: true }));
        });
        optionsBox.appendChild(optionNode);
    });

    wrapper.classList.toggle('disabled', !!selectEl.disabled);
}

function resetDistrict() {
    if (!districtSelect) return;
    districtSelect.innerHTML = '<option value="">Спочатку оберіть область</option>';
    districtSelect.disabled = true;
    syncCustomSelect(districtSelect);
}

function resetCemetery() {
    if (!cemeterySelect) return;
    cemeterySelect.innerHTML = '<option value="">Спочатку оберіть район</option>';
    cemeterySelect.disabled = true;
    syncCustomSelect(cemeterySelect);
    if (cemeteryPickerSubmit) cemeteryPickerSubmit.disabled = true;
}

function resolveAjaxEndpoint() {
    const path = window.location.pathname && window.location.pathname !== '/' ? window.location.pathname : '/test.php';
    return path;
}

function loadDistricts(regionId) {
    if (!districtSelect) return;
    resetCemetery();
    districtSelect.disabled = true;
    districtSelect.innerHTML = '<option value="">Завантаження...</option>';
    syncCustomSelect(districtSelect);

    const endpoint = resolveAjaxEndpoint();
    fetch(endpoint + '?ajax_districts=1&region_id=' + encodeURIComponent(regionId), { credentials: 'same-origin' })
        .then((res) => res.text())
        .then((html) => {
            districtSelect.innerHTML = html;
            districtSelect.disabled = false;
            syncCustomSelect(districtSelect);
        })
        .catch(() => {
            districtSelect.innerHTML = '<option value="">Помилка завантаження</option>';
            districtSelect.disabled = true;
            syncCustomSelect(districtSelect);
        });
}

function loadCemeteriesByDistrict(districtId) {
    if (!cemeterySelect) return;
    cemeterySelect.disabled = true;
    cemeterySelect.innerHTML = '<option value="">Завантаження...</option>';
    syncCustomSelect(cemeterySelect);
    if (cemeteryPickerSubmit) cemeteryPickerSubmit.disabled = true;

    const endpoint = resolveAjaxEndpoint();
    fetch(endpoint + '?ajax_cemeteries=1&district_id=' + encodeURIComponent(districtId), { credentials: 'same-origin' })
        .then((res) => res.text())
        .then((html) => {
            cemeterySelect.innerHTML = html;
            cemeterySelect.disabled = false;
            syncCustomSelect(cemeterySelect);
            if (cemeteryPickerSubmit) cemeteryPickerSubmit.disabled = !String(cemeterySelect.value || '').trim();
        })
        .catch(() => {
            cemeterySelect.innerHTML = '<option value="">Помилка завантаження</option>';
            cemeterySelect.disabled = true;
            syncCustomSelect(cemeterySelect);
        });
}

function initializeCemeteryFilters() {
    syncCustomSelect(regionSelect);
    syncCustomSelect(districtSelect);
    syncCustomSelect(cemeterySelect);
    resetDistrict();
    resetCemetery();
}

function hasRenderableMapPayload(payload) {
    return !!(
        payload
        && payload.status === 'ok'
        && payload.cemetery
        && Array.isArray(payload.quarters)
        && payload.quarters.length > 0
    );
}

function setMapSectionsVisibility(isVisible) {
    [mapSummarySection, quarterMapSection, quarterDetailSection].forEach((section) => {
        if (!section) {
            return;
        }
        section.hidden = !isVisible;
        if (isVisible) {
            section.style.removeProperty('display');
        } else {
            section.style.display = 'none';
        }
    });
}

function resetSummary() {
    summaryCemetery.textContent = '-';
    summaryQuarter.textContent = '-';
    summaryRows.textContent = '-';
    summaryPlaces.textContent = '-';
    summaryBurials.textContent = '-';
}

function extractJsonPayload(rawText) {
    const text = String(rawText || '').trim();
    if (!text) {
        return null;
    }

    try {
        return JSON.parse(text);
    } catch (error) {
        const start = text.indexOf('{');
        const end = text.lastIndexOf('}');
        if (start >= 0 && end > start) {
            const sliced = text.slice(start, end + 1);
            try {
                return JSON.parse(sliced);
            } catch (nestedError) {
                return null;
            }
        }
    }

    return null;
}

function buildServerErrorMessage(rawText, fallbackText) {
    const text = String(rawText || '').replace(/\s+/g, ' ').trim();
    if (!text) {
        return fallbackText;
    }

    if (/warning|fatal error|notice|syntaxerror|parse error/i.test(text)) {
        return 'Сервер повернув некоректну відповідь під час завантаження карти.';
    }

    if (text.length > 180) {
        return text.slice(0, 177) + '...';
    }

    return text;
}

function buildMapRequestCandidates(cemeteryId) {
    const candidates = [];
    const seen = new Set();

    const addCandidate = (path) => {
        const normalizedPath = String(path || '').trim();
        if (!normalizedPath) {
            return;
        }

        const url = new URL(normalizedPath, window.location.origin);
        url.searchParams.set('action', 'map_data');
        url.searchParams.set('cemetery_id', String(cemeteryId));

        const key = url.toString();
        if (seen.has(key)) {
            return;
        }

        seen.add(key);
        candidates.push(key);
    };

    addCandidate(mapDataBaseUrl);
    addCandidate('/cemetery-map.php');
    addCandidate('/cemetery-map');
    addCandidate(configuredMapRoutePath);
    addCandidate(window.location.pathname || configuredMapRoutePath);

    return candidates;
}

async function fetchMapPayload(cemeteryId) {
    const candidates = buildMapRequestCandidates(cemeteryId);
    let lastErrorMessage = 'Сталася помилка під час завантаження карти.';

    for (const requestUrl of candidates) {
        try {
            const response = await fetch(requestUrl, {
                credentials: 'same-origin'
            });
            const rawText = await response.text();
            const payload = extractJsonPayload(rawText);

            if (response.ok && payload && typeof payload === 'object') {
                return payload;
            }

            lastErrorMessage = buildServerErrorMessage(rawText, 'Не вдалося завантажити карту.');
        } catch (error) {
            lastErrorMessage = error instanceof Error && error.message
                ? error.message
                : 'Сталася помилка під час завантаження карти.';
        }
    }

    throw new Error(lastErrorMessage);
}

function syncUrlState() {
    const cemeteryId = String(cemeterySelect.value || '').trim();
    const quarterKey = String(quarterSelect.value || '').trim();
    const params = new URLSearchParams(window.location.search);

    if (cemeteryId) {
        params.set('cemetery_id', cemeteryId);
    } else {
        params.delete('cemetery_id');
    }

    params.delete('idx');

    if (quarterKey) {
        params.set('quarter', quarterKey);
    } else {
        params.delete('quarter');
    }

    const nextUrl = window.location.origin
        + window.location.pathname
        + (params.toString() ? '?' + params.toString() : '')
        + window.location.hash;
    window.history.replaceState({}, '', nextUrl);
}

function clearMapView() {
    quarterMapGrid.innerHTML = '';
    quarterMapGrid.classList.remove('is-single');
    if (quarterMapFrame) {
        quarterMapFrame.classList.remove('is-single');
    }
    quarterGrid.innerHTML = '';
    quarterMapTitle.textContent = 'Оберіть кладовище';
    quarterTitle.textContent = 'Оберіть квартал';
}

function getQuarterList() {
    return currentPayload && Array.isArray(currentPayload.quarters) ? currentPayload.quarters : [];
}

function getSelectedQuarter() {
    const quarters = getQuarterList();
    if (quarters.length === 0) {
        return null;
    }

    const selectedKey = quarterSelect.value;
    return quarters.find((item) => item.key === selectedKey) || quarters[0];
}

function renderQuarterOptions(payload, preferredQuarterKey = '') {
    quarterSelect.innerHTML = '';

    if (!payload || !Array.isArray(payload.quarters) || payload.quarters.length === 0) {
        const option = document.createElement('option');
        option.value = '';
        option.textContent = 'Немає кварталів';
        quarterSelect.appendChild(option);
        quarterSelect.disabled = true;
        return '';
    }

    let selectedKey = preferredQuarterKey;
    payload.quarters.forEach((quarter, index) => {
        const option = document.createElement('option');
        option.value = quarter.key;
        option.textContent = 'Квартал ' + quarter.key;
        if ((selectedKey !== '' && quarter.key === selectedKey) || (selectedKey === '' && index === 0)) {
            option.selected = true;
            selectedKey = quarter.key;
        }
        quarterSelect.appendChild(option);
    });

    quarterSelect.disabled = false;
    return selectedKey;
}

function createEmptyPlaceIcon() {
    const iconWrap = document.createElement('div');
    iconWrap.className = 'test-place__icon';
    iconWrap.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M7 16.17v-9.17a3 3 0 0 1 3 -3h4a3 3 0 0 1 3 3v9.171"></path><path d="M12 7v5"></path><path d="M10 9h4"></path><path d="M5 21v-2a3 3 0 0 1 3 -3h8a3 3 0 0 1 3 3v2h-14"></path></svg>';
    return iconWrap;
}

function createQuarterTile(quarter, isSelected) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'test-quarter-tile' + (isSelected ? ' is-selected' : '');
    button.dataset.quarterKey = quarter.key;

    const totalPlaces = quarter.rows * quarter.places_per_row;
    const occupiedPlaces = Number(quarter.occupied_places || 0);
    const occupancy = totalPlaces > 0 ? Math.round((occupiedPlaces / totalPlaces) * 100) : 0;
    button.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
    button.setAttribute(
        'aria-label',
        'Квартал ' + quarter.key + '. ' + occupiedPlaces + ' із ' + totalPlaces + ' місць зайнято.'
    );

    const marker = document.createElement('span');
    marker.className = 'test-quarter-tile__marker';
    marker.textContent = isSelected ? 'Обрано' : 'Квартал';
    button.appendChild(marker);

    const title = document.createElement('strong');
    title.className = 'test-quarter-tile__title';
    title.textContent = '№ ' + quarter.key;
    button.appendChild(title);

    const meta = document.createElement('div');
    meta.className = 'test-quarter-tile__meta';
    meta.textContent = quarter.rows + ' рядів • ' + totalPlaces + ' місць';
    button.appendChild(meta);

    const progress = document.createElement('div');
    progress.className = 'test-quarter-tile__progress';
    progress.innerHTML = '<span></span>';
    progress.firstChild.style.width = (occupiedPlaces > 0 ? Math.max(8, occupancy) : 0) + '%';
    button.appendChild(progress);

    const stats = document.createElement('div');
    stats.className = 'test-quarter-tile__stats';

    const occupied = document.createElement('span');
    occupied.textContent = 'Зайнято: ' + occupiedPlaces;
    stats.appendChild(occupied);

    const burials = document.createElement('span');
    burials.textContent = 'Поховань: ' + quarter.burial_count;
    stats.appendChild(burials);

    button.appendChild(stats);
    return button;
}

function renderQuarterMap() {
    quarterMapGrid.innerHTML = '';

    const quarters = getQuarterList();
    quarterMapGrid.classList.toggle('is-single', quarters.length === 1);
    if (quarterMapFrame) {
        quarterMapFrame.classList.toggle('is-single', quarters.length === 1);
    }

    if (quarters.length === 0) {
        quarterMapTitle.textContent = 'Карта кварталів недоступна';

        const empty = document.createElement('div');
        empty.className = 'test-map-empty';
        empty.textContent = 'Для цього кладовища ще немає кварталів з розміченими місцями.';
        quarterMapGrid.appendChild(empty);
        return;
    }

    const selectedQuarter = getSelectedQuarter();
    const cemeteryTitle = currentPayload && currentPayload.cemetery ? currentPayload.cemetery.title : 'Кладовище';
    quarterMapTitle.textContent = cemeteryTitle + ' • карта кварталів';

    quarters.forEach((quarter) => {
        quarterMapGrid.appendChild(createQuarterTile(quarter, selectedQuarter && selectedQuarter.key === quarter.key));
    });
}

function createPlaceCell(rowNumber, placeNumber, cellData) {
    const burials = cellData && Array.isArray(cellData.burials) ? cellData.burials : [];
    const firstBurial = burials[0] || null;
    const hasMenu = !firstBurial || burials.length > 1;
    const root = hasMenu
        ? document.createElement('button')
        : (firstBurial && firstBurial.url ? document.createElement('a') : document.createElement('div'));
    root.className = 'test-place' + (firstBurial ? ' test-place--occupied' : '');

    if (root instanceof HTMLButtonElement) {
        root.type = 'button';
        root.classList.add('test-place--interactive');
    }

    if (!hasMenu && firstBurial && firstBurial.url) {
        root.href = firstBurial.url;
    }

    root.dataset.row = String(rowNumber);
    root.dataset.place = String(placeNumber);

    const names = burials.map((burial) => burial.short_name || burial.name).filter(Boolean);
    const tooltipText = firstBurial
        ? ('Ряд ' + rowNumber + ', місце ' + placeNumber + ' • ' + names.join(', '))
        : '';
    root.setAttribute(
        'aria-label',
        firstBurial
            ? ('Ряд ' + rowNumber + ', місце ' + placeNumber + '. ' + names.join(', '))
            : ('Ряд ' + rowNumber + ', місце ' + placeNumber + ', вільно')
    );

    if (firstBurial && firstBurial.photo) {
        root.className += ' test-place--with-photo';
        root.style.setProperty('--place-photo', 'url("' + firstBurial.photo + '")');
    }

    if (burials.length > 1) {
        const counter = document.createElement('span');
        counter.className = 'test-place__count';
        counter.textContent = '+' + (burials.length - 1);
        root.appendChild(counter);
    }

    const info = document.createElement('div');
    info.className = 'test-place__body';
    if (tooltipText) {
        info.dataset.tooltip = tooltipText;
    }
    info.tabIndex = -1;

    if (!firstBurial) {
        root.className += ' test-place--empty';

        const state = document.createElement('div');
        state.className = 'test-place__vacant';
        state.textContent = 'Вільно';
        info.appendChild(state);

        root._placePayload = {
            mode: 'empty',
            row: rowNumber,
            place: placeNumber,
            quarterKey: quarterSelect.value || '',
        };

        root.appendChild(info);
        return root;
    }

    const name = document.createElement('div');
    name.className = 'test-place__person';
    name.textContent = firstBurial.short_name || firstBurial.name;
    info.appendChild(name);

    if (firstBurial.years) {
        const years = document.createElement('div');
        years.className = 'test-place__years';
        years.textContent = firstBurial.years;
        info.appendChild(years);
    }

    if (burials.length > 1) {
        const stack = document.createElement('div');
        stack.className = 'test-place__hint';
        stack.textContent = '';
        info.appendChild(stack);
    }

    if (burials.length > 1) {
        root._placePayload = {
            mode: 'burials',
            row: rowNumber,
            place: placeNumber,
            quarterKey: quarterSelect.value || '',
            burials: burials,
        };
    }

    root.appendChild(info);
    return root;
}

function buildAddBurialUrl(payload) {
    const cemetery = currentPayload && currentPayload.cemetery ? currentPayload.cemetery : null;
    const url = new URL('/graveaddform.php', window.location.origin);
    if (cemetery && cemetery.id) {
        url.searchParams.set('cemetery_id', String(cemetery.id));
    }
    if (payload && payload.quarterKey) {
        url.searchParams.set('quarter', String(payload.quarterKey));
        url.searchParams.set('pos1', String(payload.quarterKey));
    }
    if (payload && payload.row) {
        url.searchParams.set('row', String(payload.row));
        url.searchParams.set('pos2', String(payload.row));
    }
    if (payload && payload.place) {
        url.searchParams.set('place', String(payload.place));
        url.searchParams.set('pos3', String(payload.place));
    }
    url.searchParams.set('from_map', '1');
    return url.toString();
}

function createPopoverHeading(title, subtitle) {
    const heading = document.createElement('div');
    heading.className = 'test-place-popover__heading';

    const titleNode = document.createElement('strong');
    titleNode.textContent = title;
    heading.appendChild(titleNode);

    const subtitleNode = document.createElement('span');
    subtitleNode.textContent = subtitle;
    heading.appendChild(subtitleNode);

    return heading;
}

function hidePlacePopover() {
    if (!placePopover) {
        return;
    }

    if (activePlaceTrigger) {
        activePlaceTrigger.classList.remove('is-active');
    }

    activePlaceTrigger = null;
    placePopover.removeAttribute('data-side');
    placePopover.hidden = true;
    if (placePopoverContent) {
        placePopoverContent.innerHTML = '';
    }
    if (placePopoverPanel) {
        placePopoverPanel.style.left = '';
        placePopoverPanel.style.top = '';
        placePopoverPanel.style.right = '';
        placePopoverPanel.style.bottom = '';
    }
    placePopover.removeAttribute('data-mode');
    document.body.classList.remove('test-popover-open');
}

function updatePlacePopoverPosition() {
    if (!placePopover || !placePopoverPanel || !activePlaceTrigger || !document.body.contains(activePlaceTrigger)) {
        hidePlacePopover();
        return;
    }

    const isMobilePopover = window.matchMedia('(max-width: 768px)').matches;
    const triggerRect = activePlaceTrigger.getBoundingClientRect();
    placePopover.hidden = false;
    placePopoverPanel.style.left = '';
    placePopoverPanel.style.top = '';
    placePopoverPanel.style.right = '';
    placePopoverPanel.style.bottom = '';

    if (isMobilePopover) {
        placePopover.dataset.mode = 'mobile';
        placePopover.removeAttribute('data-side');
        placePopoverPanel.style.left = '0';
        placePopoverPanel.style.right = '0';
        placePopoverPanel.style.bottom = '0';
        return;
    }

    placePopover.dataset.mode = 'desktop';
    placePopoverPanel.style.left = '0px';
    placePopoverPanel.style.top = '0px';

    const panelRect = placePopoverPanel.getBoundingClientRect();
    let left = triggerRect.left + (triggerRect.width / 2) - (panelRect.width / 2);
    left = Math.max(12, Math.min(window.innerWidth - panelRect.width - 12, left));

    let top = triggerRect.bottom + 14;
    let isTop = false;
    if (top + panelRect.height > window.innerHeight - 12) {
        top = triggerRect.top - panelRect.height - 14;
        isTop = true;
    }
    top = Math.max(12, top);

    placePopoverPanel.style.left = Math.round(left) + 'px';
    placePopoverPanel.style.top = Math.round(top) + 'px';
    placePopover.dataset.side = isTop ? 'top' : 'bottom';
}

function showPlacePopover(trigger, payload) {
    if (!placePopoverContent || !placePopover || !payload) {
        return;
    }

    if (activePlaceTrigger && activePlaceTrigger !== trigger) {
        activePlaceTrigger.classList.remove('is-active');
    }

    activePlaceTrigger = trigger;
    activePlaceTrigger.classList.add('is-active');
    document.body.classList.add('test-popover-open');

    const title = payload.mode === 'burials'
        ? 'Місце ' + payload.place + ' • кілька поховань'
        : 'Місце ' + payload.place + ' вільне';
    const subtitle = 'Квартал ' + payload.quarterKey + ' • ряд ' + payload.row;
    placePopoverContent.innerHTML = '';
    const heading = createPopoverHeading(title, subtitle);
    placePopoverContent.appendChild(heading);

    const body = document.createElement('div');
    body.className = 'test-place-popover__body';
    placePopoverContent.appendChild(body);

    if (payload.mode === 'burials') {
        const list = document.createElement('div');
        list.className = 'test-place-popover__list';
        payload.burials.forEach((burial) => {
            const label = [burial.name || burial.short_name || 'Поховання', burial.years || ''].filter(Boolean).join(' • ');
            const link = document.createElement('a');
            link.className = 'test-place-popover__link';
            link.href = burial.url;
            if (burial.photo) {
                const thumb = document.createElement('img');
                thumb.className = 'test-place-popover__thumb';
                thumb.src = burial.photo;
                thumb.alt = burial.short_name || burial.name || 'Фото поховання';
                thumb.loading = 'lazy';
                link.appendChild(thumb);
            } else {
                const thumbPlaceholder = document.createElement('span');
                thumbPlaceholder.className = 'test-place-popover__thumb test-place-popover__thumb--empty';
                thumbPlaceholder.setAttribute('aria-hidden', 'true');
                link.appendChild(thumbPlaceholder);
            }
            const text = document.createElement('span');
            text.className = 'test-place-popover__link-text';
            text.textContent = label;
            link.appendChild(text);
            list.appendChild(link);
        });
        body.appendChild(list);
    } else {
        const addUrl = buildAddBurialUrl(payload);
        const text = document.createElement('p');
        text.className = 'test-place-popover__text';
        text.textContent = 'Тут ще немає поховань. Можна одразу відкрити форму з уже підставленими координатами.';
        body.appendChild(text);

        const action = document.createElement('a');
        action.className = 'test-place-popover__action';
        action.href = addUrl;
        action.textContent = 'Додати поховання';
        body.appendChild(action);
    }
    updatePlacePopoverPosition();
}

function renderQuarter() {
    hidePlacePopover();
    quarterGrid.innerHTML = '';
    resetSummary();

    if (currentPayload && currentPayload.cemetery) {
        summaryCemetery.textContent = currentPayload.cemetery.title || '-';
    }

    const quarters = getQuarterList();
    if (quarters.length === 0) {
        renderQuarterMap();
        quarterTitle.textContent = 'Карта місць недоступна';
        syncUrlState();
        return;
    }

    const quarter = getSelectedQuarter();
    if (!quarter) {
        renderQuarterMap();
        quarterTitle.textContent = 'Карта місць недоступна';
        syncUrlState();
        return;
    }

    summaryCemetery.textContent = currentPayload.cemetery ? currentPayload.cemetery.title : '-';
    summaryQuarter.textContent = 'Квартал ' + quarter.key;
    summaryRows.textContent = String(quarter.rows);
    summaryPlaces.textContent = String(quarter.rows * quarter.places_per_row);
    summaryBurials.textContent = String(quarter.burial_count);
    quarterTitle.textContent = (currentPayload.cemetery ? currentPayload.cemetery.title : 'Кладовище') + ' • квартал ' + quarter.key;

    const head = document.createElement('section');
    head.className = 'test-grid-head';

    const corner = document.createElement('div');
    corner.className = 'test-grid-head__corner';
    corner.textContent = 'Ряди';
    head.appendChild(corner);

    const placeHead = document.createElement('div');
    placeHead.className = 'test-grid-head__places';
    placeHead.style.setProperty('--places-per-row', String(quarter.places_per_row));

    for (let placeNumber = 1; placeNumber <= quarter.places_per_row; placeNumber += 1) {
        const placeLabel = document.createElement('div');
        placeLabel.className = 'test-grid-head__place';
        placeLabel.textContent = 'Місце ' + placeNumber;
        placeHead.appendChild(placeLabel);
    }

    head.appendChild(placeHead);
    quarterGrid.appendChild(head);

    const cells = quarter.cells || {};
    for (let rowNumber = 1; rowNumber <= quarter.rows; rowNumber += 1) {
        const row = document.createElement('section');
        row.className = 'test-row';

        const rowHeader = document.createElement('div');
        rowHeader.className = 'test-row__label';
        rowHeader.textContent = 'Ряд ' + rowNumber;
        row.appendChild(rowHeader);

        const places = document.createElement('div');
        places.className = 'test-row__places';
        places.style.setProperty('--places-per-row', String(quarter.places_per_row));

        for (let placeNumber = 1; placeNumber <= quarter.places_per_row; placeNumber += 1) {
            const cellKey = rowNumber + '-' + placeNumber;
            places.appendChild(createPlaceCell(rowNumber, placeNumber, cells[cellKey] || null));
        }

        row.appendChild(places);
        quarterGrid.appendChild(row);
    }

    renderQuarterMap();
    syncUrlState();
}

async function loadCemeteryMap(cemeteryId, preferredQuarterKey = '') {
    if (!cemeteryId) {
        currentPayload = null;
        quarterSelect.disabled = true;
        quarterSelect.innerHTML = '<option value="">Спочатку оберіть кладовище</option>';
        setPickerVisible(true);
        setMapSectionsVisibility(false);
        clearMapView();
        resetSummary();
        syncUrlState();
        return;
    }

    setMapSectionsVisibility(false);
    clearMapView();
    resetSummary();
    setPickerVisible(false);

    try {
        const payload = await fetchMapPayload(cemeteryId);

        currentPayload = payload;

        if (!payload || payload.status !== 'ok') {
            quarterSelect.disabled = true;
            quarterSelect.innerHTML = '<option value="">Немає даних</option>';
            setPickerVisible(true);
            syncUrlState();
            return;
        }

        if (!hasRenderableMapPayload(payload)) {
            quarterSelect.disabled = true;
            quarterSelect.innerHTML = '<option value="">Немає кварталів</option>';
            setPickerVisible(true);
            syncUrlState();
            return;
        }

        const selectedQuarter = renderQuarterOptions(payload, preferredQuarterKey);
        if (selectedQuarter) {
            quarterSelect.value = selectedQuarter;
        }
        setMapSectionsVisibility(true);
        renderQuarter();

    } catch (error) {
        currentPayload = null;
        quarterSelect.disabled = true;
        quarterSelect.innerHTML = '<option value="">Помилка завантаження</option>';
        setPickerVisible(true);
        setMapSectionsVisibility(false);
        console.error(error);
        syncUrlState();
    }
}

if (cemeteryPickerForm) {
    cemeteryPickerForm.addEventListener('submit', (event) => {
        event.preventDefault();
        hidePlacePopover();
        const cemeteryId = String(cemeterySelect.value || '').trim();
        if (!cemeteryId) return;
        loadCemeteryMap(cemeteryId);
    });
}

if (cemeterySelect && cemeteryPickerSubmit) {
    cemeterySelect.addEventListener('change', () => {
        cemeteryPickerSubmit.disabled = !String(cemeterySelect.value || '').trim();
    });
}

if (regionSelect) {
    regionSelect.addEventListener('change', () => {
        const regionId = String(regionSelect.value || '').trim();
        if (!regionId) {
            resetDistrict();
            resetCemetery();
            return;
        }
        loadDistricts(regionId);
    });
}

if (districtSelect) {
    districtSelect.addEventListener('change', () => {
        const districtId = String(districtSelect.value || '').trim();
        if (!districtId) {
            resetCemetery();
            return;
        }
        loadCemeteriesByDistrict(districtId);
    });
}

if (changeCemeteryBtn) {
    changeCemeteryBtn.addEventListener('click', () => {
        hidePlacePopover();
        setPickerVisible(true);
        cemeteryPickerSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
}

quarterSelect.addEventListener('change', () => {
    hidePlacePopover();
    renderQuarter();
});

quarterMapGrid.addEventListener('click', (event) => {
    const tile = event.target.closest('.test-quarter-tile');
    if (!tile) {
        return;
    }

    const quarterKey = tile.dataset.quarterKey || '';
    if (quarterKey === '') {
        return;
    }

    quarterSelect.value = quarterKey;
    renderQuarter();
});

quarterGrid.addEventListener('click', (event) => {
    const trigger = event.target.closest('.test-place--interactive');
    if (!trigger) {
        return;
    }

    const payload = trigger._placePayload || null;
    if (!payload) {
        return;
    }

    if (activePlaceTrigger === trigger && !placePopover.hidden) {
        hidePlacePopover();
        return;
    }

    showPlacePopover(trigger, payload);
});

if (placePopoverClose) {
    placePopoverClose.addEventListener('click', hidePlacePopover);
}

if (placePopover) {
    placePopover.addEventListener('click', (event) => {
        if (event.target === placePopover) {
            hidePlacePopover();
        }
    });
}

document.addEventListener('click', (event) => {
    if (placePopover.hidden) {
        return;
    }

    const clickedInsidePopover = placePopover.contains(event.target);
    const clickedTrigger = activePlaceTrigger && activePlaceTrigger.contains(event.target);
    if (!clickedInsidePopover && !clickedTrigger) {
        hidePlacePopover();
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        hidePlacePopover();
    }
});

window.addEventListener('resize', () => {
    if (!placePopover.hidden) {
        updatePlacePopoverPosition();
    }
});

window.addEventListener('scroll', () => {
    if (!placePopover.hidden) {
        updatePlacePopoverPosition();
    }
}, true);

initializeCemeteryFilters();

document.addEventListener('click', (event) => {
    if (!event.target.closest('.custom-select-wrapper')) {
        closeAllCustomSelects();
    }
});

if (initialCemeteryId > 0) {
    setPickerVisible(false);
    loadCemeteryMap(String(initialCemeteryId), initialQuarterKey);
} else {
    setPickerVisible(true);
    if (cemeteryPickerSubmit) {
        cemeteryPickerSubmit.disabled = !String(cemeterySelect.value || '').trim();
    }
    setMapSectionsVisibility(false);
}

function syncStepStates() {
    const steps = document.querySelectorAll('.test-map-picker__step');
    const regionVal = regionSelect ? String(regionSelect.value || '').trim() : '';
    const districtVal = districtSelect ? String(districtSelect.value || '').trim() : '';
    const cemeteryVal = cemeterySelect ? String(cemeterySelect.value || '').trim() : '';

    steps.forEach(function(step) {
        const stepNum = step.dataset.step;
        if (stepNum === '1') {
            step.dataset.state = regionVal ? 'completed' : 'active';
        } else if (stepNum === '2') {
            if (!regionVal) {
                step.dataset.state = 'waiting';
            } else if (districtVal) {
                step.dataset.state = 'completed';
            } else {
                step.dataset.state = 'active';
            }
        } else if (stepNum === '3') {
            if (!districtVal) {
                step.dataset.state = 'waiting';
            } else if (cemeteryVal) {
                step.dataset.state = 'completed';
            } else {
                step.dataset.state = 'active';
            }
        }
    });
}

if (regionSelect) regionSelect.addEventListener('change', syncStepStates);
if (districtSelect) districtSelect.addEventListener('change', syncStepStates);
if (cemeterySelect) cemeterySelect.addEventListener('change', syncStepStates);
syncStepStates();
</script>
<?php
echo Page_Down();
