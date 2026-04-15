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
    'idxkladb' => '',
    'lname' => '',
    'fname' => '',
    'mname' => '',
    'dt1' => '',
    'dt2' => '',
    'dt1_year' => '',
    'dt1_month' => '',
    'dt1_day' => '',
    'dt2_year' => '',
    'dt2_month' => '',
    'dt2_day' => '',
    'dt1_unknown' => '0',
    'dt2_unknown' => '0',
    'pos1_unknown' => '0',
    'pos2_unknown' => '0',
    'pos3_unknown' => '0',
    'pos1' => '',
    'pos2' => '',
    'pos3' => '',
];

function graveAddPrefillDigits(?string $value): string
{
    $digits = preg_replace('/\D+/u', '', trim((string)$value));
    if ($digits === null || $digits === '') {
        return '';
    }

    $normalized = ltrim($digits, '0');
    return $normalized !== '' ? $normalized : '';
}

function graveAddApplyQueryPrefill(array &$formData): bool
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        return false;
    }

    $cemeteryId = (int)($_GET['cemetery_id'] ?? ($_GET['idxkladb'] ?? 0));
    $pos1 = graveAddPrefillDigits((string)($_GET['pos1'] ?? ($_GET['quarter'] ?? '')));
    $pos2 = graveAddPrefillDigits((string)($_GET['pos2'] ?? ($_GET['row'] ?? '')));
    $pos3 = graveAddPrefillDigits((string)($_GET['pos3'] ?? ($_GET['place'] ?? '')));

    $hasCoords = ($pos1 !== '' || $pos2 !== '' || $pos3 !== '');
    if ($cemeteryId <= 0 && !$hasCoords) {
        return false;
    }

    if ($cemeteryId > 0) {
        $dblink = DbConnect();
        $select = [
            'c.idx',
            'c.district',
            'c.town',
        ];
        $join = [];

        if (dbTableExists($dblink, 'district')) {
            $select[] = 'd.region';
            $join[] = 'LEFT JOIN district d ON c.district = d.idx';
        } else {
            $select[] = "'' AS region";
        }

        $res = mysqli_query(
            $dblink,
            'SELECT ' . implode(', ', $select) . '
             FROM cemetery c
             ' . implode("\n", $join) . '
             WHERE c.idx = ' . $cemeteryId . '
             LIMIT 1'
        );
        $cemetery = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_close($dblink);

        if ($cemetery) {
            $formData['region'] = (string)($cemetery['region'] ?? '');
            $formData['district'] = (string)($cemetery['district'] ?? '');
            $formData['town'] = (string)($cemetery['town'] ?? '');
            $formData['idxkladb'] = (string)($cemetery['idx'] ?? '');
        }
    }

    if ($pos1 !== '') {
        $formData['pos1_unknown'] = '0';
        $formData['pos1'] = $pos1;
    }
    if ($pos2 !== '') {
        $formData['pos2_unknown'] = '0';
        $formData['pos2'] = $pos2;
    }
    if ($pos3 !== '') {
        $formData['pos3_unknown'] = '0';
        $formData['pos3'] = $pos3;
    }

    return $cemeteryId > 0 || $hasCoords;
}

$hasQueryPrefill = graveAddApplyQueryPrefill($formData);

if (isset($_GET['ajax_districts']) && !empty($_GET['region_id'])) {
    echo getDistricts((int)$_GET['region_id']);
    exit;
}

if (isset($_GET['ajax_settlements']) && !empty($_GET['region_id']) && !empty($_GET['district_id'])) {
    echo getSettlements((int)$_GET['region_id'], (int)$_GET['district_id']);
    exit;
}

if (isset($_GET['ajax_cemeteries']) && !empty($_GET['district_id'])) {
    echo CemeterySelect((int)$_GET['district_id']);
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

if (
    $isAuthorized
    && $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['md'])
    && in_array((string)$_POST['md'], ['grave', 'graveaddform'], true)
) {
    $formData['region'] = trim((string)($_POST['region'] ?? ''));
    $formData['district'] = trim((string)($_POST['district'] ?? ''));
    $formData['town'] = trim((string)($_POST['town'] ?? ''));
    $formData['idxkladb'] = trim((string)($_POST['idxkladb'] ?? ''));
    $formData['lname'] = trim((string)($_POST['lname'] ?? ''));
    $formData['fname'] = trim((string)($_POST['fname'] ?? ''));
    $formData['mname'] = trim((string)($_POST['mname'] ?? ''));
    $formData['dt1'] = trim((string)($_POST['dt1'] ?? ''));
    $formData['dt2'] = trim((string)($_POST['dt2'] ?? ''));
    $formData['dt1_year'] = graveAddNormalizePartialDatePart((string)($_POST['dt1_year'] ?? ''), 4, 1, 9999);
    $formData['dt1_month'] = graveAddNormalizePartialDatePart((string)($_POST['dt1_month'] ?? ''), 2, 1, 12);
    $formData['dt1_day'] = graveAddNormalizePartialDatePart((string)($_POST['dt1_day'] ?? ''), 2, 1, 31);
    $formData['dt2_year'] = graveAddNormalizePartialDatePart((string)($_POST['dt2_year'] ?? ''), 4, 1, 9999);
    $formData['dt2_month'] = graveAddNormalizePartialDatePart((string)($_POST['dt2_month'] ?? ''), 2, 1, 12);
    $formData['dt2_day'] = graveAddNormalizePartialDatePart((string)($_POST['dt2_day'] ?? ''), 2, 1, 31);
    $formData['dt1_unknown'] = (string)($_POST['dt1_unknown'] ?? '0');
    $formData['dt2_unknown'] = (string)($_POST['dt2_unknown'] ?? '0');
    $formData['pos1_unknown'] = (string)($_POST['pos1_unknown'] ?? '0');
    $formData['pos2_unknown'] = (string)($_POST['pos2_unknown'] ?? '0');
    $formData['pos3_unknown'] = (string)($_POST['pos3_unknown'] ?? '0');
    $formData['pos1'] = trim((string)($_POST['pos1'] ?? ''));
    $formData['pos2'] = trim((string)($_POST['pos2'] ?? ''));
    $formData['pos3'] = trim((string)($_POST['pos3'] ?? ''));

    if ($formData['dt1'] !== '') {
        graveAddResetPartialDate($formData, 'dt1');
        $formData['dt1_unknown'] = '0';
    } elseif (graveAddHasPartialDate($formData, 'dt1')) {
        $formData['dt1_unknown'] = '0';
    } elseif ($formData['dt1_unknown'] === '1') {
        graveAddResetPartialDate($formData, 'dt1');
    }

    if ($formData['dt2'] !== '') {
        graveAddResetPartialDate($formData, 'dt2');
        $formData['dt2_unknown'] = '0';
    } elseif (graveAddHasPartialDate($formData, 'dt2')) {
        $formData['dt2_unknown'] = '0';
    } elseif ($formData['dt2_unknown'] === '1') {
        graveAddResetPartialDate($formData, 'dt2');
    }

    $dt1Unknown = $formData['dt1_unknown'] === '1';
    $dt2Unknown = $formData['dt2_unknown'] === '1';
    $dt1HasPartial = graveAddHasPartialDate($formData, 'dt1');
    $dt2HasPartial = graveAddHasPartialDate($formData, 'dt2');
    $pos1Unknown = $formData['pos1_unknown'] === '1';
    $pos2Unknown = $formData['pos2_unknown'] === '1';
    $pos3Unknown = $formData['pos3_unknown'] === '1';

    $requiredMissing = (
        (int)$formData['region'] <= 0
        || (int)$formData['district'] <= 0
        || (int)$formData['town'] <= 0
        || (int)$formData['idxkladb'] <= 0
        || $formData['lname'] === ''
        || $formData['fname'] === ''
        || (!$dt1Unknown && !$dt1HasPartial && $formData['dt1'] === '')
        || (!$dt2Unknown && !$dt2HasPartial && $formData['dt2'] === '')
        || (!$pos1Unknown && $formData['pos1'] === '')
        || (!$pos2Unknown && $formData['pos2'] === '')
        || (!$pos3Unknown && $formData['pos3'] === '')
    );

    if ($requiredMissing) {
        $showMessage = true;
        $messageType = 'error';
        $messageText = 'Заповніть обов`язкові поля на всіх етапах форми.';
    } else {
        $dblink = DbConnect();
        $stmt = mysqli_prepare(
            $dblink,
            'INSERT INTO grave (fname, lname, mname, dt1, dt1_year, dt1_month, dt1_day, dt2, dt2_year, dt2_month, dt2_day, idtadd, idxadd, idxkladb, pos1, pos2, pos3) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?)'
        );

        if (!$stmt) {
            $showMessage = true;
            $messageType = 'error';
            $messageText = 'Помилка підготовки запиту: ' . mysqli_error($dblink);
        } else {
            $fname = $formData['fname'];
            $lname = $formData['lname'];
            $mname = $formData['mname'];
            $dt1 = ($dt1Unknown || $dt1HasPartial) ? '0000-00-00' : $formData['dt1'];
            $dt1Year = $dt1HasPartial ? (int)($formData['dt1_year'] ?: 0) : 0;
            $dt1Month = $dt1HasPartial ? (int)($formData['dt1_month'] ?: 0) : 0;
            $dt1Day = $dt1HasPartial ? (int)($formData['dt1_day'] ?: 0) : 0;
            $dt2 = ($dt2Unknown || $dt2HasPartial) ? '0000-00-00' : $formData['dt2'];
            $dt2Year = $dt2HasPartial ? (int)($formData['dt2_year'] ?: 0) : 0;
            $dt2Month = $dt2HasPartial ? (int)($formData['dt2_month'] ?: 0) : 0;
            $dt2Day = $dt2HasPartial ? (int)($formData['dt2_day'] ?: 0) : 0;
            $authorId = (int)$_SESSION['uzver'];
            $cemeteryId = (int)$formData['idxkladb'];
            $pos1 = $pos1Unknown ? '' : $formData['pos1'];
            $pos2 = $pos2Unknown ? '' : $formData['pos2'];
            $pos3 = $pos3Unknown ? '' : $formData['pos3'];

            mysqli_stmt_bind_param(
                $stmt,
                'ssssiiisiiiiisss',
                $fname,
                $lname,
                $mname,
                $dt1,
                $dt1Year,
                $dt1Month,
                $dt1Day,
                $dt2,
                $dt2Year,
                $dt2Month,
                $dt2Day,
                $authorId,
                $cemeteryId,
                $pos1,
                $pos2,
                $pos3
            );
            $saved = mysqli_stmt_execute($stmt);

            if (!$saved) {
                $showMessage = true;
                $messageType = 'error';
                $messageText = 'Помилка збереження: ' . mysqli_error($dblink);
            } else {
                $newId = (int)mysqli_insert_id($dblink);
                graveAddStorePosUnknownFlags($newId, [
                    'pos1_unknown' => $formData['pos1_unknown'],
                    'pos2_unknown' => $formData['pos2_unknown'],
                    'pos3_unknown' => $formData['pos3_unknown'],
                ]);
                $uploadDir = __DIR__ . '/graves/' . $newId;
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $uploaded = [];
                foreach (['photo1', 'photo2'] as $field) {
                    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
                        continue;
                    }

                    $ext = strtolower((string)pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
                    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif'], true)) {
                        continue;
                    }

                    $safeName = $field . '.' . $ext;
                    $targetPath = $uploadDir . '/' . $safeName;
                    $ok = gravecompress($_FILES[$field]['tmp_name'], $targetPath);

                    if ($ok && file_exists($targetPath)) {
                        $uploaded[$field] = '/graves/' . $newId . '/' . $safeName;
                    }
                }

                if (!empty($uploaded)) {
                    $updates = [];
                    foreach ($uploaded as $column => $path) {
                        $updates[] = $column . "='" . mysqli_real_escape_string($dblink, $path) . "'";
                    }
                    mysqli_query($dblink, 'UPDATE grave SET ' . implode(', ', $updates) . ' WHERE idx=' . $newId);
                }

                $showMessage = true;
                $messageType = 'success';
                $messageText = 'Запис про поховання додано успішно!';
                $formData = [
                    'region' => '',
                    'district' => '',
                    'town' => '',
                    'idxkladb' => '',
                    'lname' => '',
                    'fname' => '',
                    'mname' => '',
                    'dt1' => '',
                    'dt2' => '',
                    'dt1_year' => '',
                    'dt1_month' => '',
                    'dt1_day' => '',
                    'dt2_year' => '',
                    'dt2_month' => '',
                    'dt2_day' => '',
                    'dt1_unknown' => '0',
                    'dt2_unknown' => '0',
                    'pos1_unknown' => '0',
                    'pos2_unknown' => '0',
                    'pos3_unknown' => '0',
                    'pos1' => '',
                    'pos2' => '',
                    'pos3' => '',
                ];
            }
            mysqli_stmt_close($stmt);
        }
        mysqli_close($dblink);
    }
}

