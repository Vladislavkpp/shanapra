<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/function.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/classes/Lenta-grave.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'toggle_save_grave') {
    header('Content-Type: application/json; charset=utf-8');

    $dblink = DbConnect();
    $userId = (int)($_SESSION['uzver'] ?? 0);
    $graveId = (int)($_POST['grave_id'] ?? 0);
    $now = date('Y-m-d H:i:s');

    if ($userId <= 0 || $graveId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Некоректні дані'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $existsRes = mysqli_query(
        $dblink,
        "SELECT idx FROM saved_grave WHERE user_id = $userId AND grave_id = $graveId LIMIT 1"
    );

    if ($existsRes && mysqli_num_rows($existsRes) > 0) {
        mysqli_query($dblink, "DELETE FROM saved_grave WHERE user_id = $userId AND grave_id = $graveId");
        echo json_encode(['status' => 'removed'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    mysqli_query(
        $dblink,
        "INSERT INTO saved_grave (user_id, grave_id, idtadd, fk_saved_user, fk_saved_grave)
         VALUES ($userId, $graveId, '$now', $userId, $graveId)"
    );
    echo json_encode(['status' => 'saved'], JSON_UNESCAPED_UNICODE);
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

function cardOutTestEsc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function cardOutTestFormatDate(?string $date, string $fallback = 'Не вказано'): string
{
    $date = trim((string)$date);
    if ($date === '' || $date === '0000-00-00') {
        return $fallback;
    }

    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return $fallback;
    }

    return date('d.m.Y', $timestamp);
}

function cardOutTestFormatDateTime(?string $dateTime, string $fallback = 'Не вказано'): string
{
    $dateTime = trim((string)$dateTime);
    if ($dateTime === '' || $dateTime === '0000-00-00' || $dateTime === '0000-00-00 00:00:00') {
        return $fallback;
    }

    $timestamp = strtotime($dateTime);
    if ($timestamp === false) {
        return $fallback;
    }

    return date('d.m.Y H:i', $timestamp);
}

function cardOutTestFormatLifeRange(?string $birth, ?string $death): string
{
    $birthFormatted = cardOutTestFormatDate($birth, '');
    $deathFormatted = cardOutTestFormatDate($death, '');

    if ($birthFormatted !== '' && $deathFormatted !== '') {
        return $birthFormatted . ' - ' . $deathFormatted;
    }
    if ($birthFormatted !== '') {
        return $birthFormatted . ' - ...';
    }
    if ($deathFormatted !== '') {
        return '... - ' . $deathFormatted;
    }

    return 'Дати не вказані';
}

function cardOutTestFormatLifeYears(?string $birth, ?string $death): string
{
    $birth = trim((string)$birth);
    $death = trim((string)$death);
    if ($birth === '' || $death === '' || $birth === '0000-00-00' || $death === '0000-00-00') {
        return 'Невідомо';
    }

    try {
        $birthDate = new DateTime($birth);
        $deathDate = new DateTime($death);
    } catch (Exception $e) {
        return 'Невідомо';
    }

    if ($deathDate < $birthDate) {
        return 'Невідомо';
    }

    return (string)$birthDate->diff($deathDate)->y . ' р.';
}

function cardOutTestModerationResetSql(): string
{
    return implode(",\n            ", [
        "moderation_status='pending'",
        "moderation_submitted_at=NOW()",
        "moderation_reviewed_at=NULL",
        "moderation_reviewed_by=NULL",
        "moderation_note=NULL",
        "moderation_reject_reason=NULL",
    ]);
}

function cardOutTestRegionOptions(string $selectedValue): string
{
    $dblink = DbConnect();
    $res = mysqli_query($dblink, 'SELECT idx, title FROM region ORDER BY title');
    $out = '<option value="">Оберіть область</option>';

    while ($res && $row = mysqli_fetch_assoc($res)) {
        $value = (string)$row['idx'];
        $selected = ($value === $selectedValue) ? ' selected' : '';
        $out .= '<option value="' . cardOutTestEsc($value) . '"' . $selected . '>' . cardOutTestEsc($row['title']) . '</option>';
    }

    mysqli_close($dblink);
    return $out;
}

function cardOutTestDistrictOptions(string $regionValue, string $selectedValue): string
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
        $out .= '<option value="' . cardOutTestEsc($value) . '"' . $selected . '>' . cardOutTestEsc($row['title']) . '</option>';
    }

    mysqli_close($dblink);
    return $out;
}

function cardOutTestTownOptions(string $regionValue, string $districtValue, string $selectedValue): string
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
        $out .= '<option value="' . cardOutTestEsc($value) . '"' . $selected . '>' . cardOutTestEsc($row['title']) . '</option>';
    }

    mysqli_close($dblink);
    return $out;
}

function cardOutTestCemeteryOptions(string $districtValue, string $selectedValue): string
{
    if ((int)$districtValue <= 0) {
        return '<option value="">Виберіть кладовище</option>';
    }

    $dblink = DbConnect();
    $districtId = (int)$districtValue;
    $res = mysqli_query($dblink, 'SELECT idx, title FROM cemetery WHERE district = ' . $districtId . ' ORDER BY title');
    $out = '<option value="">Виберіть кладовище</option>';

    while ($res && $row = mysqli_fetch_assoc($res)) {
        $value = (string)$row['idx'];
        $selected = ($value === $selectedValue) ? ' selected' : '';
        $out .= '<option value="' . cardOutTestEsc($value) . '"' . $selected . '>' . cardOutTestEsc($row['title']) . '</option>';
    }

    mysqli_close($dblink);
    return $out;
}

function cardOutTestLoadGrave(mysqli $dblink, int $idx): ?array
{
    if ($idx <= 0) {
        return null;
    }

    $query = "
        SELECT
            g.*,
            c.idx AS cemetery_idx,
            c.title AS cemetery_title,
            c.adress AS cemetery_address,
            c.gpsx AS cemetery_gpsx,
            c.gpsy AS cemetery_gpsy,
            m.idx AS town_idx,
            m.title AS town_title,
            d.idx AS district_idx,
            d.title AS district_title,
            r.idx AS region_idx,
            r.title AS region_title,
            u.fname AS author_fname,
            u.lname AS author_lname,
            u.avatar AS author_avatar
        FROM grave g
        LEFT JOIN cemetery c ON g.idxkladb = c.idx
        LEFT JOIN misto m ON c.town = m.idx
        LEFT JOIN district d ON c.district = d.idx
        LEFT JOIN region r ON d.region = r.idx
        LEFT JOIN users u ON g.idxadd = u.idx
        WHERE g.idx = $idx
        LIMIT 1
    ";
    $res = mysqli_query($dblink, $query);
    if (!$res) {
        return null;
    }

    return mysqli_fetch_assoc($res) ?: null;
}

function cardOutTestBuildEditFormData(?array $grave): array
{
    $data = [
        'region' => '',
        'district' => '',
        'town' => '',
        'idxkladb' => '',
        'lname' => '',
        'fname' => '',
        'mname' => '',
        'dt1' => '',
        'dt2' => '',
        'dt1_unknown' => '0',
        'dt2_unknown' => '0',
        'pos1_unknown' => '0',
        'pos2_unknown' => '0',
        'pos3_unknown' => '0',
        'pos1' => '',
        'pos2' => '',
        'pos3' => '',
    ];

    if (!$grave) {
        return $data;
    }

    $data['region'] = (string)($grave['region_idx'] ?? '');
    $data['district'] = (string)($grave['district_idx'] ?? '');
    $data['town'] = (string)($grave['town_idx'] ?? '');
    $data['idxkladb'] = (string)($grave['idxkladb'] ?? ($grave['cemetery_idx'] ?? ''));
    $data['lname'] = (string)($grave['lname'] ?? '');
    $data['fname'] = (string)($grave['fname'] ?? '');
    $data['mname'] = (string)($grave['mname'] ?? '');
    $data['pos1'] = trim((string)($grave['pos1'] ?? ''));
    $data['pos2'] = trim((string)($grave['pos2'] ?? ''));
    $data['pos3'] = trim((string)($grave['pos3'] ?? ''));
    if ($data['pos1'] === '0') {
        $data['pos1'] = '';
    }
    if ($data['pos2'] === '0') {
        $data['pos2'] = '';
    }
    if ($data['pos3'] === '0') {
        $data['pos3'] = '';
    }

    $dt1 = trim((string)($grave['dt1'] ?? ''));
    if ($dt1 === '' || $dt1 === '0000-00-00') {
        $data['dt1_unknown'] = '1';
        $data['dt1'] = '';
    } else {
        $data['dt1'] = $dt1;
    }

    $dt2 = trim((string)($grave['dt2'] ?? ''));
    if ($dt2 === '' || $dt2 === '0000-00-00') {
        $data['dt2_unknown'] = '1';
    $data['dt2'] = '';
    } else {
        $data['dt2'] = $dt2;
    }

    $posUnknownFlags = cardOutTestResolvePosUnknownFlags($grave);
    $data['pos1_unknown'] = $posUnknownFlags['pos1_unknown'];
    $data['pos2_unknown'] = $posUnknownFlags['pos2_unknown'];
    $data['pos3_unknown'] = $posUnknownFlags['pos3_unknown'];

    return $data;
}

