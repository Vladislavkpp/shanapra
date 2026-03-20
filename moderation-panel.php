<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/roles.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/function.php';

$allowedRoles = [ROLE_MODERATOR, ROLE_WEBMASTER, ROLE_CREATOR];
$isAuthenticated = isset($_SESSION['uzver']) && (int)$_SESSION['uzver'] > 0;
$accessDeniedCode = $isAuthenticated ? 403 : 401;
$hasModerationAccess = isset($_SESSION['status'])
    && function_exists('hasAnyRole')
    && hasAnyRole((int)$_SESSION['status'], $allowedRoles);

$statusLabels = [
    'pending' => 'На модерації',
    'approved' => 'Схвалено',
    'rejected' => 'Відхилено',
];

$statusActionLabels = [
    'pending' => 'подано',
    'approved' => 'схвалено',
    'rejected' => 'відхилено',
];

$typeLabels = [
    'grave' => 'Поховання',
    'cemetery' => 'Кладовище',
];

function modEsc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function modResolveCurrentUserId(): int
{
    $sessionUserId = (int)($_SESSION['uzver'] ?? 0);
    if ($sessionUserId > 0) {
        return $sessionUserId;
    }

    $cookieUserId = (int)($_COOKIE['user_auth'] ?? 0);
    if ($cookieUserId > 0) {
        return $cookieUserId;
    }

    $currentSessionId = trim(session_id());
    if ($currentSessionId === '') {
        return 0;
    }

    $dblink = DbConnect();
    if (!$dblink) {
        return 0;
    }

    $sessionIdEscaped = mysqli_real_escape_string($dblink, $currentSessionId);
    $sql = "SELECT user_id FROM user_sessions WHERE session_id = '$sessionIdEscaped' LIMIT 1";
    $res = mysqli_query($dblink, $sql);
    if ($res && ($row = mysqli_fetch_assoc($res))) {
        $resolvedUserId = (int)($row['user_id'] ?? 0);
        mysqli_close($dblink);
        if ($resolvedUserId > 0) {
            $_SESSION['uzver'] = $resolvedUserId;
            return $resolvedUserId;
        }
        return 0;
    }

    mysqli_close($dblink);
    return 0;
}

function modResolveModeratorName(int $moderatorId): string
{
    if ($moderatorId <= 0) {
        return '-';
    }

    $moderatorDb = DbConnect();
    if ($moderatorDb) {
        $moderatorRes = mysqli_query($moderatorDb, 'SELECT fname, lname FROM users WHERE idx = ' . $moderatorId . ' LIMIT 1');
        if ($moderatorRes && ($moderatorRow = mysqli_fetch_assoc($moderatorRes))) {
            $moderatorName = modAuthorName($moderatorRow['fname'] ?? null, $moderatorRow['lname'] ?? null, $moderatorId);
            mysqli_close($moderatorDb);
            return $moderatorName !== 'Невідомо' ? $moderatorName : ('ID ' . $moderatorId);
        }
        mysqli_close($moderatorDb);
    }

    return 'ID ' . $moderatorId;
}

function modRenderAccessPage(int $code): void
{
    global $hide_page_down;

    $code = $code === 401 ? 401 : 403;
    $requestUri = modEsc((string)($_SERVER['REQUEST_URI'] ?? ''));
    $isUnauthorized = $code === 401;
    $title = $isUnauthorized ? 'Потрібна авторизація' : 'Доступ заборонено';
    $text = $isUnauthorized
        ? 'Для перегляду цієї сторінки потрібен вхід в обліковий запис.'
        : 'Ви авторизовані, але ваш обліковий запис не має прав доступу до панелі модерації.';
    $primaryUrl = $isUnauthorized ? '/auth.php' : '/';
    $primaryLabel = $isUnauthorized ? 'Увійти' : 'На головну';

    http_response_code($code);
    $hide_page_down = true;
    View_Clear();
    View_Add(Page_Up($title . ' — ' . $code));
    View_Add('<link rel="stylesheet" href="/assets/css/404.css">');
    View_Add(Menu_Up());
    View_Add('<div class="out-index out-index--404">');
    View_Add('<div class="page-404"><div class="page-404__inner"><div class="page-404__icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg></div><div class="page-404__code" aria-hidden="true">' . $code . '</div><h1 class="page-404__title">' . modEsc($title) . '</h1><p class="page-404__text">' . modEsc($text) . '</p>' . ($requestUri !== '' ? '<div class="page-404__uri">' . $requestUri . '</div>' : '') . '<div class="page-404__actions"><a href="' . $primaryUrl . '" class="page-404__btn page-404__btn--primary">' . modEsc($primaryLabel) . '</a></div></div></div>');
    View_Add('</div>');
    View_Add(Page_Down());
    View_Out();
}