function graveAddEsc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function graveAddNormalizePartialDatePart(string $value, int $maxLength, int $min, int $max): string
{
    $digits = preg_replace('/\D+/u', '', trim($value));
    if ($digits === null || $digits === '') {
        return '';
    }

    if (strlen($digits) > $maxLength) {
        $digits = substr($digits, 0, $maxLength);
    }

    $number = (int)$digits;
    if ($number < $min || $number > $max) {
        return '';
    }

    return (string)$number;
}

function graveAddHasPartialDate(array $formData, string $prefix): bool
{
    foreach (['day', 'month', 'year'] as $part) {
        if (trim((string)($formData[$prefix . '_' . $part] ?? '')) !== '') {
            return true;
        }
    }

    return false;
}

function graveAddResetPartialDate(array &$formData, string $prefix): void
{
    foreach (['day', 'month', 'year'] as $part) {
        $formData[$prefix . '_' . $part] = '';
    }
}

function graveAddBuildPartialDateMask(array $formData, string $prefix): string
{
    $segments = [];
    foreach (['day', 'month', 'year'] as $part) {
        $value = trim((string)($formData[$prefix . '_' . $part] ?? ''));
        if ($value === '') {
            $segments[] = '??';
            continue;
        }

        if ($part === 'year') {
            $segments[] = str_pad($value, 4, '0', STR_PAD_LEFT);
            continue;
        }

        $segments[] = str_pad($value, 2, '0', STR_PAD_LEFT);
    }

    return implode('.', $segments);
}

function graveAddStorePosUnknownFlags(int $graveId, array $flags): void
{
    if ($graveId <= 0) {
        return;
    }

    $normalized = [
        'pos1_unknown' => (($flags['pos1_unknown'] ?? '0') === '1') ? '1' : '0',
        'pos2_unknown' => (($flags['pos2_unknown'] ?? '0') === '1') ? '1' : '0',
        'pos3_unknown' => (($flags['pos3_unknown'] ?? '0') === '1') ? '1' : '0',
    ];

    $hasUnknown = in_array('1', $normalized, true);
    if (!isset($_SESSION['grave_pos_unknown_flags']) || !is_array($_SESSION['grave_pos_unknown_flags'])) {
        $_SESSION['grave_pos_unknown_flags'] = [];
    }

    if ($hasUnknown) {
        $_SESSION['grave_pos_unknown_flags'][$graveId] = $normalized;
        return;
    }

    unset($_SESSION['grave_pos_unknown_flags'][$graveId]);
}

function graveAddRegionOptions(string $selectedValue): string
{
    $dblink = DbConnect();
    $res = mysqli_query($dblink, 'SELECT idx, title FROM region ORDER BY title');
    $out = '<option value="">Оберіть область</option>';

    while ($res && $row = mysqli_fetch_assoc($res)) {
        $value = (string)$row['idx'];
        $selected = ($value === $selectedValue) ? ' selected' : '';
        $out .= '<option value="' . graveAddEsc($value) . '"' . $selected . '>' . graveAddEsc($row['title']) . '</option>';
    }

    mysqli_close($dblink);
    return $out;
}

function graveAddDistrictOptions(string $regionValue, string $selectedValue): string
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
        $out .= '<option value="' . graveAddEsc($value) . '"' . $selected . '>' . graveAddEsc($row['title']) . '</option>';
    }

    mysqli_close($dblink);
    return $out;
}

function graveAddSettlementOptions(string $regionValue, string $districtValue, string $selectedValue): string
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
        $out .= '<option value="' . graveAddEsc($value) . '"' . $selected . '>' . graveAddEsc($row['title']) . '</option>';
    }

    mysqli_close($dblink);
    return $out;
}

function graveAddCemeteryOptions(string $districtValue, string $selectedValue): string
{
    if ((int)$districtValue <= 0) {
        return '<option value="">Оберіть район</option>';
    }

    $dblink = DbConnect();
    $districtId = (int)$districtValue;
    $res = mysqli_query($dblink, 'SELECT idx, title FROM cemetery WHERE district = ' . $districtId . ' ORDER BY title');
    $out = '<option value="">Оберіть кладовище</option>';

    while ($res && $row = mysqli_fetch_assoc($res)) {
        $value = (string)$row['idx'];
        $selected = ($value === $selectedValue) ? ' selected' : '';
        $out .= '<option value="' . graveAddEsc($value) . '"' . $selected . '>' . graveAddEsc($row['title']) . '</option>';
    }

    mysqli_close($dblink);
    return $out;
}

View_Clear();
View_Add(Page_Up('Нове поховання'));
View_Add(Menu_Up());