function cardOutTestResolvePosUnknownFlags(?array $grave): array
{
    $defaults = [
        'pos1_unknown' => '0',
        'pos2_unknown' => '0',
        'pos3_unknown' => '0',
    ];

    if (!$grave) {
        return $defaults;
    }

    $graveId = (int)($grave['idx'] ?? 0);
    $sessionFlags = [];
    if ($graveId > 0 && isset($_SESSION['grave_pos_unknown_flags'][$graveId]) && is_array($_SESSION['grave_pos_unknown_flags'][$graveId])) {
        $sessionFlags = $_SESSION['grave_pos_unknown_flags'][$graveId];
    }

    foreach (['pos1', 'pos2', 'pos3'] as $field) {
        $unknownKey = $field . '_unknown';
        $value = trim((string)($grave[$field] ?? ''));
        if ($value !== '' && $value !== '0') {
            $defaults[$unknownKey] = '0';
            continue;
        }
        if ($value === '0') {
            $defaults[$unknownKey] = '0';
            continue;
        }
        $defaults[$unknownKey] = (($sessionFlags[$unknownKey] ?? '0') === '1') ? '1' : '0';
    }

    return $defaults;
}

function cardOutTestStorePosUnknownFlags(int $graveId, array $flags): void
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

function cardOutTestRenderPosValue(?array $grave, string $field): string
{
    $value = trim((string)($grave[$field] ?? ''));
    if ($value !== '' && $value !== '0') {
        return cardOutTestEsc($value);
    }

    $flags = cardOutTestResolvePosUnknownFlags($grave);
    $unknownKey = $field . '_unknown';
    if (($flags[$unknownKey] ?? '0') === '1') {
        return cardOutTestEsc('Невідомо');
    }

    return cardOutTestEsc('Не вказано');
}

function cardOutTestNormalizeCoord(string $value): ?float
{
    $value = trim(str_replace(',', '.', $value));
    if ($value === '' || !is_numeric($value)) {
        return null;
    }

    return (float)$value;
}

function cardOutTestResolveCoordinates(?float $gpsx, ?float $gpsy): ?array
{
    if ($gpsx === null || $gpsy === null) {
        return null;
    }

    $variants = [
        ['lat' => $gpsx, 'lon' => $gpsy],
        ['lat' => $gpsy, 'lon' => $gpsx],
    ];

    $best = null;
    $bestScore = -999;

    foreach ($variants as $variant) {
        $lat = $variant['lat'];
        $lon = $variant['lon'];
        $score = 0;

        if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
            $score = -999;
        } else {
            $score += 2;
            if ($lat >= 44 && $lat <= 53 && $lon >= 22 && $lon <= 41) {
                $score += 3;
            } elseif ($lat >= 35 && $lat <= 60 && $lon >= 10 && $lon <= 60) {
                $score += 1;
            }
        }

        if ($score > $bestScore) {
            $bestScore = $score;
            $best = $variant;
        }
    }

    return $bestScore >= 0 ? $best : null;
}

function cardOutTestFormatCoord(float $value): string
{
    return rtrim(rtrim(sprintf('%.7F', $value), '0'), '.');
}

function cardOutTestPhotoPath(?string $path, string $fallback = '/graves/noimage.jpg'): string
{
    $path = trim((string)$path);
    if ($path === '' || !is_file($_SERVER['DOCUMENT_ROOT'] . $path)) {
        return $fallback;
    }

    return $path;
}

function cardOutTestAvatarPath(?string $path, string $fallback = '/avatars/ava.png'): string
{
    $path = trim((string)$path);
    if ($path === '') {
        return $fallback;
    }

    if ($path[0] !== '/') {
        $path = '/' . $path;
    }

    if (!is_file($_SERVER['DOCUMENT_ROOT'] . $path)) {
        return $fallback;
    }

    return $path;
}

function cardOutTestCollectPhotos(array $row): array
{
    $photos = [];
    foreach (['photo1', 'photo2', 'photo3'] as $field) {
        $path = trim((string)($row[$field] ?? ''));
        if ($path !== '' && is_file($_SERVER['DOCUMENT_ROOT'] . $path)) {
            $photos[] = $path;
        }
    }

    if (empty($photos)) {
        $photos[] = '/graves/noimage.jpg';
    }

    return array_values(array_unique($photos));
}

function cardOutTestRenderStatusBadge(string $status): string
{
    $status = strtolower(trim($status));
    if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
        $status = 'pending';
    }

    $label = 'На модерації';
    $class = 'grvdet-status grvdet-status--pending';

    if ($status === 'approved') {
        $label = 'Перевірено';
        $class = 'grvdet-status grvdet-status--approved';
    } elseif ($status === 'rejected') {
        $label = 'Відхилено';
        $class = 'grvdet-status grvdet-status--rejected';
    }

    return '<span class="' . $class . '">' . cardOutTestEsc($label) . '</span>';
}

function cardOutTestBuildViewData(?array $grave, int $currentUserId): array
{
    $data = [
        'safeName' => 'Картка поховання',
        'pageTitle' => 'Картка поховання',
        'photos' => ['/graves/noimage.jpg'],
        'statusBadge' => cardOutTestRenderStatusBadge('pending'),
        'canEdit' => false,
        'authorId' => 0,
        'authorName' => 'Невідомий користувач',
        'authorAvatar' => cardOutTestAvatarPath(''),
        'authorProfileUrl' => '#',
        'authorFieldValue' => cardOutTestEsc('Невідомий користувач'),
    ];

    if (!$grave) {
        return $data;
    }

    $nameParts = array_filter([
        trim((string)($grave['lname'] ?? '')),
        trim((string)($grave['fname'] ?? '')),
        trim((string)($grave['mname'] ?? '')),
    ], static fn($value) => $value !== '');

    $data['safeName'] = !empty($nameParts) ? implode(' ', $nameParts) : 'Без ПІБ';
    $data['pageTitle'] = $data['safeName'];
    $data['photos'] = cardOutTestCollectPhotos($grave);
    $data['statusBadge'] = cardOutTestRenderStatusBadge((string)($grave['moderation_status'] ?? 'pending'));
    $data['canEdit'] = $currentUserId > 0 && $currentUserId === (int)($grave['idxadd'] ?? 0);

    $authorName = trim((string)($grave['author_fname'] ?? '') . ' ' . (string)($grave['author_lname'] ?? ''));
    $authorName = $authorName !== '' ? $authorName : 'Невідомий користувач';
    $data['authorName'] = $authorName;
    $data['authorId'] = (int)($grave['idxadd'] ?? 0);
    $data['authorAvatar'] = cardOutTestAvatarPath((string)($grave['author_avatar'] ?? ''));
    $data['authorProfileUrl'] = $data['authorId'] > 0 ? '/public-profile.php?idx=' . $data['authorId'] : '#';

    if ($data['authorId'] > 0) {
        $data['authorFieldValue'] = '<button type="button" class="grvdet-author-btn" data-author-id="' . $data['authorId'] . '" data-author-name="' . cardOutTestEsc($authorName) . '" data-author-avatar="' . cardOutTestEsc($data['authorAvatar']) . '" data-author-profile="' . cardOutTestEsc($data['authorProfileUrl']) . '" data-author-self="' . ($data['authorId'] === $currentUserId ? '1' : '0') . '">' . cardOutTestEsc($authorName) . '</button>';
    } else {
        $data['authorFieldValue'] = cardOutTestEsc($authorName);
    }

    return $data;
}

function cardOutTestRenderField(string $label, string $value, string $extraClass = ''): string
{
    $class = 'grvdet-field';
    if ($extraClass !== '') {
        $class .= ' ' . $extraClass;
    }

    return '<div class="' . $class . '"><span>' . cardOutTestEsc($label) . '</span><b>' . $value . '</b></div>';
}

function cardOutTestRenderSaveIcon(bool $active): string
{
    if ($active) {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M2 2v13.5a.5.5 0 0 0 .74.439L8 13.069l5.26 2.87A.5.5 0 0 0 14 15.5V2a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2"/></svg>';
    }

    return '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M2 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v13.5a.5.5 0 0 1-.777.416L8 13.101l-5.223 2.815A.5.5 0 0 1 2 15.5zm2-1a1 1 0 0 0-1 1v12.566l4.723-2.482a.5.5 0 0 1 .554 0L13 14.566V2a1 1 0 0 0-1-1z"/></svg>';
}