function modFormatDateRange(?string $dt1, ?string $dt2): string
{
    $dt1 = trim((string)$dt1);
    $dt2 = trim((string)$dt2);
    $format = static function (string $date): string {
        if ($date === '' || $date === '0000-00-00') {
            return '';
        }
        $ts = strtotime($date);
        return $ts === false ? '' : date('d.m.Y', $ts);
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

function modFormatDateTime(?string $value): string
{
    $value = trim((string)$value);
    if ($value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
        return '-';
    }
    $ts = strtotime($value);
    return $ts === false ? '-' : date('d.m.Y H:i', $ts);
}

function modDateInputValue(?string $value): string
{
    $value = trim((string)$value);
    return ($value === '' || $value === '0000-00-00') ? '' : $value;
}

function modAuthorName(?string $fname, ?string $lname, int $idxadd): string
{
    $fname = trim((string)$fname);
    $lname = trim((string)$lname);
    $full = trim($lname . ' ' . $fname);
    if ($full !== '') {
        return $full;
    }
    return $idxadd > 0 ? 'ID ' . $idxadd : 'Невідомо';
}

function modAreaLabel(string $value, string $suffix): string
{
    $value = trim($value);
    if ($value === '' || $value === '-') {
        return '-';
    }
    return stripos($value, $suffix) !== false ? $value : ($value . ' ' . $suffix);
}

function modHasFile(?string $path): bool
{
    $path = trim((string)$path);
    if ($path === '') {
        return false;
    }
    if (stripos($path, 'http://') === 0 || stripos($path, 'https://') === 0) {
        return false;
    }
    if ($path[0] !== '/') {
        $path = '/' . ltrim($path, '/');
    }
    return is_file($_SERVER['DOCUMENT_ROOT'] . $path);
}

function modResolvedFilePath(?string $path): string
{
    $path = trim((string)$path);
    if ($path === '') {
        return '';
    }
    if (stripos($path, 'http://') === 0 || stripos($path, 'https://') === 0) {
        return $path;
    }
    if ($path[0] !== '/') {
        $path = '/' . ltrim($path, '/');
    }
    return modHasFile($path) ? $path : '';
}

function modNormalizeStoredPath(?string $path): string
{
    $path = trim((string)$path);
    if ($path === '') {
        return '';
    }
    if (stripos($path, 'http://') === 0 || stripos($path, 'https://') === 0 || $path[0] === '/') {
        return $path;
    }
    return '/' . $path;
}

function modResolveGravePreview(array $grave, int $graveId): string
{
    foreach (['photo1', 'photo2', 'photo3'] as $field) {
        $resolved = modResolvedFilePath($grave[$field] ?? '');
        if ($resolved !== '') {
            return $resolved;
        }
    }

    $fallback = '/graves/' . $graveId . '/foto1.jpg';
    return modHasFile($fallback) ? $fallback : '';
}

function modResolveCemeteryPreview(array $cemetery): string
{
    return modResolvedFilePath($cemetery['scheme'] ?? '');
}

function modNormalizeTab(?string $value): string
{
    return in_array($value, ['grave', 'cemetery'], true) ? $value : 'grave';
}

function modNormalizeStatus(?string $value): string
{
    return in_array($value, ['pending', 'approved', 'rejected'], true) ? $value : 'pending';
}

function modBuildPanelUrl(string $tab, string $status, string $view = 'list', string $type = '', int $id = 0): string
{
    $params = [
        'tab' => modNormalizeTab($tab),
        'status' => modNormalizeStatus($status),
    ];

    if ($view === 'edit' && in_array($type, ['grave', 'cemetery'], true) && $id > 0) {
        $params['view'] = 'edit';
        $params['type'] = $type;
        $params['id'] = $id;
    }

    return '/moderation-panel.php?' . http_build_query($params);
}

function modFormAlert(string $message, string $type): string
{
    if ($message === '') {
        return '';
    }
    $class = $type === 'success' ? 'mod-alert--success' : 'mod-alert--error';
    return '<div class="mod-alert ' . $class . '">' . modEsc($message) . '</div>';
}

function modJournalStatusIcon(string $status): string
{
    if ($status === 'approved') {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M3 12a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"></path><path d="M9 12l2 2l4 -4"></path></svg>';
    }
    if ($status === 'rejected') {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M3 12a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"></path><path d="M10 10l4 4m0 -4l-4 4"></path></svg>';
    }
    return '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M10 14l11 -11"></path><path d="M21 3l-6.5 18a.55 .55 0 0 1 -1 0l-3.5 -7l-7 -3.5a.55 .55 0 0 1 0 -1l18 -6.5"></path></svg>';
}

function modJournalActorRole(string $status): string
{
    return $status === 'pending' ? 'Відправник' : 'Модератор';
}

function modCardIcon(string $icon, int $size = 18): string
{
    $svgOpen = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">';
    $svgClose = '</svg>';
    if ($icon === 'map-pin') {
        return $svgOpen . '<path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M9 11a3 3 0 1 0 6 0a3 3 0 0 0 -6 0"></path><path d="M17.657 16.657l-4.243 4.243a2 2 0 0 1 -2.827 0l-4.244 -4.243a8 8 0 1 1 11.314 0"></path>' . $svgClose;
    }
    if ($icon === 'calendar') {
        return $svgOpen . '<path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M4 7a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2v-12"></path><path d="M16 3v4"></path><path d="M8 3v4"></path><path d="M4 11h16"></path><path d="M11 15h1"></path><path d="M12 15v3"></path>' . $svgClose;
    }
    if ($icon === 'user-circle') {
        return $svgOpen . '<path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M3 12a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"></path><path d="M9 10a3 3 0 1 0 6 0a3 3 0 1 0 -6 0"></path><path d="M6.168 18.849a4 4 0 0 1 3.832 -2.849h4a4 4 0 0 1 3.834 2.855"></path>' . $svgClose;
    }
    return $svgOpen . '<path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M3 7l6 -3l6 3l6 -3v13l-6 3l-6 -3l-6 3v-13"></path><path d="M9 4v13"></path><path d="M15 7v13"></path>' . $svgClose;
}

function modCardInfoItemHtml(string $icon, string $text): string
{
    return '<span class="mod-entry-card__info-item"><span class="mod-entry-card__meta-icon" aria-hidden="true">' . modCardIcon($icon) . '</span><span>' . modEsc($text) . '</span></span>';
}

function modCardTextLineHtml(string $icon, string $text, string $extraClass = ''): string
{
    $className = 'mod-entry-card__text-line';
    if ($extraClass !== '') {
        $className .= ' ' . $extraClass;
    }
    return '<div class="' . $className . '"><span class="mod-entry-card__meta-icon" aria-hidden="true">' . modCardIcon($icon) . '</span><span>' . modEsc($text) . '</span></div>';
}

function modCardAuthorHtml(string $author): string
{
    return '<span class="mod-entry-card__meta-icon" aria-hidden="true">' . modCardIcon('user-circle') . '</span><span class="mod-entry-card__author">' . modEsc($author) . '</span>';
}

function modRegionOptions(string $selectedValue): string
{
    $dblink = DbConnect();
    $res = mysqli_query($dblink, 'SELECT idx, title FROM region ORDER BY title');
    $out = '<option value="">Оберіть область</option>';
    while ($res && $row = mysqli_fetch_assoc($res)) {
        $value = (string)$row['idx'];
        $selected = $value === $selectedValue ? ' selected' : '';
        $out .= '<option value="' . modEsc($value) . '"' . $selected . '>' . modEsc((string)$row['title']) . '</option>';
    }
    mysqli_close($dblink);
    return $out;
}

function modDistrictOptions(string $regionValue, string $selectedValue): string
{
    if ((int)$regionValue <= 0) {
        return '<option value="">Спочатку оберіть область</option>';
    }
    $dblink = DbConnect();
    $res = mysqli_query($dblink, 'SELECT idx, title FROM district WHERE region = ' . (int)$regionValue . ' ORDER BY title');
    $out = '<option value="">Оберіть район</option>';
    while ($res && $row = mysqli_fetch_assoc($res)) {
        $value = (string)$row['idx'];
        $selected = $value === $selectedValue ? ' selected' : '';
        $out .= '<option value="' . modEsc($value) . '"' . $selected . '>' . modEsc((string)$row['title']) . '</option>';
    }
    mysqli_close($dblink);
    return $out;
}

function modTownOptions(string $regionValue, string $districtValue, string $selectedValue): string
{
    if ((int)$regionValue <= 0 || (int)$districtValue <= 0) {
        return '<option value="">Оберіть район</option>';
    }
    $dblink = DbConnect();
    $res = mysqli_query($dblink, 'SELECT idx, title FROM misto WHERE idxregion = ' . (int)$regionValue . ' AND idxdistrict = ' . (int)$districtValue . ' ORDER BY title');
    $out = '<option value="">Оберіть населений пункт</option>';
    while ($res && $row = mysqli_fetch_assoc($res)) {
        $value = (string)$row['idx'];
        $selected = $value === $selectedValue ? ' selected' : '';
        $out .= '<option value="' . modEsc($value) . '"' . $selected . '>' . modEsc((string)$row['title']) . '</option>';
    }
    mysqli_close($dblink);
    return $out;
}

function modCemeteryOptions(string $districtValue, string $selectedValue): string
{
    return CemeterySelect((int)$districtValue, (int)$selectedValue);
}

function modLoadGraveForEdit(mysqli $dblink, int $graveId): ?array
{
    if ($graveId <= 0) {
        return null;
    }
    $sql = "SELECT g.*, c.title AS cemetery_title, c.district AS district_id, c.town AS town_id, d.region AS region_id FROM grave g LEFT JOIN cemetery c ON g.idxkladb = c.idx LEFT JOIN district d ON c.district = d.idx WHERE g.idx = $graveId LIMIT 1";
    $res = mysqli_query($dblink, $sql);
    return $res ? (mysqli_fetch_assoc($res) ?: null) : null;
}

function modLoadCemeteryForEdit(mysqli $dblink, int $cemeteryId): ?array
{
    if ($cemeteryId <= 0) {
        return null;
    }
    $sql = "SELECT c.*, d.region AS region_id FROM cemetery c LEFT JOIN district d ON c.district = d.idx WHERE c.idx = $cemeteryId LIMIT 1";
    $res = mysqli_query($dblink, $sql);
    return $res ? (mysqli_fetch_assoc($res) ?: null) : null;
}

function modBuildGraveFormData(array $grave): array
{
    return [
        'region' => (string)($grave['region_id'] ?? ''),
        'district' => (string)($grave['district_id'] ?? ''),
        'town' => (string)($grave['town_id'] ?? ''),
        'idxkladb' => (string)($grave['idxkladb'] ?? ''),
        'lname' => (string)($grave['lname'] ?? ''),
        'fname' => (string)($grave['fname'] ?? ''),
        'mname' => (string)($grave['mname'] ?? ''),
        'dt1' => modDateInputValue($grave['dt1'] ?? ''),
        'dt2' => modDateInputValue($grave['dt2'] ?? ''),
        'pos1' => (string)($grave['pos1'] ?? ''),
        'pos2' => (string)($grave['pos2'] ?? ''),
        'pos3' => (string)($grave['pos3'] ?? ''),
        'photo1' => (string)($grave['photo1'] ?? ''),
        'photo2' => (string)($grave['photo2'] ?? ''),
        'photo3' => (string)($grave['photo3'] ?? ''),
        'moderation_note' => (string)($grave['moderation_note'] ?? ''),
    ];
}

function modBuildCemeteryFormData(array $cemetery): array
{
    return [
        'region' => (string)($cemetery['region_id'] ?? ''),
        'district' => (string)($cemetery['district'] ?? ''),
        'town' => (string)($cemetery['town'] ?? ''),
        'title' => (string)($cemetery['title'] ?? ''),
        'adress-cemetery' => (string)($cemetery['adress'] ?? ''),
        'gpsx' => (string)($cemetery['gpsx'] ?? ''),
        'gpsy' => (string)($cemetery['gpsy'] ?? ''),
        'scheme' => (string)($cemetery['scheme'] ?? ''),
        'moderation_note' => (string)($cemetery['moderation_note'] ?? ''),
    ];
}

function modRenderImageCard(?string $path, string $title): string
{
    $safeTitle = modEsc($title);
    if (modHasFile($path)) {
        return '<div class="mod-file-card"><img src="' . modEsc((string)$path) . '" alt="' . $safeTitle . '"><span>' . $safeTitle . '</span></div>';
    }
    return '<div class="mod-file-card is-empty"><span>' . $safeTitle . '</span><small>Файл відсутній</small></div>';
}

function modIsPositiveNumericString(string $value): bool
{
    return preg_match('/^[1-9][0-9]*$/', trim($value)) === 1;
}

function modRenderUploadCard(string $inputId, string $inputName, string $title, string $accept, ?string $existingPath, string $emptyText): string
{
    $safeId = modEsc($inputId);
    $safeName = modEsc($inputName);
    $safeTitle = modEsc($title);
    $safeAccept = modEsc($accept);
    $hasPreview = modHasFile($existingPath);
    $previewSrc = $hasPreview ? modEsc((string)$existingPath) : '';

    ob_start();
    ?>
    <div class="acm-file agf-upload-card mod-upload-card<?= $hasPreview ? ' has-preview' : '' ?>" data-upload-card="<?= $safeName ?>" data-preview-src="<?= $previewSrc ?>">
        <div class="acm-file-title-row">
            <span class="acm-file-title"><?= $safeTitle ?></span>
            <span id="<?= $safeId ?>-badge" class="agf-upload-badge"<?= $hasPreview ? '' : ' hidden' ?>>Фото завантажено</span>
        </div>
        <input id="<?= $safeId ?>" class="acm-file-input" type="file" name="<?= $safeName ?>" accept="<?= $safeAccept ?>">
        <label for="<?= $safeId ?>" id="<?= $safeId ?>-dropzone" class="agf-upload-dropzone">
            <div class="agf-upload-preview">
                <img id="<?= $safeId ?>-preview-img" alt="<?= $safeTitle ?>"<?= $hasPreview ? ' src="' . $previewSrc . '"' : '' ?>>
                <div class="agf-upload-empty">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                        <path d="M15 8h.01"></path>
                        <path d="M12.5 21h-6.5a3 3 0 0 1 -3 -3v-12a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v6.5"></path>
                        <path d="M3 16l5 -5c.928 -.893 2.072 -.893 3 0l4 4"></path>
                        <path d="M14 14l1 -1c.67 -.644 1.45 -.824 2.182 -.54"></path>
                        <path d="M16 19h6"></path>
                        <path d="M19 16v6"></path>
                    </svg>
                    <span><?= modEsc($emptyText) ?></span>
                </div>
            </div>
        </label>
        <div class="acm-file-control">
            <button type="button" id="<?= $safeId ?>-trigger" class="acm-file-btn"><?= $hasPreview ? 'Змінити' : 'Вибрати фото' ?></button>
            <button type="button" id="<?= $safeId ?>-view" class="acm-file-btn agf-view-btn"<?= $hasPreview ? '' : ' hidden' ?>>Переглянути</button>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function modRenderGraveEditForm(array $formData, int $graveId, string $tab, string $status, string $context = 'page'): string
{
    $district = modEsc((string)($formData['district'] ?? ''));
    $town = modEsc((string)($formData['town'] ?? ''));
    $cemetery = modEsc((string)($formData['idxkladb'] ?? ''));
    $listUrl = modEsc(modBuildPanelUrl($tab, $status));
    $isModal = $context === 'modal';
    if ($isModal) {
        ob_start();
        ?>
        <form class="mod-edit-form mod-edit-form--modal mod-edit-form--inline" id="modGraveForm" method="post" action="<?= modEsc(modBuildPanelUrl($tab, $status, 'edit', 'grave', $graveId)) ?>" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="mod_action" value="grave_update">
            <input type="hidden" name="edit_type" value="grave">
            <input type="hidden" name="edit_id" value="<?= (int)$graveId ?>">
            <input type="hidden" name="return_tab" value="<?= modEsc($tab) ?>">
            <input type="hidden" name="return_status" value="<?= modEsc($status) ?>">
            <input type="hidden" name="ajax_modal" value="1">
            <section class="mod-entry-modal__section">
                <h4>Основна інформація</h4>
                <div class="mod-entry-modal__grid mod-entry-modal__grid--three">
                    <label class="mod-field"><span>Прізвище *</span><input type="text" name="lname" value="<?= modEsc((string)($formData['lname'] ?? '')) ?>" required></label>
                    <label class="mod-field"><span>Імʼя *</span><input type="text" name="fname" value="<?= modEsc((string)($formData['fname'] ?? '')) ?>" required></label>
                    <label class="mod-field"><span>По батькові</span><input type="text" name="mname" value="<?= modEsc((string)($formData['mname'] ?? '')) ?>"></label>
                </div>
                <div class="mod-entry-modal__grid mod-entry-modal__grid--two mod-entry-modal__grid--compact-top">
                    <label class="mod-field"><span>Дата народження *</span><input type="date" name="dt1" value="<?= modEsc((string)($formData['dt1'] ?? '')) ?>" required></label>
                    <label class="mod-field"><span>Дата смерті *</span><input type="date" name="dt2" value="<?= modEsc((string)($formData['dt2'] ?? '')) ?>" required></label>
                </div>
            </section>
            <section class="mod-entry-modal__section">
                <h4>Розташування</h4>
                <div class="mod-entry-modal__grid mod-entry-modal__grid--three">
                    <label class="mod-field"><span>Область *</span><select id="mod-grave-region" name="region" data-role="region" required><?= modRegionOptions((string)($formData['region'] ?? '')) ?></select></label>
                    <label class="mod-field"><span>Район *</span><select id="mod-grave-district" name="district" data-role="district" data-selected="<?= $district ?>" required><?= modDistrictOptions((string)($formData['region'] ?? ''), (string)($formData['district'] ?? '')) ?></select></label>
                    <label class="mod-field"><span>Населений пункт *</span><select id="mod-grave-town" name="town" data-role="town" data-selected="<?= $town ?>" required><?= modTownOptions((string)($formData['region'] ?? ''), (string)($formData['district'] ?? ''), (string)($formData['town'] ?? '')) ?></select></label>
                </div>
                <div class="mod-entry-modal__grid mod-entry-modal__grid--two mod-entry-modal__grid--compact-top">
                    <label class="mod-field"><span>Кладовище *</span><select id="mod-grave-cemetery" name="idxkladb" data-role="cemetery" data-selected="<?= $cemetery ?>" required><?= modCemeteryOptions((string)($formData['district'] ?? ''), (string)($formData['idxkladb'] ?? '')) ?></select></label>
                    <div class="mod-entry-modal__grid mod-entry-modal__grid--three mod-entry-modal__grid-compact">
                        <label class="mod-field"><span>Квартал *</span><input type="text" name="pos1" value="<?= modEsc((string)($formData['pos1'] ?? '')) ?>" inputmode="numeric" pattern="[1-9][0-9]*" data-positive-number="1" required></label>
                        <label class="mod-field"><span>Ряд *</span><input type="text" name="pos2" value="<?= modEsc((string)($formData['pos2'] ?? '')) ?>" inputmode="numeric" pattern="[1-9][0-9]*" data-positive-number="1" required></label>
                        <label class="mod-field"><span>Місце *</span><input type="text" name="pos3" value="<?= modEsc((string)($formData['pos3'] ?? '')) ?>" inputmode="numeric" pattern="[1-9][0-9]*" data-positive-number="1" required></label>
                    </div>
                </div>
            </section>
            <section class="mod-entry-modal__section">
                <h4>Фотографії</h4>
                <div class="acm-file-grid agf-file-grid-two mod-file-grid mod-file-grid--three">
                    <?= modRenderUploadCard('mod-photo1', 'photo1', 'Фото поховання', '.jpg,.jpeg,.png,.gif', $formData['photo1'] ?? '', 'Фото не встановлено') ?>
                    <?= modRenderUploadCard('mod-photo2', 'photo2', 'Фото таблички', '.jpg,.jpeg,.png,.gif', $formData['photo2'] ?? '', 'Фото не встановлено') ?>
                    <?= modRenderUploadCard('mod-photo3', 'photo3', 'Додаткове фото', '.jpg,.jpeg,.png,.gif', $formData['photo3'] ?? '', 'Фото не встановлено') ?>
                </div>
            </section>
            <div class="mod-form-actions mod-form-actions--modal">
                <button type="button" class="mod-action-btn is-back" data-mod-cancel-edit>Скасувати</button>
                <button type="submit" class="mod-action-btn is-save">Зберегти зміни</button>
            </div>
        </form>
        <?php
        return ob_get_clean();
    }
    ob_start();
    ?>
    <form class="mod-edit-form<?= $isModal ? ' mod-edit-form--modal' : '' ?>" id="modGraveForm" method="post" action="<?= modEsc(modBuildPanelUrl($tab, $status, 'edit', 'grave', $graveId)) ?>" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="mod_action" value="grave_update">
        <input type="hidden" name="edit_type" value="grave">
        <input type="hidden" name="edit_id" value="<?= (int)$graveId ?>">
        <input type="hidden" name="return_tab" value="<?= modEsc($tab) ?>">
        <input type="hidden" name="return_status" value="<?= modEsc($status) ?>">
        <?php if ($isModal): ?>
            <input type="hidden" name="ajax_modal" value="1">
        <?php endif; ?>
        <fieldset class="mod-form-section">
            <legend class="mod-form-section-title">Дані поховання</legend>
            <div class="mod-form-row mod-form-row--three">
                <label class="mod-field"><span>Прізвище *</span><input type="text" name="lname" value="<?= modEsc((string)($formData['lname'] ?? '')) ?>" required></label>
                <label class="mod-field"><span>Імʼя *</span><input type="text" name="fname" value="<?= modEsc((string)($formData['fname'] ?? '')) ?>" required></label>
                <label class="mod-field"><span>По батькові</span><input type="text" name="mname" value="<?= modEsc((string)($formData['mname'] ?? '')) ?>"></label>
            </div>
            <div class="mod-form-row mod-form-row--two">
                <label class="mod-field"><span>Дата народження *</span><input type="date" name="dt1" value="<?= modEsc((string)($formData['dt1'] ?? '')) ?>" required></label>
                <label class="mod-field"><span>Дата смерті *</span><input type="date" name="dt2" value="<?= modEsc((string)($formData['dt2'] ?? '')) ?>" required></label>
            </div>
        </fieldset>
        <fieldset class="mod-form-section">
            <legend class="mod-form-section-title">Розташування</legend>
            <div class="mod-form-row mod-form-row--four">
                <label class="mod-field"><span>Область *</span><select id="mod-grave-region" name="region" data-role="region" required><?= modRegionOptions((string)($formData['region'] ?? '')) ?></select></label>
                <label class="mod-field"><span>Район *</span><select id="mod-grave-district" name="district" data-role="district" data-selected="<?= $district ?>" required><?= modDistrictOptions((string)($formData['region'] ?? ''), (string)($formData['district'] ?? '')) ?></select></label>
                <label class="mod-field"><span>Населений пункт *</span><select id="mod-grave-town" name="town" data-role="town" data-selected="<?= $town ?>" required><?= modTownOptions((string)($formData['region'] ?? ''), (string)($formData['district'] ?? ''), (string)($formData['town'] ?? '')) ?></select></label>
                <label class="mod-field"><span>Кладовище *</span><select id="mod-grave-cemetery" name="idxkladb" data-role="cemetery" data-selected="<?= $cemetery ?>" required><?= modCemeteryOptions((string)($formData['district'] ?? ''), (string)($formData['idxkladb'] ?? '')) ?></select></label>
            </div>
        </fieldset>
        <fieldset class="mod-form-section">
            <legend class="mod-form-section-title">Місце поховання</legend>
            <div class="mod-form-row mod-form-row--three">
                <label class="mod-field"><span>Квартал *</span><input type="text" name="pos1" value="<?= modEsc((string)($formData['pos1'] ?? '')) ?>" inputmode="numeric" pattern="[1-9][0-9]*" data-positive-number="1" required></label>
                <label class="mod-field"><span>Ряд *</span><input type="text" name="pos2" value="<?= modEsc((string)($formData['pos2'] ?? '')) ?>" inputmode="numeric" pattern="[1-9][0-9]*" data-positive-number="1" required></label>
                <label class="mod-field"><span>Місце *</span><input type="text" name="pos3" value="<?= modEsc((string)($formData['pos3'] ?? '')) ?>" inputmode="numeric" pattern="[1-9][0-9]*" data-positive-number="1" required></label>
            </div>
        </fieldset>
        <fieldset class="mod-form-section">
            <legend class="mod-form-section-title">Фотографії</legend>
            <div class="acm-file-grid agf-file-grid-two mod-file-grid mod-file-grid--three">
                <?= modRenderUploadCard('mod-photo1', 'photo1', 'Фото поховання', '.jpg,.jpeg,.png,.gif', $formData['photo1'] ?? '', 'Фото не встановлено') ?>
                <?= modRenderUploadCard('mod-photo2', 'photo2', 'Фото таблички', '.jpg,.jpeg,.png,.gif', $formData['photo2'] ?? '', 'Фото не встановлено') ?>
                <?= modRenderUploadCard('mod-photo3', 'photo3', 'Додаткове фото', '.jpg,.jpeg,.png,.gif', $formData['photo3'] ?? '', 'Фото не встановлено') ?>
            </div>
        </fieldset>
        <div class="mod-form-actions<?= $isModal ? ' mod-form-actions--modal' : '' ?>">
            <?php if ($isModal): ?>
                <button type="button" class="mod-action-btn is-back" data-mod-cancel-edit>Скасувати</button>
            <?php else: ?>
                <a class="mod-action-btn is-back" href="<?= $listUrl ?>">Повернутися до списку</a>
            <?php endif; ?>
            <button type="submit" class="mod-action-btn is-save">Зберегти зміни</button>
        </div>
    </form>
    <?php
    return ob_get_clean();
}

function modRenderCemeteryEditForm(array $formData, int $cemeteryId, string $tab, string $status, string $context = 'page'): string
{
    $district = modEsc((string)($formData['district'] ?? ''));
    $town = modEsc((string)($formData['town'] ?? ''));
    $listUrl = modEsc(modBuildPanelUrl($tab, $status));
    $isModal = $context === 'modal';
    if ($isModal) {
        ob_start();
        ?>
        <form class="mod-edit-form mod-edit-form--modal mod-edit-form--inline" id="modCemeteryForm" method="post" action="<?= modEsc(modBuildPanelUrl($tab, $status, 'edit', 'cemetery', $cemeteryId)) ?>" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="mod_action" value="cemetery_update">
            <input type="hidden" name="edit_type" value="cemetery">
            <input type="hidden" name="edit_id" value="<?= (int)$cemeteryId ?>">
            <input type="hidden" name="return_tab" value="<?= modEsc($tab) ?>">
            <input type="hidden" name="return_status" value="<?= modEsc($status) ?>">
            <input type="hidden" name="ajax_modal" value="1">
            <section class="mod-entry-modal__section">
                <h4>Основна інформація</h4>
                <div class="mod-entry-modal__grid mod-entry-modal__grid--two">
                    <label class="mod-field"><span>Область *</span><select id="mod-cemetery-region" name="region" data-role="region" required><?= modRegionOptions((string)($formData['region'] ?? '')) ?></select></label>
                    <div class="mod-entry-modal__field"><span>ID картки</span><strong>ID <?= (int)$cemeteryId ?></strong></div>
                    <label class="mod-field"><span>Район *</span><select id="mod-cemetery-district" name="district" data-role="district" data-selected="<?= $district ?>" required><?= modDistrictOptions((string)($formData['region'] ?? ''), (string)($formData['district'] ?? '')) ?></select></label>
                    <label class="mod-field"><span>Населений пункт *</span><select id="mod-cemetery-town" name="town" data-role="town" data-selected="<?= $town ?>" required><?= modTownOptions((string)($formData['region'] ?? ''), (string)($formData['district'] ?? ''), (string)($formData['town'] ?? '')) ?></select></label>
                </div>
            </section>
            <section class="mod-entry-modal__section">
                <h4>Дані кладовища</h4>
                <div class="mod-entry-modal__grid mod-entry-modal__grid--two">
                    <label class="mod-field"><span>Назва кладовища *</span><input type="text" name="title" value="<?= modEsc((string)($formData['title'] ?? '')) ?>" required></label>
                    <label class="mod-field"><span>Адреса</span><input type="text" name="cemetery-adr" value="<?= modEsc((string)($formData['adress-cemetery'] ?? '')) ?>"></label>
                    <label class="mod-field"><span>GPS X</span><input id="mod-cemetery-gpsx" type="text" name="gpsx" value="<?= modEsc((string)($formData['gpsx'] ?? '')) ?>"></label>
                    <label class="mod-field"><span>GPS Y</span><input id="mod-cemetery-gpsy" type="text" name="gpsy" value="<?= modEsc((string)($formData['gpsy'] ?? '')) ?>"></label>
                </div>
                <div class="mod-map-picker-row">
                    <button type="button" id="mod-cemetery-open-map" class="acm-btn acm-btn--ghost mod-map-picker-btn">Вказати на карті</button>
                    <small>Можна скоригувати точку прямо на карті й застосувати координати.</small>
                </div>
            </section>
            <section class="mod-entry-modal__section">
                <h4>Схема кладовища</h4>
                <div class="acm-file-grid mod-file-grid mod-file-grid--single">
                    <?= modRenderUploadCard('mod-scheme', 'scheme', 'Схема кладовища', '.jpg,.jpeg,.png', $formData['scheme'] ?? '', 'Натисніть для вибору або перетягніть файл') ?>
                </div>
            </section>
            <div class="mod-form-actions mod-form-actions--modal">
                <button type="button" class="mod-action-btn is-back" data-mod-cancel-edit>Скасувати</button>
                <button type="submit" class="mod-action-btn is-save">Зберегти зміни</button>
            </div>
        </form>
        <?php
        return ob_get_clean();
    }
    ob_start();
    ?>
    <form class="mod-edit-form<?= $isModal ? ' mod-edit-form--modal' : '' ?>" id="modCemeteryForm" method="post" action="<?= modEsc(modBuildPanelUrl($tab, $status, 'edit', 'cemetery', $cemeteryId)) ?>" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="mod_action" value="cemetery_update">
        <input type="hidden" name="edit_type" value="cemetery">
        <input type="hidden" name="edit_id" value="<?= (int)$cemeteryId ?>">
        <input type="hidden" name="return_tab" value="<?= modEsc($tab) ?>">
        <input type="hidden" name="return_status" value="<?= modEsc($status) ?>">
        <?php if ($isModal): ?>
            <input type="hidden" name="ajax_modal" value="1">
        <?php endif; ?>
        <fieldset class="mod-form-section">
            <legend class="mod-form-section-title">Розташування</legend>
            <div class="mod-form-row mod-form-row--three">
                <label class="mod-field"><span>Область *</span><select id="mod-cemetery-region" name="region" data-role="region" required><?= modRegionOptions((string)($formData['region'] ?? '')) ?></select></label>
                <label class="mod-field"><span>Район *</span><select id="mod-cemetery-district" name="district" data-role="district" data-selected="<?= $district ?>" required><?= modDistrictOptions((string)($formData['region'] ?? ''), (string)($formData['district'] ?? '')) ?></select></label>
                <label class="mod-field"><span>Населений пункт *</span><select id="mod-cemetery-town" name="town" data-role="town" data-selected="<?= $town ?>" required><?= modTownOptions((string)($formData['region'] ?? ''), (string)($formData['district'] ?? ''), (string)($formData['town'] ?? '')) ?></select></label>
            </div>
        </fieldset>
        <fieldset class="mod-form-section">
            <legend class="mod-form-section-title">Основні дані</legend>
            <div class="mod-form-row mod-form-row--two">
                <label class="mod-field"><span>Назва кладовища *</span><input type="text" name="title" value="<?= modEsc((string)($formData['title'] ?? '')) ?>" required></label>
                <label class="mod-field"><span>Адреса</span><input type="text" name="cemetery-adr" value="<?= modEsc((string)($formData['adress-cemetery'] ?? '')) ?>"></label>
            </div>
            <div class="mod-form-row mod-form-row--two">
                <label class="mod-field"><span>GPS X</span><input id="mod-cemetery-gpsx" type="text" name="gpsx" value="<?= modEsc((string)($formData['gpsx'] ?? '')) ?>"></label>
                <label class="mod-field"><span>GPS Y</span><input id="mod-cemetery-gpsy" type="text" name="gpsy" value="<?= modEsc((string)($formData['gpsy'] ?? '')) ?>"></label>
            </div>
            <div class="mod-map-picker-row">
                <button type="button" id="mod-cemetery-open-map" class="acm-btn acm-btn--ghost mod-map-picker-btn">Вказати на карті</button>
                <small>Поточна точка відкриється на карті. Можна змінити мітку і застосувати нові координати.</small>
            </div>
        </fieldset>
        <fieldset class="mod-form-section">
            <legend class="mod-form-section-title">Схема кладовища</legend>
            <div class="acm-file-grid mod-file-grid mod-file-grid--single">
                <?= modRenderUploadCard('mod-scheme', 'scheme', 'Схема кладовища', '.jpg,.jpeg,.png', $formData['scheme'] ?? '', 'Натисніть для вибору або перетягніть файл') ?>
            </div>
        </fieldset>
        <div class="mod-form-actions<?= $isModal ? ' mod-form-actions--modal' : '' ?>">
            <?php if ($isModal): ?>
                <button type="button" class="mod-action-btn is-back" data-mod-cancel-edit>Скасувати</button>
            <?php else: ?>
                <a class="mod-action-btn is-back" href="<?= $listUrl ?>">Повернутися до списку</a>
            <?php endif; ?>
            <button type="submit" class="mod-action-btn is-save">Зберегти зміни</button>
        </div>
    </form>
    <?php
    return ob_get_clean();
}

function modRenderEditFormByType(string $type, array $formData, int $id, string $tab, string $status, string $context = 'page'): string
{
    if ($type === 'grave') {
        return modRenderGraveEditForm($formData, $id, $tab, $status, $context);
    }
    if ($type === 'cemetery') {
        return modRenderCemeteryEditForm($formData, $id, $tab, $status, $context);
    }
    return '';
}

function modLoadGraveCardRow(mysqli $dblink, int $graveId): ?array
{
    if ($graveId <= 0) {
        return null;
    }
    $sql = "SELECT g.idx, g.fname, g.lname, g.mname, g.dt1, g.dt2, g.idtadd, g.idxadd, g.idxkladb, g.pos1, g.pos2, g.pos3, g.photo1, g.photo2, g.photo3, g.moderation_status, g.moderation_submitted_at, g.moderation_reviewed_at, g.moderation_reviewed_by, g.moderation_note, g.moderation_reject_reason, u.fname AS author_fname, u.lname AS author_lname, ur.fname AS reviewer_fname, ur.lname AS reviewer_lname, c.title AS cemetery_title, m.title AS town_name, d.title AS district_name, r.title AS region_name FROM grave g LEFT JOIN users u ON g.idxadd = u.idx LEFT JOIN users ur ON g.moderation_reviewed_by = ur.idx LEFT JOIN cemetery c ON g.idxkladb = c.idx LEFT JOIN misto m ON c.town = m.idx LEFT JOIN district d ON c.district = d.idx LEFT JOIN region r ON d.region = r.idx WHERE g.idx = $graveId LIMIT 1";
    $res = mysqli_query($dblink, $sql);
    return $res ? (mysqli_fetch_assoc($res) ?: null) : null;
}

function modLoadCemeteryCardRow(mysqli $dblink, int $cemeteryId): ?array
{
    if ($cemeteryId <= 0) {
        return null;
    }
    $sql = "SELECT c.idx, c.title, c.town, c.district, c.adress, c.dtadd, c.idxadd, c.scheme, c.gpsx, c.gpsy, c.moderation_status, c.moderation_submitted_at, c.moderation_reviewed_at, c.moderation_reviewed_by, c.moderation_note, c.moderation_reject_reason, u.fname AS author_fname, u.lname AS author_lname, ur.fname AS reviewer_fname, ur.lname AS reviewer_lname, m.title AS town_name, d.title AS district_name, r.title AS region_name FROM cemetery c LEFT JOIN users u ON c.idxadd = u.idx LEFT JOIN users ur ON c.moderation_reviewed_by = ur.idx LEFT JOIN misto m ON c.town = m.idx LEFT JOIN district d ON c.district = d.idx LEFT JOIN region r ON d.region = r.idx WHERE c.idx = $cemeteryId LIMIT 1";
    $res = mysqli_query($dblink, $sql);
    return $res ? (mysqli_fetch_assoc($res) ?: null) : null;
}

function modBuildGraveItemFromRow(array $row): array
{
    $idx = (int)($row['idx'] ?? 0);
    $title = trim(implode(' ', array_filter([trim((string)($row['lname'] ?? '')), trim((string)($row['fname'] ?? '')), trim((string)($row['mname'] ?? ''))])));
    $title = $title !== '' ? $title : 'Без ПІБ';
    $pos1 = trim((string)($row['pos1'] ?? ''));
    $pos2 = trim((string)($row['pos2'] ?? ''));
    $pos3 = trim((string)($row['pos3'] ?? ''));
    $cemTitle = trim((string)($row['cemetery_title'] ?? ''));
    $town = trim((string)($row['town_name'] ?? ''));
    $district = trim((string)($row['district_name'] ?? ''));
    $region = trim((string)($row['region_name'] ?? ''));
    $locationParts = [];
    if ($region !== '') { $locationParts[] = $region . ' обл.'; }
    if ($district !== '') { $locationParts[] = $district . ' р-н'; }
    if ($town !== '') { $locationParts[] = $town; }
    $status = (string)($row['moderation_status'] ?? 'pending');
    if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
        $status = 'pending';
    }
    $submittedRaw = (string)($row['moderation_submitted_at'] ?? '');
    if ($submittedRaw === '') { $submittedRaw = (string)($row['idtadd'] ?? ''); }
    $reviewedRaw = trim((string)($row['moderation_reviewed_at'] ?? ''));
    $actionRaw = ($status === 'pending' || $reviewedRaw === '' || $reviewedRaw === '0000-00-00 00:00:00')
        ? $submittedRaw
        : $reviewedRaw;
    $previewPath = modResolveGravePreview($row, $idx);

    return [
        'id' => (string)$idx,
        'type' => 'grave',
        'title' => $title,
        'dates' => modFormatDateRange($row['dt1'] ?? null, $row['dt2'] ?? null),
        'location' => !empty($locationParts) ? implode(', ', $locationParts) : 'Локація не вказана',
        'region' => $region !== '' ? $region : '-',
        'district' => $district !== '' ? $district : '-',
        'town' => $town !== '' ? $town : '-',
        'cemetery' => $cemTitle !== '' ? $cemTitle : '-',
        'pos1' => $pos1 !== '' ? $pos1 : '-',
        'pos2' => $pos2 !== '' ? $pos2 : '-',
        'pos3' => $pos3 !== '' ? $pos3 : '-',
        'address' => '-',
        'moderation_note' => trim((string)($row['moderation_note'] ?? '')),
        'reject_reason' => trim((string)($row['moderation_reject_reason'] ?? '')),
        'author' => modAuthorName($row['author_fname'] ?? null, $row['author_lname'] ?? null, (int)($row['idxadd'] ?? 0)),
        'reviewer' => modAuthorName($row['reviewer_fname'] ?? null, $row['reviewer_lname'] ?? null, (int)($row['moderation_reviewed_by'] ?? 0)),
        'submitted_iso' => $submittedRaw,
        'action_iso' => $actionRaw,
        'status' => $status,
        'has_photo' => $previewPath !== '',
        'preview_path' => $previewPath,
        'photo1' => modResolvedFilePath($row['photo1'] ?? ''),
        'photo2' => modResolvedFilePath($row['photo2'] ?? ''),
        'photo3' => modResolvedFilePath($row['photo3'] ?? ''),
        'scheme' => '',
        'gpsx' => '',
        'gpsy' => '',
        'edit_url' => modBuildPanelUrl('grave', $status, 'edit', 'grave', $idx),
    ];
}

function modBuildCemeteryItemFromRow(array $row): array
{
    $status = (string)($row['moderation_status'] ?? 'pending');
    if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
        $status = 'pending';
    }
    $submittedRaw = (string)($row['moderation_submitted_at'] ?? '');
    if ($submittedRaw === '') { $submittedRaw = (string)($row['dtadd'] ?? ''); }
    $reviewedRaw = trim((string)($row['moderation_reviewed_at'] ?? ''));
    $actionRaw = ($status === 'pending' || $reviewedRaw === '' || $reviewedRaw === '0000-00-00 00:00:00')
        ? $submittedRaw
        : $reviewedRaw;
    $locationParts = [];
    $town = trim((string)($row['town_name'] ?? ''));
    $district = trim((string)($row['district_name'] ?? ''));
    $region = trim((string)($row['region_name'] ?? ''));
    $address = trim((string)($row['adress'] ?? ''));
    if ($town !== '') { $locationParts[] = $town; }
    if ($district !== '') { $locationParts[] = $district . ' р-н'; }
    if ($region !== '') { $locationParts[] = $region . ' обл.'; }
    $previewPath = modResolveCemeteryPreview($row);

    return [
        'id' => (string)($row['idx'] ?? ''),
        'type' => 'cemetery',
        'title' => (string)($row['title'] ?? 'Кладовище'),
        'location' => !empty($locationParts) ? implode(', ', $locationParts) : 'Локація не вказана',
        'region' => $region !== '' ? $region : '-',
        'district' => $district !== '' ? $district : '-',
        'town' => $town !== '' ? $town : '-',
        'cemetery' => '-',
        'pos1' => '-',
        'pos2' => '-',
        'pos3' => '-',
        'address' => $address !== '' ? $address : '-',
        'moderation_note' => trim((string)($row['moderation_note'] ?? '')),
        'reject_reason' => trim((string)($row['moderation_reject_reason'] ?? '')),
        'author' => modAuthorName($row['author_fname'] ?? null, $row['author_lname'] ?? null, (int)($row['idxadd'] ?? 0)),
        'reviewer' => modAuthorName($row['reviewer_fname'] ?? null, $row['reviewer_lname'] ?? null, (int)($row['moderation_reviewed_by'] ?? 0)),
        'submitted_iso' => $submittedRaw,
        'action_iso' => $actionRaw,
        'status' => $status,
        'has_photo' => $previewPath !== '',
        'preview_path' => $previewPath,
        'photo1' => '',
        'photo2' => '',
        'photo3' => '',
        'scheme' => modResolvedFilePath($row['scheme'] ?? ''),
        'gpsx' => trim((string)($row['gpsx'] ?? '')),
        'gpsy' => trim((string)($row['gpsy'] ?? '')),
        'edit_url' => modBuildPanelUrl('cemetery', $status, 'edit', 'cemetery', (int)($row['idx'] ?? 0)),
    ];
}

function modBuildItemPayload(array $item, array $statusLabels, array $typeLabels): array
{
    $statusKey = isset($statusLabels[$item['status']]) ? (string)$item['status'] : 'pending';
    $typeKey = (string)($item['type'] ?? '');
    $typeLabel = $typeLabels[$typeKey] ?? $typeKey;
    $submittedDisplay = modFormatDateTime($item['submitted_iso'] ?? '');
    $datesText = trim((string)($item['dates'] ?? ''));
    $locationText = trim((string)($item['location'] ?? '-'));
    $regionText = trim((string)($item['region'] ?? '-'));
    $districtText = trim((string)($item['district'] ?? '-'));
    $townText = trim((string)($item['town'] ?? '-'));
    $cemeteryText = trim((string)($item['cemetery'] ?? '-'));
    $addressText = trim((string)($item['address'] ?? '-'));
    $titleText = (string)($item['title'] ?? '');
    $idText = trim((string)($item['id'] ?? '-'));
    $authorText = (string)($item['author'] ?? '-');
    $reviewerText = trim((string)($item['reviewer'] ?? ''));
    $regionDisplay = modAreaLabel($regionText, 'область');
    $districtDisplay = modAreaLabel($districtText, 'район');
    $locationDisplay = implode(', ', array_filter([
        $regionDisplay !== '-' ? $regionDisplay : '',
        $districtDisplay !== '-' ? $districtDisplay : '',
        $townText !== '-' ? $townText : '',
    ]));
    $gravePlacementText = implode(', ', array_filter([
        $cemeteryText !== '-' ? $cemeteryText : '',
        ($item['pos1'] ?? '-') !== '-' ? 'кв. ' . $item['pos1'] : '',
        ($item['pos3'] ?? '-') !== '-' ? 'місце ' . $item['pos3'] : '',
        ($item['pos2'] ?? '-') !== '-' ? 'ряд ' . $item['pos2'] : '',
    ]));
    $summaryText = $typeKey === 'grave'
        ? $gravePlacementText
        : ($addressText !== '-' ? $addressText : $locationDisplay);
    $searchText = implode(' ', array_filter([
        $titleText,
        $typeLabel,
        $datesText,
        $locationText,
        $regionText,
        $districtText,
        $townText,
        $cemeteryText,
        $addressText,
        $idText,
        $authorText,
        $statusLabels[$statusKey] ?? '',
    ]));

    return [
        'id' => $idText,
        'type' => $typeKey,
        'type_label' => $typeLabel,
        'title' => $titleText,
        'status' => $statusKey,
        'status_label' => $statusLabels[$statusKey] ?? 'На модерації',
        'submitted' => $submittedDisplay,
        'submitted_iso' => (string)($item['submitted_iso'] ?? ''),
        'author' => $authorText,
        'reviewer' => $reviewerText,
        'region' => $regionText,
        'district' => $districtText,
        'town' => $townText,
        'cemetery' => $cemeteryText,
        'address' => $addressText,
        'dates' => $datesText,
        'summary' => $summaryText,
        'pos1' => (string)($item['pos1'] ?? '-'),
        'pos2' => (string)($item['pos2'] ?? '-'),
        'pos3' => (string)($item['pos3'] ?? '-'),
        'note' => trim((string)($item['moderation_note'] ?? '')),
        'reject_reason' => trim((string)($item['reject_reason'] ?? '')),
        'edit_url' => (string)($item['edit_url'] ?? '#'),
        'preview' => (string)($item['preview_path'] ?? ''),
        'photo1' => (string)($item['photo1'] ?? ''),
        'photo2' => (string)($item['photo2'] ?? ''),
        'photo3' => (string)($item['photo3'] ?? ''),
        'scheme' => (string)($item['scheme'] ?? ''),
        'gpsx' => (string)($item['gpsx'] ?? ''),
        'gpsy' => (string)($item['gpsy'] ?? ''),
        'action_iso' => (string)($item['action_iso'] ?? ($item['submitted_iso'] ?? '')),
        'search' => $searchText,
        'location_display' => $locationDisplay !== '' ? $locationDisplay : 'Локація не вказана',
        'location_text' => $locationText,
        'info_primary' => $typeKey === 'grave'
            ? ($summaryText !== '' ? $summaryText : 'Місце поховання не вказано')
            : ($locationDisplay !== '' ? $locationDisplay : 'Локація не вказана'),
        'info_secondary' => $typeKey === 'grave'
            ? ($datesText !== '' ? $datesText : 'Дати не вказані')
            : ($addressText !== '-' ? $addressText : 'Адресу не вказано'),
        'status_class' => 'mod-entry-card__status mod-entry-card__status--' . $statusKey,
    ];
}

function modJsonResponse(array $payload): void
{
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
$defaultTab = modNormalizeTab((string)($_GET['tab'] ?? ($_POST['return_tab'] ?? 'grave')));
$defaultStatus = modNormalizeStatus((string)($_GET['status'] ?? ($_POST['return_status'] ?? 'pending')));
$requestedView = (string)($_GET['view'] ?? '');
$postedAction = (string)($_POST['mod_action'] ?? '');
$currentView = $requestedView === 'edit' || ($postedAction !== '' && $postedAction !== 'send_user_notification') ? 'edit' : 'list';
$editType = (string)($_GET['type'] ?? ($_POST['edit_type'] ?? ''));
$editType = in_array($editType, ['grave', 'cemetery'], true) ? $editType : '';
$editId = (int)($_GET['id'] ?? ($_POST['edit_id'] ?? 0));
$isAjaxEditForm = isset($_GET['ajax_edit_form']) && $_GET['ajax_edit_form'] == '1';
$isAjaxModal = $_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['ajax_modal'] ?? '') === '1';
$activeSideTab = $currentView === 'edit' && $editType !== '' ? $editType : $defaultTab;
$activeTypeFilter = $defaultTab;
$editMessageText = '';
$editMessageType = '';
$notifyMessageText = '';
$notifyMessageType = '';
$graveFormData = [];
$cemeteryFormData = [];
$graveEditRecord = null;
$cemeteryEditRecord = null;

if ((isset($_GET['ajax_districts']) || isset($_GET['ajax_settlements']) || isset($_GET['ajax_cemeteries'])) && !$hasModerationAccess) {
    http_response_code($accessDeniedCode);
    echo $accessDeniedCode === 401 ? 'Потрібна авторизація' : 'Доступ заборонено';
    exit;
}
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

if (!$hasModerationAccess) {
    modRenderAccessPage($accessDeniedCode);
    exit;
}

$graveItems = [];
$cemeteryItems = [];
$dblink = DbConnect();

if (isset($_GET['ajax_pending_feed']) && (string)($_GET['ajax_pending_feed'] ?? '') === '1') {
    if (!$hasModerationAccess) {
        mysqli_close($dblink);
        http_response_code($accessDeniedCode);
        modJsonResponse([
            'success' => false,
            'message' => $accessDeniedCode === 401 ? 'Потрібна авторизація' : 'Доступ заборонено',
        ]);
    }

    $sinceRaw = trim((string)($_GET['since'] ?? ''));
    $sinceTs = strtotime($sinceRaw);
    $sinceSql = $sinceTs !== false ? date('Y-m-d H:i:s', $sinceTs) : '';
    $pendingItems = [];

    $graveWhere = "COALESCE(g.moderation_status, 'pending') = 'pending'";
    if ($sinceSql !== '') {
        $graveWhere .= " AND COALESCE(g.moderation_submitted_at, g.idtadd) > '" . mysqli_real_escape_string($dblink, $sinceSql) . "'";
    }
    $graveFeedSql = "SELECT g.idx, g.fname, g.lname, g.mname, g.dt1, g.dt2, g.idtadd, g.idxadd, g.idxkladb, g.pos1, g.pos2, g.pos3, g.photo1, g.photo2, g.photo3, g.moderation_status, g.moderation_submitted_at, g.moderation_reviewed_at, g.moderation_reviewed_by, g.moderation_note, g.moderation_reject_reason, u.fname AS author_fname, u.lname AS author_lname, ur.fname AS reviewer_fname, ur.lname AS reviewer_lname, c.title AS cemetery_title, m.title AS town_name, d.title AS district_name, r.title AS region_name FROM grave g LEFT JOIN users u ON g.idxadd = u.idx LEFT JOIN users ur ON g.moderation_reviewed_by = ur.idx LEFT JOIN cemetery c ON g.idxkladb = c.idx LEFT JOIN misto m ON c.town = m.idx LEFT JOIN district d ON c.district = d.idx LEFT JOIN region r ON d.region = r.idx WHERE $graveWhere ORDER BY COALESCE(g.moderation_submitted_at, g.idtadd) ASC LIMIT 80";
    $graveFeedRes = mysqli_query($dblink, $graveFeedSql);
    if ($graveFeedRes) {
        while ($row = mysqli_fetch_assoc($graveFeedRes)) {
            $pendingItems[] = modBuildItemPayload(modBuildGraveItemFromRow($row), $statusLabels, $typeLabels);
        }
    }

    $cemWhere = "COALESCE(c.moderation_status, 'pending') = 'pending'";
    if ($sinceSql !== '') {
        $cemWhere .= " AND COALESCE(c.moderation_submitted_at, c.dtadd) > '" . mysqli_real_escape_string($dblink, $sinceSql) . "'";
    }
    $cemFeedSql = "SELECT c.idx, c.title, c.town, c.district, c.adress, c.dtadd, c.idxadd, c.scheme, c.gpsx, c.gpsy, c.moderation_status, c.moderation_submitted_at, c.moderation_reviewed_at, c.moderation_reviewed_by, c.moderation_note, c.moderation_reject_reason, u.fname AS author_fname, u.lname AS author_lname, ur.fname AS reviewer_fname, ur.lname AS reviewer_lname, m.title AS town_name, d.title AS district_name, r.title AS region_name FROM cemetery c LEFT JOIN users u ON c.idxadd = u.idx LEFT JOIN users ur ON c.moderation_reviewed_by = ur.idx LEFT JOIN misto m ON c.town = m.idx LEFT JOIN district d ON c.district = d.idx LEFT JOIN region r ON d.region = r.idx WHERE $cemWhere ORDER BY COALESCE(c.moderation_submitted_at, c.dtadd) ASC LIMIT 80";
    $cemFeedRes = mysqli_query($dblink, $cemFeedSql);
    if ($cemFeedRes) {
        while ($row = mysqli_fetch_assoc($cemFeedRes)) {
            $pendingItems[] = modBuildItemPayload(modBuildCemeteryItemFromRow($row), $statusLabels, $typeLabels);
        }
    }

    usort($pendingItems, static function (array $left, array $right): int {
        $leftTime = strtotime((string)($left['submitted_iso'] ?? '')) ?: 0;
        $rightTime = strtotime((string)($right['submitted_iso'] ?? '')) ?: 0;
        return $leftTime <=> $rightTime;
    });

    mysqli_close($dblink);
    modJsonResponse([
        'success' => true,
        'items' => $pendingItems,
        'server_time' => date('Y-m-d H:i:s'),
    ]);
}

if ($isAjaxEditForm) {
    $ajaxType = in_array((string)($_GET['type'] ?? ''), ['grave', 'cemetery'], true) ? (string)$_GET['type'] : '';
    $ajaxId = (int)($_GET['id'] ?? 0);
    $ajaxTab = modNormalizeTab((string)($_GET['tab'] ?? $defaultTab));
    $ajaxStatus = modNormalizeStatus((string)($_GET['status'] ?? $defaultStatus));
    $formHtml = '';
    $title = '';

    if ($ajaxType === 'grave') {
        $graveEditRecord = modLoadGraveForEdit($dblink, $ajaxId);
        if ($graveEditRecord) {
            $graveFormData = modBuildGraveFormData($graveEditRecord);
            $formHtml = modRenderGraveEditForm($graveFormData, $ajaxId, $ajaxTab, $ajaxStatus, 'modal');
            $title = trim((string)(($graveFormData['lname'] ?? '') . ' ' . ($graveFormData['fname'] ?? '') . ' ' . ($graveFormData['mname'] ?? '')));
        }
    } elseif ($ajaxType === 'cemetery') {
        $cemeteryEditRecord = modLoadCemeteryForEdit($dblink, $ajaxId);
        if ($cemeteryEditRecord) {
            $cemeteryFormData = modBuildCemeteryFormData($cemeteryEditRecord);
            $formHtml = modRenderCemeteryEditForm($cemeteryFormData, $ajaxId, $ajaxTab, $ajaxStatus, 'modal');
            $title = trim((string)($cemeteryFormData['title'] ?? ''));
        }
    }

    if ($formHtml === '') {
        mysqli_close($dblink);
        modJsonResponse([
            'success' => false,
            'message' => 'Запис для редагування не знайдено.',
        ]);
    }

    mysqli_close($dblink);
    modJsonResponse([
        'success' => true,
        'title' => $title !== '' ? $title : ($ajaxType === 'grave' ? 'Редагування поховання' : 'Редагування кладовища'),
        'formHtml' => $formHtml,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['mod_action'] ?? '');
    $editType = (string)($_POST['edit_type'] ?? $editType);
    $editId = (int)($_POST['edit_id'] ?? $editId);
    $currentView = 'edit';

    if ($action === 'send_user_notification') {
        $targetUserId = (int)($_POST['notify_user_id'] ?? 0);
        $title = trim((string)($_POST['notify_title'] ?? ''));
        $body = trim((string)($_POST['notify_body'] ?? ''));
        $category = normalizeNotificationCategory((string)($_POST['notify_category'] ?? 'manual'));
        $priority = normalizeNotificationPriority((string)($_POST['notify_priority'] ?? 'normal'));
        $actionUrl = trim((string)($_POST['notify_action_url'] ?? ''));
        $actionLabel = trim((string)($_POST['notify_action_label'] ?? ''));
        $senderUserId = (int)($_SESSION['uzver'] ?? 0);
        $senderRole = (function_exists('hasRole') && defined('ROLE_CREATOR') && hasRole((int)($_SESSION['status'] ?? 0), ROLE_CREATOR))
            ? 'admin'
            : 'moderator';

        if ($targetUserId <= 0 || $title === '' || $body === '') {
            $notifyMessageType = 'error';
            $notifyMessageText = 'Заповніть ID користувача, заголовок і текст.';
        } elseif (!function_exists('createUserNotification')) {
            $notifyMessageType = 'error';
            $notifyMessageText = 'Функція повідомлень недоступна.';
        } else {
            $notificationId = createUserNotification(
                $targetUserId,
                $title,
                $body,
                $category,
                $priority,
                $actionUrl !== '' ? $actionUrl : null,
                $actionLabel !== '' ? $actionLabel : null,
                'manual',
                null,
                $senderUserId,
                $senderRole,
                null,
                1,
                $dblink
            );

            if ($notificationId > 0) {
                $notifyMessageType = 'success';
                $notifyMessageText = 'Повідомлення надіслано.';
            } else {
                $notifyMessageType = 'error';
                $notifyMessageText = 'Не вдалося надіслати повідомлення.';
            }
        }
    } elseif ($action === 'moderation_decision') {
        $targetType = in_array((string)($_POST['target_type'] ?? ''), ['grave', 'cemetery'], true) ? (string)$_POST['target_type'] : '';
        $targetId = (int)($_POST['target_id'] ?? 0);
        $decision = in_array((string)($_POST['decision'] ?? ''), ['approved', 'rejected'], true) ? (string)$_POST['decision'] : '';
        $moderationNote = trim((string)($_POST['note'] ?? ''));
        $rejectReason = trim((string)($_POST['reject_reason'] ?? ''));
        $moderatorId = (int)($_SESSION['uzver'] ?? 0);
        $itemPayload = null;
        $decisionAtIso = '';
        $journalActorText = '';
        $authorId = 0;
        $notifyActionUrl = '';
        $senderRole = (function_exists('hasRole') && defined('ROLE_CREATOR') && hasRole((int)($_SESSION['status'] ?? 0), ROLE_CREATOR))
            ? 'admin'
            : 'moderator';

        if ($targetType === '' || $targetId <= 0 || $decision === '') {
            $editMessageType = 'error';
            $editMessageText = 'Некоректні параметри рішення модерації.';
        } elseif ($decision === 'rejected' && $rejectReason === '') {
            $editMessageType = 'error';
            $editMessageText = 'Вкажіть причину відхилення.';
        } else {
            $rejectReasonForSave = $decision === 'rejected' ? $rejectReason : '';
            if ($targetType === 'grave') {
                $stmt = mysqli_prepare($dblink, 'UPDATE grave SET moderation_status = ?, moderation_note = ?, moderation_reject_reason = ?, moderation_reviewed_at = NOW(), moderation_reviewed_by = ? WHERE idx = ? LIMIT 1');
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, 'sssii', $decision, $moderationNote, $rejectReasonForSave, $moderatorId, $targetId);
                }
            } else {
                $stmt = mysqli_prepare($dblink, 'UPDATE cemetery SET moderation_status = ?, moderation_note = ?, moderation_reject_reason = ?, moderation_reviewed_at = NOW(), moderation_reviewed_by = ? WHERE idx = ? LIMIT 1');
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, 'sssii', $decision, $moderationNote, $rejectReasonForSave, $moderatorId, $targetId);
                }
            }

            if (empty($stmt)) {
                $editMessageType = 'error';
                $editMessageText = 'Помилка підготовки запиту: ' . mysqli_error($dblink);
            } else {
                $saved = mysqli_stmt_execute($stmt);
                $stmtError = mysqli_stmt_error($stmt);
                mysqli_stmt_close($stmt);
                if (!$saved) {
                    $editMessageType = 'error';
                    $editMessageText = 'Помилка оновлення: ' . ($stmtError !== '' ? $stmtError : mysqli_error($dblink));
                } else {
                    if ($targetType === 'grave') {
                        $graveCardRow = modLoadGraveCardRow($dblink, $targetId);
                        if ($graveCardRow) {
                            $reviewedAtRaw = trim((string)($graveCardRow['moderation_reviewed_at'] ?? ''));
                            if ($reviewedAtRaw !== '' && $reviewedAtRaw !== '0000-00-00 00:00:00') {
                                $decisionAtIso = $reviewedAtRaw;
                            }
                            $itemPayload = modBuildItemPayload(modBuildGraveItemFromRow($graveCardRow), $statusLabels, $typeLabels);
                            $authorId = (int)($graveCardRow['idxadd'] ?? 0);
                            $notifyActionUrl = '/cardout.php?idx=' . $targetId;
                        }
                    } else {
                        $cemeteryCardRow = modLoadCemeteryCardRow($dblink, $targetId);
                        if ($cemeteryCardRow) {
                            $reviewedAtRaw = trim((string)($cemeteryCardRow['moderation_reviewed_at'] ?? ''));
                            if ($reviewedAtRaw !== '' && $reviewedAtRaw !== '0000-00-00 00:00:00') {
                                $decisionAtIso = $reviewedAtRaw;
                            }
                            $itemPayload = modBuildItemPayload(modBuildCemeteryItemFromRow($cemeteryCardRow), $statusLabels, $typeLabels);
                            $authorId = (int)($cemeteryCardRow['idxadd'] ?? 0);
                            $notifyActionUrl = '/cemetery.php?idx=' . $targetId;
                        }
                    }
                    if ($decisionAtIso === '') {
                        $decisionAtIso = date('Y-m-d H:i:s');
                    }
                    if ($moderatorId > 0) {
                        $actorRes = mysqli_query($dblink, 'SELECT fname, lname FROM users WHERE idx = ' . $moderatorId . ' LIMIT 1');
                        if ($actorRes && $actorRow = mysqli_fetch_assoc($actorRes)) {
                            $journalActorText = modAuthorName($actorRow['fname'] ?? null, $actorRow['lname'] ?? null, $moderatorId);
                        }
                    }
                    if ($journalActorText === '') {
                        $journalActorText = modAuthorName(null, null, $moderatorId);
                    }

                    if ($authorId > 0 && function_exists('createUserNotification')) {
                        $entityLabel = $targetType === 'grave' ? 'поховання' : 'кладовища';
                        $decisionLabel = $decision === 'approved' ? 'схвалено' : 'відхилено';
                        $notifyTitle = $decision === 'approved' ? 'Публікацію схвалено' : 'Публікацію відхилено';
                        $notifyBody = 'Ваш запис ' . $entityLabel . ' ' . $decisionLabel . '.';
                        if ($decision === 'rejected' && $rejectReason !== '') {
                            $notifyBody .= ' Причина: ' . $rejectReason . '.';
                        }

                        createUserNotification(
                            $authorId,
                            $notifyTitle,
                            $notifyBody,
                            'moderation',
                            'normal',
                            $notifyActionUrl !== '' ? $notifyActionUrl : null,
                            'Переглянути',
                            'moderation',
                            $targetId,
                            $moderatorId,
                            $senderRole,
                            null,
                            1,
                            $dblink
                        );
                    }

                    $editMessageType = 'success';
                    $editMessageText = $decision === 'approved' ? 'Запис схвалено.' : 'Запис відхилено.';
                }
            }
        }

        if ($isAjaxModal) {
            mysqli_close($dblink);
            modJsonResponse([
                'success' => $editMessageType === 'success',
                'messageType' => $editMessageType,
                'messageText' => $editMessageText,
                'alertHtml' => modFormAlert($editMessageText, $editMessageType),
                'item' => $itemPayload,
                'decisionAtIso' => $decisionAtIso,
                'decisionAtDisplay' => $decisionAtIso !== '' ? modFormatDateTime($decisionAtIso) : '',
                'journalActor' => $journalActorText,
            ]);
        }
    } elseif ($action === 'grave_update') {
        $graveFormData = [
            'region' => trim((string)($_POST['region'] ?? '')),
            'district' => trim((string)($_POST['district'] ?? '')),
            'town' => trim((string)($_POST['town'] ?? '')),
            'idxkladb' => trim((string)($_POST['idxkladb'] ?? '')),
            'lname' => trim((string)($_POST['lname'] ?? '')),
            'fname' => trim((string)($_POST['fname'] ?? '')),
            'mname' => trim((string)($_POST['mname'] ?? '')),
            'dt1' => trim((string)($_POST['dt1'] ?? '')),
            'dt2' => trim((string)($_POST['dt2'] ?? '')),
            'pos1' => trim((string)($_POST['pos1'] ?? '')),
            'pos2' => trim((string)($_POST['pos2'] ?? '')),
            'pos3' => trim((string)($_POST['pos3'] ?? '')),
        ];
        $graveEditRecord = modLoadGraveForEdit($dblink, $editId);
        $requiredMissing = $editId <= 0 || !$graveEditRecord || (int)$graveFormData['region'] <= 0 || (int)$graveFormData['district'] <= 0 || (int)$graveFormData['town'] <= 0 || (int)$graveFormData['idxkladb'] <= 0 || $graveFormData['lname'] === '' || $graveFormData['fname'] === '' || $graveFormData['dt1'] === '' || $graveFormData['dt2'] === '' || $graveFormData['pos1'] === '' || $graveFormData['pos2'] === '' || $graveFormData['pos3'] === '';
        $invalidBurialPosition = !modIsPositiveNumericString($graveFormData['pos1']) || !modIsPositiveNumericString($graveFormData['pos2']) || !modIsPositiveNumericString($graveFormData['pos3']);
        if ($requiredMissing) {
            $editMessageType = 'error';
            $editMessageText = $graveEditRecord ? 'Заповніть обов`язкові поля форми.' : 'Поховання не знайдено.';
        } elseif ($invalidBurialPosition) {
            $editMessageType = 'error';
            $editMessageText = 'Квартал, ряд і місце мають бути додатними числами без символів та без значення 0.';
        } else {
            $stmt = mysqli_prepare($dblink, 'UPDATE grave SET lname = ?, fname = ?, mname = ?, dt1 = ?, dt2 = ?, idxkladb = ?, pos1 = ?, pos2 = ?, pos3 = ? WHERE idx = ? LIMIT 1');
            if (!$stmt) {
                $editMessageType = 'error';
                $editMessageText = 'Помилка підготовки запиту: ' . mysqli_error($dblink);
            } else {
                $graveLname = $graveFormData['lname'];
                $graveFname = $graveFormData['fname'];
                $graveMname = $graveFormData['mname'];
                $graveDt1 = $graveFormData['dt1'];
                $graveDt2 = $graveFormData['dt2'];
                $graveCemeteryId = (int)$graveFormData['idxkladb'];
                $gravePos1 = $graveFormData['pos1'];
                $gravePos2 = $graveFormData['pos2'];
                $gravePos3 = $graveFormData['pos3'];
                $graveBindId = $editId;
                mysqli_stmt_bind_param($stmt, 'sssssisssi', $graveLname, $graveFname, $graveMname, $graveDt1, $graveDt2, $graveCemeteryId, $gravePos1, $gravePos2, $gravePos3, $graveBindId);
                $saved = mysqli_stmt_execute($stmt);
                $stmtError = mysqli_stmt_error($stmt);
                mysqli_stmt_close($stmt);
                if (!$saved) {
                    $editMessageType = 'error';
                    $editMessageText = 'Помилка оновлення: ' . ($stmtError !== '' ? $stmtError : mysqli_error($dblink));
                } else {
                    $uploadDir = __DIR__ . '/graves/' . $editId;
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    $uploaded = [];
                    foreach (['photo1', 'photo2', 'photo3'] as $field) {
                        if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
                            continue;
                        }
                        $ext = strtolower((string)pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
                        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif'], true)) {
                            continue;
                        }
                        $targetPath = $uploadDir . '/' . $field . '.' . $ext;
                        $ok = gravecompress($_FILES[$field]['tmp_name'], $targetPath);
                        if ($ok && file_exists($targetPath)) {
                            $uploaded[$field] = '/graves/' . $editId . '/' . $field . '.' . $ext;
                        }
                    }
                    if (!empty($uploaded)) {
                        $updates = [];
                        foreach ($uploaded as $column => $path) {
                            $updates[] = $column . "='" . mysqli_real_escape_string($dblink, $path) . "'";
                        }
                        mysqli_query($dblink, 'UPDATE grave SET ' . implode(', ', $updates) . ' WHERE idx=' . $editId . ' LIMIT 1');
                    }
                    $graveEditRecord = modLoadGraveForEdit($dblink, $editId);
                    $graveFormData = $graveEditRecord ? modBuildGraveFormData($graveEditRecord) : $graveFormData;
                    $editMessageType = 'success';
                    $editMessageText = 'Зміни у похованні збережено.';
                }
            }
        }
    } elseif ($action === 'cemetery_update') {
        $cemeteryFormData = [
            'region' => trim((string)($_POST['region'] ?? '')),
            'district' => trim((string)($_POST['district'] ?? '')),
            'town' => trim((string)($_POST['town'] ?? '')),
            'title' => trim((string)($_POST['title'] ?? '')),
            'adress-cemetery' => trim((string)($_POST['cemetery-adr'] ?? ($_POST['adress-cemetery'] ?? ''))),
            'gpsx' => trim((string)($_POST['gpsx'] ?? '')),
            'gpsy' => trim((string)($_POST['gpsy'] ?? '')),
        ];
        $cemeteryEditRecord = modLoadCemeteryForEdit($dblink, $editId);
        $requiredMissing = $editId <= 0 || !$cemeteryEditRecord || (int)$cemeteryFormData['region'] <= 0 || (int)$cemeteryFormData['district'] <= 0 || (int)$cemeteryFormData['town'] <= 0 || $cemeteryFormData['title'] === '';
        if ($requiredMissing) {
            $editMessageType = 'error';
            $editMessageText = $cemeteryEditRecord ? 'Заповніть обов`язкові поля форми.' : 'Кладовище не знайдено.';
        } else {
            $stmt = mysqli_prepare($dblink, 'UPDATE cemetery SET district = ?, town = ?, title = ?, adress = ?, gpsx = ?, gpsy = ? WHERE idx = ? LIMIT 1');
            if (!$stmt) {
                $editMessageType = 'error';
                $editMessageText = 'Помилка підготовки запиту: ' . mysqli_error($dblink);
            } else {
                $cemeteryDistrictId = (int)$cemeteryFormData['district'];
                $cemeteryTownId = (int)$cemeteryFormData['town'];
                $cemeteryTitle = $cemeteryFormData['title'];
                $cemeteryAddress = $cemeteryFormData['adress-cemetery'];
                $cemeteryGpsx = $cemeteryFormData['gpsx'];
                $cemeteryGpsy = $cemeteryFormData['gpsy'];
                $cemeteryBindId = $editId;
                mysqli_stmt_bind_param($stmt, 'iissssi', $cemeteryDistrictId, $cemeteryTownId, $cemeteryTitle, $cemeteryAddress, $cemeteryGpsx, $cemeteryGpsy, $cemeteryBindId);
                $saved = mysqli_stmt_execute($stmt);
                $stmtError = mysqli_stmt_error($stmt);
                mysqli_stmt_close($stmt);
                if (!$saved) {
                    $editMessageType = 'error';
                    $editMessageText = 'Помилка оновлення: ' . ($stmtError !== '' ? $stmtError : mysqli_error($dblink));
                } else {
                    if (isset($_FILES['scheme']) && $_FILES['scheme']['error'] === UPLOAD_ERR_OK) {
                        $uploadDir = __DIR__ . '/cemeteries/' . $editId;
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0777, true);
                        }
                        $ext = strtolower((string)pathinfo($_FILES['scheme']['name'], PATHINFO_EXTENSION));
                        if (in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
                            $targetPath = $uploadDir . '/scheme.' . $ext;
                            $ok = kladbcompress($_FILES['scheme']['tmp_name'], $targetPath);
                            if ($ok && file_exists($targetPath)) {
                                $schemePath = '/cemeteries/' . $editId . '/scheme.' . $ext;
                                $schemeEscaped = mysqli_real_escape_string($dblink, $schemePath);
                                mysqli_query($dblink, "UPDATE cemetery SET scheme = '$schemeEscaped' WHERE idx = $editId LIMIT 1");
                            }
                        }
                    }
                    $cemeteryEditRecord = modLoadCemeteryForEdit($dblink, $editId);
                    $cemeteryFormData = $cemeteryEditRecord ? modBuildCemeteryFormData($cemeteryEditRecord) : $cemeteryFormData;
                    $editMessageType = 'success';
                    $editMessageText = 'Зміни у кладовищі збережено.';
                }
            }
        }
    }
}

