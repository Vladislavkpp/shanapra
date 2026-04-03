<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/function.php';

function testMapEsc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
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
    $birth = trim((string)($grave['dt1'] ?? ''));
    $death = trim((string)($grave['dt2'] ?? ''));

    $birthYear = ($birth !== '' && $birth !== '0000-00-00') ? substr($birth, 0, 4) : '';
    $deathYear = ($death !== '' && $death !== '0000-00-00') ? substr($death, 0, 4) : '';

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

    $cemeteryRes = mysqli_query(
        $dblink,
        "SELECT idx, title, adress
         FROM cemetery
         WHERE idx = {$cemeteryId}
           AND LOWER(COALESCE(moderation_status, 'pending')) <> 'rejected'
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
    ];

    if (!dbTableExists($dblink, 'grave')) {
        $payload['message'] = 'У поточній базі даних ще немає таблиці поховань, тому схема кварталів поки недоступна.';
        return $payload;
    }

    $payload['grave_data_available'] = true;

    $graveRes = mysqli_query(
        $dblink,
        "SELECT idx, lname, fname, mname, dt1, dt2, pos1, pos2, pos3, photo1
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
$initialCemeteryId = $cemeteries[0]['id'] ?? 0;
mysqli_close($dblink);

$pageUp = Page_Up('Карта кладовища');
$pageUp = str_replace('</head>', '    <link rel="stylesheet" href="/test.css">' . xbr . '</head>', $pageUp);
echo $pageUp;
echo Menu_Up();
?>
<main class="test-map-page">
    <div class="test-map-shell">
        <section class="test-map-controls">
            <label class="test-map-field">
                <span>Кладовище</span>
                <select id="cemetery-select" class="test-map-select"<?= $initialCemeteryId <= 0 ? ' disabled' : '' ?>>
                    <?php if ($cemeteries): ?>
                        <?php foreach ($cemeteries as $cemetery): ?>
                            <option value="<?= (int)$cemetery['id'] ?>">
                                <?= testMapEsc($cemetery['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="">Немає доступних кладовищ</option>
                    <?php endif; ?>
                </select>
            </label>

            <label class="test-map-field">
                <span>Квартал</span>
                <select id="quarter-select" class="test-map-select" disabled>
                    <option value="">Спочатку оберіть кладовище</option>
                </select>
            </label>
        </section>

        <div id="map-status" class="test-map-status" aria-live="polite">
            Завантаження даних карти...
        </div>

        <section class="test-map-summary">
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

        <section class="test-quarter-card">
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
                <div id="quarter-map-grid" class="test-quarter-map" aria-live="polite"></div>
            </div>
        </section>

        <section class="test-quarter-card">
            <div class="test-quarter-card__header">
                <div>
                    <span class="test-map-section-label">Рівень 2</span>
                    <h2 id="quarter-title">Оберіть квартал</h2>
                    <p class="test-map-note">Схема показує лише координати, уже внесені на сайт. Якщо реальних місць більше, вони з'являться після заповнення даних.</p>
                </div>
                <div class="test-quarter-legend">
                    <span class="test-quarter-legend__item">
                        <i class="test-quarter-legend__dot test-quarter-legend__dot--empty"></i>
                        Вільне місце
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

<script>
const initialCemeteryId = <?= (int)$initialCemeteryId ?>;
const cemeterySelect = document.getElementById('cemetery-select');
const quarterSelect = document.getElementById('quarter-select');
const mapStatus = document.getElementById('map-status');
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

let currentPayload = null;

function setStatus(message, state = '') {
    mapStatus.textContent = message;
    mapStatus.className = 'test-map-status' + (state ? ' is-' + state : '');
}

function resetSummary() {
    summaryCemetery.textContent = '-';
    summaryQuarter.textContent = '-';
    summaryRows.textContent = '-';
    summaryPlaces.textContent = '-';
    summaryBurials.textContent = '-';
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
    const root = firstBurial && firstBurial.url ? document.createElement('a') : document.createElement('div');
    root.className = 'test-place' + (firstBurial ? ' test-place--occupied' : '');

    if (firstBurial && firstBurial.url) {
        root.href = firstBurial.url;
    }

    if (!firstBurial) {
        root.appendChild(createEmptyPlaceIcon());

        const state = document.createElement('div');
        state.className = 'test-place__state';
        state.textContent = 'Вільно';
        root.appendChild(state);

        root.setAttribute('aria-label', 'Ряд ' + rowNumber + ', місце ' + placeNumber + ', вільно');
        return root;
    }

    const names = burials.map((burial) => burial.short_name || burial.name).filter(Boolean);
    root.title = names.join(', ');
    root.setAttribute('aria-label', 'Ряд ' + rowNumber + ', місце ' + placeNumber + '. ' + names.join(', '));

    if (firstBurial.photo) {
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

    root.appendChild(info);
    return root;
}

function renderQuarter() {
    quarterGrid.innerHTML = '';
    resetSummary();

    const quarters = getQuarterList();
    if (quarters.length === 0) {
        renderQuarterMap();
        quarterTitle.textContent = 'Карта місць недоступна';
        return;
    }

    const quarter = getSelectedQuarter();
    if (!quarter) {
        renderQuarterMap();
        quarterTitle.textContent = 'Карта місць недоступна';
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
}

async function loadCemeteryMap(cemeteryId, preferredQuarterKey = '') {
    if (!cemeteryId) {
        currentPayload = null;
        quarterSelect.disabled = true;
        quarterSelect.innerHTML = '<option value="">Спочатку оберіть кладовище</option>';
        clearMapView();
        resetSummary();
        setStatus('Оберіть кладовище, щоб переглянути квартали.');
        return;
    }

    setStatus('Завантаження карти кладовища...');

    try {
        const response = await fetch('/test.php?action=map_data&cemetery_id=' + encodeURIComponent(cemeteryId), {
            credentials: 'same-origin'
        });
        const payload = await response.json();

        currentPayload = payload;

        if (!payload || payload.status !== 'ok') {
            quarterSelect.disabled = true;
            quarterSelect.innerHTML = '<option value="">Немає даних</option>';
            clearMapView();
            quarterMapTitle.textContent = 'Дані недоступні';
            quarterTitle.textContent = 'Дані недоступні';
            resetSummary();
            setStatus(payload && payload.message ? payload.message : 'Не вдалося завантажити карту.', 'error');
            return;
        }

        const selectedQuarter = renderQuarterOptions(payload, preferredQuarterKey);
        if (selectedQuarter) {
            quarterSelect.value = selectedQuarter;
        }
        renderQuarter();

        if (payload.message) {
            setStatus(payload.message, payload.quarters.length > 0 ? '' : 'warning');
        } else if (payload.cemetery) {
            setStatus('Показано схему за даними, вже внесеними для кладовища "' + payload.cemetery.title + '".', 'success');
        } else {
            setStatus('Дані карти завантажено.', 'success');
        }

    } catch (error) {
        currentPayload = null;
        quarterSelect.disabled = true;
        quarterSelect.innerHTML = '<option value="">Помилка завантаження</option>';
        clearMapView();
        quarterMapTitle.textContent = 'Дані недоступні';
        quarterTitle.textContent = 'Дані недоступні';
        resetSummary();
        setStatus('Сталася помилка під час завантаження карти.', 'error');
    }
}

cemeterySelect.addEventListener('change', () => {
    loadCemeteryMap(cemeterySelect.value);
});

quarterSelect.addEventListener('change', renderQuarter);

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

if (initialCemeteryId > 0) {
    loadCemeteryMap(String(initialCemeteryId));
} else {
    setStatus('У системі немає доступних кладовищ для відображення.', 'warning');
}
</script>
<?php
echo Page_Down();