function cardOutTestRenderGallery(array $photos, string $alt): string
{
    $count = count($photos);
    $isPlaceholderOnly = $count === 1 && ($photos[0] ?? '') === '/graves/noimage.jpg';
    $out = '<div class="grvdet-gallery" data-gallery>';
    $out .= '<div class="grvdet-gallery-stage">';

    foreach ($photos as $index => $photo) {
        $activeClass = $index === 0 ? ' is-active' : '';
        $attrs = ' class="grvdet-gallery-image' . $activeClass . '"';
        if (!$isPlaceholderOnly) {
            $attrs .= ' data-gallery-image="' . ($index + 1) . '" tabindex="0" role="button" aria-label="Відкрити фото ' . ($index + 1) . '"';
        }
        $out .= '<img src="' . cardOutTestEsc($photo) . '" alt="' . cardOutTestEsc($alt) . '"' . $attrs . '>';
    }

    $out .= '<div class="grvdet-gallery-overlay">';
    if (!$isPlaceholderOnly) {
        $out .= '<span class="grvdet-gallery-zoom" aria-hidden="true">';
        $out .= '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-zoom-in"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 10a7 7 0 1 0 14 0a7 7 0 1 0 -14 0" /><path d="M7 10l6 0" /><path d="M10 7l0 6" /><path d="M21 21l-6 -6" /></svg>';
        $out .= '<span class="grvdet-gallery-zoom-text">Повне фото</span>';
        $out .= '</span>';
    }
    if ($count > 1) {
        $out .= '<div class="grvdet-gallery-nav">';
        $out .= '<button type="button" class="grvdet-gallery-btn" data-gallery-prev aria-label="Попереднє фото">&#10094;</button>';
        $out .= '<button type="button" class="grvdet-gallery-btn" data-gallery-next aria-label="Наступне фото">&#10095;</button>';
        $out .= '</div>';
    }
    $out .= '</div>';
    $out .= '</div>';

    $out .= '</div>';
    return $out;
}

function cardOutTestRenderRelatedGraves(array $graves): string
{
    if (empty($graves)) {
        return '<div class="grvdet-empty-block">Для цього кладовища поки немає інших карток поховань.</div>';
    }

    $out = '<div class="grvdet-related-grid">';
    foreach ($graves as $grave) {
        $graveId = (int)($grave['idx'] ?? 0);
        $nameParts = array_filter([
            trim((string)($grave['lname'] ?? '')),
            trim((string)($grave['fname'] ?? '')),
            trim((string)($grave['mname'] ?? '')),
        ], static fn($value) => $value !== '');
        $name = !empty($nameParts) ? implode(' ', $nameParts) : 'Без ПІБ';
        $photo = cardOutTestPhotoPath((string)($grave['photo1'] ?? ''));
        $lifeRange = cardOutTestFormatLifeRange((string)($grave['dt1'] ?? ''), (string)($grave['dt2'] ?? ''));

        $out .= '<a href="/cardout.php?idx=' . $graveId . '" class="grvdet-related-card">';
        $out .= '<span class="grvdet-related-media"><img src="' . cardOutTestEsc($photo) . '" alt="' . cardOutTestEsc($name) . '"></span>';
        $out .= '<span class="grvdet-related-body">';
        $out .= '<b>' . cardOutTestEsc($name) . '</b>';
        $out .= '<small>' . cardOutTestEsc($lifeRange) . '</small>';
        $out .= '</span>';
        $out .= '</a>';
    }
    $out .= '</div>';

    return $out;
}

function cardOutTestRenderFeed(array $messages): string
{
    if (empty($messages)) {
        return '<div class="grvdet-empty-block">Публікацій для цієї картки ще немає.</div>';
    }

    $out = '<div class="grvdet-feed">';
    foreach ($messages as $message) {
        $author = trim((string)($message['username'] ?? ''));
        $author = $author !== '' ? $author : 'Користувач системи';
        $date = cardOutTestFormatDateTime((string)($message['dttmadd'] ?? ''));
        $text = trim((string)($message['atext'] ?? ''));

        $out .= '<article class="grvdet-feed-item">';
        $out .= '<div class="grvdet-feed-meta"><b>' . cardOutTestEsc($author) . '</b><span>' . cardOutTestEsc($date) . '</span></div>';
        $out .= '<p>' . nl2br(cardOutTestEsc($text)) . '</p>';
        $out .= '</article>';
    }
    $out .= '</div>';

    return $out;
}

$idx = (int)($_GET['idx'] ?? 0);
$view = (string)($_GET['view'] ?? 'view');
if (!in_array($view, ['view', 'edit', 'branch'], true)) {
    $view = 'view';
}
$branchPostId = (int)($_GET['post'] ?? 0);
$branchCommentId = (int)($_GET['comment_id'] ?? 0);
$branchReplyToId = (int)($_GET['reply_to'] ?? 0);
$dblink = DbConnect();
$lentaGrave = new LentaGrave($dblink);
$lentaGrave->handlePublishRequest($idx);

$grave = null;
$relatedGraves = [];
$feedComposerHtml = '';
$feedDividerHtml = '';
$feedFlashHtml = '';
$feedMessagesHtml = '';
$feedCount = 0;
$cemeteryGravesCount = 0;
$isSaved = false;
$publicationsTitle = 'Останні публікації';
$publicationsMeta = '';
$publicationsLinkHtml = '';
$grave = cardOutTestLoadGrave($dblink, $idx);

if ($grave) {
    $cemeteryId = (int)($grave['cemetery_idx'] ?? 0);
    if ($cemeteryId > 0) {
        $countRes = mysqli_query(
            $dblink,
            "SELECT COUNT(*) AS cnt FROM grave WHERE idxkladb = $cemeteryId AND LOWER(COALESCE(moderation_status, 'pending')) <> 'rejected'"
        );
        if ($countRes && ($countRow = mysqli_fetch_assoc($countRes))) {
            $cemeteryGravesCount = (int)($countRow['cnt'] ?? 0);
        }

        $relatedRes = mysqli_query(
            $dblink,
            "
                SELECT idx, lname, fname, mname, dt1, dt2, photo1
                FROM grave
                WHERE idxkladb = $cemeteryId
                  AND idx <> $idx
                  AND LOWER(COALESCE(moderation_status, 'pending')) <> 'rejected'
                ORDER BY idx DESC
                LIMIT 6
            "
        );
        if ($relatedRes) {
            while ($row = mysqli_fetch_assoc($relatedRes)) {
                $relatedGraves[] = $row;
            }
        }
    }

    $feedCount = $lentaGrave->countMessages($idx);
    $feedFlashHtml = $lentaGrave->renderFlash();
    if ($view === 'branch' && $branchPostId > 0) {
        $branchViewData = $lentaGrave->getBranchViewData(
            $idx,
            $branchPostId,
            $branchCommentId > 0 ? $branchCommentId : null,
            $branchReplyToId > 0 ? $branchReplyToId : null
        );
        $publicationsTitle = (string)($branchViewData['title'] ?? 'Гілка коментарів');
        $publicationsMeta = (string)($branchViewData['meta'] ?? '');
        $publicationsLinkHtml = (string)($branchViewData['link_html'] ?? '');
        $feedMessagesHtml = (string)($branchViewData['content_html'] ?? '');
    } else {
        if ($view === 'branch') {
            $publicationsTitle = 'Гілка коментарів';
            $publicationsMeta = 'Оберіть публікацію';
            $publicationsLinkHtml = '<a href="/cardout.php?idx=' . (int)$idx . '#publications" class="grvdet-inline-link" data-branch-open>До всіх публікацій</a>';
            $feedMessagesHtml = '<div class="ltt-empty">Щоб відкрити гілку, спочатку виберіть публікацію зі стрічки.</div>';
        } else {
            $feedComposerHtml = $lentaGrave->renderComposer($idx);
            $feedDividerHtml = $feedCount > 0 ? '<div class="ltt-feed-separator" aria-hidden="true"></div>' : '';
            $feedMessagesHtml = $lentaGrave->renderMessages($idx, 10);
        }
    }

    if ($publicationsMeta === '') {
        $publicationsMeta = 'У стрічці: ' . $feedCount;
    }

    $userId = (int)($_SESSION['uzver'] ?? 0);
    if ($userId > 0) {
        $savedRes = mysqli_query(
            $dblink,
            "SELECT 1 FROM saved_grave WHERE user_id = $userId AND grave_id = $idx LIMIT 1"
        );
        $isSaved = $savedRes && mysqli_num_rows($savedRes) > 0;
    }
}

$currentUserId = (int)($_SESSION['uzver'] ?? 0);
$viewData = cardOutTestBuildViewData($grave, $currentUserId);
$safeName = $viewData['safeName'];
$pageTitle = $viewData['pageTitle'];
$photos = $viewData['photos'];
$statusBadge = $viewData['statusBadge'];
$canEdit = $viewData['canEdit'];
$authorId = $viewData['authorId'];
$authorName = $viewData['authorName'];
$authorAvatar = $viewData['authorAvatar'];
$authorProfileUrl = $viewData['authorProfileUrl'];
$authorFieldValue = $viewData['authorFieldValue'];

$editMessageType = '';
$editMessageText = '';
$editFormData = cardOutTestBuildEditFormData($grave);