if ($editType === 'grave' && $editId > 0 && empty($graveFormData)) {
    $graveEditRecord = $graveEditRecord ?: modLoadGraveForEdit($dblink, $editId);
    if ($graveEditRecord) {
        $graveFormData = modBuildGraveFormData($graveEditRecord);
    }
}
if ($editType === 'cemetery' && $editId > 0 && empty($cemeteryFormData)) {
    $cemeteryEditRecord = $cemeteryEditRecord ?: modLoadCemeteryForEdit($dblink, $editId);
    if ($cemeteryEditRecord) {
        $cemeteryFormData = modBuildCemeteryFormData($cemeteryEditRecord);
    }
}

if ($isAjaxModal) {
    $responseFormHtml = '';
    $itemPayload = null;

    if ($editType === 'grave' && $editId > 0) {
        $responseFormHtml = modRenderGraveEditForm($graveFormData, $editId, $defaultTab, $defaultStatus, 'modal');
        $graveCardRow = modLoadGraveCardRow($dblink, $editId);
        if ($graveCardRow) {
            $itemPayload = modBuildItemPayload(modBuildGraveItemFromRow($graveCardRow), $statusLabels, $typeLabels);
        }
    } elseif ($editType === 'cemetery' && $editId > 0) {
        $responseFormHtml = modRenderCemeteryEditForm($cemeteryFormData, $editId, $defaultTab, $defaultStatus, 'modal');
        $cemeteryCardRow = modLoadCemeteryCardRow($dblink, $editId);
        if ($cemeteryCardRow) {
            $itemPayload = modBuildItemPayload(modBuildCemeteryItemFromRow($cemeteryCardRow), $statusLabels, $typeLabels);
        }
    }

    mysqli_close($dblink);
    modJsonResponse([
        'success' => $editMessageType === 'success',
        'messageType' => $editMessageType,
        'messageText' => $editMessageText,
        'alertHtml' => modFormAlert($editMessageText, $editMessageType),
        'formHtml' => $responseFormHtml,
        'item' => $itemPayload,
    ]);
}