if (!$isAuthorized) {
    View_Add('<link rel="stylesheet" href="/assets/css/in-dev.css">');
    View_Add('<div class="out-index out-index--404">');
    View_Add(AuthRequired_Content('/auth.php', 'Увійти'));
    View_Add('</div>');
} else {
    $safeRegion = graveAddEsc($formData['region']);
    $safeDistrict = graveAddEsc($formData['district']);
    $safeTown = graveAddEsc($formData['town']);
    $safeCemetery = graveAddEsc($formData['idxkladb']);
    $safeLname = graveAddEsc($formData['lname']);
    $safeFname = graveAddEsc($formData['fname']);
    $safeMname = graveAddEsc($formData['mname']);
    $dt1Unknown = ($formData['dt1_unknown'] ?? '0') === '1';
    $dt2Unknown = ($formData['dt2_unknown'] ?? '0') === '1';
    $dt1HasPartial = graveAddHasPartialDate($formData, 'dt1');
    $dt2HasPartial = graveAddHasPartialDate($formData, 'dt2');
    $pos1Unknown = ($formData['pos1_unknown'] ?? '0') === '1';
    $pos2Unknown = ($formData['pos2_unknown'] ?? '0') === '1';
    $pos3Unknown = ($formData['pos3_unknown'] ?? '0') === '1';
    $safeDt1 = ($dt1Unknown || $dt1HasPartial) ? '' : graveAddEsc($formData['dt1']);
    $safeDt2 = ($dt2Unknown || $dt2HasPartial) ? '' : graveAddEsc($formData['dt2']);
    $safeDt1Mask = $dt1HasPartial ? graveAddEsc(graveAddBuildPartialDateMask($formData, 'dt1')) : '';
    $safeDt2Mask = $dt2HasPartial ? graveAddEsc(graveAddBuildPartialDateMask($formData, 'dt2')) : '';
    $safePos1 = $pos1Unknown ? '' : graveAddEsc($formData['pos1']);
    $safePos2 = $pos2Unknown ? '' : graveAddEsc($formData['pos2']);
    $safePos3 = $pos3Unknown ? '' : graveAddEsc($formData['pos3']);

    $alertHtml = '';
    if ($showMessage) {
        $alertClass = $messageType === 'success' ? 'acm-alert--success' : 'acm-alert--error';
        $alertHtml = '<div class="acm-alert ' . $alertClass . '">' . graveAddEsc($messageText) . '</div>';
    }

    $graveCssVersion = (int)@filemtime(__DIR__ . '/assets/css/grave.css');
    if ($graveCssVersion <= 0) {
        $graveCssVersion = time();
    }
    View_Add('<link rel="stylesheet" href="/assets/css/grave.css?v=' . $graveCssVersion . '">');

    ob_start();
    ?>
<div class="out acm-out">
    <main class="acm-page">
        <section class="acm-layout">
            <aside class="acm-aside">
                <div class="acm-badge">Форма додавання поховання</div>
                <h1 class="acm-heading">Додайте новий запис про поховання</h1>
                <p>Форма заповнюється поетапно, щоб дані були повними та узгодженими з каталогом кладовищ.</p>
                <ul class="acm-tips">
                    <li>Крок 1: персональні дані померлого.</li>
                    <li>Крок 2: місце поховання та позиція на кладовищі.</li>
                    <li>Крок 3: додайте фото (за потреби) та збережіть запис.</li>
                </ul>
            </aside>

            <section class="acm-form-card">
                <h2 class="acm-form-title">Додати поховання</h2>
                <p class="acm-form-subtitle">Заповніть усі етапи і натисніть «Додати запис».</p>
                <?= $alertHtml ?>

                <form id="graveaddform" class="acm-form" action="/graveaddform.php" method="post" enctype="multipart/form-data" novalidate autocomplete="off" data-submit-success="<?= ($showMessage && $messageType === 'success') ? '1' : '0' ?>">
                    <input type="hidden" name="md" value="graveaddform">

                    <div class="agf-stepper">
                        <div class="agf-step active" data-step="1">
                            <span class="agf-step-number">1</span>
                            <span class="agf-step-label">Особисті дані</span>
                        </div>
                        <div class="agf-step" data-step="2">
                            <span class="agf-step-number">2</span>
                            <span class="agf-step-label">Поховання</span>
                        </div>
                        <div class="agf-step" data-step="3">
                            <span class="agf-step-number">3</span>
                            <span class="agf-step-label">Фото</span>
                        </div>
                    </div>

                    <div class="form-step active" data-step="1">
                        <fieldset class="acm-section">
                            <p class="acm-section-title">Персональні дані</p>
                            <div class="acm-row acm-row--three">
                                <div class="acm-field">
                                    <label for="agf-lname">Прізвище *</label>
                                    <input id="agf-lname" type="text" name="lname" value="<?= $safeLname ?>" required>
                                </div>
                                <div class="acm-field">
                                    <label for="agf-fname">Ім`я *</label>
                                    <input id="agf-fname" type="text" name="fname" value="<?= $safeFname ?>" required>
                                </div>
                                <div class="acm-field">
                                    <label for="agf-mname">По батькові</label>
                                    <input id="agf-mname" type="text" name="mname" value="<?= $safeMname ?>">
                                </div>
                            </div>
                            <div class="acm-row acm-row--two">
                                <div class="acm-field">
                                    <label for="agf-dt1">Дата народження *</label>
                                    <div class="agf-date-field-stack">
                                        <input id="agf-dt1" type="date" name="dt1" value="<?= $safeDt1 ?>" placeholder="дд.мм.рррр"<?= ($dt1Unknown || $dt1HasPartial) ? '' : ' required' ?><?= ($dt1Unknown || $dt1HasPartial) ? ' disabled' : '' ?><?= $dt1HasPartial ? ' class="agf-date-input is-hidden"' : ' class="agf-date-input"' ?>>
                                        <div id="agf-dt1-display-shell" class="agf-partial-date-shell<?= $dt1HasPartial ? '' : ' is-hidden' ?>">
                                            <input id="agf-dt1-display" type="text" value="<?= $safeDt1Mask ?>" class="agf-partial-date-display" placeholder="дд.мм.рррр" readonly tabindex="-1" aria-label="Часткова дата народження">
                                            <button type="button" class="agf-partial-date-clear" data-clear-partial-date="dt1" aria-label="Очистити часткову дату народження">×</button>
                                        </div>
                                    </div>
                                    <input type="hidden" id="agf-dt1-year" name="dt1_year" value="<?= graveAddEsc($formData['dt1_year']) ?>">
                                    <input type="hidden" id="agf-dt1-month" name="dt1_month" value="<?= graveAddEsc($formData['dt1_month']) ?>">
                                    <input type="hidden" id="agf-dt1-day" name="dt1_day" value="<?= graveAddEsc($formData['dt1_day']) ?>">
                                    <input type="hidden" id="agf-dt1-unknown" name="dt1_unknown" value="<?= $dt1Unknown ? '1' : '0' ?>">
                                    <button type="button" class="agf-unknown-btn agf-date-toggle-btn<?= $dt1Unknown ? ' is-active' : '' ?>" data-date-unknown="agf-dt1" data-unknown-input="agf-dt1-unknown" data-label-off="Позначити дату - невідомо" data-label-on="Вказати дату"<?= $dt1HasPartial ? ' disabled' : '' ?>>
                                        <?= $dt1Unknown ? 'Вказати дату' : 'Позначити дату - невідомо' ?>
                                    </button>
                                </div>
                                <div class="acm-field">
                                    <label for="agf-dt2">Дата смерті *</label>
                                    <div class="agf-date-field-stack">
                                        <input id="agf-dt2" type="date" name="dt2" value="<?= $safeDt2 ?>" placeholder="дд.мм.рррр"<?= ($dt2Unknown || $dt2HasPartial) ? '' : ' required' ?><?= ($dt2Unknown || $dt2HasPartial) ? ' disabled' : '' ?><?= $dt2HasPartial ? ' class="agf-date-input is-hidden"' : ' class="agf-date-input"' ?>>
                                        <div id="agf-dt2-display-shell" class="agf-partial-date-shell<?= $dt2HasPartial ? '' : ' is-hidden' ?>">
                                            <input id="agf-dt2-display" type="text" value="<?= $safeDt2Mask ?>" class="agf-partial-date-display" placeholder="дд.мм.рррр" readonly tabindex="-1" aria-label="Часткова дата смерті">
                                            <button type="button" class="agf-partial-date-clear" data-clear-partial-date="dt2" aria-label="Очистити часткову дату смерті">×</button>
                                        </div>
                                    </div>
                                    <input type="hidden" id="agf-dt2-year" name="dt2_year" value="<?= graveAddEsc($formData['dt2_year']) ?>">
                                    <input type="hidden" id="agf-dt2-month" name="dt2_month" value="<?= graveAddEsc($formData['dt2_month']) ?>">
                                    <input type="hidden" id="agf-dt2-day" name="dt2_day" value="<?= graveAddEsc($formData['dt2_day']) ?>">
                                    <input type="hidden" id="agf-dt2-unknown" name="dt2_unknown" value="<?= $dt2Unknown ? '1' : '0' ?>">
                                    <button type="button" class="agf-unknown-btn agf-date-toggle-btn<?= $dt2Unknown ? ' is-active' : '' ?>" data-date-unknown="agf-dt2" data-unknown-input="agf-dt2-unknown" data-label-off="Позначити дату - невідомо" data-label-on="Вказати дату"<?= $dt2HasPartial ? ' disabled' : '' ?>>
                                        <?= $dt2Unknown ? 'Вказати дату' : 'Позначити дату - невідомо' ?>
                                    </button>
                                </div>
                            </div>
                            <div class="agf-partial-trigger-row">
                                <button type="button" id="agf-open-partial-dates" class="agf-partial-trigger-card">
                                    <span class="agf-partial-trigger-icon" aria-hidden="true">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor" class="icon icon-tabler icons-tabler-filled icon-tabler-click"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M7 12a1 1 0 0 1 -1 1h-3a1 1 0 0 1 0 -2h3a1 1 0 0 1 1 1m6 -9v3a1 1 0 0 1 -2 0v-3a1 1 0 0 1 2 0m-6.693 1.893l2.2 2.2a1 1 0 0 1 -1.414 1.414l-2.2 -2.2a1 1 0 0 1 1.414 -1.414m12.8 0a1 1 0 0 1 0 1.414l-2.2 2.2a1 1 0 0 1 -1.414 -1.414l2.2 -2.2a1 1 0 0 1 1.414 0m-10.6 10.6a1 1 0 0 1 0 1.414l-2.2 2.2a1 1 0 1 1 -1.414 -1.414l2.2 -2.2a1 1 0 0 1 1.414 0m3.42 -4.49l.049 -.003l.098 .003l.097 .012l.097 .022l9.048 3.014c.845 .282 .928 1.445 .131 1.843l-3.702 1.851l-1.85 3.702c-.399 .797 -1.562 .714 -1.844 -.13l-3.003 -9.011l-.033 -.135l-.012 -.097v-.148l.012 -.097l.022 -.097l.03 -.094l.04 -.09l.05 -.084l.086 -.117l.067 -.07l.037 -.034l.076 -.06l.081 -.052l.087 -.043l.103 -.04l.135 -.033z" /></svg>
                                    </span>
                                    <span class="agf-partial-trigger-copy">
                                        <strong>Вказати частичні дати</strong>
                                        <span>Якщо повна дата невідома, можна окремо задати день, місяць і рік.</span>
                                    </span>
                                </button>
                            </div>
                        </fieldset>
                        <div class="form-step-actions">
                            <button type="button" class="acm-btn acm-btn--ghost agf-clear-form">Очистити</button>
                            <button type="button" class="acm-btn acm-btn--primary step-next" data-next="2">Далі</button>
                        </div>
                    </div>

                    <div class="form-step" data-step="2">
                        <fieldset class="acm-section">
                            <p class="acm-section-title">Розташування</p>
                            <div class="acm-row acm-row--three">
                                <div class="acm-field">
                                    <label for="agf-region">Область *</label>
                                    <select id="agf-region" name="region" required>
                                        <?= graveAddRegionOptions($formData['region']) ?>
                                    </select>
                                </div>
                                <div class="acm-field">
                                    <label for="agf-district">Район *</label>
                                    <select id="agf-district" name="district" data-selected="<?= $safeDistrict ?>" required>
                                        <?= graveAddDistrictOptions($formData['region'], $formData['district']) ?>
                                    </select>
                                </div>
                                <div class="acm-field">
                                    <label for="agf-town">Населений пункт *</label>
                                    <div class="acm-settlement-wrap">
                                        <select id="agf-town" name="town" data-selected="<?= $safeTown ?>" required>
                                            <?= graveAddSettlementOptions($formData['region'], $formData['district'], $formData['town']) ?>
                                        </select>
                                        <button type="button" id="agf-open-settlement" class="acm-add-settlement-btn" data-tooltip="Додати населений пункт" aria-label="Додати населений пункт">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                                <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                                <path d="M12 5l0 14"></path>
                                                <path d="M5 12l14 0"></path>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="acm-row acm-row--two">
                                <div class="acm-field">
                                    <label for="agf-cemetery">Кладовище *</label>
                                    <div class="acm-settlement-wrap">
                                        <select id="agf-cemetery" name="idxkladb" data-selected="<?= $safeCemetery ?>" required>
                                            <?= graveAddCemeteryOptions($formData['district'], $formData['idxkladb']) ?>
                                        </select>
                                        <a href="/searchcem/addcemetery" class="acm-add-settlement-btn agf-add-link" data-tooltip="Додати кладовище" aria-label="Додати кладовище">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                                <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                                <path d="M12 5l0 14"></path>
                                                <path d="M5 12l14 0"></path>
                                            </svg>
                                        </a>
                                    </div>
                                </div>
                                <div class="acm-field">
                                    <label for="agf-pos1">Квартал *</label>
                                    <div class="agf-unknown-field-wrap">
                                        <input id="agf-pos1" type="text" name="pos1" inputmode="numeric" value="<?= $safePos1 ?>" placeholder="Вкажіть квартал"<?= $pos1Unknown ? '' : ' required' ?><?= $pos1Unknown ? ' disabled' : '' ?>>
                                        <button type="button" class="agf-unknown-btn agf-unknown-btn--icon<?= $pos1Unknown ? ' is-active' : '' ?>" data-text-unknown="agf-pos1" data-unknown-input="agf-pos1-unknown" data-unknown-placeholder="Ви позначили квартал як невідомий" data-label-off="Позначити як невідомо" data-label-on="Вказати значення" data-tooltip="<?= $pos1Unknown ? 'Вказати значення' : 'Позначити як невідомо' ?>" aria-label="<?= $pos1Unknown ? 'Вказати значення' : 'Позначити як невідомо' ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-question-mark" aria-hidden="true" focusable="false"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M8 8a3.5 3 0 0 1 3.5 -3h1a3.5 3 0 0 1 3.5 3a3 3 0 0 1 -2 3a3 4 0 0 0 -2 4" /><path d="M12 19l0 .01" /></svg>
                                        </button>
                                    </div>
                                    <input type="hidden" id="agf-pos1-unknown" name="pos1_unknown" value="<?= $pos1Unknown ? '1' : '0' ?>">
                                </div>
                            </div>
                            <div class="acm-row acm-row--two">
                                <div class="acm-field">
                                    <label for="agf-pos2">Ряд *</label>
                                    <div class="agf-unknown-field-wrap">
                                        <input id="agf-pos2" type="text" name="pos2" inputmode="numeric" value="<?= $safePos2 ?>" placeholder="Вкажіть ряд"<?= $pos2Unknown ? '' : ' required' ?><?= $pos2Unknown ? ' disabled' : '' ?>>
                                        <button type="button" class="agf-unknown-btn agf-unknown-btn--icon<?= $pos2Unknown ? ' is-active' : '' ?>" data-text-unknown="agf-pos2" data-unknown-input="agf-pos2-unknown" data-unknown-placeholder="Ви позначили ряд як невідомий" data-label-off="Позначити як невідомо" data-label-on="Вказати значення" data-tooltip="<?= $pos2Unknown ? 'Вказати значення' : 'Позначити як невідомо' ?>" aria-label="<?= $pos2Unknown ? 'Вказати значення' : 'Позначити як невідомо' ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-question-mark" aria-hidden="true" focusable="false"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M8 8a3.5 3 0 0 1 3.5 -3h1a3.5 3 0 0 1 3.5 3a3 3 0 0 1 -2 3a3 4 0 0 0 -2 4" /><path d="M12 19l0 .01" /></svg>
                                        </button>
                                    </div>
                                    <input type="hidden" id="agf-pos2-unknown" name="pos2_unknown" value="<?= $pos2Unknown ? '1' : '0' ?>">
                                </div>
                                <div class="acm-field">
                                    <label for="agf-pos3">Місце *</label>
                                    <div class="agf-unknown-field-wrap">
                                        <input id="agf-pos3" type="text" name="pos3" inputmode="numeric" value="<?= $safePos3 ?>" placeholder="Вкажіть місце"<?= $pos3Unknown ? '' : ' required' ?><?= $pos3Unknown ? ' disabled' : '' ?>>
                                        <button type="button" class="agf-unknown-btn agf-unknown-btn--icon<?= $pos3Unknown ? ' is-active' : '' ?>" data-text-unknown="agf-pos3" data-unknown-input="agf-pos3-unknown" data-unknown-placeholder="Ви позначили місце як невідоме" data-label-off="Позначити як невідомо" data-label-on="Вказати значення" data-tooltip="<?= $pos3Unknown ? 'Вказати значення' : 'Позначити як невідомо' ?>" aria-label="<?= $pos3Unknown ? 'Вказати значення' : 'Позначити як невідомо' ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-question-mark" aria-hidden="true" focusable="false"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M8 8a3.5 3 0 0 1 3.5 -3h1a3.5 3 0 0 1 3.5 3a3 3 0 0 1 -2 3a3 4 0 0 0 -2 4" /><path d="M12 19l0 .01" /></svg>
                                        </button>
                                    </div>
                                    <input type="hidden" id="agf-pos3-unknown" name="pos3_unknown" value="<?= $pos3Unknown ? '1' : '0' ?>">
                                </div>
                            </div>
                        </fieldset>
                        <div class="form-step-actions">
                            <button type="button" class="acm-btn acm-btn--ghost step-prev" data-prev="1">Назад</button>
                            <button type="button" class="acm-btn acm-btn--ghost agf-clear-form">Очистити</button>
                            <button type="button" class="acm-btn acm-btn--primary step-next" data-next="3">Далі</button>
                        </div>
                    </div>

                    <div class="form-step" data-step="3">
                        <fieldset class="acm-section">
                            <p class="acm-section-title">Файли</p>
                            <div class="acm-file-grid agf-file-grid-two">
                                <div class="acm-file agf-upload-card" data-upload-card="photo1">
                                    <span class="acm-file-title">Фото поховання</span>
                                    <input id="agf-photo1" class="acm-file-input" type="file" name="photo1" accept=".jpg,.jpeg,.png,.gif">
                                    <label for="agf-photo1" id="agf-photo1-dropzone" class="agf-upload-dropzone">
                                        <div id="agf-photo1-preview" class="agf-upload-preview">
                                            <img id="agf-photo1-preview-img" alt="Попередній перегляд фото поховання">
                                            <div class="agf-upload-empty">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-photo-plus" aria-hidden="true" focusable="false">
                                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                                    <path d="M15 8h.01"></path>
                                                    <path d="M12.5 21h-6.5a3 3 0 0 1 -3 -3v-12a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v6.5"></path>
                                                    <path d="M3 16l5 -5c.928 -.893 2.072 -.893 3 0l4 4"></path>
                                                    <path d="M14 14l1 -1c.67 -.644 1.45 -.824 2.182 -.54"></path>
                                                    <path d="M16 19h6"></path>
                                                    <path d="M19 16v6"></path>
                                                </svg>
                                                <span>Натисніть для вибору або перетягніть файл</span>
                                            </div>
                                        </div>
                                    </label>
                                    <div class="acm-file-control">
                                        <span id="agf-photo1-badge" class="agf-upload-badge" hidden>Фото завантажено</span>
                                        <button type="button" id="agf-photo1-trigger" class="acm-file-btn">Вибрати фото</button>
                                        <button type="button" id="agf-photo1-view" class="acm-file-btn agf-view-btn" hidden>Переглянути</button>
                                        <span id="agf-photo1-name" class="acm-file-name">Файл не обрано</span>
                                    </div>
                                    <small>PNG / JPG / GIF</small>
                                </div>
                                <div class="acm-file agf-upload-card" data-upload-card="photo2">
                                    <span class="acm-file-title">Фото таблички</span>
                                    <input id="agf-photo2" class="acm-file-input" type="file" name="photo2" accept=".jpg,.jpeg,.png,.gif">
                                    <label for="agf-photo2" id="agf-photo2-dropzone" class="agf-upload-dropzone">
                                        <div id="agf-photo2-preview" class="agf-upload-preview">
                                            <img id="agf-photo2-preview-img" alt="Попередній перегляд фото таблички">
                                            <div class="agf-upload-empty">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-photo-plus" aria-hidden="true" focusable="false">
                                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                                    <path d="M15 8h.01"></path>
                                                    <path d="M12.5 21h-6.5a3 3 0 0 1 -3 -3v-12a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v6.5"></path>
                                                    <path d="M3 16l5 -5c.928 -.893 2.072 -.893 3 0l4 4"></path>
                                                    <path d="M14 14l1 -1c.67 -.644 1.45 -.824 2.182 -.54"></path>
                                                    <path d="M16 19h6"></path>
                                                    <path d="M19 16v6"></path>
                                                </svg>
                                                <span>Натисніть для вибору або перетягніть файл</span>
                                            </div>
                                        </div>
                                    </label>
                                    <div class="acm-file-control">
                                        <span id="agf-photo2-badge" class="agf-upload-badge" hidden>Фото завантажено</span>
                                        <button type="button" id="agf-photo2-trigger" class="acm-file-btn">Вибрати фото</button>
                                        <button type="button" id="agf-photo2-view" class="acm-file-btn agf-view-btn" hidden>Переглянути</button>
                                        <span id="agf-photo2-name" class="acm-file-name">Файл не обрано</span>
                                    </div>
                                    <small>PNG / JPG / GIF</small>
                                </div>
                            </div>
                        </fieldset>
                        <div class="form-step-actions">
                            <button type="button" class="acm-btn acm-btn--ghost step-prev" data-prev="2">Назад</button>
                            <button type="button" class="acm-btn acm-btn--ghost agf-clear-form">Очистити</button>
                            <button type="submit" class="acm-btn acm-btn--primary">Додати запис</button>
                        </div>
                    </div>
                </form>
            </section>
        </section>

        <section class="acm-after-form">
            <h3 class="acm-after-form-title">Рекомендації перед збереженням</h3>
            <div class="acm-after-form-grid">
                <article class="acm-after-form-item">
                    <h4>Перевірте ПІБ і дати</h4>
                    <p>Уточніть написання прізвища та імені, а також формат дат перед фінальним кроком.</p>
                </article>
                <article class="acm-after-form-item">
                    <h4>Позиція на кладовищі</h4>
                    <p>Вкажіть квартал, ряд і місце без зайвих символів для коректного пошуку в системі.</p>
                </article>
                <article class="acm-after-form-item">
                    <h4>Фото можна додати пізніше</h4>
                    <p>Якщо фото зараз недоступні, запис можна зберегти без них і оновити пізніше.</p>
                </article>
            </div>
        </section>
</main>
</div>

<div class="acm-modal" id="agf-settlement-modal" aria-hidden="true">
    <div class="acm-modal__backdrop" data-agf-close-modal></div>
    <div class="acm-modal__card" role="dialog" aria-modal="true" aria-labelledby="agf-settlement-title">
        <h3 id="agf-settlement-title" class="acm-modal__title">Додати населений пункт</h3>
        <p class="acm-modal__text">Нова назва буде додана до обраного району та області.</p>
        <div class="acm-field">
            <label for="agf-new-settlement">Назва населеного пункту</label>
            <input id="agf-new-settlement" type="text" autocomplete="off">
        </div>
        <div id="agf-settlement-hint" class="acm-modal-hint is-hidden" hidden></div>
        <div class="acm-modal__actions">
            <button type="button" class="acm-btn acm-btn--ghost" data-agf-close-modal>Скасувати</button>
            <button type="button" id="agf-save-settlement" class="acm-btn acm-btn--primary">Додати</button>
        </div>
    </div>
</div>

<div class="acm-modal" id="agf-partial-date-modal" aria-hidden="true">
    <div class="acm-modal__backdrop" data-agf-close-partial-modal></div>
    <div class="acm-modal__card agf-partial-modal-card" role="dialog" aria-modal="true" aria-labelledby="agf-partial-date-title">
        <h3 id="agf-partial-date-title" class="acm-modal__title">Вказати частичні дати</h3>
        <p class="acm-modal__text">Заповніть лише відомі частини дати. Якщо частина невідома, позначте це чекбоксом.</p>

        <div class="agf-partial-modal-grid">
            <section class="agf-partial-group" data-partial-group="dt1">
                <h4 class="agf-partial-group-title">Дата народження</h4>
                <div class="agf-partial-input-row">
                    <label class="agf-partial-input-box">
                        <span>День</span>
                        <input type="text" id="agf-modal-dt1-day" inputmode="numeric" maxlength="2" placeholder="ДД">
                    </label>
                    <label class="agf-partial-input-box">
                        <span>Місяць</span>
                        <input type="text" id="agf-modal-dt1-month" inputmode="numeric" maxlength="2" placeholder="ММ">
                    </label>
                    <label class="agf-partial-input-box">
                        <span>Рік</span>
                        <input type="text" id="agf-modal-dt1-year" inputmode="numeric" maxlength="4" placeholder="РРРР">
                    </label>
                </div>
                <div class="agf-partial-check-grid">
                    <label class="agf-partial-check">
                        <input type="checkbox" id="agf-modal-dt1-day-unknown">
                        <span>Позначити день невідомо</span>
                    </label>
                    <label class="agf-partial-check">
                        <input type="checkbox" id="agf-modal-dt1-month-unknown">
                        <span>Позначити місяць невідомо</span>
                    </label>
                    <label class="agf-partial-check">
                        <input type="checkbox" id="agf-modal-dt1-year-unknown">
                        <span>Позначити рік невідомо</span>
                    </label>
                </div>
            </section>

            <section class="agf-partial-group" data-partial-group="dt2">
                <h4 class="agf-partial-group-title">Дата смерті</h4>
                <div class="agf-partial-input-row">
                    <label class="agf-partial-input-box">
                        <span>День</span>
                        <input type="text" id="agf-modal-dt2-day" inputmode="numeric" maxlength="2" placeholder="ДД">
                    </label>
                    <label class="agf-partial-input-box">
                        <span>Місяць</span>
                        <input type="text" id="agf-modal-dt2-month" inputmode="numeric" maxlength="2" placeholder="ММ">
                    </label>
                    <label class="agf-partial-input-box">
                        <span>Рік</span>
                        <input type="text" id="agf-modal-dt2-year" inputmode="numeric" maxlength="4" placeholder="РРРР">
                    </label>
                </div>
                <div class="agf-partial-check-grid">
                    <label class="agf-partial-check">
                        <input type="checkbox" id="agf-modal-dt2-day-unknown">
                        <span>Позначити день невідомо</span>
                    </label>
                    <label class="agf-partial-check">
                        <input type="checkbox" id="agf-modal-dt2-month-unknown">
                        <span>Позначити місяць невідомо</span>
                    </label>
                    <label class="agf-partial-check">
                        <input type="checkbox" id="agf-modal-dt2-year-unknown">
                        <span>Позначити рік невідомо</span>
                    </label>
                </div>
            </section>
        </div>

        <div id="agf-partial-date-hint" class="acm-modal-hint is-hidden" hidden></div>
        <div class="acm-modal__actions">
            <button type="button" class="acm-btn acm-btn--ghost" data-agf-close-partial-modal>Скасувати</button>
            <button type="button" id="agf-save-partial-dates" class="acm-btn acm-btn--primary">Зберегти</button>
        </div>
    </div>
</div>

<div class="acm-modal" id="agf-photo-modal" aria-hidden="true">
    <div class="acm-modal__backdrop" data-agf-close-photo-modal></div>
    <div class="acm-modal__card agf-photo-modal-card" role="dialog" aria-modal="true" aria-labelledby="agf-photo-modal-title">
        <h3 id="agf-photo-modal-title" class="acm-modal__title">Перегляд фото</h3>
        <div class="agf-photo-modal-image-wrap">
            <img id="agf-photo-modal-img" alt="Перегляд завантаженого фото">
        </div>
        <div class="acm-modal__actions">
            <button type="button" class="acm-btn acm-btn--ghost" data-agf-close-photo-modal>Закрити</button>
        </div>
    </div>
</div>

<script>
(function () {
    const form = document.getElementById("graveaddform");
    if (!form) {
        return;
    }

    const regionSel = document.getElementById("agf-region");
    const districtSel = document.getElementById("agf-district");
    const townSel = document.getElementById("agf-town");
    const cemeterySel = document.getElementById("agf-cemetery");
    const openSettlementBtn = document.getElementById("agf-open-settlement");
    const settlementModal = document.getElementById("agf-settlement-modal");
    const newSettlementInput = document.getElementById("agf-new-settlement");
    const saveSettlementBtn = document.getElementById("agf-save-settlement");
    const settlementHint = document.getElementById("agf-settlement-hint");
    const closeModalNodes = settlementModal ? settlementModal.querySelectorAll("[data-agf-close-modal]") : [];
    const openPartialDatesBtn = document.getElementById("agf-open-partial-dates");
    const partialDateModal = document.getElementById("agf-partial-date-modal");
    const partialDateHint = document.getElementById("agf-partial-date-hint");
    const savePartialDatesBtn = document.getElementById("agf-save-partial-dates");
    const closePartialModalNodes = partialDateModal ? partialDateModal.querySelectorAll("[data-agf-close-partial-modal]") : [];
    const clearFormBtns = Array.from(form.querySelectorAll(".agf-clear-form"));
    const photoModal = document.getElementById("agf-photo-modal");
    const photoModalImg = document.getElementById("agf-photo-modal-img");
    const photoModalTitle = document.getElementById("agf-photo-modal-title");
    const closePhotoModalNodes = photoModal ? photoModal.querySelectorAll("[data-agf-close-photo-modal]") : [];
    const dateUnknownButtons = Array.from(form.querySelectorAll(".agf-unknown-btn"));
    const partialDateClearButtons = Array.from(form.querySelectorAll("[data-clear-partial-date]"));
    const partialDateFields = {
        dt1: {
            key: "dt1",
            label: "Дата народження",
            input: document.getElementById("agf-dt1"),
            display: document.getElementById("agf-dt1-display"),
            shell: document.getElementById("agf-dt1-display-shell"),
            clearButton: form.querySelector("[data-clear-partial-date=\"dt1\"]"),
            hidden: {
                day: document.getElementById("agf-dt1-day"),
                month: document.getElementById("agf-dt1-month"),
                year: document.getElementById("agf-dt1-year")
            },
            modal: {
                day: document.getElementById("agf-modal-dt1-day"),
                month: document.getElementById("agf-modal-dt1-month"),
                year: document.getElementById("agf-modal-dt1-year")
            },
            unknown: {
                hidden: document.getElementById("agf-dt1-unknown"),
                day: document.getElementById("agf-modal-dt1-day-unknown"),
                month: document.getElementById("agf-modal-dt1-month-unknown"),
                year: document.getElementById("agf-modal-dt1-year-unknown")
            }
        },
        dt2: {
            key: "dt2",
            label: "Дата смерті",
            input: document.getElementById("agf-dt2"),
            display: document.getElementById("agf-dt2-display"),
            shell: document.getElementById("agf-dt2-display-shell"),
            clearButton: form.querySelector("[data-clear-partial-date=\"dt2\"]"),
            hidden: {
                day: document.getElementById("agf-dt2-day"),
                month: document.getElementById("agf-dt2-month"),
                year: document.getElementById("agf-dt2-year")
            },
            modal: {
                day: document.getElementById("agf-modal-dt2-day"),
                month: document.getElementById("agf-modal-dt2-month"),
                year: document.getElementById("agf-modal-dt2-year")
            },
            unknown: {
                hidden: document.getElementById("agf-dt2-unknown"),
                day: document.getElementById("agf-modal-dt2-day-unknown"),
                month: document.getElementById("agf-modal-dt2-month-unknown"),
                year: document.getElementById("agf-modal-dt2-year-unknown")
            }
        }
    };

    const filePairs = [
        {
            input: document.getElementById("agf-photo1"),
            name: document.getElementById("agf-photo1-name"),
            card: form.querySelector("[data-upload-card=\"photo1\"]"),
            image: document.getElementById("agf-photo1-preview-img"),
            trigger: document.getElementById("agf-photo1-trigger"),
            viewButton: document.getElementById("agf-photo1-view"),
            badge: document.getElementById("agf-photo1-badge"),
            dropzone: document.getElementById("agf-photo1-dropzone")
        },
        {
            input: document.getElementById("agf-photo2"),
            name: document.getElementById("agf-photo2-name"),
            card: form.querySelector("[data-upload-card=\"photo2\"]"),
            image: document.getElementById("agf-photo2-preview-img"),
            trigger: document.getElementById("agf-photo2-trigger"),
            viewButton: document.getElementById("agf-photo2-view"),
            badge: document.getElementById("agf-photo2-badge"),
            dropzone: document.getElementById("agf-photo2-dropzone")
        }
    ];

    const stepNodes = Array.from(form.querySelectorAll(".form-step"));
    const stepIndicators = Array.from(form.querySelectorAll(".agf-step"));
    let currentStep = 1;
    const draftStorageKey = "agf.graveaddform.draft.v2";
    const draftTtlMs = 1000 * 60 * 60 * 6;
    const isSubmitSuccess = form.dataset.submitSuccess === "1";
    let suppressDraftSave = false;
    let bodyLockScrollY = 0;
    let bodyLockActive = false;

    const placeholderById = {
        "agf-region": "Оберіть область",
        "agf-district": "Оберіть район",
        "agf-town": "Оберіть населений пункт",
        "agf-cemetery": "Оберіть кладовище"
    };

    if (!regionSel || !districtSel || !townSel || !cemeterySel || !openSettlementBtn || !settlementModal || !newSettlementInput || !saveSettlementBtn || !openPartialDatesBtn || !partialDateModal || !partialDateHint || !savePartialDatesBtn) {
        return;
    }

    function setUnknownState(button, isUnknown) {
        if (!button) {
            return;
        }
        const inputId = button.dataset.dateUnknown || button.dataset.textUnknown;
        const hiddenId = button.dataset.unknownInput;
        const input = inputId ? document.getElementById(inputId) : null;
        const hidden = hiddenId ? document.getElementById(hiddenId) : null;
        if (!input || !hidden) {
            return;
        }
        if (!input.dataset.defaultPlaceholder) {
            input.dataset.defaultPlaceholder = input.getAttribute("placeholder") || (button.dataset.dateUnknown ? "дд.мм.рррр" : "");
        }
        hidden.value = isUnknown ? "1" : "0";
        if (isUnknown) {
            if (button.dataset.dateUnknown) {
                const partialConfig = getPartialDateConfig(input.name);
                if (partialConfig) {
                    const hasVisibleMask = partialConfig.shell && !partialConfig.shell.classList.contains("is-hidden");
                    if (hasVisibleMask && partialConfig.clearButton) {
                        partialConfig.clearButton.click();
                    } else {
                        clearPartialDate(input.name, true);
                    }
                }
            }
            input.value = "";
            input.disabled = true;
            input.removeAttribute("required");
            input.setAttribute("placeholder", button.dataset.unknownPlaceholder || "Ви позначили дату - невідомо");
            button.classList.add("is-active");
            if (button.classList.contains("agf-unknown-btn--icon")) {
                var activeLabel = button.dataset.labelOn || "Вказати значення";
                button.setAttribute("data-tooltip", activeLabel);
                button.setAttribute("aria-label", activeLabel);
            } else {
                button.textContent = button.dataset.labelOn || "Вказати значення";
            }
        } else {
            input.disabled = false;
            if (!button.dataset.dateUnknown || !hasPartialDate(input.name)) {
                input.setAttribute("required", "");
            }
            input.setAttribute("placeholder", input.dataset.defaultPlaceholder);
            button.classList.remove("is-active");
            if (button.classList.contains("agf-unknown-btn--icon")) {
                var defaultLabel = button.dataset.labelOff || "Позначити як невідомо";
                button.setAttribute("data-tooltip", defaultLabel);
                button.setAttribute("aria-label", defaultLabel);
            } else {
                button.textContent = button.dataset.labelOff || "Позначити як невідомо";
            }
        }
        if (button.dataset.dateUnknown) {
            applyPartialDateState(input.name);
        }
    }

    function setPartialHint(text, isError) {
        const hintText = text || "";
        partialDateHint.textContent = hintText;
        partialDateHint.style.color = isError ? "#8b2330" : "#285c89";
        partialDateHint.hidden = hintText === "";
        partialDateHint.classList.toggle("is-hidden", hintText === "");
    }

    function updateBodyLock() {
        const anyOpen = !!(partialDateModal && partialDateModal.classList.contains("is-open"));
        document.body.classList.toggle("agf-body-locked", anyOpen);
        document.documentElement.classList.toggle("agf-body-locked", anyOpen);

        if (anyOpen && !bodyLockActive) {
            bodyLockScrollY = window.scrollY || window.pageYOffset || 0;
            document.body.style.position = "fixed";
            document.body.style.top = "-" + bodyLockScrollY + "px";
            document.body.style.left = "0";
            document.body.style.right = "0";
            document.body.style.width = "100%";
            bodyLockActive = true;
        } else if (!anyOpen && bodyLockActive) {
            document.body.style.position = "";
            document.body.style.top = "";
            document.body.style.left = "";
            document.body.style.right = "";
            document.body.style.width = "";
            window.scrollTo(0, bodyLockScrollY);
            bodyLockActive = false;
        }
    }

    function sanitizePartialInput(inputEl, maxLength) {
        if (!inputEl) {
            return;
        }
        inputEl.addEventListener("input", function () {
            const digits = inputEl.value.replace(/\D/g, "").slice(0, maxLength);
            inputEl.value = digits;
        });
    }

    function getNextPartialModalInput(fieldName, part) {
        const orderedFields = [
            { field: "dt1", part: "day" },
            { field: "dt1", part: "month" },
            { field: "dt1", part: "year" },
            { field: "dt2", part: "day" },
            { field: "dt2", part: "month" },
            { field: "dt2", part: "year" }
        ];

        const currentIndex = orderedFields.findIndex(function (item) {
            return item.field === fieldName && item.part === part;
        });
        if (currentIndex === -1) {
            return null;
        }

        for (let i = currentIndex + 1; i < orderedFields.length; i += 1) {
            const nextItem = orderedFields[i];
            const nextConfig = getPartialDateConfig(nextItem.field);
            const nextInput = nextConfig && nextConfig.modal ? nextConfig.modal[nextItem.part] : null;
            if (nextInput && !nextInput.disabled) {
                return nextInput;
            }
        }

        return null;
    }

    function getPartialDateConfig(fieldName) {
        return partialDateFields[fieldName] || null;
    }

    function getDateUnknownButton(fieldName) {
        const config = getPartialDateConfig(fieldName);
        if (!config || !config.input) {
            return null;
        }
        return form.querySelector("[data-date-unknown=\"" + config.input.id + "\"]");
    }

    function updateDateUnknownButtonState(fieldName) {
        const button = getDateUnknownButton(fieldName);
        if (!button) {
            return;
        }

        const isDisabled = hasPartialDate(fieldName);
        button.disabled = isDisabled;
        if (isDisabled) {
            button.setAttribute("aria-disabled", "true");
            button.title = "Кнопка недоступна, поки вказана часткова дата.";
        } else {
            button.removeAttribute("aria-disabled");
            button.removeAttribute("title");
        }
    }

    function hasPartialDate(fieldName) {
        const config = getPartialDateConfig(fieldName);
        if (!config) {
            return false;
        }
        return ["day", "month", "year"].some(function (part) {
            return !!(config.hidden[part] && config.hidden[part].value.trim() !== "");
        });
    }

    function clearPartialDate(fieldName, skipApply) {
        const config = getPartialDateConfig(fieldName);
        if (!config) {
            return;
        }
        ["day", "month", "year"].forEach(function (part) {
            if (config.hidden[part]) {
                config.hidden[part].value = "";
            }
            if (config.modal[part]) {
                config.modal[part].value = "";
                config.modal[part].disabled = false;
                config.modal[part].placeholder = part === "year" ? "РРРР" : (part === "month" ? "ММ" : "ДД");
            }
            if (config.unknown[part]) {
                config.unknown[part].checked = false;
            }
        });
        if (config.display) {
            config.display.value = "";
        }
        if (config.shell) {
            config.shell.classList.add("is-hidden");
        }
        if (config.input) {
            config.input.classList.remove("is-hidden");
        }
        if (!skipApply) {
            applyPartialDateState(fieldName);
        }
    }

    function formatPartialMask(parts) {
        return ["day", "month", "year"].map(function (part) {
            const value = (parts[part] || "").trim();
            if (value === "") {
                return "??";
            }
            return part === "year"
                ? value.padStart(4, "0")
                : value.padStart(2, "0");
        }).join(".");
    }

    function syncStoredFullDate(fieldName) {
        const config = getPartialDateConfig(fieldName);
        if (!config || !config.input) {
            return;
        }

        const value = (config.input.value || "").trim();
        if (/^\d{4}-\d{2}-\d{2}$/.test(value)) {
            config.input.dataset.lastKnownIso = value;
        }
    }

    function splitIsoDateParts(value) {
        const normalizedValue = (value || "").trim();
        if (!/^\d{4}-\d{2}-\d{2}$/.test(normalizedValue)) {
            return null;
        }

        const parts = normalizedValue.split("-");
        if (parts.length !== 3) {
            return null;
        }

        return {
            year: parts[0],
            month: parts[1],
            day: parts[2]
        };
    }

    function getFullDateParts(fieldName) {
        const config = getPartialDateConfig(fieldName);
        if (!config || !config.input) {
            return null;
        }

        const sources = [
            config.input.value,
            config.input.getAttribute("value"),
            config.input.dataset.lastKnownIso
        ];

        for (let i = 0; i < sources.length; i += 1) {
            const dateParts = splitIsoDateParts(sources[i]);
            if (dateParts) {
                return dateParts;
            }
        }

        return null;
    }

    function getModalDateParts(fieldName) {
        const config = getPartialDateConfig(fieldName);
        if (!config) {
            return null;
        }

        if (hasPartialDate(fieldName)) {
            return {
                day: config.hidden.day ? config.hidden.day.value.trim() : "",
                month: config.hidden.month ? config.hidden.month.value.trim() : "",
                year: config.hidden.year ? config.hidden.year.value.trim() : ""
            };
        }

        return getFullDateParts(fieldName);
    }

    function applyPartialDateState(fieldName) {
        const config = getPartialDateConfig(fieldName);
        if (!config || !config.input || !config.display || !config.shell) {
            return;
        }

        const hasPartial = hasPartialDate(fieldName);
        if (hasPartial) {
            const mask = formatPartialMask({
                day: config.hidden.day ? config.hidden.day.value : "",
                month: config.hidden.month ? config.hidden.month.value : "",
                year: config.hidden.year ? config.hidden.year.value : ""
            });
            config.display.value = mask;
            config.shell.classList.remove("is-hidden");
            config.input.classList.add("is-hidden");
            config.input.value = "";
            config.input.disabled = true;
            config.input.removeAttribute("required");
            if (config.unknown.hidden) {
                config.unknown.hidden.value = "0";
            }
            updateDateUnknownButtonState(fieldName);
            return;
        }

        config.display.value = "";
        config.shell.classList.add("is-hidden");
        config.input.classList.remove("is-hidden");
        const isUnknown = !!(config.unknown.hidden && config.unknown.hidden.value === "1");
        config.input.disabled = isUnknown;
        if (isUnknown) {
            config.input.removeAttribute("required");
        } else {
            config.input.setAttribute("required", "");
        }
        updateDateUnknownButtonState(fieldName);
    }

    function syncPartialModalField(fieldName) {
        const config = getPartialDateConfig(fieldName);
        if (!config) {
            return;
        }

        const hasPartial = hasPartialDate(fieldName);
        const modalDateParts = getModalDateParts(fieldName);

        ["day", "month", "year"].forEach(function (part) {
            const modalInput = config.modal[part];
            const unknownCheckbox = config.unknown[part];
            if (!modalInput || !unknownCheckbox) {
                return;
            }

            const value = modalDateParts ? (modalDateParts[part] || "").trim() : "";

            const isUnknown = value === "";
            unknownCheckbox.checked = isUnknown && hasPartial;
            modalInput.value = value;
            modalInput.disabled = unknownCheckbox.checked;
            modalInput.placeholder = unknownCheckbox.checked
                ? "Невідомо"
                : (part === "year" ? "РРРР" : (part === "month" ? "ММ" : "ДД"));
        });
    }

    function openPartialDateModal() {
        setPartialHint("", false);
        syncPartialModalField("dt1");
        syncPartialModalField("dt2");
        partialDateModal.classList.add("is-open");
        partialDateModal.setAttribute("aria-hidden", "false");
        updateBodyLock();
        setTimeout(function () {
            const firstInput = partialDateModal.querySelector("input[type=\"text\"]");
            if (firstInput) {
                firstInput.focus();
            }
        }, 0);
    }

    function closePartialDateModal() {
        partialDateModal.classList.remove("is-open");
        partialDateModal.setAttribute("aria-hidden", "true");
        setPartialHint("", false);
        updateBodyLock();
    }

    function normalizePartialModalInput(value, maxLength) {
        return value.replace(/\D/g, "").slice(0, maxLength);
    }

    function collectPartialModalValue(fieldName) {
        const config = getPartialDateConfig(fieldName);
        if (!config) {
            return { status: "empty" };
        }

        const limits = {
            day: { maxLength: 2, min: 1, max: 31, label: "день" },
            month: { maxLength: 2, min: 1, max: 12, label: "місяць" },
            year: { maxLength: 4, min: 1, max: 9999, label: "рік" }
        };
        const rawValues = {};
        let checkedCount = 0;
        let knownCount = 0;

        ["day", "month", "year"].forEach(function (part) {
            const modalInput = config.modal[part];
            const unknownCheckbox = config.unknown[part];
            const maxLength = limits[part].maxLength;
            rawValues[part] = modalInput ? normalizePartialModalInput(modalInput.value, maxLength) : "";
            if (unknownCheckbox && unknownCheckbox.checked) {
                checkedCount += 1;
            }
            if (rawValues[part] !== "") {
                knownCount += 1;
            }
        });

        if (checkedCount === 0 && knownCount === 0) {
            return { status: "empty" };
        }

        if (checkedCount === 3) {
            return { status: "unknown" };
        }

        const result = {};
        for (const part of ["day", "month", "year"]) {
            const unknownCheckbox = config.unknown[part];
            const rules = limits[part];
            const currentValue = rawValues[part];
            if (unknownCheckbox && unknownCheckbox.checked) {
                result[part] = "";
                continue;
            }
            if (currentValue === "") {
                return {
                    status: "error",
                    message: config.label + ": заповніть " + rules.label + ' або позначте його як "невідомо".'
                };
            }

            const number = Number(currentValue);
            if (number < rules.min || number > rules.max) {
                return {
                    status: "error",
                    message: config.label + ": некоректно вказано " + rules.label + "."
                };
            }
            result[part] = String(number);
        }

        return { status: "filled", values: result };
    }

    function savePartialDates() {
        const dt1Value = collectPartialModalValue("dt1");
        if (dt1Value.status === "error") {
            setPartialHint(dt1Value.message, true);
            return;
        }

        const dt2Value = collectPartialModalValue("dt2");
        if (dt2Value.status === "error") {
            setPartialHint(dt2Value.message, true);
            return;
        }

        [
            { field: "dt1", value: dt1Value },
            { field: "dt2", value: dt2Value }
        ].forEach(function (item) {
            const config = getPartialDateConfig(item.field);
            const unknownButton = getDateUnknownButton(item.field);
            if (!config) {
                return;
            }

            if (item.value.status === "unknown") {
                setUnknownState(unknownButton, true);
                return;
            }

            if (item.value.status === "filled") {
                ["day", "month", "year"].forEach(function (part) {
                    if (config.hidden[part]) {
                        config.hidden[part].value = item.value.values[part] || "";
                    }
                });
                config.input.value = "";
                if (config.unknown.hidden) {
                    config.unknown.hidden.value = "0";
                }
                setUnknownState(unknownButton, false);
            } else {
                ["day", "month", "year"].forEach(function (part) {
                    if (config.hidden[part]) {
                        config.hidden[part].value = "";
                    }
                });
            }

            applyPartialDateState(item.field);
            clearInvalid(config.input);
        });

        setPartialHint("", false);
        closePartialDateModal();
        saveDraft();
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

    function clearDraft() {
        try {
            window.localStorage.removeItem(draftStorageKey);
        } catch (error) {
            // ignore storage errors
        }
    }

    function saveDraft() {
        if (suppressDraftSave) {
            return;
        }
        try {
            const values = {};
            const fields = form.querySelectorAll("input[name], select[name], textarea[name]");
            fields.forEach(function (field) {
                if (field.type === "file" || field.type === "hidden") {
                    return;
                }
                values[field.name] = field.value;
            });
            const dt1Unknown = document.getElementById("agf-dt1-unknown");
            const dt2Unknown = document.getElementById("agf-dt2-unknown");
            const dt1Day = document.getElementById("agf-dt1-day");
            const dt1Month = document.getElementById("agf-dt1-month");
            const dt1Year = document.getElementById("agf-dt1-year");
            const dt2Day = document.getElementById("agf-dt2-day");
            const dt2Month = document.getElementById("agf-dt2-month");
            const dt2Year = document.getElementById("agf-dt2-year");
            const pos1Unknown = document.getElementById("agf-pos1-unknown");
            const pos2Unknown = document.getElementById("agf-pos2-unknown");
            const pos3Unknown = document.getElementById("agf-pos3-unknown");
            if (dt1Unknown) {
                values.dt1_unknown = dt1Unknown.value;
            }
            if (dt2Unknown) {
                values.dt2_unknown = dt2Unknown.value;
            }
            if (dt1Day) {
                values.dt1_day = dt1Day.value;
            }
            if (dt1Month) {
                values.dt1_month = dt1Month.value;
            }
            if (dt1Year) {
                values.dt1_year = dt1Year.value;
            }
            if (dt2Day) {
                values.dt2_day = dt2Day.value;
            }
            if (dt2Month) {
                values.dt2_month = dt2Month.value;
            }
            if (dt2Year) {
                values.dt2_year = dt2Year.value;
            }
            if (pos1Unknown) {
                values.pos1_unknown = pos1Unknown.value;
            }
            if (pos2Unknown) {
                values.pos2_unknown = pos2Unknown.value;
            }
            if (pos3Unknown) {
                values.pos3_unknown = pos3Unknown.value;
            }
            window.localStorage.setItem(draftStorageKey, JSON.stringify({
                step: currentStep,
                values: values,
                savedAt: Date.now()
            }));
        } catch (error) {
            // ignore storage errors
        }
    }

    function applyDraft(draft) {
        if (!draft || typeof draft !== "object") {
            return;
        }

        const values = draft.values && typeof draft.values === "object" ? draft.values : {};
        Object.keys(values).forEach(function (name) {
            const field = form.querySelector("[name=\"" + name + "\"]");
            if (!field || field.type === "file" || field.type === "hidden") {
                return;
            }
            field.value = typeof values[name] === "string" ? values[name] : "";
        });

        const dt1Unknown = document.getElementById("agf-dt1-unknown");
        const dt2Unknown = document.getElementById("agf-dt2-unknown");
        const dt1Day = document.getElementById("agf-dt1-day");
        const dt1Month = document.getElementById("agf-dt1-month");
        const dt1Year = document.getElementById("agf-dt1-year");
        const dt2Day = document.getElementById("agf-dt2-day");
        const dt2Month = document.getElementById("agf-dt2-month");
        const dt2Year = document.getElementById("agf-dt2-year");
        const pos1Unknown = document.getElementById("agf-pos1-unknown");
        const pos2Unknown = document.getElementById("agf-pos2-unknown");
        const pos3Unknown = document.getElementById("agf-pos3-unknown");
        if (dt1Unknown && typeof values.dt1_unknown === "string") {
            dt1Unknown.value = values.dt1_unknown;
        }
        if (dt2Unknown && typeof values.dt2_unknown === "string") {
            dt2Unknown.value = values.dt2_unknown;
        }
        if (dt1Day && typeof values.dt1_day === "string") {
            dt1Day.value = values.dt1_day;
        }
        if (dt1Month && typeof values.dt1_month === "string") {
            dt1Month.value = values.dt1_month;
        }
        if (dt1Year && typeof values.dt1_year === "string") {
            dt1Year.value = values.dt1_year;
        }
        if (dt2Day && typeof values.dt2_day === "string") {
            dt2Day.value = values.dt2_day;
        }
        if (dt2Month && typeof values.dt2_month === "string") {
            dt2Month.value = values.dt2_month;
        }
        if (dt2Year && typeof values.dt2_year === "string") {
            dt2Year.value = values.dt2_year;
        }
        if (pos1Unknown && typeof values.pos1_unknown === "string") {
            pos1Unknown.value = values.pos1_unknown;
        }
        if (pos2Unknown && typeof values.pos2_unknown === "string") {
            pos2Unknown.value = values.pos2_unknown;
        }
        if (pos3Unknown && typeof values.pos3_unknown === "string") {
            pos3Unknown.value = values.pos3_unknown;
        }

        if (typeof values.district === "string") {
            districtSel.dataset.selected = values.district;
        }
        if (typeof values.town === "string") {
            townSel.dataset.selected = values.town;
        }
        if (typeof values.idxkladb === "string") {
            cemeterySel.dataset.selected = values.idxkladb;
        }

        applyPartialDateState("dt1");
        applyPartialDateState("dt2");

        const savedStep = Number(draft.step || 1);
        if (savedStep >= 1 && savedStep <= stepNodes.length) {
            currentStep = savedStep;
        }
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

        if (selectedOption) {
            trigger.textContent = selectedOption.textContent;
        } else if (options[0] && options[0].textContent) {
            trigger.textContent = options[0].textContent;
        } else {
            trigger.textContent = placeholder;
        }

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
                clearInvalid(selectEl);
                syncCustomSelect(selectEl);
                closeAllCustomSelects();
                selectEl.dispatchEvent(new Event("change", { bubbles: true }));
            });
            optionsBox.appendChild(optionNode);
        });

        wrapper.classList.toggle("disabled", !!selectEl.disabled);
    }

    function setHint(text, isError) {
        const hintText = text || "";
        settlementHint.textContent = hintText;
        settlementHint.style.color = isError ? "#8b2330" : "#285c89";
        settlementHint.hidden = hintText === "";
        settlementHint.classList.toggle("is-hidden", hintText === "");
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

    function openPhotoModal(src, titleText) {
        if (!photoModal || !photoModalImg) {
            return;
        }
        if (!src) {
            return;
        }
        photoModalImg.src = src;
        if (photoModalTitle) {
            photoModalTitle.textContent = titleText || "Перегляд фото";
        }
        photoModal.classList.add("is-open");
        photoModal.setAttribute("aria-hidden", "false");
    }

    function closePhotoModal() {
        if (!photoModal || !photoModalImg) {
            return;
        }
        photoModal.classList.remove("is-open");
        photoModal.setAttribute("aria-hidden", "true");
        photoModalImg.removeAttribute("src");
    }

    function loadDistricts(regionId, selectedDistrict, selectedTown, selectedCemetery) {
        districtSel.disabled = true;
        townSel.disabled = true;
        cemeterySel.disabled = true;

        districtSel.innerHTML = "<option value=\"\">Завантаження...</option>";
        townSel.innerHTML = "<option value=\"\">Оберіть район</option>";
        cemeterySel.innerHTML = "<option value=\"\">Оберіть район</option>";
        syncCustomSelect(districtSel);
        syncCustomSelect(townSel);
        syncCustomSelect(cemeterySel);
        toggleSettlementButton();

        fetch("/graveaddform.php?ajax_districts=1&region_id=" + encodeURIComponent(regionId), { credentials: "same-origin" })
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
                    loadCemeteries(districtSel.value, selectedCemetery);
                } else {
                    townSel.disabled = true;
                    cemeterySel.disabled = true;
                    townSel.innerHTML = "<option value=\"\">Оберіть район</option>";
                    cemeterySel.innerHTML = "<option value=\"\">Оберіть район</option>";
                    syncCustomSelect(townSel);
                    syncCustomSelect(cemeterySel);
                }
                toggleSettlementButton();
                saveDraft();
            })
            .catch(function () {
                districtSel.innerHTML = "<option value=\"\">Помилка завантаження</option>";
                districtSel.disabled = true;
                townSel.disabled = true;
                cemeterySel.disabled = true;
                townSel.innerHTML = "<option value=\"\">Оберіть район</option>";
                cemeterySel.innerHTML = "<option value=\"\">Оберіть район</option>";
                syncCustomSelect(districtSel);
                syncCustomSelect(townSel);
                syncCustomSelect(cemeterySel);
                toggleSettlementButton();
                saveDraft();
            });
    }

    function loadSettlements(regionId, districtId, selectedTown, selectedTownTitle) {
        townSel.disabled = true;
        townSel.innerHTML = "<option value=\"\">Завантаження...</option>";
        syncCustomSelect(townSel);

        fetch("/graveaddform.php?ajax_settlements=1&region_id=" + encodeURIComponent(regionId) + "&district_id=" + encodeURIComponent(districtId), { credentials: "same-origin" })
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

    function loadCemeteries(districtId, selectedCemetery) {
        cemeterySel.disabled = true;
        cemeterySel.innerHTML = "<option value=\"\">Завантаження...</option>";
        syncCustomSelect(cemeterySel);

        fetch("/graveaddform.php?ajax_cemeteries=1&district_id=" + encodeURIComponent(districtId), { credentials: "same-origin" })
            .then(function (response) { return response.text(); })
            .then(function (html) {
                cemeterySel.innerHTML = html;
                if (selectedCemetery) {
                    cemeterySel.value = selectedCemetery;
                }
                cemeterySel.disabled = false;
                syncCustomSelect(cemeterySel);
                saveDraft();
            })
            .catch(function () {
                cemeterySel.innerHTML = "<option value=\"\">Помилка завантаження</option>";
                cemeterySel.disabled = true;
                syncCustomSelect(cemeterySel);
                saveDraft();
            });
    }

    function onlyPositiveInput(inputEl) {
        if (!inputEl) {
            return;
        }
        inputEl.addEventListener("input", function () {
            const digits = inputEl.value.replace(/\D/g, "");
            inputEl.value = digits.replace(/^0+/, "");
        });
    }

    function clearInvalid(field) {
        field.classList.remove("agf-invalid");
        const wrapper = getCustomWrapper(field);
        if (wrapper) {
            wrapper.classList.remove("agf-invalid");
        }
    }

    function markInvalid(field) {
        field.classList.add("agf-invalid");
        const wrapper = getCustomWrapper(field);
        if (wrapper) {
            wrapper.classList.add("agf-invalid");
        }
    }

    function isFieldValid(field) {
        if (!field || field.disabled || !field.hasAttribute("required")) {
            return true;
        }

        let valid = true;
        if (field.tagName === "SELECT") {
            valid = !!field.value;
        } else {
            valid = field.value.trim() !== "";
        }

        if (valid) {
            clearInvalid(field);
        } else {
            markInvalid(field);
        }
        return valid;
    }

    function validateStep(step) {
        const stepNode = stepNodes.find(function (node) {
            return Number(node.dataset.step) === Number(step);
        });
        if (!stepNode) {
            return true;
        }

        const requiredFields = Array.from(stepNode.querySelectorAll("[required]"));
        let firstInvalid = null;

        requiredFields.forEach(function (field) {
            const valid = isFieldValid(field);
            if (!valid && !firstInvalid) {
                firstInvalid = field;
            }
        });

        if (firstInvalid) {
            const wrapper = getCustomWrapper(firstInvalid);
            if (wrapper) {
                wrapper.scrollIntoView({ behavior: "smooth", block: "center" });
            } else {
                firstInvalid.focus();
            }
            return false;
        }

        return true;
    }

    function showStep(step) {
        const stepNumber = Number(step);
        stepNodes.forEach(function (node) {
            node.classList.toggle("active", Number(node.dataset.step) === stepNumber);
        });

        stepIndicators.forEach(function (item) {
            const itemStep = Number(item.dataset.step);
            item.classList.toggle("active", itemStep === stepNumber);
            item.classList.toggle("completed", itemStep < stepNumber);
            item.classList.toggle("is-clickable", itemStep <= currentStep || itemStep === currentStep + 1);
        });

        currentStep = stepNumber;
        saveDraft();
    }

    function clearFormFields() {
        suppressDraftSave = true;

        form.querySelectorAll(".agf-invalid").forEach(function (node) {
            node.classList.remove("agf-invalid");
        });

        Array.from(form.querySelectorAll("input[name], textarea[name]")).forEach(function (field) {
            if (field.type === "file" || field.type === "hidden") {
                return;
            }
            field.value = "";
        });

        regionSel.value = "";
        districtSel.value = "";
        townSel.value = "";
        cemeterySel.value = "";
        clearPartialDate("dt1");
        clearPartialDate("dt2");
        const posUnknownHiddenIds = ["agf-pos1-unknown", "agf-pos2-unknown", "agf-pos3-unknown"];
        posUnknownHiddenIds.forEach(function (id) {
            const hidden = document.getElementById(id);
            if (hidden) {
                hidden.value = "0";
            }
        });
        districtSel.dataset.selected = "";
        townSel.dataset.selected = "";
        cemeterySel.dataset.selected = "";

        districtSel.disabled = true;
        townSel.disabled = true;
        cemeterySel.disabled = true;
        districtSel.innerHTML = "<option value=\"\">Спочатку оберіть область</option>";
        townSel.innerHTML = "<option value=\"\">Оберіть район</option>";
        cemeterySel.innerHTML = "<option value=\"\">Оберіть район</option>";

        filePairs.forEach(function (pair) {
            if (!pair) {
                return;
            }
            if (pair.input) {
                pair.input.value = "";
            }
            setUploadEmpty(pair);
        });

        closeAllCustomSelects();
        closeModal();
        closePartialDateModal();
        closePhotoModal();
        dateUnknownButtons.forEach(function (button) {
            setUnknownState(button, false);
        });
        toggleSettlementButton();
        syncCustomSelect(regionSel);
        syncCustomSelect(districtSel);
        syncCustomSelect(townSel);
        syncCustomSelect(cemeterySel);

        currentStep = 1;
        showStep(1);

        suppressDraftSave = false;
        clearDraft();
    }

    form.querySelectorAll(".step-next").forEach(function (button) {
        button.addEventListener("click", function () {
            const nextStep = Number(button.dataset.next || 0);
            if (!nextStep) {
                return;
            }
            if (validateStep(currentStep)) {
                showStep(nextStep);
            }
        });
    });

    form.querySelectorAll(".step-prev").forEach(function (button) {
        button.addEventListener("click", function () {
            const prevStep = Number(button.dataset.prev || 0);
            if (prevStep) {
                showStep(prevStep);
            }
        });
    });

    stepIndicators.forEach(function (item) {
        item.addEventListener("click", function () {
            const requestedStep = Number(item.dataset.step || 0);
            if (!requestedStep || requestedStep === currentStep) {
                return;
            }
            if (requestedStep < currentStep) {
                showStep(requestedStep);
                return;
            }
            if (validateStep(currentStep)) {
                showStep(requestedStep);
            }
        });
    });

    clearFormBtns.forEach(function (button) {
        button.addEventListener("click", clearFormFields);
    });

    form.addEventListener("submit", function (event) {
        for (let step = 1; step <= stepNodes.length; step += 1) {
            if (!validateStep(step)) {
                event.preventDefault();
                showStep(step);
                return;
            }
        }
    });

    [regionSel, districtSel, townSel, cemeterySel].forEach(function (selectEl) {
        selectEl.addEventListener("change", function () {
            clearInvalid(selectEl);
            saveDraft();
        });
    });

    Array.from(form.querySelectorAll("input[name]")).forEach(function (inputEl) {
        if (inputEl.type === "file" || inputEl.type === "hidden") {
            return;
        }
        inputEl.addEventListener("input", function () {
            clearInvalid(inputEl);
            saveDraft();
        });
    });

    Array.from(form.querySelectorAll("input[name], textarea[name]")).forEach(function (inputEl) {
        if (inputEl.type === "file" || inputEl.type === "hidden" || inputEl.hasAttribute("required")) {
            return;
        }
        inputEl.addEventListener("input", saveDraft);
        inputEl.addEventListener("change", saveDraft);
    });

    regionSel.addEventListener("change", function () {
        const regionId = regionSel.value;
        if (!regionId) {
            districtSel.disabled = true;
            townSel.disabled = true;
            cemeterySel.disabled = true;
            districtSel.innerHTML = "<option value=\"\">Спочатку оберіть область</option>";
            townSel.innerHTML = "<option value=\"\">Оберіть район</option>";
            cemeterySel.innerHTML = "<option value=\"\">Оберіть район</option>";
            syncCustomSelect(districtSel);
            syncCustomSelect(townSel);
            syncCustomSelect(cemeterySel);
            toggleSettlementButton();
            return;
        }
        loadDistricts(regionId, "", "", "");
    });

    districtSel.addEventListener("change", function () {
        const districtId = districtSel.value;
        if (!regionSel.value || !districtId) {
            townSel.disabled = true;
            cemeterySel.disabled = true;
            townSel.innerHTML = "<option value=\"\">Оберіть район</option>";
            cemeterySel.innerHTML = "<option value=\"\">Оберіть район</option>";
            syncCustomSelect(townSel);
            syncCustomSelect(cemeterySel);
            toggleSettlementButton();
            return;
        }
        loadSettlements(regionSel.value, districtId, "", "");
        loadCemeteries(districtId, "");
    });

    openSettlementBtn.addEventListener("click", openModal);
    openPartialDatesBtn.addEventListener("click", openPartialDateModal);
    closeModalNodes.forEach(function (node) {
        node.addEventListener("click", closeModal);
    });
    closePartialModalNodes.forEach(function (node) {
        node.addEventListener("click", closePartialDateModal);
    });
    closePhotoModalNodes.forEach(function (node) {
        node.addEventListener("click", closePhotoModal);
    });
    savePartialDatesBtn.addEventListener("click", savePartialDates);

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape") {
            if (settlementModal.classList.contains("is-open")) {
                closeModal();
            }
            if (partialDateModal.classList.contains("is-open")) {
                closePartialDateModal();
            }
            if (photoModal && photoModal.classList.contains("is-open")) {
                closePhotoModal();
            }
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

        fetch("/graveaddform.php", {
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

    function setUploadEmpty(pair) {
        if (!pair || !pair.input || !pair.name || !pair.card || !pair.image) {
            return;
        }
        pair.name.textContent = "Файл не обрано";
        pair.image.removeAttribute("src");
        pair.card.classList.remove("has-preview", "dragover");
        pair.card.dataset.previewSrc = "";
        if (pair.badge) {
            pair.badge.hidden = true;
        }
        if (pair.viewButton) {
            pair.viewButton.hidden = true;
            pair.viewButton.disabled = true;
        }
        if (pair.trigger) {
            pair.trigger.textContent = "Вибрати фото";
        }
    }

    function setUploadPreview(pair, file) {
        if (!pair || !pair.input || !pair.name || !pair.card || !pair.image || !file) {
            return;
        }

        pair.name.textContent = file.name;
        if (pair.trigger) {
            pair.trigger.textContent = "Змінити";
        }
        if (!file.type || file.type.toLowerCase().indexOf("image/") !== 0) {
            pair.card.classList.remove("has-preview");
            pair.card.dataset.previewSrc = "";
            if (pair.badge) {
                pair.badge.hidden = true;
            }
            if (pair.viewButton) {
                pair.viewButton.hidden = true;
                pair.viewButton.disabled = true;
            }
            return;
        }

        const reader = new FileReader();
        reader.onload = function (event) {
            const result = event && event.target ? event.target.result : "";
            if (!result) {
                return;
            }
            pair.image.src = result;
            pair.card.dataset.previewSrc = result;
            pair.card.classList.add("has-preview");
            if (pair.badge) {
                pair.badge.hidden = false;
            }
            if (pair.viewButton) {
                pair.viewButton.hidden = false;
                pair.viewButton.disabled = false;
            }
        };
        reader.readAsDataURL(file);
    }

    filePairs.forEach(function (pair) {
        if (!pair.input || !pair.name || !pair.card || !pair.image || !pair.trigger || !pair.viewButton || !pair.badge || !pair.dropzone) {
            return;
        }

        setUploadEmpty(pair);

        pair.trigger.addEventListener("click", function () {
            pair.input.click();
        });

        pair.viewButton.addEventListener("click", function () {
            const src = pair.card.dataset.previewSrc || "";
            if (!src) {
                return;
            }
            openPhotoModal(src, pair.name.textContent || "Перегляд фото");
        });

        pair.input.addEventListener("change", function () {
            const file = pair.input.files && pair.input.files[0] ? pair.input.files[0] : null;
            if (!file) {
                return;
            }
            setUploadPreview(pair, file);
        });

        pair.dropzone.addEventListener("dragover", function (event) {
            event.preventDefault();
            pair.card.classList.add("dragover");
        });

        pair.dropzone.addEventListener("dragleave", function () {
            pair.card.classList.remove("dragover");
        });

        pair.dropzone.addEventListener("drop", function (event) {
            event.preventDefault();
            pair.card.classList.remove("dragover");
            const files = event.dataTransfer && event.dataTransfer.files ? event.dataTransfer.files : null;
            if (!files || !files[0]) {
                return;
            }

            const droppedFile = files[0];
            try {
                const dt = new DataTransfer();
                dt.items.add(droppedFile);
                pair.input.files = dt.files;
            } catch (error) {
                setUploadPreview(pair, droppedFile);
                return;
            }

            pair.input.dispatchEvent(new Event("change", { bubbles: true }));
        });
    });

    onlyPositiveInput(document.getElementById("agf-pos1"));
    onlyPositiveInput(document.getElementById("agf-pos2"));
    onlyPositiveInput(document.getElementById("agf-pos3"));
    sanitizePartialInput(document.getElementById("agf-modal-dt1-day"), 2);
    sanitizePartialInput(document.getElementById("agf-modal-dt1-month"), 2);
    sanitizePartialInput(document.getElementById("agf-modal-dt1-year"), 4);
    sanitizePartialInput(document.getElementById("agf-modal-dt2-day"), 2);
    sanitizePartialInput(document.getElementById("agf-modal-dt2-month"), 2);
    sanitizePartialInput(document.getElementById("agf-modal-dt2-year"), 4);

    ["dt1", "dt2"].forEach(function (fieldName) {
        const config = getPartialDateConfig(fieldName);
        if (!config) {
            return;
        }

        ["day", "month", "year"].forEach(function (part) {
            const modalInput = config.modal[part];
            const unknownCheckbox = config.unknown[part];
            const defaultPlaceholder = part === "year" ? "РРРР" : (part === "month" ? "ММ" : "ДД");

            if (unknownCheckbox) {
                unknownCheckbox.addEventListener("change", function () {
                    if (!modalInput) {
                        return;
                    }
                    if (unknownCheckbox.checked) {
                        modalInput.value = "";
                        modalInput.disabled = true;
                        modalInput.placeholder = "Невідомо";
                    } else {
                        modalInput.disabled = false;
                        modalInput.placeholder = defaultPlaceholder;
                    }
                    setPartialHint("", false);
                });
            }

            if (modalInput) {
                modalInput.addEventListener("input", function () {
                    setPartialHint("", false);
                    if (unknownCheckbox && unknownCheckbox.checked && modalInput.value !== "") {
                        unknownCheckbox.checked = false;
                        modalInput.disabled = false;
                        modalInput.placeholder = defaultPlaceholder;
                    }

                     const maxLength = Number(modalInput.getAttribute("maxlength") || modalInput.maxLength || 0);
                     if (maxLength > 0 && modalInput.value.length >= maxLength) {
                        const nextInput = getNextPartialModalInput(fieldName, part);
                        if (nextInput) {
                            nextInput.focus();
                            nextInput.select();
                        }
                    }
                });
            }
        });

        if (config.input) {
            syncStoredFullDate(fieldName);
            config.input.addEventListener("input", function () {
                syncStoredFullDate(fieldName);
                if (config.input.value !== "") {
                    clearPartialDate(fieldName);
                }
            });
            config.input.addEventListener("change", function () {
                syncStoredFullDate(fieldName);
            });
        }
    });

    partialDateClearButtons.forEach(function (button) {
        button.addEventListener("click", function () {
            const fieldName = button.dataset.clearPartialDate || "";
            if (!fieldName) {
                return;
            }
            clearPartialDate(fieldName);
            const config = getPartialDateConfig(fieldName);
            if (config && config.input) {
                clearInvalid(config.input);
                if (!config.input.disabled) {
                    config.input.focus();
                }
            }
            saveDraft();
        });
    });

    const hasQueryPrefill = <?= $hasQueryPrefill ? 'true' : 'false' ?>;

    if (isSubmitSuccess) {
        clearDraft();
    } else if (!hasQueryPrefill) {
        const draft = loadDraft();
        if (draft) {
            applyDraft(draft);
        }
    }

    dateUnknownButtons.forEach(function (button) {
        const hiddenId = button.dataset.unknownInput || "";
        const hidden = hiddenId ? document.getElementById(hiddenId) : null;
        const isUnknown = hidden && hidden.value === "1";
        setUnknownState(button, !!isUnknown);
        button.addEventListener("click", function () {
            const currentUnknown = hidden && hidden.value === "1";
            setUnknownState(button, !currentUnknown);
            const inputId = button.dataset.dateUnknown || button.dataset.textUnknown || "";
            const input = inputId ? document.getElementById(inputId) : null;
            if (input) {
                clearInvalid(input);
            }
            saveDraft();
        });
    });

    applyPartialDateState("dt1");
    applyPartialDateState("dt2");

    const initialRegion = regionSel.value;
    const initialDistrict = districtSel.dataset.selected || "";
    const initialTown = townSel.dataset.selected || "";
    const initialCemetery = cemeterySel.dataset.selected || "";

    if (!initialRegion) {
        districtSel.disabled = true;
        townSel.disabled = true;
        cemeterySel.disabled = true;
    }

    syncCustomSelect(regionSel);
    syncCustomSelect(districtSel);
    syncCustomSelect(townSel);
    syncCustomSelect(cemeterySel);

    if (initialRegion) {
        loadDistricts(initialRegion, initialDistrict, initialTown, initialCemetery);
    } else {
        toggleSettlementButton();
        saveDraft();
    }

    showStep(currentStep);
})();
</script>
    <?php
    View_Add(ob_get_clean());
}

View_Add(Page_Down());
View_Out();
View_Clear();