if ($view === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['md'] ?? '') === 'grave_edit') {
    $editFormData['region'] = trim((string)($_POST['region'] ?? ''));
    $editFormData['district'] = trim((string)($_POST['district'] ?? ''));
    $editFormData['town'] = trim((string)($_POST['town'] ?? ''));
    $editFormData['idxkladb'] = trim((string)($_POST['idxkladb'] ?? ''));
    $editFormData['lname'] = trim((string)($_POST['lname'] ?? ''));
    $editFormData['fname'] = trim((string)($_POST['fname'] ?? ''));
    $editFormData['mname'] = trim((string)($_POST['mname'] ?? ''));
    $editFormData['dt1'] = trim((string)($_POST['dt1'] ?? ''));
    $editFormData['dt2'] = trim((string)($_POST['dt2'] ?? ''));
    $editFormData['dt1_unknown'] = (string)($_POST['dt1_unknown'] ?? '0');
    $editFormData['dt2_unknown'] = (string)($_POST['dt2_unknown'] ?? '0');
    $editFormData['pos1_unknown'] = (string)($_POST['pos1_unknown'] ?? '0');
    $editFormData['pos2_unknown'] = (string)($_POST['pos2_unknown'] ?? '0');
    $editFormData['pos3_unknown'] = (string)($_POST['pos3_unknown'] ?? '0');
    $editFormData['pos1'] = trim((string)($_POST['pos1'] ?? ''));
    $editFormData['pos2'] = trim((string)($_POST['pos2'] ?? ''));
    $editFormData['pos3'] = trim((string)($_POST['pos3'] ?? ''));

    $dt1Unknown = $editFormData['dt1_unknown'] === '1';
    $dt2Unknown = $editFormData['dt2_unknown'] === '1';
    $pos1Unknown = $editFormData['pos1_unknown'] === '1';
    $pos2Unknown = $editFormData['pos2_unknown'] === '1';
    $pos3Unknown = $editFormData['pos3_unknown'] === '1';
    $normalizeDate = function (string $value, bool $isUnknown): string {
        if ($isUnknown) {
            return '0000-00-00';
        }
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
    };

    if ($currentUserId <= 0) {
        $editMessageType = 'error';
        $editMessageText = 'Для редагування потрібно авторизуватися.';
    } elseif (!$grave) {
        $editMessageType = 'error';
        $editMessageText = 'Картку не знайдено.';
    } elseif (!$canEdit) {
        $editMessageType = 'error';
        $editMessageText = 'Редагувати може лише користувач, який додав цю картку.';
    } else {
        $dt1 = $normalizeDate($editFormData['dt1'], $dt1Unknown);
        $dt2 = $normalizeDate($editFormData['dt2'], $dt2Unknown);

        $requiredMissing = (
            (int)$editFormData['region'] <= 0
            || (int)$editFormData['district'] <= 0
            || (int)$editFormData['town'] <= 0
            || (int)$editFormData['idxkladb'] <= 0
            || $editFormData['lname'] === ''
            || $editFormData['fname'] === ''
            || (!$dt1Unknown && $dt1 === '')
            || (!$dt2Unknown && $dt2 === '')
            || (!$pos1Unknown && $editFormData['pos1'] === '')
            || (!$pos2Unknown && $editFormData['pos2'] === '')
            || (!$pos3Unknown && $editFormData['pos3'] === '')
        );

        if ($requiredMissing) {
            $editMessageType = 'error';
            $editMessageText = 'Заповніть обов`язкові поля.';
        } else {
            $stmt = mysqli_prepare(
                $dblink,
                'UPDATE grave SET lname = ?, fname = ?, mname = ?, dt1 = ?, dt2 = ?, idxkladb = ?, pos1 = ?, pos2 = ?, pos3 = ?, ' . cardOutTestModerationResetSql() . ' WHERE idx = ? AND idxadd = ? LIMIT 1'
            );

            if (!$stmt) {
                $editMessageType = 'error';
                $editMessageText = 'Помилка підготовки запиту: ' . mysqli_error($dblink);
            } else {
                $cemeteryId = (int)$editFormData['idxkladb'];
                $pos1 = $pos1Unknown ? '' : $editFormData['pos1'];
                $pos2 = $pos2Unknown ? '' : $editFormData['pos2'];
                $pos3 = $pos3Unknown ? '' : $editFormData['pos3'];
                mysqli_stmt_bind_param(
                    $stmt,
                    'sssssisssii',
                    $editFormData['lname'],
                    $editFormData['fname'],
                    $editFormData['mname'],
                    $dt1,
                    $dt2,
                    $cemeteryId,
                    $pos1,
                    $pos2,
                    $pos3,
                    $idx,
                    $currentUserId
                );
                $updated = mysqli_stmt_execute($stmt);
                $stmtError = mysqli_stmt_error($stmt);
                mysqli_stmt_close($stmt);

                if (!$updated) {
                    $editMessageType = 'error';
                    $editMessageText = 'Помилка оновлення: ' . ($stmtError !== '' ? $stmtError : mysqli_error($dblink));
                } else {
                    cardOutTestStorePosUnknownFlags($idx, [
                        'pos1_unknown' => $editFormData['pos1_unknown'],
                        'pos2_unknown' => $editFormData['pos2_unknown'],
                        'pos3_unknown' => $editFormData['pos3_unknown'],
                    ]);
                    $photoUpdates = [];
                    $oldPhotos = [];
                    $oldRes = mysqli_query($dblink, "SELECT photo1, photo2, photo3 FROM grave WHERE idx = $idx LIMIT 1");
                    if ($oldRes && ($oldRow = mysqli_fetch_assoc($oldRes))) {
                        $oldPhotos = $oldRow;
                    }

                    $uploadDir = __DIR__ . '/graves/' . $idx;
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }

                    foreach (['photo1', 'photo2', 'photo3'] as $field) {
                        if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
                            continue;
                        }

                        $ext = strtolower((string)pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
                        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif'], true)) {
                            continue;
                        }

                        $safeName = $field . '_' . time() . '.' . $ext;
                        $targetPath = $uploadDir . '/' . $safeName;
                        $ok = gravecompress($_FILES[$field]['tmp_name'], $targetPath);
                        if (!$ok) {
                            move_uploaded_file($_FILES[$field]['tmp_name'], $targetPath);
                        }

                        if (file_exists($targetPath)) {
                            $photoUpdates[$field] = '/graves/' . $idx . '/' . $safeName;
                        }
                    }

                    if (!empty($photoUpdates)) {
                        $updates = [];
                        foreach ($photoUpdates as $field => $path) {
                            $updates[] = $field . "='" . mysqli_real_escape_string($dblink, $path) . "'";
                        }
                        mysqli_query(
                            $dblink,
                            "UPDATE grave SET " . implode(', ', $updates) . ", " . cardOutTestModerationResetSql() . " WHERE idx = $idx AND idxadd = $currentUserId LIMIT 1"
                        );

                        foreach ($photoUpdates as $field => $path) {
                            $oldPath = $oldPhotos[$field] ?? '';
                            if ($oldPath && $oldPath !== $path) {
                                $oldFile = __DIR__ . $oldPath;
                                if (is_file($oldFile)) {
                                    unlink($oldFile);
                                }
                            }
                        }
                    }

                    $editMessageType = 'success';
                    $editMessageText = 'Зміни успішно збережено.';
                    $grave = cardOutTestLoadGrave($dblink, $idx);
                    $viewData = cardOutTestBuildViewData($grave, $currentUserId);
                    $safeName = $viewData['safeName'];
                    $pageTitle = $viewData['pageTitle'];
                    $photos = $viewData['photos'];
                    $statusBadge = $viewData['statusBadge'];
                    $canEdit = $viewData['canEdit'];
                    $authorId = $viewData['authorId'];
                    $authorName = $viewData['authorName'];
                    $authorAvatar = $viewData['authorAvatar'];
                    $authorProfileUrl = $viewData['authorProfileUrl'];
                    $authorFieldValue = $viewData['authorFieldValue'];
                    $editFormData = cardOutTestBuildEditFormData($grave);
                }
            }
        }
    }
}

if ($view === 'edit' && $grave) {
    $pageTitle = 'Редагування: ' . $safeName;
}

$safeRegion = cardOutTestEsc($editFormData['region']);
$safeDistrict = cardOutTestEsc($editFormData['district']);
$safeTown = cardOutTestEsc($editFormData['town']);
$safeCemetery = cardOutTestEsc($editFormData['idxkladb']);
$safeLname = cardOutTestEsc($editFormData['lname']);
$safeFname = cardOutTestEsc($editFormData['fname']);
$safeMname = cardOutTestEsc($editFormData['mname']);
$dt1Unknown = ($editFormData['dt1_unknown'] ?? '0') === '1';
$dt2Unknown = ($editFormData['dt2_unknown'] ?? '0') === '1';
$pos1Unknown = ($editFormData['pos1_unknown'] ?? '0') === '1';
$pos2Unknown = ($editFormData['pos2_unknown'] ?? '0') === '1';
$pos3Unknown = ($editFormData['pos3_unknown'] ?? '0') === '1';
$safeDt1 = $dt1Unknown ? '' : cardOutTestEsc($editFormData['dt1']);
$safeDt2 = $dt2Unknown ? '' : cardOutTestEsc($editFormData['dt2']);
$safePos1 = $pos1Unknown ? '' : cardOutTestEsc($editFormData['pos1']);
$safePos2 = $pos2Unknown ? '' : cardOutTestEsc($editFormData['pos2']);
$safePos3 = $pos3Unknown ? '' : cardOutTestEsc($editFormData['pos3']);
$editPhoto1 = cardOutTestPhotoPath((string)($grave['photo1'] ?? ''), '');
$editPhoto2 = cardOutTestPhotoPath((string)($grave['photo2'] ?? ''), '');
$safePhoto1 = cardOutTestEsc($editPhoto1);
$safePhoto2 = cardOutTestEsc($editPhoto2);
$safePhoto1Name = cardOutTestEsc($editPhoto1 !== '' ? basename($editPhoto1) : '');
$safePhoto2Name = cardOutTestEsc($editPhoto2 !== '' ? basename($editPhoto2) : '');
$editSubmitSuccess = $editMessageType === 'success' ? '1' : '0';
$editAlertHtml = '';
if ($editMessageText !== '') {
    $alertClass = $editMessageType === 'success' ? 'acm-alert--success' : 'acm-alert--error';
    $editAlertHtml = '<div class="acm-alert ' . $alertClass . '">' . cardOutTestEsc($editMessageText) . '</div>';
}