$graveSql = "SELECT g.idx, g.fname, g.lname, g.mname, g.dt1, g.dt2, g.idtadd, g.idxadd, g.idxkladb, g.pos1, g.pos2, g.pos3, g.photo1, g.photo2, g.photo3, g.moderation_status, g.moderation_submitted_at, g.moderation_reviewed_at, g.moderation_reviewed_by, g.moderation_note, g.moderation_reject_reason, u.fname AS author_fname, u.lname AS author_lname, ur.fname AS reviewer_fname, ur.lname AS reviewer_lname, c.title AS cemetery_title, m.title AS town_name, d.title AS district_name, r.title AS region_name FROM grave g LEFT JOIN users u ON g.idxadd = u.idx LEFT JOIN users ur ON g.moderation_reviewed_by = ur.idx LEFT JOIN cemetery c ON g.idxkladb = c.idx LEFT JOIN misto m ON c.town = m.idx LEFT JOIN district d ON c.district = d.idx LEFT JOIN region r ON d.region = r.idx ORDER BY COALESCE(g.moderation_submitted_at, g.idtadd) DESC LIMIT 200";
$graveRes = mysqli_query($dblink, $graveSql);
if (!$graveRes) {
    $graveRes = mysqli_query($dblink, "SELECT g.idx, g.fname, g.lname, g.mname, g.dt1, g.dt2, g.idtadd, g.idxadd, g.idxkladb, g.pos1, g.pos2, g.pos3, g.photo1, g.photo2, g.photo3, u.fname AS author_fname, u.lname AS author_lname, c.title AS cemetery_title, m.title AS town_name, d.title AS district_name, r.title AS region_name FROM grave g LEFT JOIN users u ON g.idxadd = u.idx LEFT JOIN cemetery c ON g.idxkladb = c.idx LEFT JOIN misto m ON c.town = m.idx LEFT JOIN district d ON c.district = d.idx LEFT JOIN region r ON d.region = r.idx ORDER BY g.idtadd DESC LIMIT 200");
}
if ($graveRes) {
    while ($row = mysqli_fetch_assoc($graveRes)) {
        $idx = (int)($row['idx'] ?? 0);
        $title = trim(implode(' ', array_filter([trim((string)($row['lname'] ?? '')), trim((string)($row['fname'] ?? '')), trim((string)($row['mname'] ?? ''))])));
        $title = $title !== '' ? $title : 'Без ПІБ';
        $pos1 = trim((string)($row['pos1'] ?? ''));
        $pos2 = trim((string)($row['pos2'] ?? ''));
        $pos3 = trim((string)($row['pos3'] ?? ''));
        $cemTitle = trim((string)($row['cemetery_title'] ?? ''));
        $town = trim((string)($row['town_name'] ?? ''));
        $district = trim((string)($row['district_name'] ?? ''));
        $region = trim((string)($row['region_name'] ?? ''));
        $locationParts = [];
        if ($region !== '') { $locationParts[] = $region . ' обл.'; }
        if ($district !== '') { $locationParts[] = $district . ' р-н'; }
        if ($town !== '') { $locationParts[] = $town; }
        $status = (string)($row['moderation_status'] ?? 'pending');
        if (!isset($statusLabels[$status])) { $status = 'pending'; }
        $submittedRaw = (string)($row['moderation_submitted_at'] ?? '');
        if ($submittedRaw === '') { $submittedRaw = (string)($row['idtadd'] ?? ''); }
        $reviewedRaw = trim((string)($row['moderation_reviewed_at'] ?? ''));
        $actionRaw = ($status === 'pending' || $reviewedRaw === '' || $reviewedRaw === '0000-00-00 00:00:00')
            ? $submittedRaw
            : $reviewedRaw;
        $previewPath = modResolveGravePreview($row, $idx);
        $graveItems[] = [
            'id' => (string)$idx,
            'type' => 'grave',
            'title' => $title,
            'dates' => modFormatDateRange($row['dt1'] ?? null, $row['dt2'] ?? null),
            'location' => !empty($locationParts) ? implode(', ', $locationParts) : 'Локація не вказана',
            'region' => $region !== '' ? $region : '-',
            'district' => $district !== '' ? $district : '-',
            'town' => $town !== '' ? $town : '-',
            'cemetery' => $cemTitle !== '' ? $cemTitle : '-',
            'pos1' => $pos1 !== '' ? $pos1 : '-',
            'pos2' => $pos2 !== '' ? $pos2 : '-',
            'pos3' => $pos3 !== '' ? $pos3 : '-',
            'address' => '-',
            'moderation_note' => trim((string)($row['moderation_note'] ?? '')),
            'reject_reason' => trim((string)($row['moderation_reject_reason'] ?? '')),
            'author' => modAuthorName($row['author_fname'] ?? null, $row['author_lname'] ?? null, (int)($row['idxadd'] ?? 0)),
            'reviewer' => modAuthorName($row['reviewer_fname'] ?? null, $row['reviewer_lname'] ?? null, (int)($row['moderation_reviewed_by'] ?? 0)),
            'submitted_iso' => $submittedRaw,
            'action_iso' => $actionRaw,
            'status' => $status,
            'has_photo' => $previewPath !== '',
            'preview_path' => $previewPath,
            'photo1' => modResolvedFilePath($row['photo1'] ?? ''),
            'photo2' => modResolvedFilePath($row['photo2'] ?? ''),
            'photo3' => modResolvedFilePath($row['photo3'] ?? ''),
            'scheme' => '',
            'edit_url' => modBuildPanelUrl('grave', $status, 'edit', 'grave', $idx),
        ];
    }
}

$cemSql = "SELECT c.idx, c.title, c.town, c.district, c.adress, c.dtadd, c.idxadd, c.scheme, c.gpsx, c.gpsy, c.moderation_status, c.moderation_submitted_at, c.moderation_reviewed_at, c.moderation_reviewed_by, c.moderation_note, c.moderation_reject_reason, u.fname AS author_fname, u.lname AS author_lname, ur.fname AS reviewer_fname, ur.lname AS reviewer_lname, m.title AS town_name, d.title AS district_name, r.title AS region_name FROM cemetery c LEFT JOIN users u ON c.idxadd = u.idx LEFT JOIN users ur ON c.moderation_reviewed_by = ur.idx LEFT JOIN misto m ON c.town = m.idx LEFT JOIN district d ON c.district = d.idx LEFT JOIN region r ON d.region = r.idx ORDER BY COALESCE(c.moderation_submitted_at, c.dtadd) DESC LIMIT 200";
$cemeteryRes = mysqli_query($dblink, $cemSql);
if (!$cemeteryRes) {
    $cemeteryRes = mysqli_query($dblink, "SELECT c.idx, c.title, c.town, c.district, c.adress, c.dtadd, c.idxadd, c.scheme, c.gpsx, c.gpsy, u.fname AS author_fname, u.lname AS author_lname, m.title AS town_name, d.title AS district_name, r.title AS region_name FROM cemetery c LEFT JOIN users u ON c.idxadd = u.idx LEFT JOIN misto m ON c.town = m.idx LEFT JOIN district d ON c.district = d.idx LEFT JOIN region r ON d.region = r.idx ORDER BY c.dtadd DESC LIMIT 200");
}
if ($cemeteryRes) {
    while ($row = mysqli_fetch_assoc($cemeteryRes)) {
        $status = (string)($row['moderation_status'] ?? 'pending');
        if (!isset($statusLabels[$status])) { $status = 'pending'; }
        $submittedRaw = (string)($row['moderation_submitted_at'] ?? '');
        if ($submittedRaw === '') { $submittedRaw = (string)($row['dtadd'] ?? ''); }
        $reviewedRaw = trim((string)($row['moderation_reviewed_at'] ?? ''));
        $actionRaw = ($status === 'pending' || $reviewedRaw === '' || $reviewedRaw === '0000-00-00 00:00:00')
            ? $submittedRaw
            : $reviewedRaw;
        $locationParts = [];
        $town = trim((string)($row['town_name'] ?? ''));
        $district = trim((string)($row['district_name'] ?? ''));
        $region = trim((string)($row['region_name'] ?? ''));
        $address = trim((string)($row['adress'] ?? ''));
        if ($town !== '') { $locationParts[] = $town; }
        if ($district !== '') { $locationParts[] = $district . ' р-н'; }
        if ($region !== '') { $locationParts[] = $region . ' обл.'; }
        $previewPath = modResolveCemeteryPreview($row);
        $cemeteryItems[] = [
            'id' => (string)($row['idx'] ?? ''),
            'type' => 'cemetery',
            'title' => (string)($row['title'] ?? 'Кладовище'),
            'location' => !empty($locationParts) ? implode(', ', $locationParts) : 'Локація не вказана',
            'region' => $region !== '' ? $region : '-',
            'district' => $district !== '' ? $district : '-',
            'town' => $town !== '' ? $town : '-',
            'cemetery' => '-',
            'pos1' => '-',
            'pos2' => '-',
            'pos3' => '-',
            'address' => $address !== '' ? $address : '-',
            'moderation_note' => trim((string)($row['moderation_note'] ?? '')),
            'reject_reason' => trim((string)($row['moderation_reject_reason'] ?? '')),
            'author' => modAuthorName($row['author_fname'] ?? null, $row['author_lname'] ?? null, (int)($row['idxadd'] ?? 0)),
            'reviewer' => modAuthorName($row['reviewer_fname'] ?? null, $row['reviewer_lname'] ?? null, (int)($row['moderation_reviewed_by'] ?? 0)),
            'submitted_iso' => $submittedRaw,
            'action_iso' => $actionRaw,
            'status' => $status,
            'has_photo' => $previewPath !== '',
            'preview_path' => $previewPath,
            'photo1' => '',
            'photo2' => '',
            'photo3' => '',
            'scheme' => modResolvedFilePath($row['scheme'] ?? ''),
            'gpsx' => trim((string)($row['gpsx'] ?? '')),
            'gpsy' => trim((string)($row['gpsy'] ?? '')),
            'edit_url' => modBuildPanelUrl('cemetery', $status, 'edit', 'cemetery', (int)($row['idx'] ?? 0)),
        ];
    }
}
mysqli_close($dblink);