ob_start();
?>
<article class="grvdet-panel grvdet-panel--wide" data-ltt-publications-panel data-grave-id="<?= (int)$idx ?>">
    <div class="grvdet-panel-head" id="publications">
        <div class="grvdet-panel-title">
            <h2><?= cardOutTestEsc($publicationsTitle) ?></h2>
            <span class="grvdet-panel-meta"><?= cardOutTestEsc($publicationsMeta) ?></span>
        </div>
        <?= $publicationsLinkHtml ?>
    </div>
    <?= $feedFlashHtml ?>
    <?= $feedComposerHtml ?>
    <?= $feedDividerHtml ?>
    <?= $feedMessagesHtml ?>
</article>
<?php
$publicationsPanelHtml = ob_get_clean();

if ($view !== 'edit' && (string)($_SERVER['HTTP_X_BRANCH_PARTIAL'] ?? '') === '1') {
    header('Content-Type: text/html; charset=utf-8');
    echo $publicationsPanelHtml;
    exit;
}

View_Clear();
View_Add(Page_Up($pageTitle));
View_Add(Menu_Up());
View_Add('<link rel="stylesheet" href="/assets/css/cardout.css?v=3">');
$lentaCssVersion = (int)@filemtime(__DIR__ . '/assets/css/lenta-grave.css');
if ($lentaCssVersion <= 0) {
    $lentaCssVersion = time();
}
$lentaJsVersion = (int)@filemtime(__DIR__ . '/assets/js/lenta-grave.js');
if ($lentaJsVersion <= 0) {
    $lentaJsVersion = time();
}
View_Add('<link rel="stylesheet" href="/assets/css/lenta-grave.css?v=' . $lentaCssVersion . '">');
View_Add('<script src="/assets/js/lenta-grave.js?v=' . $lentaJsVersion . '" defer></script>');
if ($view === 'edit') {
    $graveCssVersion = (int)@filemtime(__DIR__ . '/assets/css/grave.css');
    if ($graveCssVersion <= 0) {
        $graveCssVersion = time();
    }
    View_Add('<link rel="stylesheet" href="/assets/css/grave.css?v=' . $graveCssVersion . '">');
}