$items = array_merge($graveItems, $cemeteryItems);
usort($items, static function (array $left, array $right): int {
    $leftTime = strtotime((string)($left['submitted_iso'] ?? '')) ?: 0;
    $rightTime = strtotime((string)($right['submitted_iso'] ?? '')) ?: 0;
    return $rightTime <=> $leftTime;
});

$statusCounts = [
    'grave' => ['pending' => 0, 'approved' => 0, 'rejected' => 0],
    'cemetery' => ['pending' => 0, 'approved' => 0, 'rejected' => 0],
];
foreach ($items as $item) {
    $typeKey = (string)($item['type'] ?? '');
    $statusKey = (string)($item['status'] ?? 'pending');
    if (!isset($statusCounts[$typeKey][$statusKey])) {
        continue;
    }
    $statusCounts[$typeKey][$statusKey]++;
}
$dashboardCounts = [
    'total' => count($items),
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
    'grave' => count($graveItems),
    'cemetery' => count($cemeteryItems),
];

foreach ($items as $item) {
    $statusKey = isset($statusLabels[$item['status']]) ? $item['status'] : 'pending';
    $dashboardCounts[$statusKey]++;
}

$dashboardCardsHtml = '';
$journalItemsHtml = '';
$journalItemsFullHtml = '';
$journalTotalCount = count($items);
$journalLimit = min($journalTotalCount, 15);

foreach ($items as $index => $item) {
    $statusKey = isset($statusLabels[$item['status']]) ? $item['status'] : 'pending';
    $statusText = $statusLabels[$statusKey];
    $statusActionText = $statusActionLabels[$statusKey] ?? 'подано';
    $statusActorRole = modJournalActorRole($statusKey);
    $submittedDisplay = modFormatDateTime($item['submitted_iso'] ?? '');
    $journalIso = (string)($item['action_iso'] ?? ($item['submitted_iso'] ?? ''));
    $journalDisplay = modFormatDateTime($journalIso);
    $journalIconHtml = modJournalStatusIcon($statusKey);
    $typeKey = (string)($item['type'] ?? '');
    $typeLabel = $typeLabels[$typeKey] ?? $typeKey;
    $datesText = trim((string)($item['dates'] ?? ''));
    $locationText = trim((string)($item['location'] ?? '-'));
    $regionText = trim((string)($item['region'] ?? '-'));
    $districtText = trim((string)($item['district'] ?? '-'));
    $townText = trim((string)($item['town'] ?? '-'));
    $cemeteryText = trim((string)($item['cemetery'] ?? '-'));
    $addressText = trim((string)($item['address'] ?? '-'));
    $moderationNoteText = trim((string)($item['moderation_note'] ?? ''));
    $rejectReasonText = trim((string)($item['reject_reason'] ?? ''));
    $idText = trim((string)($item['id'] ?? '-'));
    $authorText = (string)($item['author'] ?? '-');
    $reviewerText = trim((string)($item['reviewer'] ?? ''));
    $titleText = (string)($item['title'] ?? '');
    $searchText = implode(' ', array_filter([
        $titleText,
        $typeLabel,
        $datesText,
        $locationText,
        $regionText,
        $districtText,
        $townText,
        $cemeteryText,
        $addressText,
        $idText,
        $authorText,
        $statusText,
    ]));
    $regionDisplay = modAreaLabel($regionText, 'область');
    $districtDisplay = modAreaLabel($districtText, 'район');
    $locationDisplay = implode(', ', array_filter([
        $regionDisplay !== '-' ? $regionDisplay : '',
        $districtDisplay !== '-' ? $districtDisplay : '',
        $townText !== '-' ? $townText : '',
    ]));
    $gravePlacementText = implode(', ', array_filter([
        $cemeteryText !== '-' ? $cemeteryText : '',
        $item['pos1'] !== '-' ? 'кв. ' . $item['pos1'] : '',
        $item['pos3'] !== '-' ? 'місце ' . $item['pos3'] : '',
        $item['pos2'] !== '-' ? 'ряд ' . $item['pos2'] : '',
    ]));
    $summaryText = $typeKey === 'grave'
        ? $gravePlacementText
        : ($addressText !== '-' ? $addressText : $locationDisplay);
    $typeIcon = $typeKey === 'grave'
        ? '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" /><path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2" /></svg>'
        : modCardIcon('map', 24);
    $dashboardCardsHtml .= '<article class="mod-entry-card" data-status="' . modEsc($statusKey) . '" data-type="' . modEsc($typeKey) . '" data-search="' . modEsc($searchText) . '" data-id="' . modEsc($idText) . '" data-title="' . modEsc($titleText) . '" data-status-label="' . modEsc($statusText) . '" data-type-label="' . modEsc($typeLabel) . '" data-submitted="' . modEsc($submittedDisplay) . '" data-submitted-iso="' . modEsc((string)($item['submitted_iso'] ?? '')) . '" data-author="' . modEsc($authorText) . '" data-reviewer="' . modEsc($reviewerText) . '" data-region="' . modEsc($regionText) . '" data-district="' . modEsc($districtText) . '" data-town="' . modEsc($townText) . '" data-cemetery="' . modEsc($cemeteryText) . '" data-address="' . modEsc($addressText) . '" data-dates="' . modEsc($datesText) . '" data-summary="' . modEsc($summaryText) . '" data-pos1="' . modEsc((string)($item['pos1'] ?? '-')) . '" data-pos2="' . modEsc((string)($item['pos2'] ?? '-')) . '" data-pos3="' . modEsc((string)($item['pos3'] ?? '-')) . '" data-note="' . modEsc($moderationNoteText) . '" data-reject-reason="' . modEsc($rejectReasonText) . '" data-edit-url="' . modEsc((string)($item['edit_url'] ?? '#')) . '" data-preview="' . modEsc((string)($item['preview_path'] ?? '')) . '" data-photo1="' . modEsc((string)($item['photo1'] ?? '')) . '" data-photo2="' . modEsc((string)($item['photo2'] ?? '')) . '" data-photo3="' . modEsc((string)($item['photo3'] ?? '')) . '" data-scheme="' . modEsc((string)($item['scheme'] ?? '')) . '" data-gpsx="' . modEsc((string)($item['gpsx'] ?? '')) . '" data-gpsy="' . modEsc((string)($item['gpsy'] ?? '')) . '">';
    $dashboardCardsHtml .= '<div class="mod-entry-card__head">';
    if (!empty($item['preview_path'])) {
        $dashboardCardsHtml .= '<span class="mod-entry-card__media mod-entry-card__media--photo"><img src="' . modEsc((string)$item['preview_path']) . '" alt="' . modEsc($titleText !== '' ? $titleText : $typeLabel) . '" loading="lazy"></span>';
    } else {
        $dashboardCardsHtml .= '<span class="mod-entry-card__media mod-entry-card__media--icon mod-entry-card__media--' . modEsc($typeKey) . '">' . $typeIcon . '</span>';
    }
    $dashboardCardsHtml .= '<div class="mod-entry-card__titles"><div class="mod-entry-card__title-row"><h3>' . modEsc($titleText) . '</h3><span class="mod-entry-card__id">ID ' . modEsc($idText) . '</span></div><span class="mod-entry-card__subtitle">' . modEsc($typeLabel) . '</span></div><span class="mod-entry-card__status mod-entry-card__status--' . modEsc($statusKey) . '">' . modEsc($statusText) . '</span></div>';
    $dashboardCardsHtml .= '<div class="mod-entry-card__body">';
    if ($typeKey === 'grave') {
        $dashboardCardsHtml .= '<div class="mod-entry-card__info-row">'
            . modCardInfoItemHtml('map-pin', $summaryText !== '' ? $summaryText : 'Місце поховання не вказано')
            . modCardInfoItemHtml('calendar', $datesText !== '' ? $datesText : 'Дати не вказані')
            . '</div>';
        $dashboardCardsHtml .= modCardTextLineHtml('map', $locationDisplay !== '' ? $locationDisplay : 'Локація не вказана');
    } else {
        $dashboardCardsHtml .= modCardTextLineHtml('map', $locationDisplay !== '' ? $locationDisplay : 'Локація не вказана');
        $dashboardCardsHtml .= modCardTextLineHtml('map-pin', $addressText !== '-' ? $addressText : 'Адресу не вказано', 'mod-entry-card__text-line--muted');
    }
    $dashboardCardsHtml .= '</div>';
    $rejectChipHtml = '';
    if ($statusKey === 'rejected' && $rejectReasonText !== '') {
        $rejectChipHtml = '<span class="mod-entry-card__reject-chip" title="' . modEsc($rejectReasonText) . '">Причина: ' . modEsc($rejectReasonText) . '</span>';
    }
    $dashboardCardsHtml .= '<div class="mod-entry-card__footer"><div class="mod-entry-card__author-wrap">' . modCardAuthorHtml($authorText) . $rejectChipHtml . '</div><span class="mod-entry-card__date">' . modEsc($submittedDisplay) . '</span></div>';
    $dashboardCardsHtml .= '</article>';

    $journalItemHtml = '<article class="mod-activity-item" data-id="' . modEsc($idText) . '" data-status="' . modEsc($statusKey) . '" data-type="' . modEsc($typeKey) . '" data-search="' . modEsc($searchText) . '"><span class="mod-activity-item__marker mod-activity-item__marker--' . modEsc($statusKey) . '">' . $journalIconHtml . '</span><div class="mod-activity-item__body"><div class="mod-activity-item__row"><strong><span class="mod-activity-item__role">' . modEsc($statusActorRole) . ':</span> ' . modEsc($authorText) . '</strong><span class="mod-activity-item__verb mod-activity-item__verb--' . modEsc($statusKey) . '">' . modEsc($statusActionText) . '</span><time datetime="' . modEsc($journalIso) . '">' . modEsc($journalDisplay) . '</time></div><div class="mod-activity-item__title">' . modEsc($titleText) . '</div><div class="mod-activity-item__meta">' . modEsc($typeLabel) . '</div></div></article>';
    $journalItemsFullHtml .= $journalItemHtml;
    if ($index < $journalLimit) {
        $journalItemsHtml .= $journalItemHtml;
    }
}

$journalItemsHtml = '';
$journalItemsFullHtml = '';
$journalTotalCount = 0;
$journalLimit = 0;

$journalDb = DbConnect();
$journalSql = "SELECT l.id, l.entity_type, l.entity_id, l.action_type, l.status_after, l.actor_user_id, l.source_user_id, l.created_at, ua.fname AS actor_fname, ua.lname AS actor_lname, us.fname AS source_fname, us.lname AS source_lname, g.fname AS grave_fname, g.lname AS grave_lname, g.mname AS grave_mname, c.title AS cemetery_title FROM moderation_journal_log l LEFT JOIN users ua ON ua.idx = l.actor_user_id LEFT JOIN users us ON us.idx = l.source_user_id LEFT JOIN grave g ON l.entity_type = 'grave' AND g.idx = l.entity_id LEFT JOIN cemetery c ON l.entity_type = 'cemetery' AND c.idx = l.entity_id ORDER BY l.created_at DESC, l.id DESC LIMIT 400";
$journalRes = mysqli_query($journalDb, $journalSql);
if ($journalRes) {
    $journalRows = [];
    while ($row = mysqli_fetch_assoc($journalRes)) {
        $journalRows[] = $row;
    }
    if (!empty($journalRows)) {
        $journalItemsHtml = '';
        $journalItemsFullHtml = '';
        $journalTotalCount = count($journalRows);
        $journalLimit = min($journalTotalCount, 15);

        foreach ($journalRows as $index => $row) {
            $typeKey = (string)($row['entity_type'] ?? '');
            if (!in_array($typeKey, ['grave', 'cemetery'], true)) {
                continue;
            }
            $actionType = (string)($row['action_type'] ?? '');
            $statusKey = $actionType === 'approved' ? 'approved' : ($actionType === 'rejected' ? 'rejected' : 'pending');
            $typeLabel = $typeLabels[$typeKey] ?? $typeKey;
            $statusActionText = $statusActionLabels[$statusKey] ?? 'подано';
            $statusActorRole = modJournalActorRole($statusKey);
            $actorFname = trim((string)($row['actor_fname'] ?? ''));
            $actorLname = trim((string)($row['actor_lname'] ?? ''));
            $sourceFname = trim((string)($row['source_fname'] ?? ''));
            $sourceLname = trim((string)($row['source_lname'] ?? ''));
            if ($actorFname === '' && $sourceFname !== '') {
                $actorFname = $sourceFname;
            }
            if ($actorLname === '' && $sourceLname !== '') {
                $actorLname = $sourceLname;
            }
            $actorId = (int)($row['actor_user_id'] ?? 0);
            if ($actorId <= 0) {
                $actorId = (int)($row['source_user_id'] ?? 0);
            }
            $actorText = modAuthorName($actorFname, $actorLname, $actorId);
            $entityId = (int)($row['entity_id'] ?? 0);
            $titleText = $typeKey === 'grave'
                ? trim(implode(' ', array_filter([trim((string)($row['grave_lname'] ?? '')), trim((string)($row['grave_fname'] ?? '')), trim((string)($row['grave_mname'] ?? ''))])))
                : trim((string)($row['cemetery_title'] ?? ''));
            if ($titleText === '') {
                $titleText = ($typeLabel !== '' ? $typeLabel : 'Запис') . ' ID ' . $entityId;
            }
            $journalIso = (string)($row['created_at'] ?? '');
            $journalDisplay = modFormatDateTime($journalIso);
            $journalIconHtml = modJournalStatusIcon($statusKey);
            $searchText = implode(' ', array_filter([$actorText, $titleText, $typeLabel, $statusActionText, (string)$entityId]));
            $journalItemHtml = '<article class="mod-activity-item" data-id="' . modEsc((string)$entityId) . '" data-status="' . modEsc($statusKey) . '" data-type="' . modEsc($typeKey) . '" data-search="' . modEsc($searchText) . '"><span class="mod-activity-item__marker mod-activity-item__marker--' . modEsc($statusKey) . '">' . $journalIconHtml . '</span><div class="mod-activity-item__body"><div class="mod-activity-item__row"><strong><span class="mod-activity-item__role">' . modEsc($statusActorRole) . ':</span> ' . modEsc($actorText) . '</strong><span class="mod-activity-item__verb mod-activity-item__verb--' . modEsc($statusKey) . '">' . modEsc($statusActionText) . '</span><time datetime="' . modEsc($journalIso) . '">' . modEsc($journalDisplay) . '</time></div><div class="mod-activity-item__title">' . modEsc($titleText) . '</div><div class="mod-activity-item__meta">' . modEsc($typeLabel) . '</div></div></article>';
            $journalItemsFullHtml .= $journalItemHtml;
            if ($index < $journalLimit) {
                $journalItemsHtml .= $journalItemHtml;
            }
        }
    }
}
mysqli_close($journalDb);

$listViewUrl = modBuildPanelUrl($defaultTab, $defaultStatus);
$editTitle = $editType === 'grave' ? trim((string)(($graveFormData['lname'] ?? '') . ' ' . ($graveFormData['fname'] ?? '') . ' ' . ($graveFormData['mname'] ?? ''))) : trim((string)($cemeteryFormData['title'] ?? ''));
$editTitle = $editTitle !== '' ? $editTitle : ($typeLabels[$editType] ?? 'Редагування');
$editAlertHtml = modFormAlert($editMessageText, $editMessageType);
$notifyAlertHtml = $notifyMessageText !== '' ? modFormAlert($notifyMessageText, $notifyMessageType) : '';
$editFormHtml = '';
$moderatorId = modResolveCurrentUserId();
$moderatorName = modResolveModeratorName($moderatorId);
$editNotFound = $currentView === 'edit' && $editType !== '' && $editId > 0 && (($editType === 'grave' && !$graveEditRecord && empty($graveFormData)) || ($editType === 'cemetery' && !$cemeteryEditRecord && empty($cemeteryFormData)));
if ($editType === 'grave' && $editId > 0 && !empty($graveFormData)) {
    $editFormHtml = modRenderGraveEditForm($graveFormData, $editId, $defaultTab, $defaultStatus);
} elseif ($editType === 'cemetery' && $editId > 0 && !empty($cemeteryFormData)) {
    $editFormHtml = modRenderCemeteryEditForm($cemeteryFormData, $editId, $defaultTab, $defaultStatus);
}
if ($currentView === 'edit' && $editFormHtml === '' && $editAlertHtml === '') {
    $editAlertHtml = modFormAlert($editNotFound ? 'Запис для редагування не знайдено.' : 'Оберіть карточку та відкрийте редагування.', 'error');
}

View_Clear();
View_Add(Page_Up('Панель модерації'));
View_Add(Menu_Up());
View_Add('<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">');
View_Add('<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>');
View_Add('<link rel="stylesheet" href="/assets/css/moderation-panel.css?v=37">');
View_Add('<link rel="stylesheet" href="/assets/css/datepicker.css?v=2">');