ob_start();
?>
<div class="out out-grvdet">
    <main class="grvdet-page<?= $view === 'edit' ? ' acm-page' : '' ?>">
        <?php if (!$grave): ?>
            <section class="grvdet-empty">
                <h1>Картку не знайдено</h1>
                <p>Запис за вказаним ідентифікатором відсутній або був видалений.</p>
                <div class="grvdet-actions">
                    <a href="/searchx.php" class="grvdet-btn grvdet-btn--dark">Повернутися до пошуку</a>
                    <a href="/" class="grvdet-btn grvdet-btn--light">На головну</a>
                </div>
            </section>
        <?php elseif ($view === 'edit'): ?>
            <section class="acm-layout">
                <aside class="acm-aside">
                    <div class="acm-badge">Форма редагування</div>
                    <h1 class="acm-heading">Оновіть запис про поховання</h1>
                    <p>Форма редагування заповнюється поетапно, щоб зміни були узгодженими з каталогом кладовищ.</p>
                    <ul class="acm-tips">
                        <li>Крок 1: перевірте персональні дані.</li>
                        <li>Крок 2: уточніть локацію та позицію.</li>
                        <li>Крок 3: за потреби оновіть фото й збережіть.</li>
                    </ul>
                </aside>

                <section class="acm-form-card">
                    <div class="acm-form-head">
                        <div>
                            <h2 class="acm-form-title">Редагувати поховання</h2>
                            <p class="acm-form-subtitle">Внесіть потрібні зміни та натисніть «Зберегти зміни».</p>
                        </div>
                        <a href="/cardout.php?idx=<?= (int)$idx ?>" class="acm-form-link">Повернутися до картки</a>
                    </div>
                    <?= $editAlertHtml ?>

                    <?php if (!$canEdit): ?>
                        <div class="acm-alert acm-alert--error">
                            Редагувати може лише користувач, який додав цю картку.
                        </div>
                        <div class="form-step-actions">
                            <a href="/cardout.php?idx=<?= (int)$idx ?>" class="acm-btn acm-btn--ghost">До картки</a>
                            <?php if ($currentUserId <= 0): ?>
                                <a href="/auth.php" class="acm-btn acm-btn--primary">Увійти</a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <form id="graveeditform" class="acm-form" method="post" enctype="multipart/form-data" novalidate autocomplete="off" data-submit-success="<?= $editSubmitSuccess ?>">
                            <input type="hidden" name="md" value="grave_edit">

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
                                            <input id="agf-dt1" type="date" name="dt1" value="<?= $safeDt1 ?>" placeholder="дд.мм.рррр"<?= $dt1Unknown ? '' : ' required' ?><?= $dt1Unknown ? ' disabled' : '' ?>>
                                            <input type="hidden" id="agf-dt1-unknown" name="dt1_unknown" value="<?= $dt1Unknown ? '1' : '0' ?>">
                                            <button type="button" class="agf-unknown-btn<?= $dt1Unknown ? ' is-active' : '' ?>" data-date-unknown="agf-dt1" data-unknown-input="agf-dt1-unknown" data-label-off="Позначити дату - невідомо" data-label-on="Вказати дату">
                                                <?= $dt1Unknown ? 'Вказати дату' : 'Позначити дату - невідомо' ?>
                                            </button>
                                        </div>
                                        <div class="acm-field">
                                            <label for="agf-dt2">Дата смерті *</label>
                                            <input id="agf-dt2" type="date" name="dt2" value="<?= $safeDt2 ?>" placeholder="дд.мм.рррр"<?= $dt2Unknown ? '' : ' required' ?><?= $dt2Unknown ? ' disabled' : '' ?>>
                                            <input type="hidden" id="agf-dt2-unknown" name="dt2_unknown" value="<?= $dt2Unknown ? '1' : '0' ?>">
                                            <button type="button" class="agf-unknown-btn<?= $dt2Unknown ? ' is-active' : '' ?>" data-date-unknown="agf-dt2" data-unknown-input="agf-dt2-unknown" data-label-off="Позначити дату - невідомо" data-label-on="Вказати дату">
                                                <?= $dt2Unknown ? 'Вказати дату' : 'Позначити дату - невідомо' ?>
                                            </button>
                                        </div>
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
                                                <?= cardOutTestRegionOptions($editFormData['region']) ?>
                                            </select>
                                        </div>
                                        <div class="acm-field">
                                            <label for="agf-district">Район *</label>
                                            <select id="agf-district" name="district" data-selected="<?= $safeDistrict ?>" required>
                                                <?= cardOutTestDistrictOptions($editFormData['region'], $editFormData['district']) ?>
                                            </select>
                                        </div>
                                        <div class="acm-field">
                                            <label for="agf-town">Населений пункт *</label>
                                            <div class="acm-settlement-wrap">
                                                <select id="agf-town" name="town" data-selected="<?= $safeTown ?>" required>
                                                    <?= cardOutTestTownOptions($editFormData['region'], $editFormData['district'], $editFormData['town']) ?>
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
                                                    <?= cardOutTestCemeteryOptions($editFormData['district'], $editFormData['idxkladb']) ?>
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
                                        <div class="acm-file agf-upload-card" data-upload-card="photo1" data-existing-src="<?= $safePhoto1 ?>" data-existing-name="<?= $safePhoto1Name ?>">
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
                                        <div class="acm-file agf-upload-card" data-upload-card="photo2" data-existing-src="<?= $safePhoto2 ?>" data-existing-name="<?= $safePhoto2Name ?>">
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
                                    <button type="submit" class="acm-btn acm-btn--primary">Зберегти зміни</button>
                                </div>
                            </div>
                        </form>
                    <?php endif; ?>
                </section>
            </section>

            <?php if ($canEdit): ?>
            <section class="acm-after-form">
                <h3 class="acm-after-form-title">Поради перед збереженням</h3>
                <div class="acm-after-form-grid">
                    <article class="acm-after-form-item">
                        <h4>Звірте ПІБ і дати</h4>
                        <p>Перед збереженням перевірте написання імені та коректність дат.</p>
                    </article>
                    <article class="acm-after-form-item">
                        <h4>Локація має значення</h4>
                        <p>Уточніть кладовище та позицію, щоб запис було легко знайти.</p>
                    </article>
                    <article class="acm-after-form-item">
                        <h4>Фото необов`язкові</h4>
                        <p>Можна зберегти зміни без фото та додати їх пізніше.</p>
                    </article>
                </div>
            </section>
            <?php endif; ?>

            <div class="acm-modal" id="agf-settlement-modal" aria-hidden="true">
                <div class="acm-modal__backdrop" data-agf-close-modal></div>
                <div class="acm-modal__card" role="dialog" aria-modal="true" aria-labelledby="agf-settlement-title">
                    <h3 id="agf-settlement-title" class="acm-modal__title">Додати населений пункт</h3>
                    <p class="acm-modal__text">Нова назва буде додана до обраного району та області.</p>
                    <div class="acm-field">
                        <label for="agf-new-settlement">Назва населеного пункту</label>
                        <input id="agf-new-settlement" type="text" autocomplete="off">
                    </div>
                    <div id="agf-settlement-hint" class="acm-modal-hint"></div>
                    <div class="acm-modal__actions">
                        <button type="button" class="acm-btn acm-btn--ghost" data-agf-close-modal>Скасувати</button>
                        <button type="button" id="agf-save-settlement" class="acm-btn acm-btn--primary">Додати</button>
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
    const form = document.getElementById("graveeditform");
    if (!form) {
        return;
    }

    const baseUrl = window.location.pathname;

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
    const clearFormBtns = Array.from(form.querySelectorAll(".agf-clear-form"));
    const photoModal = document.getElementById("agf-photo-modal");
    const photoModalImg = document.getElementById("agf-photo-modal-img");
    const photoModalTitle = document.getElementById("agf-photo-modal-title");
    const closePhotoModalNodes = photoModal ? photoModal.querySelectorAll("[data-agf-close-photo-modal]") : [];
    const dateUnknownButtons = Array.from(form.querySelectorAll(".agf-unknown-btn"));
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
    const draftStorageKey = "agf.graveeditform.<?= (int)$idx ?>.draft.v1";
    const draftTtlMs = 1000 * 60 * 60 * 6;
    const isSubmitSuccess = form.dataset.submitSuccess === "1";
    let suppressDraftSave = false;

    const placeholderById = {
        "agf-region": "Оберіть область",
        "agf-district": "Оберіть район",
        "agf-town": "Оберіть населений пункт",
        "agf-cemetery": "Оберіть кладовище"
    };

    if (!regionSel || !districtSel || !townSel || !cemeterySel || !openSettlementBtn || !settlementModal || !newSettlementInput || !saveSettlementBtn) {
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
            input.setAttribute("required", "");
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
            const pos1Unknown = document.getElementById("agf-pos1-unknown");
            const pos2Unknown = document.getElementById("agf-pos2-unknown");
            const pos3Unknown = document.getElementById("agf-pos3-unknown");
            if (dt1Unknown) {
                values.dt1_unknown = dt1Unknown.value;
            }
            if (dt2Unknown) {
                values.dt2_unknown = dt2Unknown.value;
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
        const pos1Unknown = document.getElementById("agf-pos1-unknown");
        const pos2Unknown = document.getElementById("agf-pos2-unknown");
        const pos3Unknown = document.getElementById("agf-pos3-unknown");
        if (dt1Unknown && typeof values.dt1_unknown === "string") {
            dt1Unknown.value = values.dt1_unknown;
        }
        if (dt2Unknown && typeof values.dt2_unknown === "string") {
            dt2Unknown.value = values.dt2_unknown;
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

        fetch(baseUrl + "?ajax_districts=1&region_id=" + encodeURIComponent(regionId), { credentials: "same-origin" })
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

        fetch(baseUrl + "?ajax_settlements=1&region_id=" + encodeURIComponent(regionId) + "&district_id=" + encodeURIComponent(districtId), { credentials: "same-origin" })
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

        fetch(baseUrl + "?ajax_cemeteries=1&district_id=" + encodeURIComponent(districtId), { credentials: "same-origin" })
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
    closeModalNodes.forEach(function (node) {
        node.addEventListener("click", closeModal);
    });
    closePhotoModalNodes.forEach(function (node) {
        node.addEventListener("click", closePhotoModal);
    });

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape") {
            if (settlementModal.classList.contains("is-open")) {
                closeModal();
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

        fetch(baseUrl, {
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

    function setUploadExisting(pair) {
        if (!pair || !pair.card || !pair.image) {
            return;
        }
        const existingSrc = pair.card.dataset.existingSrc || "";
        if (!existingSrc) {
            return;
        }
        const existingName = pair.card.dataset.existingName || "Фото завантажено";
        pair.image.src = existingSrc;
        pair.card.dataset.previewSrc = existingSrc;
        pair.card.classList.add("has-preview");
        if (pair.name) {
            pair.name.textContent = existingName;
        }
        if (pair.badge) {
            pair.badge.hidden = false;
        }
        if (pair.viewButton) {
            pair.viewButton.hidden = false;
            pair.viewButton.disabled = false;
        }
        if (pair.trigger) {
            pair.trigger.textContent = "Змінити";
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
        setUploadExisting(pair);

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

    if (isSubmitSuccess) {
        clearDraft();
    } else {
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
        <?php else: ?>
            <?php
            $cemeteryTitle = trim((string)($grave['cemetery_title'] ?? ''));
            $cemeteryTitle = $cemeteryTitle !== '' ? $cemeteryTitle : 'Не вказано';
            ?>
            <div class="grvdet-sticky" data-grvdet-sticky>
                <img src="<?= cardOutTestEsc($photos[0] ?? '/graves/noimage.jpg') ?>" alt="<?= cardOutTestEsc($safeName) ?>">
                <div>
                    <strong><?= cardOutTestEsc($safeName) ?></strong>
                    <span>ID #<?= (int)$grave['idx'] ?></span>
                </div>
                <button type="button" class="grvdet-sticky-top" data-scroll-top aria-label="Повернутися вгору">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 19V5"></path>
                        <path d="M5 12l7-7 7 7"></path>
                    </svg>
                </button>
            </div>

            <section class="grvdet-hero" data-grvdet-hero>
                <div class="grvdet-media-card">
                    <?= cardOutTestRenderGallery($photos, $safeName) ?>
                </div>

                <div class="grvdet-main">
                    <div class="grvdet-breadcrumbs">
                        <a href="/">Головна</a>
                        <span>/</span>
                        <a href="/searchx.php">Поховання</a>
                        <span>/</span>
                        <b><?= cardOutTestEsc($safeName) ?></b>
                    </div>

                    <div class="grvdet-topline">
                        <?= $statusBadge ?>
                        <span class="grvdet-id">ID #<?= (int)$grave['idx'] ?></span>
                    </div>

                    <h1 class="grvdet-title"><?= cardOutTestEsc($safeName) ?></h1>
                    <p class="grvdet-subtitle">
                        Основна сторінка картки поховання. Тут зібрані біографічні дані, місце поховання, статус запису
                        та публікації спільноти.
                    </p>

                    <div class="grvdet-chips">
                        <?php if (!empty($grave['town_title'])): ?>
                            <span class="grvdet-chip"><?= cardOutTestEsc((string)$grave['town_title']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($grave['district_title'])): ?>
                            <span class="grvdet-chip"><?= cardOutTestEsc((string)$grave['district_title']) ?> р-н</span>
                        <?php endif; ?>
                        <?php if (!empty($grave['region_title'])): ?>
                            <span class="grvdet-chip"><?= cardOutTestEsc((string)$grave['region_title']) ?> обл.</span>
                        <?php endif; ?>
                        <span class="grvdet-chip grvdet-chip--accent"><?= cardOutTestEsc($cemeteryTitle) ?></span>
                    </div>

                    <div class="grvdet-facts">
                        <?= cardOutTestRenderField('Дата народження', cardOutTestEsc(cardOutTestFormatDate((string)($grave['dt1'] ?? '')))) ?>
                        <?= cardOutTestRenderField('Дата смерті', cardOutTestEsc(cardOutTestFormatDate((string)($grave['dt2'] ?? '')))) ?>
                        <?= cardOutTestRenderField('Вік', cardOutTestEsc(cardOutTestFormatLifeYears((string)($grave['dt1'] ?? ''), (string)($grave['dt2'] ?? '')))) ?>
                        <?= cardOutTestRenderField('Додав запис', $authorFieldValue) ?>
                    </div>

                    <div class="grvdet-actions">
                        <a href="/searchx.php" class="grvdet-btn grvdet-btn--light">
                            <span class="grvdet-btn-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-search"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 10a7 7 0 1 0 14 0a7 7 0 1 0 -14 0" /><path d="M21 21l-6 -6" /></svg>
                            </span>
                            <span>До пошуку</span>
                        </a>
                        <?php if ((int)($grave['cemetery_idx'] ?? 0) > 0): ?>
                            <a href="/cemetery.php?idx=<?= (int)$grave['cemetery_idx'] ?>" class="grvdet-btn grvdet-btn--dark">Сторінка кладовища</a>
                        <?php endif; ?>
                        <?php if ((int)($_SESSION['uzver'] ?? 0) > 0): ?>
                            <button
                                type="button"
                                class="grvdet-btn grvdet-btn--save<?= $isSaved ? ' is-active' : '' ?>"
                                data-save-grave="<?= (int)$grave['idx'] ?>"
                                aria-pressed="<?= $isSaved ? 'true' : 'false' ?>"
                            >
                                <span class="grvdet-save-icon"><?= cardOutTestRenderSaveIcon($isSaved) ?></span>
                                <span class="grvdet-save-label"><?= $isSaved ? 'Збережено' : 'Зберегти' ?></span>
                            </button>
                        <?php else: ?>
                            <a href="/auth.php" class="grvdet-btn grvdet-btn--save">
                                <span class="grvdet-save-icon"><?= cardOutTestRenderSaveIcon(false) ?></span>
                                <span class="grvdet-save-label">Зберегти</span>
                            </a>
                        <?php endif; ?>
                        <?php if ($canEdit): ?>
                            <a href="/cardout/editgraveform?idx=<?= (int)$grave['idx'] ?>" class="grvdet-btn grvdet-btn--ghost">
                                <span class="grvdet-btn-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-edit"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1" /><path d="M20.385 6.585a2.1 2 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415" /><path d="M16 5l3 3" /></svg>
                                </span>
                                <span>Редагувати</span>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <section class="grvdet-grid">
                <article class="grvdet-panel">
                    <div class="grvdet-panel-head">
                        <h2>Місце розташування</h2>
                    </div>
                    <div class="grvdet-info-grid">
                        <?= cardOutTestRenderField('Область', cardOutTestEsc(trim((string)($grave['region_title'] ?? '')) !== '' ? (string)$grave['region_title'] : 'Не вказано')) ?>
                        <?= cardOutTestRenderField('Район', cardOutTestEsc(trim((string)($grave['district_title'] ?? '')) !== '' ? (string)$grave['district_title'] : 'Не вказано')) ?>
                        <?= cardOutTestRenderField('Населений пункт', cardOutTestEsc(trim((string)($grave['town_title'] ?? '')) !== '' ? (string)$grave['town_title'] : 'Не вказано'), 'grvdet-field--full') ?>
                    </div>
                </article>

                <article class="grvdet-panel">
                    <div class="grvdet-panel-head">
                        <h2>Місце поховання</h2>
                    </div>
                    <div class="grvdet-burial-grid">
                        <?= cardOutTestRenderField('Кладовище', cardOutTestEsc($cemeteryTitle)) ?>
                        <?= cardOutTestRenderField('Квартал', cardOutTestRenderPosValue($grave, 'pos1')) ?>
                        <?= cardOutTestRenderField('Ряд', cardOutTestRenderPosValue($grave, 'pos2')) ?>
                        <?= cardOutTestRenderField('Місце', cardOutTestRenderPosValue($grave, 'pos3')) ?>
                    </div>
                </article>

                <article class="grvdet-panel grvdet-panel--wide">
                    <div class="grvdet-panel-head">
                        <div class="grvdet-panel-title">
                            <h2>Інші поховання на цьому кладовищі</h2>
                            <span class="grvdet-panel-meta">Усього карток: <?= $cemeteryGravesCount ?></span>
                        </div>
                        <?php if ((int)($grave['cemetery_idx'] ?? 0) > 0): ?>
                            <a href="/cemetery.php?idx=<?= (int)$grave['cemetery_idx'] ?>" class="grvdet-inline-link grvdet-related-link-head">Відкрити кладовище</a>
                        <?php endif; ?>
                    </div>
                    <?= cardOutTestRenderRelatedGraves($relatedGraves) ?>
                    <?php if ((int)($grave['cemetery_idx'] ?? 0) > 0): ?>
                        <a href="/cemetery.php?idx=<?= (int)$grave['cemetery_idx'] ?>" class="grvdet-inline-link grvdet-related-link-bottom">Відкрити кладовище</a>
                    <?php endif; ?>
                </article>

                <article class="grvdet-panel grvdet-panel--wide grvdet-ad">
                    <div class="grvdet-ad__inner">
                        <div class="grvdet-ad__content">
                            <h3>Рекламний блок</h3>
                            <p>Тут може бути ваша реклама. Для деталей перейдіть на сторінку контактної інформації.</p>
                        </div>
                        <a href="/contacts.php" class="grvdet-btn grvdet-btn--light grvdet-ad__btn">Контактна інформація</a>
                    </div>
                </article>

                <?= $publicationsPanelHtml ?>
            </section>

            <div class="grvdet-author-pop" id="grvdetAuthorPop" aria-hidden="true">
                <button type="button" class="grvdet-close-btn grvdet-author-pop__close" data-author-pop-close aria-label="Закрити">&#10005;</button>
                <div class="grvdet-author-pop__head">
                    <span class="grvdet-author-pop__avatar">
                        <img src="<?= cardOutTestEsc($authorAvatar) ?>" alt="<?= cardOutTestEsc($authorName) ?>" data-author-avatar>
                    </span>
                    <div class="grvdet-author-pop__meta">
                        <strong data-author-name><?= cardOutTestEsc($authorName) ?></strong>
                        <span>Додав запис</span>
                    </div>
                </div>
                <div class="grvdet-author-pop__actions">
                    <a class="grvdet-author-pop__link" href="<?= cardOutTestEsc($authorProfileUrl) ?>" data-author-profile>Переглянути профіль</a>
                    <div class="grvdet-author-pop__link grvdet-author-pop__link--ghost" data-author-chat-form aria-disabled="true">Особисті чати вимкнено</div>
                    <a class="grvdet-author-pop__link grvdet-author-pop__link--self is-hidden" href="/profile.php" data-author-self-link>До профілю</a>
                    <a class="grvdet-author-pop__link grvdet-author-pop__link--ghost" href="/auth.php" data-author-login>
                        <span class="grvdet-author-pop__btn-icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-message"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M8 9h8" /><path d="M8 13h6" /><path d="M18 4a3 3 0 0 1 3 3v8a3 3 0 0 1 -3 3h-5l-5 3v-3h-2a3 3 0 0 1 -3 -3v-8a3 3 0 0 1 3 -3h12" /></svg>
                        </span>
                        <span>Увійти щоб написати</span>
                    </a>
                </div>
            </div>

            <div class="grvdet-share-pop" id="grvdetSharePop" aria-hidden="true">
                <button type="button" class="grvdet-close-btn grvdet-share-pop__close" data-share-pop-close aria-label="Закрити">&#10005;</button>
                <div class="grvdet-share-pop__head">
                    <div class="grvdet-share-pop__meta">
                        <strong data-share-title>Посилання</strong>
                        <span>Скопіюйте URL або скористайтеся кнопкою нижче</span>
                    </div>
                </div>
                <div class="grvdet-share-pop__body">
                    <label class="grvdet-share-pop__field">
                        <span>Посилання</span>
                        <input type="text" value="" readonly data-share-link-input>
                    </label>
                    <div class="grvdet-share-pop__actions">
                        <button type="button" class="grvdet-author-pop__btn" data-share-copy>
                            <span class="grvdet-author-pop__btn-icon" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9 15l6 -6" /><path d="M11 6l.463 -.536a5 5 0 0 1 7.071 7.072l-.534 .464" /><path d="M13 18l-.397 .534a5.068 5.068 0 0 1 -7.127 0a4.972 4.972 0 0 1 0 -7.071l.524 -.463" /></svg>
                            </span>
                            <span>Скопіювати</span>
                        </button>
                        <div class="grvdet-share-pop__feedback" data-share-feedback aria-live="polite"></div>
                    </div>
                </div>
            </div>

            <script>
            (function () {
                var galleries = document.querySelectorAll("[data-gallery]");
                if (!galleries.length) return;

                galleries.forEach(function (gallery) {
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

                    if (!images.length) return;

                    function ensureModal() {
                        if (modal) {
                            return;
                        }

                        var thumbsMarkup = images.map(function (image, index) {
                            var alt = image.alt || "";
                            return '<button type="button" class="grvdet-photo-modal__thumb" data-photo-modal-thumb="' + index + '" aria-label="Фото ' + (index + 1) + '"><img src="' + image.src + '" alt="' + alt.replace(/"/g, '&quot;') + '"></button>';
                        }).join("");

                        modal = document.createElement("div");
                        modal.className = "grvdet-photo-modal" + (images.length <= 1 ? " is-single" : "");
                        modal.innerHTML =
                            '<div class="grvdet-photo-modal__backdrop" data-photo-modal-close></div>' +
                            '<div class="grvdet-photo-modal__dialog" role="dialog" aria-modal="true" aria-label="Повнорозмірне фото">' +
                                '<button type="button" class="grvdet-close-btn grvdet-photo-modal__close" data-photo-modal-close aria-label="Закрити">&#10005;</button>' +
                                '<div class="grvdet-photo-modal__layout">' +
                                    (images.length > 1 ? '<div class="grvdet-photo-modal__thumbs" data-photo-modal-thumbs>' + thumbsMarkup + '</div>' : '') +
                                    '<div class="grvdet-photo-modal__stage">' +
                                        '<div class="grvdet-photo-modal__frame">' +
                                            '<img src="" alt="" class="grvdet-photo-modal__image">' +
                                        '</div>' +
                                        (images.length > 1
                                            ? '<button type="button" class="grvdet-photo-modal__nav grvdet-photo-modal__nav--prev" data-photo-modal-prev aria-label="Попереднє фото">&#10094;</button>' +
                                              '<button type="button" class="grvdet-photo-modal__nav grvdet-photo-modal__nav--next" data-photo-modal-next aria-label="Наступне фото">&#10095;</button>'
                                        : '') +
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

                function updateSaveButton(button, active) {
                    var iconNode = button.querySelector(".grvdet-save-icon");
                    var labelNode = button.querySelector(".grvdet-save-label");
                    if (!iconNode || !labelNode) return;

                    iconNode.innerHTML = active
                        ? '<?= cardOutTestRenderSaveIcon(true) ?>'
                        : '<?= cardOutTestRenderSaveIcon(false) ?>';
                    labelNode.textContent = active ? "Збережено" : "Зберегти";
                    button.classList.toggle("is-active", active);
                    button.setAttribute("aria-pressed", active ? "true" : "false");
                }

                var saveBtn = document.querySelector("[data-save-grave]");
                if (saveBtn) {
                    saveBtn.addEventListener("click", function () {
                        if (saveBtn.disabled) return;

                        var fd = new FormData();
                        fd.append("action", "toggle_save_grave");
                        fd.append("grave_id", saveBtn.getAttribute("data-save-grave") || "");

                        saveBtn.disabled = true;

                        fetch(window.location.href, {
                            method: "POST",
                            body: fd,
                            credentials: "same-origin"
                        })
                            .then(function (response) {
                                if (!response.ok) {
                                    throw new Error("network");
                                }
                                return response.json();
                            })
                            .then(function (payload) {
                                if (!payload || !payload.status) {
                                    throw new Error("invalid");
                                }

                                if (payload.status === "saved") {
                                    updateSaveButton(saveBtn, true);
                                    return;
                                }

                                if (payload.status === "removed") {
                                    updateSaveButton(saveBtn, false);
                                    return;
                                }

                                throw new Error(payload.message || "unknown");
                            })
                            .catch(function () {
                                alert("Не вдалося змінити статус збереження.");
                            })
                            .finally(function () {
                                saveBtn.disabled = false;
                            });
                    });
                }

                var authorPop = document.getElementById("grvdetAuthorPop");
                var sharePop = document.getElementById("grvdetSharePop");
                if (authorPop) {
                    var avatarNode = authorPop.querySelector("[data-author-avatar]");
                    var nameNode = authorPop.querySelector("[data-author-name]");
                    var profileLink = authorPop.querySelector("[data-author-profile]");
                    var chatForm = authorPop.querySelector("[data-author-chat-form]");
                    var chatInput = chatForm ? chatForm.querySelector("input[name='target_user']") : null;
                    var loginLink = authorPop.querySelector("[data-author-login]");
                    var selfLink = authorPop.querySelector("[data-author-self-link]");
                    var closeBtn = authorPop.querySelector("[data-author-pop-close]");
                    var currentUserId = <?= $currentUserId ?>;
                    var shareTitleNode = sharePop ? sharePop.querySelector("[data-share-title]") : null;
                    var shareInput = sharePop ? sharePop.querySelector("[data-share-link-input]") : null;
                    var shareCopyBtn = sharePop ? sharePop.querySelector("[data-share-copy]") : null;
                    var shareFeedback = sharePop ? sharePop.querySelector("[data-share-feedback]") : null;
                    var shareCloseBtn = sharePop ? sharePop.querySelector("[data-share-pop-close]") : null;

                    function closeAuthorPop() {
                        authorPop.classList.remove("is-open");
                        authorPop.setAttribute("aria-hidden", "true");
                    }

                    function closeSharePop() {
                        if (!sharePop) {
                            return;
                        }

                        sharePop.classList.remove("is-open");
                        sharePop.setAttribute("aria-hidden", "true");
                        if (shareFeedback) {
                            shareFeedback.textContent = "";
                        }
                    }

                    function placePop(pop, event) {
                        if (!pop) {
                            return;
                        }

                        pop.style.left = "0px";
                        pop.style.top = "0px";
                        pop.classList.add("is-open");
                        pop.setAttribute("aria-hidden", "false");

                        var padding = 12;
                        var popRect = pop.getBoundingClientRect();
                        var left = event.clientX + 12;
                        var top = event.clientY + 12;

                        if (left + popRect.width > window.innerWidth - padding) {
                            left = window.innerWidth - popRect.width - padding;
                        }
                        if (top + popRect.height > window.innerHeight - padding) {
                            top = window.innerHeight - popRect.height - padding;
                        }
                        if (left < padding) left = padding;
                        if (top < padding) top = padding;

                        pop.style.left = left + "px";
                        pop.style.top = top + "px";
                    }

                    function normalizeShareUrl(url) {
                        try {
                            return new URL(url, window.location.origin).toString();
                        } catch (error) {
                            return window.location.href;
                        }
                    }

                    function openAuthorPop(btn, event) {
                        var authorId = btn.getAttribute("data-author-id") || "";
                        var authorName = btn.getAttribute("data-author-name") || "";
                        var authorAvatar = btn.getAttribute("data-author-avatar") || "";
                        var authorProfile = btn.getAttribute("data-author-profile") || "#";
                        var isSelf = btn.getAttribute("data-author-self") === "1";

                        if (avatarNode) {
                            avatarNode.src = authorAvatar;
                            avatarNode.alt = authorName;
                        }
                        if (nameNode) {
                            nameNode.textContent = authorName;
                        }
                        if (profileLink) {
                            profileLink.setAttribute("href", authorProfile);
                        }
                        if (chatInput) {
                            chatInput.value = authorId;
                        }

                        if (currentUserId > 0) {
                            if (isSelf) {
                                if (chatForm) {
                                    chatForm.classList.add("is-hidden");
                                }
                                if (loginLink) {
                                    loginLink.classList.add("is-hidden");
                                }
                                if (selfLink) {
                                    selfLink.classList.remove("is-hidden");
                                }
                            } else {
                                if (chatForm) {
                                    chatForm.classList.remove("is-hidden");
                                }
                                if (loginLink) {
                                    loginLink.classList.add("is-hidden");
                                }
                                if (selfLink) {
                                    selfLink.classList.add("is-hidden");
                                }
                            }
                        } else {
                            if (chatForm) {
                                chatForm.classList.add("is-hidden");
                            }
                            if (loginLink) {
                                loginLink.classList.remove("is-hidden");
                            }
                            if (selfLink) {
                                selfLink.classList.add("is-hidden");
                            }
                        }

                        closeSharePop();
                        placePop(authorPop, event);
                    }

                    function openSharePop(trigger, event) {
                        if (!sharePop) {
                            return;
                        }

                        var shareTitle = trigger.getAttribute("data-share-title") || "Посилання";
                        var shareUrl = normalizeShareUrl(trigger.getAttribute("data-share-url") || window.location.href);

                        if (shareTitleNode) {
                            shareTitleNode.textContent = shareTitle;
                        }
                        if (shareInput) {
                            shareInput.value = shareUrl;
                        }
                        if (shareFeedback) {
                            shareFeedback.textContent = "";
                        }

                        closeAuthorPop();
                        placePop(sharePop, event);
                    }

                    document.addEventListener("click", function (event) {
                        var authorBtn = event.target.closest(".grvdet-author-btn");
                        if (authorBtn) {
                            event.preventDefault();
                            event.stopPropagation();
                            openAuthorPop(authorBtn, event);
                            return;
                        }

                        var shareTrigger = event.target.closest("[data-share-trigger]");
                        if (shareTrigger) {
                            event.preventDefault();
                            event.stopPropagation();
                            openSharePop(shareTrigger, event);
                            return;
                        }

                        if (event.target.closest("[data-author-pop-close]")) {
                            closeAuthorPop();
                            return;
                        }

                        if (event.target.closest("[data-share-pop-close]")) {
                            closeSharePop();
                            return;
                        }

                        if (authorPop.classList.contains("is-open") && authorPop.contains(event.target)) {
                            return;
                        }

                        if (sharePop && sharePop.classList.contains("is-open") && sharePop.contains(event.target)) {
                            return;
                        }

                        closeAuthorPop();
                        closeSharePop();
                    });

                    if (shareCopyBtn && shareInput) {
                        shareCopyBtn.addEventListener("click", function () {
                            var value = shareInput.value || "";
                            if (value === "") {
                                return;
                            }

                            function updateShareFeedback(success) {
                                if (!shareFeedback) {
                                    return;
                                }

                                shareFeedback.textContent = success
                                    ? "Посилання скопійовано."
                                    : "Не вдалося скопіювати автоматично.";
                            }

                            if (navigator.clipboard && navigator.clipboard.writeText) {
                                navigator.clipboard.writeText(value).then(function () {
                                    updateShareFeedback(true);
                                }).catch(function () {
                                    shareInput.focus();
                                    shareInput.select();
                                    updateShareFeedback(false);
                                });
                                return;
                            }

                            shareInput.focus();
                            shareInput.select();
                            try {
                                updateShareFeedback(document.execCommand("copy"));
                            } catch (error) {
                                updateShareFeedback(false);
                            }
                        });
                    }

                    document.addEventListener("keydown", function (event) {
                        if (event.key === "Escape") {
                            closeAuthorPop();
                            closeSharePop();
                        }
                    });

                    window.addEventListener("scroll", function () {
                        closeAuthorPop();
                        closeSharePop();
                    }, true);
                }

                var sticky = document.querySelector("[data-grvdet-sticky]");
                var hero = document.querySelector("[data-grvdet-hero]");
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
        <?php endif; ?>
    </main>
</div>
<?php
$pageHtml = ob_get_clean();

mysqli_close($dblink);

View_Add($pageHtml);
View_Add(Page_Down());
View_Out();
View_Clear();