ob_start();
?>
<div class="out out-moderation">
    <main class="mod-panel" data-default-tab="<?= modEsc($activeTypeFilter) ?>" data-default-status="<?= modEsc($defaultStatus) ?>" data-view="list">
        <div class="mod-shell">
            <section class="mod-content">
                <div class="mod-banner">
                    <div class="mod-banner-main">
                        <div class="mod-banner-brand">
                            <span class="mod-banner-logo" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                    <path d="M12 3a12 12 0 0 0 8.5 3a12 12 0 0 1 -8.5 15a12 12 0 0 1 -8.5 -15a12 12 0 0 0 8.5 -3"></path>
                                    <path d="M12 3v18"></path>
                                </svg>
                            </span>
                            <div class="mod-banner-copy">
                                <span class="mod-banner-text">Панель модерації</span>
                                <span class="mod-banner-subtext">Система управління контентом</span>
                            </div>
                        </div>
                        <div class="mod-banner-user" aria-label="Поточний модератор">
                            <span class="mod-banner-user__icon" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                    <path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0"></path>
                                    <path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2"></path>
                                </svg>
                            </span>
                            <span class="mod-banner-user__text">Модератор: <?= modEsc($moderatorName) ?></span>
                        </div>
                    </div>
                </div>

                <div class="mod-summary" id="modBannerStats" aria-label="Статистика модерації">
                    <div class="mod-banner-stat mod-banner-stat--total" data-stat="total">
                        <span class="mod-stat-icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="6" width="18" height="13" rx="3"></rect>
                                <path d="M8 10h8"></path>
                                <path d="M9 6V4"></path>
                                <path d="M15 6V4"></path>
                            </svg>
                        </span>
                        <div class="mod-stat-copy">
                            <strong><?= (int)($dashboardCounts['total'] ?? 0) ?></strong>
                            <span>Всього</span>
                        </div>
                    </div>
                    <div class="mod-banner-stat mod-banner-stat--pending" data-stat="pending">
                        <span class="mod-stat-icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="8"></circle>
                                <path d="M12 8v4l2 2"></path>
                            </svg>
                        </span>
                        <div class="mod-stat-copy">
                            <strong><?= (int)($dashboardCounts['pending'] ?? 0) ?></strong>
                            <span>На модерації</span>
                        </div>
                    </div>
                    <div class="mod-banner-stat mod-banner-stat--approved" data-stat="approved">
                        <span class="mod-stat-icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M5 12l4 4L19 7"></path>
                            </svg>
                        </span>
                        <div class="mod-stat-copy">
                            <strong><?= (int)($dashboardCounts['approved'] ?? 0) ?></strong>
                            <span>Схвалено</span>
                        </div>
                    </div>
                    <div class="mod-banner-stat mod-banner-stat--rejected" data-stat="rejected">
                        <span class="mod-stat-icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 6L6 18"></path>
                                <path d="M6 6l12 12"></path>
                            </svg>
                        </span>
                        <div class="mod-stat-copy">
                            <strong><?= (int)($dashboardCounts['rejected'] ?? 0) ?></strong>
                            <span>Відхилено</span>
                        </div>
                    </div>
                    <div class="mod-banner-stat mod-banner-stat--ratio">
                        <span class="mod-stat-icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M7 7h10"></path>
                                <path d="M7 12h10"></path>
                                <path d="M7 17h6"></path>
                                <path d="M4 7h.01"></path>
                                <path d="M4 12h.01"></path>
                                <path d="M4 17h.01"></path>
                            </svg>
                        </span>
                        <div class="mod-stat-copy">
                            <strong><?= (int)($dashboardCounts['grave'] ?? 0) ?> / <?= (int)($dashboardCounts['cemetery'] ?? 0) ?></strong>
                            <span>Поховань / Кладовищ</span>
                        </div>
                    </div>
                </div>

                <section class="mod-dashboard" id="modDashboardSection">
                    <div class="mod-view-tabs" role="tablist" aria-label="Режим панелі">
                        <button type="button" class="mod-view-tab is-active" data-panel="moderation">
                            <span class="mod-view-tab__icon" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                    <path d="M12 21a12 12 0 0 1 -8.5 -15a12 12 0 0 0 8.5 -3a12 12 0 0 0 8.5 3c.568 1.933 .635 3.957 .223 5.89"></path>
                                    <path d="M17.001 19a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"></path>
                                    <path d="M19.001 15.5v1.5"></path>
                                    <path d="M19.001 21v1.5"></path>
                                    <path d="M22.032 17.25l-1.299 .75"></path>
                                    <path d="M17.27 20l-1.3 .75"></path>
                                    <path d="M15.97 17.25l1.3 .75"></path>
                                    <path d="M20.733 20l1.3 .75"></path>
                                </svg>
                            </span>
                            <span>Модерація</span>
                        </button>
                        <button type="button" class="mod-view-tab" data-panel="journal">
                            <span class="mod-view-tab__icon" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                    <path d="M14 3v4a1 1 0 0 0 1 1h4"></path>
                                    <path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2"></path>
                                    <path d="M9 17h6"></path>
                                    <path d="M9 13h6"></path>
                                </svg>
                            </span>
                            <span>Журнал</span>
                            <span class="mod-view-tab__count"><?= (int)$journalTotalCount ?></span>
                        </button>
                        <button type="button" class="mod-view-tab" data-panel="notify">
                            <span class="mod-view-tab__icon" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-bell"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M10 5a2 2 0 1 1 4 0a7 7 0 0 1 4 6v3a4 4 0 0 0 2 3h-16a4 4 0 0 0 2 -3v-3a7 7 0 0 1 4 -6" /><path d="M9 17v1a3 3 0 0 0 6 0v-1" /></svg>
                            </span>
                            <span>Розсилка повідомлень</span>
                        </button>
                    </div>

                    <div class="mod-dashboard-view is-active" id="modModerationView">
                        <div class="mod-toolbar">
                            <div class="mod-filter-group mod-filter-group--status" role="tablist" aria-label="Фільтр за статусом">
                                <button type="button" class="mod-filter-pill<?= $defaultStatus === 'pending' ? ' is-active' : '' ?>" data-status-filter="pending">
                                    <span class="mod-filter-pill__icon" aria-hidden="true">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                            <circle cx="12" cy="12" r="8"></circle>
                                            <path d="M12 8v4l2 2"></path>
                                        </svg>
                                    </span>
                                    <span class="mod-filter-pill__label">На модерації</span>
                                    <span class="mod-filter-pill__count"><?= (int)($dashboardCounts['pending'] ?? 0) ?></span>
                                </button>
                                <button type="button" class="mod-filter-pill<?= $defaultStatus === 'approved' ? ' is-active' : '' ?>" data-status-filter="approved">
                                    <span class="mod-filter-pill__icon" aria-hidden="true">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M5 12l4 4L19 7"></path>
                                        </svg>
                                    </span>
                                    <span class="mod-filter-pill__label">Схвалено</span>
                                    <span class="mod-filter-pill__count"><?= (int)($dashboardCounts['approved'] ?? 0) ?></span>
                                </button>
                                <button type="button" class="mod-filter-pill<?= $defaultStatus === 'rejected' ? ' is-active' : '' ?>" data-status-filter="rejected">
                                    <span class="mod-filter-pill__icon" aria-hidden="true">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M18 6L6 18"></path>
                                            <path d="M6 6l12 12"></path>
                                        </svg>
                                    </span>
                                    <span class="mod-filter-pill__label">Відхилено</span>
                                    <span class="mod-filter-pill__count"><?= (int)($dashboardCounts['rejected'] ?? 0) ?></span>
                                </button>
                            </div>
                                <div class="mod-toolbar__right">
                                <div class="mod-filter-group mod-filter-group--type" role="tablist" aria-label="Фільтр за типом">
                                    <button type="button" class="mod-type-pill<?= $activeTypeFilter === 'grave' ? ' is-active' : '' ?>" data-type-filter="grave">Поховання</button>
                                    <button type="button" class="mod-type-pill<?= $activeTypeFilter === 'cemetery' ? ' is-active' : '' ?>" data-type-filter="cemetery">Кладовища</button>
                                </div>
                                <label class="mod-searchbar">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="M20 20l-3.5-3.5"></path></svg>
                                    <input type="search" id="modDashboardSearch" placeholder="Пошук...">
                                </label>
                            </div>
                        </div>

                        <div class="mod-dashboard-grid">
                            <div class="mod-feed">
                                <div class="mod-entry-list" id="modEntryList"><?= $dashboardCardsHtml !== '' ? $dashboardCardsHtml : '<div class="mod-empty">Наразі немає записів для модерації.</div>' ?></div>
                                <p class="mod-empty" id="modEmpty" hidden>Немає записів за вибраними фільтрами.</p>
                            </div>
                            <aside class="mod-activity-panel">
                                <div class="mod-activity-panel__head">
                                    <h3>Журнал дій</h3>
                                    <span id="modActivityPreviewCount">Останні 15 дій</span>
                                </div>
                                <div class="mod-activity-list" id="modActivityList"><?= $journalItemsHtml !== '' ? $journalItemsHtml : '<div class="mod-empty">Журнал порожній.</div>' ?></div>
                            </aside>
                        </div>
                    </div>

                    <div class="mod-dashboard-view" id="modJournalView" hidden>
                        <div class="mod-journal-card">
                            <div class="mod-activity-panel__head">
                                <h3>Журнал дій</h3>
                                <span id="modActivityTotalCount"><?= (int)$journalTotalCount ?> записів</span>
                            </div>
                            <div class="mod-activity-list mod-activity-list--full" id="modActivityListFull"><?= $journalItemsFullHtml !== '' ? $journalItemsFullHtml : '<div class="mod-empty">Журнал порожній.</div>' ?></div>
                        </div>
                    </div>

                    <div class="mod-dashboard-view" id="modNotifyView" hidden>
                        <div class="mod-notify-shell">
                            <section class="mod-notify-card" aria-label="Надіслати повідомлення користувачу">
                                <div class="mod-notify-header">
                                    <h2>Розсилка повідомлень</h2>
                                    <p>Надішліть коротке системне повідомлення без відкриття адмін-панелі.</p>
                                </div>
                                <?= $notifyAlertHtml ?>
                                <form method="post" class="mod-notify-form" novalidate>
                                    <input type="hidden" name="mod_action" value="send_user_notification">
                                    <div class="mod-form-row mod-form-row--two">
                                        <label class="mod-field">
                                            <span>ID користувача</span>
                                            <input type="number" name="notify_user_id" required>
                                        </label>
                                        <label class="mod-field">
                                            <span>Категорія</span>
                                            <select id="mod-notify-category" name="notify_category">
                                                <option value="manual">Повідомлення</option>
                                                <option value="moderation">Модерація</option>
                                                <option value="system">Системне</option>
                                                <option value="account">Акаунт</option>
                                                <option value="wallet">Гаманець</option>
                                            </select>
                                        </label>
                                    </div>
                                    <div class="mod-form-row mod-form-row--two">
                                        <label class="mod-field">
                                            <span>Пріоритет</span>
                                            <select id="mod-notify-priority" name="notify_priority">
                                                <option value="normal">Звичайний</option>
                                                <option value="high">Високий</option>
                                                <option value="low">Низький</option>
                                            </select>
                                        </label>
                                        <label class="mod-field">
                                            <span>Заголовок</span>
                                            <input type="text" name="notify_title" required>
                                        </label>
                                    </div>
                                    <div class="mod-form-row mod-form-row--single">
                                        <label class="mod-field">
                                            <span>Текст</span>
                                            <textarea name="notify_body" rows="3" required></textarea>
                                        </label>
                                    </div>
                                    <div class="mod-form-row mod-form-row--two">
                                        <label class="mod-field">
                                            <span>Action URL</span>
                                            <input type="text" name="notify_action_url" placeholder="/profile.php?md=11">
                                        </label>
                                        <label class="mod-field">
                                            <span>Action label</span>
                                            <input type="text" name="notify_action_label" placeholder="Відкрити">
                                        </label>
                                    </div>
                                    <button type="submit" class="mod-notify-submit">Надіслати</button>
                                </form>
                            </section>
                        </div>
                    </div>
                </section>

            </section>
        </div>
    </main>
</div>
<div class="acm-modal" id="mod-entry-modal" aria-hidden="true">
    <div class="acm-modal__backdrop" data-mod-close-entry-modal></div>
    <div class="acm-modal__card mod-entry-modal-card" role="dialog" aria-modal="true" aria-labelledby="mod-entry-modal-title">
        <button type="button" class="mod-entry-modal__close" data-mod-close-entry-modal aria-label="Закрити">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6L6 18"></path><path d="M6 6l12 12"></path></svg>
        </button>
        <div class="mod-entry-modal__scroll">
            <div class="mod-entry-modal__header">
                <div class="mod-entry-modal__lead">
                    <span class="mod-entry-modal__media" id="modEntryModalMedia" aria-hidden="true"></span>
                    <div>
                        <h3 id="mod-entry-modal-title" class="mod-entry-modal__title"></h3>
                        <p class="mod-entry-modal__subtitle" id="modEntryModalSubtitle"></p>
                    </div>
                </div>
                <span class="mod-entry-modal__status" id="modEntryModalStatus"></span>
            </div>
            <div class="mod-entry-modal__body">
                <div id="modEntryModalFlash" class="mod-entry-modal__flash" hidden></div>
                <div class="mod-entry-modal__view" id="modEntryModalView">
                    <section class="mod-entry-modal__section">
                        <h4>Основна інформація</h4>
                        <div class="mod-entry-modal__info-grid">
                            <div class="mod-entry-modal__field">
                                <span>Розташування</span>
                                <strong id="modEntryModalLocation">-</strong>
                            </div>
                            <div class="mod-entry-modal__field">
                                <span>ID картки</span>
                                <strong id="modEntryModalId">-</strong>
                            </div>
                        </div>
                    </section>
                    <section class="mod-entry-modal__section">
                        <h4 id="modEntryModalDataTitle">Дані запису</h4>
                        <div class="mod-entry-modal__grid mod-entry-modal__grid--two" id="modEntryModalDataGrid"></div>
                    </section>
                    <section class="mod-entry-modal__section">
                        <h4>Відправник</h4>
                        <div class="mod-entry-modal__sender">
                            <div>
                                <strong id="modEntryModalAuthor">-</strong>
                                <span id="modEntryModalDate">-</span>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
            <div class="mod-entry-modal__actions">
                <a class="mod-action-btn is-back mod-entry-modal__action-view" id="modEntryModalEdit" href="#"><span class="mod-action-btn__icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3l-11 11l-4 1l1 -4z"></path></svg></span><span>Редагувати</span></a>
                <button type="button" class="mod-action-btn is-reject mod-entry-modal__action-view" id="modEntryModalRejectBtn"><span class="mod-action-btn__icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18"></path><path d="M6 6l12 12"></path></svg></span><span>Відхилити</span></button>
                <button type="button" class="mod-action-btn is-approve mod-entry-modal__action-view" id="modEntryModalApproveBtn"><span class="mod-action-btn__icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l4 4L19 7"></path></svg></span><span>Схвалити</span></button>
                <button type="button" class="mod-action-btn is-back mod-entry-modal__action-edit" id="modEntryModalCancelEdit"><span class="mod-action-btn__icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-x"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M18 6l-12 12"></path><path d="M6 6l12 12"></path></svg></span><span>Скасувати</span></button>
                <button type="button" class="mod-action-btn is-save mod-entry-modal__action-edit" id="modEntryModalSaveEdit"><span class="mod-action-btn__icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-check"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M5 12l5 5l10 -10"></path></svg></span><span>Зберегти</span></button>
            </div>
        </div>
    </div>
</div>
<div class="acm-modal" id="mod-photo-modal" aria-hidden="true">
    <div class="acm-modal__backdrop" data-mod-close-photo-modal></div>
    <div class="acm-modal__card agf-photo-modal-card" role="dialog" aria-modal="true" aria-labelledby="mod-photo-modal-title">
        <h3 id="mod-photo-modal-title" class="acm-modal__title">Перегляд фото</h3>
        <div class="agf-photo-modal-image-wrap">
            <img id="mod-photo-modal-img" alt="Перегляд завантаженого фото">
        </div>
        <div class="acm-modal__actions">
            <button type="button" class="acm-btn acm-btn--ghost" data-mod-close-photo-modal>Закрити</button>
        </div>
    </div>
</div>
<div class="acm-modal" id="mod-map-modal" aria-hidden="true">
    <div class="acm-modal__backdrop" data-mod-close-map-modal></div>
    <div class="acm-modal__card mod-map-modal-card" role="dialog" aria-modal="true" aria-labelledby="mod-map-modal-title">
        <button type="button" class="mod-map-modal-close" id="mod-map-modal-close-top" data-mod-close-map-modal aria-label="Закрити" hidden>
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M18 6L6 18"></path>
                <path d="M6 6l12 12"></path>
            </svg>
        </button>
        <h3 id="mod-map-modal-title" class="acm-modal__title">Вибір координат на карті</h3>
        <p class="mod-map-modal-text">Клікніть на карті, щоб поставити мітку. Потім натисніть «Застосувати координати».</p>
        <div id="mod-map-canvas"></div>
        <div id="mod-map-hint" class="mod-map-hint"></div>
        <div class="acm-modal__actions">
            <button type="button" class="acm-btn acm-btn--ghost" data-mod-close-map-modal>Скасувати</button>
            <button type="button" id="mod-apply-map" class="acm-btn mod-map-apply-btn">Застосувати координати</button>
        </div>
    </div>
</div>
<div class="acm-modal" id="mod-reject-modal" aria-hidden="true">
    <div class="acm-modal__backdrop" data-mod-close-reject-modal></div>
    <div class="acm-modal__card mod-reject-modal-card" role="dialog" aria-modal="true" aria-labelledby="mod-reject-modal-title">
        <div class="mod-reject-modal-header">
            <h3 id="mod-reject-modal-title" class="acm-modal__title">Підтвердження відмови</h3>
            <button type="button" class="mod-reject-modal-close" data-mod-close-reject-modal aria-label="Закрити">&times;</button>
        </div>
        <div class="mod-reject-modal-body">
            <p class="mod-reject-modal-text">Причина відхилення</p>
            <div class="mod-reject-reason-grid" id="modRejectReasonGrid">
                <label class="mod-reject-reason-option">
                    <input type="radio" name="mod-reject-reason-choice" value="Спам">
                    <span class="mod-reject-reason-option__text">Спам</span>
                </label>
                <label class="mod-reject-reason-option">
                    <input type="radio" name="mod-reject-reason-choice" value="Невірно вказана інформація">
                    <span class="mod-reject-reason-option__text">Невірно вказана інформація</span>
                </label>
                <label class="mod-reject-reason-option">
                    <input type="radio" name="mod-reject-reason-choice" value="Фото не відповідає вимогам">
                    <span class="mod-reject-reason-option__text">Фото не відповідає вимогам</span>
                </label>
                <label class="mod-reject-reason-option">
                    <input type="radio" name="mod-reject-reason-choice" value="Дублювання існуючої картки">
                    <span class="mod-reject-reason-option__text">Дублювання існуючої картки</span>
                </label>
                <label class="mod-reject-reason-option">
                    <input type="radio" name="mod-reject-reason-choice" value="__other__">
                    <span class="mod-reject-reason-option__text">Інша причина</span>
                </label>
            </div>
            <div class="mod-reject-other-reason" id="modRejectOtherWrap" hidden>
                <label for="modRejectReasonInput">Вкажіть іншу причину</label>
                <input type="text" id="modRejectReasonInput" maxlength="255" placeholder="Напишіть причину відмови">
            </div>
            <div class="mod-reject-modal-error" id="modRejectModalError" hidden></div>
        </div>
        <div class="acm-modal__actions mod-reject-modal-actions">
            <button type="button" class="acm-btn acm-btn--ghost" data-mod-close-reject-modal>Скасувати</button>
            <button type="button" class="acm-btn mod-map-apply-btn" id="modRejectConfirmBtn">Підтвердити відмову</button>
        </div>
    </div>
</div>
<script src="/assets/js/moderation-panel-mock.js?v=36" defer></script>
<?php
$pageHtml = ob_get_clean();

View_Add($pageHtml);
View_Add(Page_Down());
View_Out();
