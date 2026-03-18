<?php

/**
 * @var $md
 * @var $buf
 */

require_once "function.php";
require_once "mailer.php";
require_once "roles.php";
require_once "validator.php";
require_once "classes/chats.php";
showMessage();

View_Clear();
View_Add(Page_Up('Профіль'));
View_Add(Menu_Up());
View_Add(Menu_Profile_Mobile());

View_Add('<div class="layout">');

View_Add('<aside class="sidebar">');
View_Add(Menu_Profile());
View_Add('</aside>');

View_Add('<main class="content-profile">');
View_Add('<div class="content-inner">');
View_Add('<div class="out-profile">');



if (!isset($_SESSION['logged']) || $_SESSION['logged'] != 1) {
    $mdValue = isset($md) ? (string)$md : '';
    $legacyResetToken = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
    if ($mdValue === '73' && $legacyResetToken !== '') {
        header('Location: /auth.php?reset_token=' . urlencode($legacyResetToken));
        exit;
    }
    header('Location: /auth.php');
    exit;
}

$dblink = DbConnect();
if ($md === '010') {
    $md = '4';
}

// Смена фамилии
if ($md == 22 && isset($_POST['lname'])) {
    $a = $_POST['lname'];
    $sql = 'UPDATE users SET lname="' . $a . '" WHERE idx=' . $_SESSION['uzver'];
    mysqli_query($dblink, $sql);
    $md = 2;
}

// Смена имени
if ($md == 23 && isset($_POST['fname'])) {
    $a = $_POST['fname'];
    $sql = 'UPDATE users SET fname="' . $a . '" WHERE idx=' . $_SESSION['uzver'];
    mysqli_query($dblink, $sql);
    $md = 2;
}




// Обновление телефона
if ($md == 33 && isset($_POST['tel'])) {
    $a = $_POST['tel'];
    $sql = 'UPDATE users SET tel="' . $a . '" WHERE idx=' . $_SESSION['uzver'];
    mysqli_query($dblink, $sql);
    $md = 3;
}


function sendActivationFromProfile(int $userId): bool
{
    $dblink = DbConnect();

    $act = $dblink->prepare("
        SELECT email, fname, activ
        FROM users
        WHERE idx = ?
        LIMIT 1
    ");
    $act->bind_param("i", $userId);
    $act->execute();

    $act->bind_result($email, $fname, $activ);
    if (!$act->fetch()) {
        $act->close();
        return false;
    }
    $act->close();

    if ((int)$activ === 1) {
        return false;
    }

    return sendActivationEmail(
        $userId,
        $email,
        $fname,
        $dblink
    );
}

function profilePublicationModerationMeta(?string $status): array
{
    $statusKey = strtolower(trim((string)$status));
    if (!in_array($statusKey, ['pending', 'approved', 'rejected'], true)) {
        $statusKey = 'pending';
    }

    $labels = [
        'pending' => 'На модерації',
        'approved' => 'Перевірено модератором',
        'rejected' => 'Відхилено',
    ];

    return [
        'key' => $statusKey,
        'label' => $labels[$statusKey],
    ];
}




// Загальна информация
if (($md == 0) || ($md == '')) {
    if ($_SESSION['logged'] == 1) {
        $sql = 'SELECT u.*, d.title AS district_title, r.title AS region_title 
        FROM users u
        LEFT JOIN district d ON u.mesto = d.idx
        LEFT JOIN region r ON d.region = r.idx
        WHERE u.idx=' . intval($_SESSION['uzver']);

        $res = mysqli_query($dblink, $sql);
        if (mysqli_num_rows($res) == 1) {
            $p = mysqli_fetch_assoc($res);
            $userId = (int)$_SESSION['uzver'];

            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_saved_grave') {
                header('Content-Type: application/json; charset=utf-8');
                $graveId = isset($_POST['grave_id']) ? (int)$_POST['grave_id'] : 0;
                if ($graveId <= 0) {
                    echo json_encode(['status' => 'error', 'msg' => 'Некоректний ID']);
                    exit;
                }

                $stmtRemoveSaved = mysqli_prepare($dblink, "DELETE FROM saved_grave WHERE user_id = ? AND grave_id = ? LIMIT 1");
                mysqli_stmt_bind_param($stmtRemoveSaved, 'ii', $userId, $graveId);
                $okRemoveSaved = mysqli_stmt_execute($stmtRemoveSaved);
                mysqli_stmt_close($stmtRemoveSaved);

                if ($okRemoveSaved) {
                    echo json_encode(['status' => 'ok']);
                } else {
                    echo json_encode(['status' => 'error', 'msg' => 'Помилка видалення']);
                }
                exit;
            }

            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_activation'])) {
                $ok = sendActivationFromProfile((int)$_SESSION['uzver']);

                if ($ok) {
                    $_SESSION['message'] = 'Лист для активації надіслано. Перевірте пошту, а також папку "Спам".';
                    $_SESSION['messageType'] = 'success';
                } else {
                    $_SESSION['message'] = 'Не вдалося надіслати лист для активації. Спробуйте ще раз.';
                    $_SESSION['messageType'] = 'error';
                }
                header('Location: /profile.php');
                exit;
            }

            View_Add('<link rel="stylesheet" href="/assets/css/profile.css">');
            View_Add('<div class="profile-view-container">');
            $activeProfileTab = isset($_GET['tab']) ? (string)$_GET['tab'] : 'general';
            if (!in_array($activeProfileTab, ['general', 'publications', 'saved'], true)) {
                $activeProfileTab = 'general';
            }

            // Заголовок
            View_Add('<div class="profile-view-header">');
            View_Add('<h1 class="profile-view-title">Профіль</h1>');
            View_Add('<p class="profile-view-subtitle">Керуйте своїми особистими даними</p>');
            View_Add('</div>');
            View_Add('<div class="profile-view-tabs">');
            View_Add('<button type="button" class="profile-view-tab-btn ' . ($activeProfileTab === 'general' ? 'active' : '') . '" data-tab="general">Загальна інформація</button>');
            View_Add('<button type="button" class="profile-view-tab-btn ' . ($activeProfileTab === 'publications' ? 'active' : '') . '" data-tab="publications">Мої публікації</button>');
            View_Add('<button type="button" class="profile-view-tab-btn ' . ($activeProfileTab === 'saved' ? 'active' : '') . '" data-tab="saved">Збережене</button>');
            View_Add('</div>');
            View_Add('<div class="profile-view-tab-content" id="profile-tab-general" style="display: ' . ($activeProfileTab === 'general' ? 'block' : 'none') . ';">');

            // Основная карточка профиля
            View_Add('<div class="profile-view-main-card">');
            View_Add('<div class="profile-view-avatar-wrapper">');
            View_Add('<img class="profile-view-avatar" src="' . ($p['avatar'] != '' ? htmlspecialchars($p['avatar']) : '/avatars/ava.png') . '" alt="Аватар">');
            View_Add('</div>');

            if (!empty($p['fname']) || !empty($p['lname'])) {
                View_Add('<div class="profile-view-name">' . htmlspecialchars($p['lname'] . ' ' . $p['fname']) . '</div>');
            } else {
                View_Add('<div class="profile-view-name">Пользователь</div>');
                View_Add('<div style="margin-top: 10px; padding: 10px 15px; background-color: #eef5ff; border: 1px dashed #a0c4ff; border-radius: 10px; font-size: 14px;">');
                View_Add('<p style="margin: 0;">Ви ще не вказали інформацію про себе. <a href="/profile.php/?md=2" class="profile-fill-link">Заповнити зараз</a></p>');
                View_Add('</div>');
            }
            View_Add('</div>');

            // Две карточки с информацией
            View_Add('<div class="profile-view-info-grid">');

            // Карточка контактной информации
            View_Add('<div class="profile-view-info-card">');
            View_Add('<h2 class="profile-view-info-card-title">Контактна інформація</h2>');
            View_Add('<p class="profile-view-info-card-subtitle">Ваші контактні дані</p>');

            View_Add('<div class="profile-view-info-block">');
            View_Add('<div class="profile-view-icon-box">');
            View_Add('<svg class="profile-view-icon-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">');
            View_Add('<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>');
            View_Add('</svg>');
            View_Add('</div>');
            View_Add('<div class="profile-view-info-item-content">');
            View_Add('<span class="profile-view-info-label">Email</span>');
            View_Add('<span class="profile-view-info-value">' . htmlspecialchars($p['email']) . '</span>');
            View_Add('</div>');
            View_Add('</div>');
            
            View_Add('<div class="profile-view-info-block">');
            View_Add('<div class="profile-view-icon-box">');
            View_Add('<svg class="profile-view-icon-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">');
            View_Add('<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>');
            View_Add('</svg>');
            View_Add('</div>');
            View_Add('<div class="profile-view-info-item-content">');
            View_Add('<span class="profile-view-info-label">Телефон</span>');
            View_Add('<span class="profile-view-info-value">' . (!empty($p['tel']) ? htmlspecialchars($p['tel']) : 'Не вказано') . '</span>');
            View_Add('</div>');
            View_Add('</div>');
            
            View_Add('<div class="profile-view-info-block">');
            View_Add('<div class="profile-view-icon-box">');
            View_Add('<svg class="profile-view-icon-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">');
            View_Add('<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>');
            View_Add('<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>');
            View_Add('</svg>');
            View_Add('</div>');
            View_Add('<div class="profile-view-info-item-content">');
            View_Add('<span class="profile-view-info-label">Місто</span>');
            $location = '';
            if (!empty($p['region_title'])) {
                $location .= htmlspecialchars($p['region_title']) . ' область';
            }
            if (!empty($p['district_title'])) {
                if (!empty($location)) $location .= ', ';
                $location .= htmlspecialchars($p['district_title']) . ' район';
            }
            if (empty($location)) {
                $location = 'Не вказано';
            }
            View_Add('<span class="profile-view-info-value">' . $location . '</span>');
            View_Add('</div>');
            View_Add('</div>');

            View_Add('</div>');

            // Карточка статистики аккаунта
            View_Add('<div class="profile-view-info-card">');
            View_Add('<h2 class="profile-view-info-card-title">Статистика облікового запису</h2>');
            View_Add('<p class="profile-view-info-card-subtitle">Інформація про ваш обліковий запис</p>');

            // Дата регистрации
            $regDate = 'Не указана';
            if (!empty($p['dttmreg'])) {
                $timestamp = strtotime($p['dttmreg']);
                if ($timestamp !== false) {
                    $months = ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
                    $regDate = date('j', $timestamp) . ' ' . $months[date('n', $timestamp) - 1] . ' ' . date('Y', $timestamp);
                }
            }

            View_Add('<div class="profile-view-info-block">');
            View_Add('<div class="profile-view-icon-box">');
            View_Add('<svg class="profile-view-icon-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">');
            View_Add('<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>');
            View_Add('</svg>');
            View_Add('</div>');
            View_Add('<div class="profile-view-info-item-content">');
            View_Add('<span class="profile-view-info-label">Дата реєстрації</span>');
            View_Add('<span class="profile-view-info-value">' . $regDate . '</span>');
            View_Add('</div>');
            View_Add('</div>');

            // Статус аккаунта
            $isAccountActive = (int)$p['activ'] === 1;
            View_Add('<div class="profile-view-status-block">');
            View_Add('<div class="profile-view-status-table">');
            View_Add('<div class="profile-view-status-row">');
            View_Add('<span class="profile-view-info-label">Статус облікового запису</span>');
            if ($isAccountActive) {
                View_Add('<span class="profile-view-status-badge profile-view-status-active">Активовано</span>');
            } else {
                View_Add('<span class="profile-view-status-badge profile-view-status-inactive">Не активовано</span>');
            }
            View_Add('</div>');
            if (!$isAccountActive) {
                View_Add('<div class="profile-view-status-actions">');
                View_Add('<form method="post">');
                View_Add('<button type="submit" name="send_activation" class="profile-view-activate-btn">Активувати акаунт</button>');
                View_Add('</form>');
                View_Add('</div>');
            }
            View_Add('</div>');
            View_Add('</div>');

            View_Add('</div>');
            View_Add('</div>');

            View_Add('</div>');
            View_Add('<div class="profile-view-tab-content" id="profile-tab-publications" style="display: ' . ($activeProfileTab === 'publications' ? 'block' : 'none') . ';">');
            $publications = [];
            $pubRes = mysqli_query($dblink, "
                SELECT
                    g.idx,
                    g.fname,
                    g.lname,
                    g.mname,
                    g.dt1,
                    g.dt2,
                    g.photo1,
                    g.moderation_status,
                    d.title AS district_title,
                    r.title AS region_title
                FROM grave g
                LEFT JOIN cemetery c ON g.idxkladb = c.idx
                LEFT JOIN district d ON c.district = d.idx
                LEFT JOIN region r ON d.region = r.idx
                WHERE g.idxadd = $userId
                ORDER BY g.idtadd DESC, g.idx DESC
            ");
            if ($pubRes) {
                while ($pubRow = mysqli_fetch_assoc($pubRes)) {
                    $publications[] = $pubRow;
                }
            }
            $publicationsCount = count($publications);

            View_Add('<div class="profile-pubs-toolbar">');
            View_Add('<div class="profile-pubs-total">Всього публікацій: <strong>' . $publicationsCount . '</strong></div>');
            View_Add('<a class="profile-pubs-add-btn" href="/graveaddform.php"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="btn-add-icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14" /><path d="M5 12l14 0" /></svg>Додати поховання</a>');
            View_Add('<div class="profile-pubs-search"><input type="text" class="profile-pubs-search-input" placeholder="Пошук за прізвищем / ім`ям / по батькові" aria-label="Пошук публікацій"></div>');
            View_Add('</div>');
            $formatPubDate = static function (?string $date): string {
                if (empty($date) || $date === '0000-00-00') {
                    return 'Дата не вказана';
                }
                $timestamp = strtotime($date);
                if ($timestamp === false) {
                    return 'Дата не вказана';
                }
                return date('d.m.Y', $timestamp);
            };

            if ($publicationsCount === 0) {
                View_Add('<div class="profile-pubs-empty">Немає публікацій</div>');
            } else {
                View_Add('<div class="profile-pubs-list">');
                foreach ($publications as $publication) {
                    $pubId = (int)$publication['idx'];
                    $photoPath = '/graves/noimage.jpg';
                    if (!empty($publication['photo1']) && is_file($_SERVER['DOCUMENT_ROOT'] . $publication['photo1'])) {
                        $photoPath = $publication['photo1'];
                    }

                    $fio = trim(($publication['lname'] ?? '') . ' ' . ($publication['fname'] ?? '') . ' ' . ($publication['mname'] ?? ''));
                    if ($fio === '') {
                        $fio = 'Без імені';
                    }
                    $searchIndex = function_exists('mb_strtolower')
                        ? mb_strtolower($fio, 'UTF-8')
                        : strtolower($fio);

                    $birthDate = $formatPubDate($publication['dt1'] ?? null);
                    $deathDate = $formatPubDate($publication['dt2'] ?? null);
                    $moderationMeta = profilePublicationModerationMeta((string)($publication['moderation_status'] ?? 'pending'));

                    $locationParts = [];
                    if (!empty($publication['region_title'])) {
                        $locationParts[] = $publication['region_title'] . ' область';
                    }
                    if (!empty($publication['district_title'])) {
                        $locationParts[] = $publication['district_title'] . ' район';
                    }
                    $locationLabel = !empty($locationParts) ? implode(', ', $locationParts) : 'Місце не вказано';

                    View_Add('
                    <div class="profile-pub-card profile-publication-card" data-search="' . htmlspecialchars($searchIndex, ENT_QUOTES, 'UTF-8') . '">
                        <img class="profile-pub-card-photo" src="' . htmlspecialchars($photoPath, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($fio, ENT_QUOTES, 'UTF-8') . '">
                        <div class="profile-pub-card-body">
                            <div class="profile-pub-card-head">
                                <div class="profile-pub-card-name">' . htmlspecialchars($fio, ENT_QUOTES, 'UTF-8') . '</div>
                                <span class="profile-pub-card-status profile-pub-card-status--' . htmlspecialchars($moderationMeta['key'], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($moderationMeta['label'], ENT_QUOTES, 'UTF-8') . '</span>
                            </div>
                            <div class="profile-pub-card-meta">' . htmlspecialchars($birthDate . ' - ' . $deathDate, ENT_QUOTES, 'UTF-8') . '</div>
                            <div class="profile-pub-card-meta">' . htmlspecialchars($locationLabel, ENT_QUOTES, 'UTF-8') . '</div>
                        </div>
                        <div class="profile-pub-card-actions">
                            <a class="profile-pub-card-link" href="/cardout.php?idx=' . $pubId . '">Переглянути</a>
                            <a class="profile-pub-card-link profile-pub-card-link--secondary" href="/cardout.php?idx=' . $pubId . '&edit=1">Редагувати</a>
                        </div>
                    </div>');
                }
                View_Add('</div>');
                View_Add('<div class="profile-pubs-empty" id="profile-pubs-no-results" style="display:none;">Немає публікацій за вашим запитом</div>');
            }
            View_Add('</div>');
            View_Add('<div class="profile-view-tab-content" id="profile-tab-saved" style="display: ' . ($activeProfileTab === 'saved' ? 'block' : 'none') . ';">');
            $savedItems = [];
            $savedRes = mysqli_query($dblink, "
                SELECT
                    g.idx,
                    g.fname,
                    g.lname,
                    g.mname,
                    g.dt1,
                    g.dt2,
                    g.photo1,
                    d.title AS district_title,
                    r.title AS region_title
                FROM saved_grave sg
                INNER JOIN grave g ON sg.grave_id = g.idx
                LEFT JOIN cemetery c ON g.idxkladb = c.idx
                LEFT JOIN district d ON c.district = d.idx
                LEFT JOIN region r ON d.region = r.idx
                WHERE sg.user_id = $userId
                ORDER BY sg.grave_id DESC
            ");
            if ($savedRes) {
                while ($savedRow = mysqli_fetch_assoc($savedRes)) {
                    $savedItems[] = $savedRow;
                }
            }
            $savedCount = count($savedItems);

            View_Add('<div class="profile-pubs-toolbar">');
            View_Add('<div class="profile-pubs-total">Збережено публікацій: <strong id="profile-saved-count">' . $savedCount . '</strong></div>');
            View_Add('<div class="profile-pubs-search"><input type="text" class="profile-saved-search-input profile-pubs-search-input" placeholder="Пошук за прізвищем / ім`ям / по батькові" aria-label="Пошук збережених публікацій"></div>');
            View_Add('</div>');

            if ($savedCount === 0) {
                View_Add('<div class="profile-pubs-empty">Немає збережених публікацій</div>');
            } else {
                View_Add('<div class="profile-pubs-list" id="profile-saved-list">');
                foreach ($savedItems as $savedItem) {
                    $graveId = (int)$savedItem['idx'];
                    $photoPath = '/graves/noimage.jpg';
                    if (!empty($savedItem['photo1']) && is_file($_SERVER['DOCUMENT_ROOT'] . $savedItem['photo1'])) {
                        $photoPath = $savedItem['photo1'];
                    }

                    $fio = trim(($savedItem['lname'] ?? '') . ' ' . ($savedItem['fname'] ?? '') . ' ' . ($savedItem['mname'] ?? ''));
                    if ($fio === '') {
                        $fio = 'Без імені';
                    }
                    $searchIndex = function_exists('mb_strtolower')
                        ? mb_strtolower($fio, 'UTF-8')
                        : strtolower($fio);

                    $birthDate = $formatPubDate($savedItem['dt1'] ?? null);
                    $deathDate = $formatPubDate($savedItem['dt2'] ?? null);

                    $locationParts = [];
                    if (!empty($savedItem['region_title'])) {
                        $locationParts[] = $savedItem['region_title'] . ' область';
                    }
                    if (!empty($savedItem['district_title'])) {
                        $locationParts[] = $savedItem['district_title'] . ' район';
                    }
                    $locationLabel = !empty($locationParts) ? implode(', ', $locationParts) : 'Місце не вказано';

                    View_Add('
                    <div class="profile-pub-card profile-saved-card" data-search="' . htmlspecialchars($searchIndex, ENT_QUOTES, 'UTF-8') . '" data-grave-id="' . $graveId . '">
                        <img class="profile-pub-card-photo profile-saved-content" src="' . htmlspecialchars($photoPath, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($fio, ENT_QUOTES, 'UTF-8') . '">
                        <div class="profile-pub-card-body profile-saved-content">
                            <div class="profile-pub-card-name">' . htmlspecialchars($fio, ENT_QUOTES, 'UTF-8') . '</div>
                            <div class="profile-pub-card-meta">' . htmlspecialchars($birthDate . ' - ' . $deathDate, ENT_QUOTES, 'UTF-8') . '</div>
                            <div class="profile-pub-card-meta">' . htmlspecialchars($locationLabel, ENT_QUOTES, 'UTF-8') . '</div>
                        </div>
                        <div class="profile-pub-card-actions profile-saved-content">
                            <a class="profile-pub-card-link" href="/cardout.php?idx=' . $graveId . '">Переглянути</a>
                            <button type="button" class="profile-pub-card-link profile-pub-card-link--danger profile-saved-remove-btn" data-fio="' . htmlspecialchars($fio, ENT_QUOTES, 'UTF-8') . '">Видалити</button>
                        </div>
                        <div class="profile-saved-confirm">
                            <div class="profile-saved-confirm-text"></div>
                            <div class="profile-saved-confirm-actions">
                                <button type="button" class="profile-saved-confirm-yes">Підтвердити</button>
                                <button type="button" class="profile-saved-confirm-cancel">Скасувати</button>
                            </div>
                        </div>
                    </div>');
                }
                View_Add('</div>');
                View_Add('<div class="profile-pubs-empty" id="profile-saved-no-results" style="display:none;">Немає збережених публікацій за вашим запитом</div>');
            }
            View_Add('</div>');
            View_Add('<script>
document.addEventListener("DOMContentLoaded", function() {
    const tabButtons = document.querySelectorAll(".profile-view-tab-btn");
    const tabContents = document.querySelectorAll(".profile-view-tab-content");
    const pubsSearchInput = document.querySelector(".profile-pubs-search-input");
    const pubsCards = document.querySelectorAll(".profile-publication-card");
    const pubsNoResults = document.getElementById("profile-pubs-no-results");
    const savedSearchInput = document.querySelector(".profile-saved-search-input");
    const savedCards = document.querySelectorAll(".profile-saved-card");
    const savedNoResults = document.getElementById("profile-saved-no-results");
    const savedCountNode = document.getElementById("profile-saved-count");

    function applyPublicationsSearch() {
        if (!pubsSearchInput || !pubsCards.length) return;
        const query = pubsSearchInput.value.trim().toLocaleLowerCase();
        let visibleCount = 0;

        pubsCards.forEach(function(card) {
            const haystack = (card.dataset.search || "").toLocaleLowerCase();
            const isVisible = query === "" || haystack.indexOf(query) !== -1;
            card.style.display = isVisible ? "" : "none";
            if (isVisible) visibleCount++;
        });

        if (pubsNoResults) {
            pubsNoResults.style.display = visibleCount === 0 ? "block" : "none";
        }
    }

    function applySavedSearch() {
        if (!savedSearchInput || !savedCards.length) return;
        const query = savedSearchInput.value.trim().toLocaleLowerCase();
        let visibleCount = 0;

        savedCards.forEach(function(card) {
            if (card.hasAttribute("data-removed")) return;
            const haystack = (card.dataset.search || "").toLocaleLowerCase();
            const isVisible = query === "" || haystack.indexOf(query) !== -1;
            card.style.display = isVisible ? "" : "none";
            if (isVisible) visibleCount++;
        });

        if (savedNoResults) {
            savedNoResults.style.display = visibleCount === 0 ? "block" : "none";
        }
    }

    tabButtons.forEach(function(btn) {
        btn.addEventListener("click", function() {
            const tabName = this.dataset.tab;

            tabButtons.forEach(function(b) { b.classList.remove("active"); });
            this.classList.add("active");

            tabContents.forEach(function(content) {
                content.style.display = "none";
            });

            const targetTab = document.getElementById("profile-tab-" + tabName);
            if (targetTab) {
                targetTab.style.display = "block";
            }

            const url = new URL(window.location.href);
            if (tabName === "general") {
                url.searchParams.delete("tab");
            } else {
                url.searchParams.set("tab", tabName);
            }
            window.history.replaceState(null, "", url.toString());
        });
    });

    if (pubsSearchInput) {
        pubsSearchInput.addEventListener("input", applyPublicationsSearch);
        applyPublicationsSearch();
    }

    if (savedSearchInput) {
        savedSearchInput.addEventListener("input", applySavedSearch);
        applySavedSearch();
    }

    savedCards.forEach(function(card) {
        const removeBtn = card.querySelector(".profile-saved-remove-btn");
        const confirmWrap = card.querySelector(".profile-saved-confirm");
        const confirmText = card.querySelector(".profile-saved-confirm-text");
        const confirmYes = card.querySelector(".profile-saved-confirm-yes");
        const confirmCancel = card.querySelector(".profile-saved-confirm-cancel");

        if (!removeBtn || !confirmWrap || !confirmText || !confirmYes || !confirmCancel) return;

        removeBtn.addEventListener("click", function() {
            savedCards.forEach(function(otherCard) {
                if (otherCard !== card) {
                    otherCard.classList.remove("is-confirming");
                }
            });
            const fio = removeBtn.dataset.fio || "цей запис";
            confirmText.textContent = "Видалити запис \\"" + fio + "\\" із збережених?";
            card.classList.add("is-confirming");
        });

        confirmCancel.addEventListener("click", function() {
            card.classList.remove("is-confirming");
        });

        confirmYes.addEventListener("click", function() {
            const graveId = card.dataset.graveId || "";
            if (!graveId) return;

            const body = new URLSearchParams();
            body.set("action", "remove_saved_grave");
            body.set("grave_id", graveId);

            fetch(window.location.href, {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
                },
                body: body.toString()
            })
                .then(function(resp) { return resp.json(); })
                .then(function(data) {
                    if (!data || data.status !== "ok") {
                        alert((data && data.msg) ? data.msg : "Помилка видалення");
                        return;
                    }

                    card.setAttribute("data-removed", "1");
                    card.remove();

                    if (savedCountNode) {
                        const current = parseInt(savedCountNode.textContent || "0", 10) || 0;
                        savedCountNode.textContent = String(Math.max(0, current - 1));
                    }

                    const hasCardsLeft = Array.from(document.querySelectorAll(".profile-saved-card")).some(function(c) {
                        return !c.hasAttribute("data-removed");
                    });

                    if (!hasCardsLeft) {
                        const list = document.getElementById("profile-saved-list");
                        if (list) list.style.display = "none";
                    }

                    applySavedSearch();
                })
                .catch(function() {
                    alert("Помилка мережі");
                });
        });
    });
});
</script>');
            View_Add('</div>');
        }
    }
}


// Кабінет прибиральника
if ($md === '10' && $_SESSION['logged'] == 1) {
    $status = $_SESSION['status'] ?? 0;
    
    // Проверка роли
    if (!hasRole($status, ROLE_CLEANER)) {
        header('Location: /profile.php');
        exit;
    }
    
    $userId = (int)$_SESSION['uzver'];
    
    // AJAX загрузка кладбищ для кабинета уборщика (JSON)
    if (isset($_GET['ajax_cemeteries']) && isset($_GET['district'])) {
        $district_id = intval($_GET['district']);
        $out = [];
        $res = mysqli_query($dblink, "SELECT idx, title FROM cemetery WHERE district = $district_id ORDER BY title ASC");
        while ($row = mysqli_fetch_assoc($res)) {
            $out[] = [
                'idx' => (int)$row['idx'],
                'title' => $row['title'],
            ];
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($out);
        exit;
    }

    // AJAX дії прибиральника по замовленню
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_order_action'])) {
        header('Content-Type: application/json; charset=utf-8');

        $sendOrderActionError = static function (string $msg): void {
            echo json_encode(['status' => 'error', 'msg' => $msg], JSON_UNESCAPED_UNICODE);
            exit;
        };

        $orderId = (int)($_POST['order_id'] ?? 0);
        $action = trim((string)($_POST['action'] ?? ''));
        if ($orderId <= 0) {
            $sendOrderActionError('Некоректний ID замовлення');
        }
        if (!in_array($action, ['accept', 'reject', 'complete'], true)) {
            $sendOrderActionError('Некоректна дія');
        }

        $stmtOrder = mysqli_prepare($dblink, "
            SELECT idx, status, chat_idx
            FROM cleaner_orders
            WHERE idx = ? AND cleaner_id = ?
            LIMIT 1
        ");
        mysqli_stmt_bind_param($stmtOrder, 'ii', $orderId, $userId);
        mysqli_stmt_execute($stmtOrder);
        mysqli_stmt_bind_result($stmtOrder, $dbOrderId, $dbStatus, $dbChatIdx);
        $orderFound = mysqli_stmt_fetch($stmtOrder);
        mysqli_stmt_close($stmtOrder);

        if (!$orderFound) {
            $sendOrderActionError('Замовлення не знайдено або недоступне');
        }

        if ($action === 'accept') {
            $stmtUpdate = mysqli_prepare($dblink, "
                UPDATE cleaner_orders
                SET status = 'accepted',
                    rejection_reason = NULL,
                    completed_at = NULL,
                    completion_comment = NULL
                WHERE idx = ? AND cleaner_id = ? AND status = 'pending'
            ");
            mysqli_stmt_bind_param($stmtUpdate, 'ii', $orderId, $userId);
            mysqli_stmt_execute($stmtUpdate);
            $affected = mysqli_stmt_affected_rows($stmtUpdate);
            mysqli_stmt_close($stmtUpdate);

            if ($affected <= 0) {
                $sendOrderActionError('Неможливо прийняти це замовлення');
            }

            echo json_encode(['status' => 'ok'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($action === 'reject') {
            $rejectionReason = trim((string)($_POST['rejection_reason'] ?? ''));
            if ($rejectionReason === '') {
                $sendOrderActionError('Вкажіть причину відмови');
            }

            $stmtUpdate = mysqli_prepare($dblink, "
                UPDATE cleaner_orders
                SET status = 'rejected',
                    rejection_reason = ?,
                    completed_at = NULL,
                    completion_comment = NULL
                WHERE idx = ? AND cleaner_id = ? AND status = 'pending'
            ");
            mysqli_stmt_bind_param($stmtUpdate, 'sii', $rejectionReason, $orderId, $userId);
            mysqli_stmt_execute($stmtUpdate);
            $affected = mysqli_stmt_affected_rows($stmtUpdate);
            mysqli_stmt_close($stmtUpdate);

            if ($affected <= 0) {
                $sendOrderActionError('Неможливо відхилити це замовлення');
            }

            echo json_encode(['status' => 'ok'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($dbStatus !== 'accepted') {
            $sendOrderActionError('Звіт можна надіслати тільки для прийнятого замовлення');
        }
        if ((int)$dbChatIdx <= 0) {
            $sendOrderActionError('Робочий чат для цього замовлення не знайдено');
        }

        $completionComment = trim((string)($_POST['completion_comment'] ?? ''));
        if (function_exists('mb_strlen') && mb_strlen($completionComment, 'UTF-8') > 3000) {
            $sendOrderActionError('Коментар занадто довгий (до 3000 символів)');
        }

        $files = $_FILES['completion_images'] ?? null;
        if (
            !$files
            || !isset($files['name'], $files['tmp_name'], $files['error'], $files['size'])
            || !is_array($files['name'])
        ) {
            $sendOrderActionError('Додайте хоча б одне фото-звіту');
        }

        $allowedMimeToExt = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];
        $maxImageSize = 5 * 1024 * 1024; // 5 MB
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            $sendOrderActionError('Не вдалося перевірити тип файлів');
        }

        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/chat_images/';
        if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0755, true)) {
            finfo_close($finfo);
            $sendOrderActionError('Не вдалося підготувати директорію для фото');
        }

        $preparedFiles = [];
        foreach ($files['name'] as $idx => $originalName) {
            $errorCode = isset($files['error'][$idx]) ? (int)$files['error'][$idx] : UPLOAD_ERR_NO_FILE;
            if ($errorCode === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($errorCode !== UPLOAD_ERR_OK) {
                finfo_close($finfo);
                $sendOrderActionError('Помилка завантаження одного з фото');
            }

            $tmpName = (string)($files['tmp_name'][$idx] ?? '');
            $fileSize = isset($files['size'][$idx]) ? (int)$files['size'][$idx] : 0;
            if ($tmpName === '' || !is_uploaded_file($tmpName)) {
                finfo_close($finfo);
                $sendOrderActionError('Некоректний файл фото-звіту');
            }
            if ($fileSize <= 0 || $fileSize > $maxImageSize) {
                finfo_close($finfo);
                $sendOrderActionError('Кожне фото має бути не більше 5 МБ');
            }

            $mime = finfo_file($finfo, $tmpName);
            if (!isset($allowedMimeToExt[$mime])) {
                finfo_close($finfo);
                $sendOrderActionError('Дозволені формати фото: JPG, PNG, GIF, WebP');
            }

            $ext = $allowedMimeToExt[$mime];
            $fileName = $orderId . '_' . time() . '_' . mt_rand(1000, 9999) . '_' . $idx . '.' . $ext;
            $fullPath = $uploadDir . $fileName;
            $webPath = '/chat_images/' . $fileName;

            $preparedFiles[] = [
                'tmp' => $tmpName,
                'full' => $fullPath,
                'web' => $webPath,
            ];
        }

        finfo_close($finfo);

        if (empty($preparedFiles)) {
            $sendOrderActionError('Додайте хоча б одне фото-звіту');
        }

        $uploadedWebPaths = [];
        foreach ($preparedFiles as $prepared) {
            if (!move_uploaded_file($prepared['tmp'], $prepared['full'])) {
                foreach ($uploadedWebPaths as $uploadedPath) {
                    $toDelete = $_SERVER['DOCUMENT_ROOT'] . $uploadedPath;
                    if (is_file($toDelete)) {
                        @unlink($toDelete);
                    }
                }
                $sendOrderActionError('Не вдалося зберегти одне з фото');
            }
            $uploadedWebPaths[] = $prepared['web'];
        }

        $chatService = new Chats($dblink);
        $reportText = "Прибиральник позначив замовлення як виконане та надіслав фото-звіт.\nБудь ласка, перевірте фото і підтвердьте виконання у цьому чаті.";
        if ($completionComment !== '') {
            $reportText .= "\nКоментар прибиральника: " . $completionComment;
        }

        if (!$chatService->addMessage((int)$dbChatIdx, $userId, $reportText)) {
            $sendOrderActionError('Не вдалося надіслати текст звіту у чат');
        }

        foreach ($uploadedWebPaths as $imgPath) {
            if (!$chatService->addMessage((int)$dbChatIdx, $userId, '', null, $imgPath)) {
                $sendOrderActionError('Не вдалося надіслати одне з фото у чат');
            }
        }

        $stmtUpdate = mysqli_prepare($dblink, "
            UPDATE cleaner_orders
            SET status = 'completion_pending',
                completed_at = NOW(),
                completion_comment = ?,
                rejection_reason = NULL
            WHERE idx = ? AND cleaner_id = ? AND status = 'accepted'
        ");
        mysqli_stmt_bind_param($stmtUpdate, 'sii', $completionComment, $orderId, $userId);
        mysqli_stmt_execute($stmtUpdate);
        $affected = mysqli_stmt_affected_rows($stmtUpdate);
        $updateErrNo = mysqli_stmt_errno($stmtUpdate);
        mysqli_stmt_close($stmtUpdate);

        if ($affected <= 0) {
            // Сумісність зі старим ENUM status без completion_pending:
            // фіксуємо completed_at/comment, а статус в UI виводимо як "очікує підтвердження".
            $stmtCompat = mysqli_prepare($dblink, "
                UPDATE cleaner_orders
                SET completed_at = NOW(),
                    completion_comment = ?,
                    rejection_reason = NULL
                WHERE idx = ? AND cleaner_id = ? AND status = 'accepted'
            ");
            mysqli_stmt_bind_param($stmtCompat, 'sii', $completionComment, $orderId, $userId);
            mysqli_stmt_execute($stmtCompat);
            $affectedCompat = mysqli_stmt_affected_rows($stmtCompat);
            mysqli_stmt_close($stmtCompat);

            if ($affectedCompat <= 0) {
                if ($updateErrNo > 0) {
                    $sendOrderActionError('Фото надіслано, але статус не оновлено. Застосуйте SQL-міграцію для completion_pending');
                }
                $sendOrderActionError('Фото надіслано, але статус замовлення не вдалося оновити');
            }
        }

        echo json_encode(['status' => 'ok'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Обработка сохранения профиля
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_cleaner_profile'])) {
        $description = trim($_POST['description'] ?? '');
        $region_id = !empty($_POST['region_id']) ? (int)$_POST['region_id'] : null;
        $district_id = !empty($_POST['district_id']) ? (int)$_POST['district_id'] : null;
        $all_cemeteries = isset($_POST['all_cemeteries_in_district']) ? 1 : 0;
        $selected_cemeteries = isset($_POST['cemeteries']) && is_array($_POST['cemeteries']) 
            ? array_map('intval', $_POST['cemeteries']) 
            : [];
        
        $names = $_POST['services']['name'] ?? [];
        $amounts = $_POST['services']['price_amount'] ?? [];
        $servicesCount = 0;
        foreach ($names as $idx => $name) {
            $amt = isset($amounts[$idx]) ? preg_replace('/\D/', '', (string)$amounts[$idx]) : '';
            if (trim((string)$name) !== '' && $amt !== '') $servicesCount++;
        }
        
        if (!$region_id || !$district_id) {
            $_SESSION['message'] = 'Вкажіть область та район роботи';
            $_SESSION['messageType'] = 'error';
            header('Location: /profile.php?md=10');
            exit;
        }
        if ($servicesCount < 1) {
            $_SESSION['message'] = 'Додайте щонайменше одну послугу з назвою та ціною';
            $_SESSION['messageType'] = 'error';
            header('Location: /profile.php?md=10');
            exit;
        }
        
        // Сохранение профиля
        $stmt = mysqli_prepare($dblink, "
            INSERT INTO cleaner_profiles (user_id, description, region_id, district_id, all_cemeteries_in_district, is_visible)
            VALUES (?, ?, ?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE
                description = VALUES(description),
                region_id = VALUES(region_id),
                district_id = VALUES(district_id),
                all_cemeteries_in_district = VALUES(all_cemeteries_in_district),
                is_visible = 1,
                updated_at = CURRENT_TIMESTAMP
        ");
        mysqli_stmt_bind_param($stmt, 'isiii', $userId, $description, $region_id, $district_id, $all_cemeteries);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        // Удаление старых привязок к кладбищам
        mysqli_query($dblink, "DELETE FROM cleaner_cemeteries WHERE user_id = $userId");
        
        // Добавление новых привязок (если не выбрано "все кладбища")
        if (!$all_cemeteries && !empty($selected_cemeteries)) {
            $stmt = mysqli_prepare($dblink, "INSERT INTO cleaner_cemeteries (user_id, cemetery_id) VALUES (?, ?)");
            foreach ($selected_cemeteries as $cem_id) {
                mysqli_stmt_bind_param($stmt, 'ii', $userId, $cem_id);
                mysqli_stmt_execute($stmt);
            }
            mysqli_stmt_close($stmt);
        }
        
        // Сохранение услуг
        if (isset($_POST['services']) && is_array($_POST['services'])) {
            $names   = $_POST['services']['name']   ?? [];
            $amounts = $_POST['services']['price_amount'] ?? [];
            $types   = $_POST['services']['price_type']   ?? [];

            // Удаляем старые услуги
            mysqli_query($dblink, "DELETE FROM cleaner_services WHERE user_id = $userId");

            // Добавляем новые (по индексам)
            $stmt = mysqli_prepare($dblink, "INSERT INTO cleaner_services (user_id, service_name, price_text, sort_order) VALUES (?, ?, ?, ?)");
            $sort_order = 0;
            foreach ($names as $idx => $name) {
                $name   = trim((string)$name);
                $amount = isset($amounts[$idx]) ? preg_replace('/\D/', '', (string)$amounts[$idx]) : '';
                $type   = isset($types[$idx]) && $types[$idx] === 'from' ? 'from' : 'exact';
                if ($name === '' || $amount === '') {
                    continue;
                }
                $price_text = ($type === 'from' ? 'від ' : '') . $amount . ' грн';
                $sort_order++;
                mysqli_stmt_bind_param($stmt, 'issi', $userId, $name, $price_text, $sort_order);
                mysqli_stmt_execute($stmt);
            }
            mysqli_stmt_close($stmt);
        }
        
        $_SESSION['message'] = 'Профіль успішно збережено!';
        $_SESSION['messageType'] = 'success';
        header('Location: /profile.php?md=10');
        exit;
    }
    
    // Получение данных пользователя и профиля
    $sql = 'SELECT u.*, cp.*, d.title AS district_title, r.title AS region_title 
        FROM users u
        LEFT JOIN cleaner_profiles cp ON u.idx = cp.user_id
        LEFT JOIN district d ON cp.district_id = d.idx
        LEFT JOIN region r ON cp.region_id = r.idx
        WHERE u.idx=' . $userId;
    
    $res = mysqli_query($dblink, $sql);
    $p = mysqli_fetch_assoc($res);
    
    // Получение услуг
    $services = [];
    $servicesRes = mysqli_query($dblink, "SELECT * FROM cleaner_services WHERE user_id = $userId ORDER BY sort_order ASC");
    while ($s = mysqli_fetch_assoc($servicesRes)) {
        $s['price_amount'] = '';
        $s['price_type'] = 'exact';
        if (!empty($s['price_text'])) {
            if (preg_match('/\d+/', $s['price_text'], $m)) {
                $s['price_amount'] = $m[0];
            }
            if (preg_match('/^\s*від/i', $s['price_text'])) {
                $s['price_type'] = 'from';
            }
        }
        $services[] = $s;
    }
    
    // Получение привязанных кладбищ
    $cemeteries = [];
    $cemRes = mysqli_query($dblink, "SELECT cemetery_id FROM cleaner_cemeteries WHERE user_id = $userId");
    while ($c = mysqli_fetch_assoc($cemRes)) {
        $cemeteries[] = (int)$c['cemetery_id'];
    }
    
    // Получение списка регионов
    $regions = [];
    $regRes = mysqli_query($dblink, "SELECT idx, title FROM region ORDER BY title ASC");
    while ($r = mysqli_fetch_assoc($regRes)) {
        $regions[] = $r;
    }
    
    // Получение заказов
    $orders = [];
    $ordersRes = mysqli_query($dblink, "
        SELECT co.*, u.fname, u.lname, u.tel, u.email
        FROM cleaner_orders co
        LEFT JOIN users u ON co.client_id = u.idx
        WHERE co.cleaner_id = $userId
        ORDER BY co.created_at DESC
    ");
    while ($o = mysqli_fetch_assoc($ordersRes)) {
        $orders[] = $o;
    }
    
    View_Add('<link rel="stylesheet" href="/assets/css/profile.css">');
    View_Add('<link rel="stylesheet" href="/assets/css/cleaners.css">');
    

    $selectedRegion = $p['region_id'] ?? null;
    $selectedDistrict = $p['district_id'] ?? null;
    
    $regionSelectHtml = '<div class="form-group">
<label>Область</label>
<select name="region_id" id="cleaner-region-select" style="display:none;">
<option value="">Виберіть область</option>';
    
    foreach ($regions as $region) {
        $selected = ($selectedRegion == $region['idx']) ? 'selected' : '';
        $regionSelectHtml .= '<option value="'.$region['idx'].'" '.$selected.'>'.htmlspecialchars($region['title']).'</option>';
    }
    
    $regionSelectHtml .= '</select>
<div class="custom-select-wrapper">
    <div class="custom-select-trigger">'.(
        $selectedRegion && isset($regions[array_search($selectedRegion, array_column($regions, 'idx'))])
            ? htmlspecialchars($regions[array_search($selectedRegion, array_column($regions, 'idx'))]['title'])
            : 'Виберіть область'
        ).'</div>
    <div class="custom-options">';
    
    foreach ($regions as $region) {
        $regionSelectHtml .= '<span class="custom-option" data-value="'.$region['idx'].'">'.htmlspecialchars($region['title']).'</span>';
    }
    
    $regionSelectHtml .= '</div></div></div>';
    
    // Определяем активную вкладку
    $activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'settings';
    if ($activeTab !== 'settings' && $activeTab !== 'orders') {
        $activeTab = 'settings';
    }
    
    // HTML для кабинета с табами
    View_Add('
<div class="cleaner-cabinet">
    <div class="cleaner-cabinet-header">
        <h1>Кабінет прибиральника</h1>
    </div>
    
    <div class="cleaner-tabs">
        <button type="button" class="cleaner-tab-btn ' . ($activeTab === 'settings' ? 'active' : '') . '" data-tab="settings">Налаштування</button>
        <button type="button" class="cleaner-tab-btn ' . ($activeTab === 'orders' ? 'active' : '') . '" data-tab="orders">Мої замовлення</button>
    </div>
    
    <div class="cleaner-tab-content" id="tab-settings" style="display: ' . ($activeTab === 'settings' ? 'block' : 'none') . ';">
        <form method="post" action="/profile.php?md=10" id="cleaner-profile-form">
            <input type="hidden" name="save_cleaner_profile" value="1">
            
            <div class="cleaner-section">
                <h2>Про себе</h2>
                <div class="form-group">
                    <label>Опис про себе</label>
                    <textarea name="description" rows="4" placeholder="Розкажіть про свій досвід, підхід до роботи..." lang="uk" spellcheck="false">'.htmlspecialchars($p['description'] ?? '').'</textarea>
                </div>
            </div>
            
            <div class="cleaner-section">
                <h2>Місце роботи</h2>
                <div class="cleaner-location-row">
                    ' . $regionSelectHtml . '
                    <div class="form-group">
                        <label>Район</label>
                        <select name="district_id" id="cleaner-district-select" style="display:none;"></select>
                        <div class="custom-select-wrapper" id="cleaner-district-wrapper">
                            <div class="custom-select-trigger">'.($selectedDistrict ? 'Завантаження...' : 'Виберіть спочатку область').'</div>
                            <div class="custom-options"></div>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label cleaner-cemeteries-checkbox">
                        <input type="checkbox" name="all_cemeteries_in_district" value="1" id="all-cemeteries-checkbox" '.($p['all_cemeteries_in_district'] ?? 0 ? 'checked' : '').'>
                        <span class="cleaner-checkbox-box"></span>
                        <span class="cleaner-checkbox-text">Працюю на всіх кладовищах обраного району</span>
                    </label>
                </div>
                
                <div class="form-group" id="cemeteries-select-group" style="'.($p['all_cemeteries_in_district'] ?? 0 ? 'display:none;' : '').'">
                    <label>Кладовища</label>
                    <div class="cemeteries-picker">
                        <select id="cemetery-add-select" style="display:none;">
                            <option value="">Виберіть кладовище</option>
                        </select>
                        <div class="custom-select-wrapper" id="cemetery-add-wrapper">
                            <div class="custom-select-trigger">Виберіть кладовище</div>
                            <div class="custom-options"></div>
                        </div>
                        <button type="button" class="btn-add-cemetery" id="add-cemetery-btn">+ Додати кладовище</button>
                    </div>
                    <div class="cemeteries-chips" id="selected-cemeteries"></div>
                    <div id="cemeteries-hidden-inputs"></div>
                    <div class="cemeteries-hint">Натисніть “Додати”, щоб прикріпити кладовище. Повторно додати те саме — не можна.</div>
                </div>
            </div>
            
            <div class="cleaner-section cleaner-section-services">
                <div class="cleaner-section-services-header">
                    <h2>Мої послуги</h2>
                    <button type="button" id="add-service-btn" class="btn-add-service">+ Додати послугу</button>
                </div>
                <div id="services-list">');
    
    $priceTypeSelectTpl = function($selExact, $selFrom, $triggerText) {
        return '<select name="services[price_type][]" class="service-price-type-select" style="display:none;">
                            <option value="exact"'.$selExact.'>Точна</option>
                            <option value="from"'.$selFrom.'>Від</option>
                        </select>
                        <div class="custom-select-wrapper service-price-type-wrapper">
                            <div class="custom-select-trigger">'.$triggerText.'</div>
                            <div class="custom-options">
                                <span class="custom-option" data-value="exact">Точна</span>
                                <span class="custom-option" data-value="from">Від</span>
                            </div>
                        </div>';
    };
    if (!empty($services)) {
        foreach ($services as $service) {
            $amt = htmlspecialchars($service['price_amount'] ?? '');
            $type = $service['price_type'] ?? 'exact';
            $selFrom = $type === 'from' ? ' selected' : '';
            $selExact = $type === 'exact' ? ' selected' : '';
            $triggerText = $type === 'from' ? 'Від' : 'Точна';
            View_Add('
                    <div class="service-item">
                        <input type="text" name="services[name][]" class="form-group input" placeholder="Назва послуги" value="'.htmlspecialchars($service['service_name']).'" required>
                        '.$priceTypeSelectTpl($selExact, $selFrom, $triggerText).'
                        <div class="service-price-amount-wrap">
                            <input type="number" name="services[price_amount][]" class="form-group input service-price-amount" placeholder="ціна" value="'.$amt.'" min="1" step="1" required>
                            <span class="service-price-grn"'.($amt ? '' : ' style="display:none"').'>грн</span>
                        </div>
                        <button type="button" class="btn-remove-service">Видалити</button>
                    </div>');
        }
    } else {
        View_Add('
                    <div class="service-item">
                        <input type="text" name="services[name][]" class="form-group input" placeholder="Назва послуги" required>
                        '.$priceTypeSelectTpl(' selected', '', 'Точна').'
                        <div class="service-price-amount-wrap">
                            <input type="number" name="services[price_amount][]" class="form-group input service-price-amount" placeholder="ціна" min="1" step="1" required>
                            <span class="service-price-grn" style="display:none">грн</span>
                        </div>
                        <button type="button" class="btn-remove-service">Видалити</button>
                    </div>');
    }
    
    View_Add('
                </div>
            </div>
            
            <div class="cleaner-section">
                <button type="submit" class="btn-save">Зберегти профіль</button>
            </div>
        </form>
    </div>
    
    <div class="cleaner-tab-content" id="tab-orders" style="display: ' . ($activeTab === 'orders' ? 'block' : 'none') . ';">
        <div class="cleaner-orders-toolbar">
            <div class="cleaner-orders-subtabs">
                <button type="button" class="cleaner-orders-subtab-btn active" data-order-status="all">Всі</button>
                <button type="button" class="cleaner-orders-subtab-btn" data-order-status="pending">Очікує прийняття</button>
                <button type="button" class="cleaner-orders-subtab-btn" data-order-status="accepted">Прийняті</button>
                <button type="button" class="cleaner-orders-subtab-btn" data-order-status="completion_pending">Очікує підтвердження</button>
                <button type="button" class="cleaner-orders-subtab-btn" data-order-status="rejected">Відмовлені</button>
                <button type="button" class="cleaner-orders-subtab-btn" data-order-status="completed">Виконані</button>
                <button type="button" class="cleaner-orders-subtab-btn" data-order-status="cancelled">Скасовані</button>
            </div>
            <div class="cleaner-orders-date-filter">
                <label class="cleaner-orders-date-filter-label">Сортування:</label>
                <select id="cleaner-orders-sort" style="display:none;">
                    <option value="desc">Від нових</option>
                    <option value="asc">Від старих</option>
                </select>
                <div class="custom-select-wrapper cleaner-orders-sort-wrapper" id="cleaner-orders-sort-wrapper">
                    <div class="custom-select-trigger">Від нових</div>
                    <div class="custom-options">
                        <span class="custom-option" data-value="desc">Від нових</span>
                        <span class="custom-option" data-value="asc">Від старих</span>
                    </div>
                </div>
            </div>
        </div>
        <div id="orders-list-container">');
    
    if (empty($orders)) {
        View_Add('<div class="cleaner-section cleaner-orders-empty"><p style="text-align: center; padding: 40px; color: #666;">Поки що немає замовлень</p></div>');
    } else {
        View_Add('<div class="cleaner-orders-no-results" id="orders-no-results" style="display:none;">Немає замовлень за обраним фільтром</div><div class="orders-list" id="orders-list">');
        foreach ($orders as $order) {
            $orderStatus = (string)($order['status'] ?? '');
            $hasCompletionReport = !empty($order['completed_at']) && $order['completed_at'] !== '0000-00-00 00:00:00';
            if ($orderStatus === 'accepted' && $hasCompletionReport) {
                $orderStatus = 'completion_pending';
            }
            $statusLabels = [
                'pending' => 'Очікує прийняття',
                'accepted' => 'Прийнято',
                'completion_pending' => 'Очікує підтвердження',
                'rejected' => 'Відхилено',
                'completed' => 'Виконано',
                'cancelled' => 'Скасовано'
            ];
            $statusLabel = $statusLabels[$orderStatus] ?? $orderStatus;
            $chatLink = $order['chat_idx'] 
                ? '<a href="/messenger.php?type=2&chat='.$order['chat_idx'].'" class="btn-chat-link">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="btn-action-icon icon icon-tabler icons-tabler-outline icon-tabler-message-user"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M13 18l-5 3v-3h-2a3 3 0 0 1 -3 -3v-8a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v4.5" /><path d="M17 17a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M22 22a2 2 0 0 0 -2 -2h-2a2 2 0 0 0 -2 2" /></svg>
                    <span class="btn-action-label">Написати в робочий чат</span>
                </a>'
                : '';

            $orderActionsHtml = '';
            if ($orderStatus === 'pending') {
                $orderActionsHtml = '
                <div class="order-actions">
                    <button type="button" class="btn-order-action btn-order-accept">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="btn-action-icon icon icon-tabler icons-tabler-outline icon-tabler-check"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12l5 5l10 -10" /></svg>
                        <span class="btn-action-label">Прийняти</span>
                    </button>
                    <button type="button" class="btn-order-action btn-order-reject">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="btn-action-icon icon icon-tabler icons-tabler-outline icon-tabler-x"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M18 6l-12 12" /><path d="M6 6l12 12" /></svg>
                        <span class="btn-action-label">Відмовитись</span>
                    </button>
                </div>';
            } elseif ($orderStatus === 'accepted') {
                $orderActionsHtml = '
                <div class="order-actions">
                    <button type="button" class="btn-order-action btn-order-complete">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="btn-action-icon icon icon-tabler icons-tabler-outline icon-tabler-circle-check"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 12a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" /><path d="M9 12l2 2l4 -4" /></svg>
                        <span class="btn-action-label">Завершити роботу</span>
                    </button>
                </div>';
            }

            $statusDetails = '';
            if ($orderStatus === 'completion_pending') {
                $statusDetails .= '<p class="order-state-note"><strong>Статус звіту:</strong> Фото надіслані, очікує підтвердження клієнта.</p>';
            }
            if ($orderStatus === 'rejected') {
                $rejectionReason = trim((string)($order['rejection_reason'] ?? ''));
                $statusDetails .= '<p><strong>Причина відмови:</strong> ' . htmlspecialchars($rejectionReason !== '' ? $rejectionReason : 'Не вказано') . '</p>';
            }
            if ($orderStatus === 'cancelled') {
                $cancelReason = trim((string)($order['rejection_reason'] ?? ''));
                if ($cancelReason !== '') {
                    $statusDetails .= '<p><strong>Причина скасування:</strong> ' . htmlspecialchars($cancelReason) . '</p>';
                }
            }
            if ($orderStatus === 'completed' || $orderStatus === 'completion_pending') {
                $completedAtText = '';
                if (!empty($order['completed_at']) && $order['completed_at'] !== '0000-00-00 00:00:00') {
                    $completedAtTs = strtotime($order['completed_at']);
                    if ($completedAtTs !== false) {
                        $completedAtText = date('d.m.Y H:i', $completedAtTs);
                    }
                }
                if ($completedAtText !== '') {
                    $statusDetails .= '<p><strong>Дата звіту:</strong> ' . htmlspecialchars($completedAtText) . '</p>';
                }
                $completionComment = trim((string)($order['completion_comment'] ?? ''));
                if ($completionComment !== '') {
                    $statusDetails .= '<p><strong>Коментар до звіту:</strong> ' . htmlspecialchars($completionComment) . '</p>';
                }
            }

            $statusDetailsBlock = $statusDetails !== '' ? '<div class="order-status-details">' . $statusDetails . '</div>' : '';
            $createdAtTs = strtotime($order['created_at']);
            View_Add('
            <div class="order-item" data-order-id="'.(int)$order['idx'].'" data-status="'.htmlspecialchars($orderStatus).'" data-created-at="'.$createdAtTs.'">
                <div class="order-header">
                    <strong>'.htmlspecialchars($order['client_name']).'</strong>
                    <span class="order-status order-status-'.$orderStatus.'">'.$statusLabel.'</span>
                </div>
                <div class="order-info">
                    <p><strong>Телефон:</strong> '.htmlspecialchars($order['client_phone']).'</p>
                    <p><strong>Кладовище:</strong> '.htmlspecialchars($order['cemetery_place'] ?? 'Не вказано').'</p>
                    <p><strong>Дата:</strong> '.htmlspecialchars($order['preferred_date'] ?? 'Не вказано').'</p>
                    <p><strong>Орієнтовна вартість:</strong> '.htmlspecialchars($order['approximate_price'] ?? 'Не вказано').'</p>
                    '.($order['comment'] ? '<p><strong>Коментар:</strong> '.htmlspecialchars($order['comment']).'</p>' : '').'
                    <p><strong>Дата створення:</strong> '.date('d.m.Y H:i', $createdAtTs).'</p>
                    '.$statusDetailsBlock.'
                </div>
                '.$orderActionsHtml.'
                '.$chatLink.'
            </div>');
        }
        View_Add('</div>');
    }
    
    View_Add('
        </div>
    </div>
</div>

<div class="order-action-modal-overlay" id="order-action-modal" style="display:none;">
    <div class="order-action-modal">
        <div class="order-action-modal-header">
            <h3 id="order-action-modal-title">Дія із замовленням</h3>
            <button type="button" class="order-action-modal-close" id="order-action-modal-close" aria-label="Закрити">&times;</button>
        </div>
        <form id="order-action-form" class="order-action-modal-body" enctype="multipart/form-data">
            <input type="hidden" id="order-action-order-id" value="">
            <input type="hidden" id="order-action-type" value="">

            <div class="order-action-field" id="order-accept-field" style="display:none;">
                <p class="order-action-hint" style="margin:0;">
                    Після підтвердження замовлення перейде у статус "Прийнято".
                </p>
            </div>

            <div class="order-action-field" id="order-reject-field" style="display:none;">
                <label class="order-action-reason-title">Причина відмови</label>
                <div class="quick-reason-grid" id="order-reject-reason-grid">
                    <label class="quick-reason-card">
                        <input type="radio" name="order-reject-reason-choice" value="Не можу виконати у зазначений термін">
                        <span class="quick-reason-card-text">Не можу виконати у зазначений термін</span>
                    </label>
                    <label class="quick-reason-card">
                        <input type="radio" name="order-reject-reason-choice" value="Невірно вказана інформація">
                        <span class="quick-reason-card-text">Невірно вказана інформація</span>
                    </label>
                    <label class="quick-reason-card">
                        <input type="radio" name="order-reject-reason-choice" value="Замовлення поза зоною обслуговування">
                        <span class="quick-reason-card-text">Замовлення поза зоною обслуговування</span>
                    </label>
                    <label class="quick-reason-card">
                        <input type="radio" name="order-reject-reason-choice" value="Форс-мажор">
                        <span class="quick-reason-card-text">Форс-мажор</span>
                    </label>
                    <label class="quick-reason-card">
                        <input type="radio" name="order-reject-reason-choice" value="__other__">
                        <span class="quick-reason-card-text">Інша причина</span>
                    </label>
                </div>
                <div class="order-action-other-reason" id="order-reject-other-field" style="display:none;">
                    <label for="order-reject-other-reason">Вкажіть іншу причину</label>
                    <input type="text" id="order-reject-other-reason" maxlength="255" placeholder="Напишіть причину відмови">
                </div>
            </div>

            <div class="order-action-field" id="order-complete-field" style="display:none;">
                <label for="order-completion-images">Фото-звіт (до 5 МБ кожне)</label>
                <input type="file" id="order-completion-images" accept="image/jpeg,image/png,image/gif,image/webp" multiple>
                <p class="order-action-hint">Фото будуть автоматично надіслані у робочий чат з клієнтом.</p>
                <label for="order-completion-comment">Коментар до звіту (необовʼязково)</label>
                <textarea id="order-completion-comment" rows="3" placeholder="Що саме зроблено"></textarea>
            </div>

            <div class="order-action-modal-footer">
                <button type="button" class="btn-order-modal-cancel" id="order-action-cancel">Скасувати</button>
                <button type="submit" class="btn-order-modal-confirm" id="order-action-confirm">Підтвердити</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Переключение табов
    const tabButtons = document.querySelectorAll(".cleaner-tab-btn");
    const tabContents = document.querySelectorAll(".cleaner-tab-content");
    
    tabButtons.forEach(btn => {
        btn.addEventListener("click", function() {
            const tabName = this.dataset.tab;
            
            // Убираем активный класс со всех кнопок
            tabButtons.forEach(b => b.classList.remove("active"));
            // Добавляем активный класс текущей кнопке
            this.classList.add("active");
            
            // Скрываем все вкладки
            tabContents.forEach(content => {
                content.style.display = "none";
            });
            
            // Показываем нужную вкладку
            const targetTab = document.getElementById("tab-" + tabName);
            if (targetTab) {
                targetTab.style.display = "block";
            }
            
            // Зберігаємо вибрану вкладку в URL для збереження при оновленні сторінки
            const url = new URL(window.location.href);
            if (tabName === "settings") {
                url.searchParams.delete("tab");
            } else {
                url.searchParams.set("tab", tabName);
            }
            window.history.replaceState(null, "", url.toString());
        });
    });
    
    // Під-таби та сортування в "Мої замовлення"
    (function() {
        const ordersList = document.getElementById("orders-list");
        const ordersContainer = document.getElementById("orders-list-container");
        const ordersEmpty = ordersContainer ? ordersContainer.querySelector(".cleaner-orders-empty") : null;
        const subtabBtns = document.querySelectorAll(".cleaner-orders-subtab-btn");
        const sortSelect = document.getElementById("cleaner-orders-sort");
        
        function applyOrdersFilterAndSort() {
            const activeSubtab = document.querySelector(".cleaner-orders-subtab-btn.active");
            const statusFilter = activeSubtab ? activeSubtab.dataset.orderStatus : "all";
            const sortOrder = (sortSelect && sortSelect.value === "asc") ? "asc" : "desc";
            
            if (!ordersList) {
                return;
            }
            
            const items = Array.from(ordersList.querySelectorAll(".order-item"));
            items.forEach(function(el) {
                const status = el.getAttribute("data-status");
                const show = statusFilter === "all" || status === statusFilter;
                el.style.display = show ? "" : "none";
                el.setAttribute("data-visible", show ? "1" : "0");
            });
            
            const visibleItems = items.filter(function(el) { return el.getAttribute("data-visible") === "1"; });
            visibleItems.sort(function(a, b) {
                const tsA = parseInt(a.getAttribute("data-created-at") || "0", 10);
                const tsB = parseInt(b.getAttribute("data-created-at") || "0", 10);
                return sortOrder === "asc" ? tsA - tsB : tsB - tsA;
            });
            
            visibleItems.forEach(function(el) { ordersList.appendChild(el); });
            
            if (ordersEmpty) {
                ordersEmpty.style.display = visibleItems.length === 0 ? "block" : "none";
            }
            const noResults = document.getElementById("orders-no-results");
            if (noResults) {
                noResults.style.display = visibleItems.length === 0 ? "block" : "none";
            }
        }
        
        subtabBtns.forEach(function(btn) {
            btn.addEventListener("click", function() {
                subtabBtns.forEach(function(b) { b.classList.remove("active"); });
                this.classList.add("active");
                applyOrdersFilterAndSort();
            });
        });
        
        if (sortSelect) {
            sortSelect.addEventListener("change", applyOrdersFilterAndSort);
        }
        
        applyOrdersFilterAndSort();
    })();

    const orderActionModal = document.getElementById("order-action-modal");
    const orderActionModalCard = orderActionModal ? orderActionModal.querySelector(".order-action-modal") : null;
    const orderActionForm = document.getElementById("order-action-form");
    const orderActionTitle = document.getElementById("order-action-modal-title");
    const orderActionOrderId = document.getElementById("order-action-order-id");
    const orderActionType = document.getElementById("order-action-type");
    const orderAcceptField = document.getElementById("order-accept-field");
    const orderRejectField = document.getElementById("order-reject-field");
    const orderCompleteField = document.getElementById("order-complete-field");
    const orderRejectReasonOptions = document.querySelectorAll("input[name=\"order-reject-reason-choice\"]");
    const orderRejectOtherField = document.getElementById("order-reject-other-field");
    const orderRejectOtherReason = document.getElementById("order-reject-other-reason");
    const orderCompletionImages = document.getElementById("order-completion-images");
    const orderCompletionComment = document.getElementById("order-completion-comment");
    const orderActionConfirm = document.getElementById("order-action-confirm");
    const orderActionCancel = document.getElementById("order-action-cancel");
    const orderActionClose = document.getElementById("order-action-modal-close");

    function showOrderActionError(msg) {
        alert(msg || "Не вдалося виконати дію");
    }

    function getCheckedReasonValue(reasonInputs) {
        if (!reasonInputs || !reasonInputs.length) return "";
        let selectedValue = "";
        reasonInputs.forEach(function(input) {
            if (input && input.checked) {
                selectedValue = input.value || "";
            }
        });
        return selectedValue;
    }

    function toggleOrderRejectOtherField() {
        const selectedReason = getCheckedReasonValue(orderRejectReasonOptions);
        const isOtherSelected = selectedReason === "__other__";
        if (orderRejectOtherField) {
            orderRejectOtherField.style.display = isOtherSelected ? "block" : "none";
        }
        if (orderRejectOtherReason) {
            orderRejectOtherReason.required = isOtherSelected;
            if (!isOtherSelected) {
                orderRejectOtherReason.value = "";
            }
        }
    }

    function closeOrderActionModal() {
        if (!orderActionModal || !orderActionForm) return;
        orderActionModal.style.display = "none";
        if (orderActionModalCard) {
            orderActionModalCard.classList.remove("order-action-modal-compact");
        }
        orderActionForm.reset();
        toggleOrderRejectOtherField();
        if (orderActionConfirm) {
            orderActionConfirm.disabled = false;
            orderActionConfirm.textContent = "Підтвердити";
        }
    }

    function openOrderActionModal(action, orderId) {
        if (!orderActionModal || !orderActionForm || !orderActionType || !orderActionOrderId) {
            return;
        }

        orderActionType.value = action;
        orderActionOrderId.value = String(orderId || "");
        if (orderAcceptField) orderAcceptField.style.display = "none";
        if (orderRejectField) orderRejectField.style.display = "none";
        if (orderCompleteField) orderCompleteField.style.display = "none";
        if (orderRejectOtherField) orderRejectOtherField.style.display = "none";
        if (orderRejectOtherReason) orderRejectOtherReason.required = false;
        if (orderCompletionImages) orderCompletionImages.required = false;
        if (orderActionModalCard) {
            orderActionModalCard.classList.remove("order-action-modal-compact");
        }

        if (action === "accept") {
            if (orderActionTitle) orderActionTitle.textContent = "Підтвердження прийняття";
            if (orderAcceptField) orderAcceptField.style.display = "block";
            if (orderActionConfirm) orderActionConfirm.textContent = "Підтвердити прийняття";
        } else if (action === "reject") {
            if (orderActionTitle) orderActionTitle.textContent = "Відмова від замовлення";
            if (orderRejectField) orderRejectField.style.display = "block";
            toggleOrderRejectOtherField();
            if (orderActionModalCard) {
                orderActionModalCard.classList.add("order-action-modal-compact");
            }
            if (orderActionConfirm) orderActionConfirm.textContent = "Підтвердити відмову";
        } else if (action === "complete") {
            if (orderActionTitle) orderActionTitle.textContent = "Фото-звіт про виконання";
            if (orderCompleteField) orderCompleteField.style.display = "block";
            if (orderCompletionImages) orderCompletionImages.required = true;
            if (orderActionConfirm) orderActionConfirm.textContent = "Надіслати звіт";
        }

        orderActionModal.style.display = "flex";
    }

    function sendOrderAction(formData) {
        return fetch("/profile.php?md=10", {
            method: "POST",
            body: formData
        })
            .then(function(resp) {
                return resp.json().catch(function() { return null; });
            })
            .then(function(data) {
                if (!data || data.status !== "ok") {
                    throw new Error((data && data.msg) ? data.msg : "Не вдалося виконати дію");
                }
                window.location.href = "/profile.php?md=10&tab=orders";
            });
    }

    document.addEventListener("click", function(e) {
        const acceptBtn = e.target.closest(".btn-order-accept");
        if (acceptBtn) {
            const orderItem = acceptBtn.closest(".order-item");
            const orderId = orderItem ? orderItem.getAttribute("data-order-id") : "";
            if (!orderId) return;
            openOrderActionModal("accept", orderId);
            return;
        }

        const rejectBtn = e.target.closest(".btn-order-reject");
        if (rejectBtn) {
            const orderItem = rejectBtn.closest(".order-item");
            const orderId = orderItem ? orderItem.getAttribute("data-order-id") : "";
            if (!orderId) return;
            openOrderActionModal("reject", orderId);
            return;
        }

        const completeBtn = e.target.closest(".btn-order-complete");
        if (completeBtn) {
            const orderItem = completeBtn.closest(".order-item");
            const orderId = orderItem ? orderItem.getAttribute("data-order-id") : "";
            if (!orderId) return;
            openOrderActionModal("complete", orderId);
        }
    });

    if (orderRejectReasonOptions && orderRejectReasonOptions.length) {
        orderRejectReasonOptions.forEach(function(input) {
            input.addEventListener("change", toggleOrderRejectOtherField);
        });
    }
    toggleOrderRejectOtherField();

    if (orderActionForm) {
        orderActionForm.addEventListener("submit", function(e) {
            e.preventDefault();
            if (!orderActionType || !orderActionOrderId) return;

            const action = orderActionType.value;
            const orderId = orderActionOrderId.value;
            if (!action || !orderId) {
                showOrderActionError("Некоректні дані замовлення");
                return;
            }

            const formData = new FormData();
            formData.append("ajax_order_action", "1");
            formData.append("order_id", orderId);
            formData.append("action", action);

            if (action === "reject") {
                const selectedReason = getCheckedReasonValue(orderRejectReasonOptions);
                if (!selectedReason) {
                    showOrderActionError("Оберіть причину відмови");
                    return;
                }
                let reason = selectedReason;
                if (selectedReason === "__other__") {
                    reason = orderRejectOtherReason ? orderRejectOtherReason.value.trim() : "";
                    if (!reason) {
                        showOrderActionError("Вкажіть іншу причину відмови");
                        return;
                    }
                }
                formData.append("rejection_reason", reason);
            } else if (action === "complete") {
                const files = orderCompletionImages ? orderCompletionImages.files : null;
                if (!files || files.length === 0) {
                    showOrderActionError("Додайте хоча б одне фото-звіту");
                    return;
                }
                for (let i = 0; i < files.length; i++) {
                    formData.append("completion_images[]", files[i]);
                }
                const completionComment = orderCompletionComment ? orderCompletionComment.value.trim() : "";
                formData.append("completion_comment", completionComment);
            }

            if (orderActionConfirm) {
                orderActionConfirm.disabled = true;
                if (action === "complete") {
                    orderActionConfirm.textContent = "Надсилання...";
                } else if (action === "accept") {
                    orderActionConfirm.textContent = "Підтвердження...";
                } else {
                    orderActionConfirm.textContent = "Збереження...";
                }
            }

            sendOrderAction(formData).catch(function(err) {
                if (orderActionConfirm) {
                    orderActionConfirm.disabled = false;
                    if (action === "complete") {
                        orderActionConfirm.textContent = "Надіслати звіт";
                    } else if (action === "accept") {
                        orderActionConfirm.textContent = "Підтвердити прийняття";
                    } else {
                        orderActionConfirm.textContent = "Підтвердити відмову";
                    }
                }
                showOrderActionError(err.message);
            });
        });
    }

    if (orderActionCancel) {
        orderActionCancel.addEventListener("click", closeOrderActionModal);
    }
    if (orderActionClose) {
        orderActionClose.addEventListener("click", closeOrderActionModal);
    }
    if (orderActionModal) {
        orderActionModal.addEventListener("click", function(e) {
            if (e.target === orderActionModal) {
                closeOrderActionModal();
            }
        });
    }
    document.addEventListener("keydown", function(e) {
        if (e.key === "Escape" && orderActionModal && orderActionModal.style.display === "flex") {
            closeOrderActionModal();
        }
    });
    
    const regionSelect = document.getElementById("cleaner-region-select");
    const districtSelect = document.getElementById("cleaner-district-select");
    const districtWrapper = document.getElementById("cleaner-district-wrapper");
    const allCemeteriesCheckbox = document.getElementById("all-cemeteries-checkbox");
    const cemeteriesGroup = document.getElementById("cemeteries-select-group");
    const cemeteriesList = document.getElementById("cemeteries-list"); // legacy (не використовується)
    const cemeteryAddSelect = document.getElementById("cemetery-add-select");
    const cemeteryAddWrapper = document.getElementById("cemetery-add-wrapper");
    const addCemeteryBtn = document.getElementById("add-cemetery-btn");
    const selectedCemeteriesEl = document.getElementById("selected-cemeteries");
    const cemeteryHiddenInputs = document.getElementById("cemeteries-hidden-inputs");
    let availableCemeteries = [];
    // Изначально выбранные кладбища из PHP 
    const initialCemeteryIds = '.json_encode($cemeteries).';
    const selectedCemeteryIds = new Set();
    
    // Инициализация / обновление custom select (кабинет прибиральника)
    function initCustomSelect(wrapper) {
        if (!wrapper) return;
        const trigger = wrapper.querySelector(".custom-select-trigger");
        const options = wrapper.querySelector(".custom-options");
        const select = wrapper.previousElementSibling;
        
        if (!trigger || !options || !select) return;
        
        // Вешаем обработчик на триггер только один раз
        if (!wrapper.dataset.csTriggerInited) {
            trigger.addEventListener("click", function(e) {
                e.stopPropagation();
                document.querySelectorAll(".custom-select-wrapper").forEach(w => {
                    const opts = w.querySelector(".custom-options");
                    if (w !== wrapper) {
                        w.classList.remove("open");
                        if (opts) opts.style.display = "none";
                    }
                });
                const open = wrapper.classList.toggle("open");
                options.style.display = open ? "flex" : "none";
            });
            wrapper.dataset.csTriggerInited = "1";
        }
        
        // Переинициализируем клики по опциям, чтобы новые элементы тоже работали
        options.querySelectorAll(".custom-option").forEach(opt => {
            opt.onclick = function() {
                trigger.textContent = opt.textContent;
                select.value = opt.dataset.value;
                select.dispatchEvent(new Event("change"));
                wrapper.classList.remove("open");
                options.style.display = "none";
            };
        });
    }
    
    const regionWrapper = regionSelect ? regionSelect.nextElementSibling : null;
    if (regionWrapper && regionWrapper.classList.contains("custom-select-wrapper")) {
        initCustomSelect(regionWrapper);
    }
    
    // Инициализируем custom select для price_type в послугах
    document.querySelectorAll(".service-price-type-wrapper").forEach(function(w) { initCustomSelect(w); });
    
    // Кастомний селект сортування замовлень (селект — previousElementSibling від wrapper)
    const sortWrapper = document.getElementById("cleaner-orders-sort-wrapper");
    if (sortWrapper) {
        initCustomSelect(sortWrapper);
        const sortTrigger = sortWrapper.querySelector(".custom-select-trigger");
        const sortSelect = document.getElementById("cleaner-orders-sort");
        if (sortSelect && sortTrigger && sortSelect.selectedIndex >= 0) {
            sortTrigger.textContent = sortSelect.options[sortSelect.selectedIndex].text;
        }
    }
    
    // Підказка грн при вводі суми
    document.querySelectorAll(".service-price-amount").forEach(function(inp) {
        inp.addEventListener("input", function() {
            var grn = this.closest(".service-price-amount-wrap");
            if (grn) {
                var span = grn.querySelector(".service-price-grn");
                if (span) span.style.display = this.value ? "inline" : "none";
            }
        });
    });
    
    // Загрузка районов
    function loadDistricts(regionId, selectedDistrict = null) {
        if (!regionId || !districtSelect || !districtWrapper) {
            if (districtWrapper) {
                districtWrapper.querySelector(".custom-select-trigger").textContent = "Виберіть спочатку область";
                districtWrapper.classList.add("disabled");
            }
            if (cemeteriesList) cemeteriesList.innerHTML = "";
            return;
        }
        
        districtWrapper.querySelector(".custom-select-trigger").textContent = "Завантаження...";
        districtWrapper.classList.add("disabled");
        
        fetch("/profile.php?md=2&ajax_districts=1&region=" + regionId)
            .then(r => r.json())
            .then(data => {
                if (!districtSelect || !districtWrapper) return;
                
                districtSelect.innerHTML = "";
                const districtOptions = districtWrapper.querySelector(".custom-options");
                if (districtOptions) districtOptions.innerHTML = "";
                
                if (!data || !data.length) {
                    districtWrapper.querySelector(".custom-select-trigger").textContent = "Райони не знайдені";
                    districtWrapper.classList.add("disabled");
                    return;
                }
                
                districtWrapper.classList.remove("disabled");
                districtSelect.innerHTML = "<option value=\'\'>Виберіть район</option>";
                
                data.forEach(d => {
                    const opt = document.createElement("option");
                    opt.value = d.idx;
                    opt.textContent = d.title;
                    if (selectedDistrict && String(d.idx) === String(selectedDistrict)) {
                        opt.selected = true;
                        districtWrapper.querySelector(".custom-select-trigger").textContent = d.title;
                    }
                    districtSelect.appendChild(opt);
                    
                    const span = document.createElement("span");
                    span.className = "custom-option";
                    span.dataset.value = d.idx;
                    span.textContent = d.title;
                    if (districtOptions) districtOptions.appendChild(span);
                });
                
                // Инициализируем custom select для района
                initCustomSelect(districtWrapper);
                
                if (selectedDistrict) {
                    loadCemeteries(selectedDistrict);
                }
            })
            .catch(() => {
                if (districtWrapper) {
                    districtWrapper.querySelector(".custom-select-trigger").textContent = "Помилка завантаження";
                }
            });
    }
    
    // Загрузка кладбищ
    function renderSelectedCemeteries() {
        if (!selectedCemeteriesEl || !cemeteryHiddenInputs) return;
        selectedCemeteriesEl.innerHTML = "";
        cemeteryHiddenInputs.innerHTML = "";

        const ids = Array.from(selectedCemeteryIds);
        if (ids.length === 0) {
            selectedCemeteriesEl.innerHTML = \'<div class="cemeteries-empty">Кладовища ще не додані</div>\';
            return;
        }

        ids.forEach(function(id) {
            const found = availableCemeteries.find(function(c) { return String(c.idx) === String(id); });
            const title = found ? found.title : ("#" + id);

            const chip = document.createElement("div");
            chip.className = "cemetery-chip";
            chip.innerHTML = `
                <span class="cemetery-chip-title"></span>
                <button type="button" class="cemetery-chip-remove" aria-label="Видалити">×</button>
            `;
            chip.querySelector(".cemetery-chip-title").textContent = title;
            chip.querySelector(".cemetery-chip-remove").addEventListener("click", function() {
                selectedCemeteryIds.delete(id);
                renderSelectedCemeteries();
            });
            selectedCemeteriesEl.appendChild(chip);

            const input = document.createElement("input");
            input.type = "hidden";
            input.name = "cemeteries[]";
            input.value = id;
            cemeteryHiddenInputs.appendChild(input);
        });
    }

    function rebuildCemeteryAddSelect() {
        if (!cemeteryAddSelect || !cemeteryAddWrapper) return;
        cemeteryAddSelect.innerHTML = "<option value=\'\'>Виберіть кладовище</option>";
        const optionsBox = cemeteryAddWrapper.querySelector(".custom-options");
        if (optionsBox) optionsBox.innerHTML = "";

        availableCemeteries.forEach(function(c) {
            const opt = document.createElement("option");
            opt.value = c.idx;
            opt.textContent = c.title;
            cemeteryAddSelect.appendChild(opt);

            const span = document.createElement("span");
            span.className = "custom-option";
            span.dataset.value = c.idx;
            span.textContent = c.title;
            if (optionsBox) optionsBox.appendChild(span);
        });

        // reset trigger text
        const trigger = cemeteryAddWrapper.querySelector(".custom-select-trigger");
        if (trigger) trigger.textContent = "Виберіть кладовище";
        initCustomSelect(cemeteryAddWrapper);
    }

    // Загрузка кладбищ (JSON) + подготовка picker
    function loadCemeteries(districtId) {
        if (!districtId) return;
        if (allCemeteriesCheckbox && allCemeteriesCheckbox.checked) return;

        fetch("/profile.php?md=10&ajax_cemeteries=1&district=" + districtId)
            .then(r => r.json())
            .then(data => {
                availableCemeteries = Array.isArray(data) ? data : [];
                
                // При первичной загрузке восстанавливаем ранее сохранённые кладбища
                if (selectedCemeteryIds.size === 0 && Array.isArray(initialCemeteryIds) && initialCemeteryIds.length) {
                    initialCemeteryIds.forEach(function(id) {
                        selectedCemeteryIds.add(String(id));
                    });
                }
                
                rebuildCemeteryAddSelect();
                renderSelectedCemeteries();
            })
            .catch(() => {
                availableCemeteries = [];
                rebuildCemeteryAddSelect();
                if (selectedCemeteriesEl) {
                    selectedCemeteriesEl.innerHTML = \'<div class="cemeteries-empty" style="color:#dc3545;">Помилка завантаження кладовищ</div>\';
                }
            });
    }
    
    // Обработка изменения региона
    if (regionSelect) {
        const selectedDistrict = '.json_encode($selectedDistrict).';
        if (regionSelect.value) {
            loadDistricts(regionSelect.value, selectedDistrict);
        }
        
        regionSelect.addEventListener("change", function() {
            loadDistricts(this.value);
            if (districtSelect) districtSelect.value = "";
            selectedCemeteryIds.clear();
            availableCemeteries = [];
            rebuildCemeteryAddSelect();
            renderSelectedCemeteries();
        });
    }
    
    // Обработка изменения района
    if (districtSelect) {
        districtSelect.addEventListener("change", function() {
            selectedCemeteryIds.clear();
            renderSelectedCemeteries();
            loadCemeteries(this.value);
        });
    }
    
    // Обработка чекбокса "все кладбища"
    if (allCemeteriesCheckbox) {
        allCemeteriesCheckbox.addEventListener("change", function() {
            if (cemeteriesGroup) {
                cemeteriesGroup.style.display = this.checked ? "none" : "block";
            }
            if (this.checked) {
                if (cemeteryHiddenInputs) cemeteryHiddenInputs.innerHTML = "";
            } else if (districtSelect && districtSelect.value) {
                loadCemeteries(districtSelect.value);
                renderSelectedCemeteries();
            }
        });
    }

    // Добавить кладбище 
    if (addCemeteryBtn && cemeteryAddSelect) {
        addCemeteryBtn.addEventListener("click", function() {
            const id = cemeteryAddSelect.value;
            if (!id) return;
            if (selectedCemeteryIds.has(id)) return;
            selectedCemeteryIds.add(id);
            renderSelectedCemeteries();
            cemeteryAddSelect.value = "";
            if (cemeteryAddWrapper) {
                const trigger = cemeteryAddWrapper.querySelector(".custom-select-trigger");
                if (trigger) trigger.textContent = "Виберіть кладовище";
            }
        });
    }
    
    // Добавление услуги
    const addServiceBtn = document.getElementById("add-service-btn");
    if (addServiceBtn) {
        addServiceBtn.addEventListener("click", function() {
            const servicesList = document.getElementById("services-list");
            if (!servicesList) return;
            const serviceItem = document.createElement("div");
            serviceItem.className = "service-item";
            serviceItem.innerHTML = `
                <input type="text" name="services[name][]" class="form-group input" placeholder="Назва послуги" required>
                <select name="services[price_type][]" class="service-price-type-select" style="display:none;">
                    <option value="exact" selected>Точна</option>
                    <option value="from">Від</option>
                </select>
                <div class="custom-select-wrapper service-price-type-wrapper">
                    <div class="custom-select-trigger">Точна</div>
                    <div class="custom-options">
                        <span class="custom-option" data-value="exact">Точна</span>
                        <span class="custom-option" data-value="from">Від</span>
                    </div>
                </div>
                <div class="service-price-amount-wrap">
                    <input type="number" name="services[price_amount][]" class="form-group input service-price-amount" placeholder="ціна" min="1" step="1" required>
                    <span class="service-price-grn" style="display:none">грн</span>
                </div>
                <button type="button" class="btn-remove-service">Видалити</button>
            `;
            servicesList.appendChild(serviceItem);
            serviceItem.querySelectorAll(".service-price-type-wrapper").forEach(function(w) { initCustomSelect(w); });
            serviceItem.querySelector(".service-price-amount").addEventListener("input", function() {
                var grn = this.closest(".service-price-amount-wrap").querySelector(".service-price-grn");
                if (grn) grn.style.display = this.value ? "inline" : "none";
            });
        });
    }
    
    // Удаление услуги
    document.addEventListener("click", function(e) {
        if (e.target.classList.contains("btn-remove-service")) {
            const serviceItem = e.target.closest(".service-item");
            if (serviceItem) {
                serviceItem.remove();
            }
        }
    });
    
    // Закрытие custom select при клике вне его
    document.addEventListener("click", function(e) {
        if (!e.target.closest(".custom-select-wrapper")) {
            document.querySelectorAll(".custom-select-wrapper").forEach(w => {
                w.classList.remove("open");
                const options = w.querySelector(".custom-options");
                if (options) options.style.display = "none";
            });
        }
    });
    
    // Валідація форми профілю — показуємо notification як у function.php (справа вгорі)
    function showProfileErrorToast(msg) {
        var existing = document.getElementById("client-profile-alert");
        if (existing) existing.remove();
        var errIcon = "<svg width=\"20\" height=\"20\" viewBox=\"0 0 24 24\" fill=\"none\" xmlns=\"http://www.w3.org/2000/svg\"><path d=\"M18 6L6 18M6 6l12 12\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"/></svg>";
        var box = document.createElement("div");
        box.id = "client-profile-alert";
        box.className = "notification notification-error";
        box.innerHTML = "<div class=\"notification-content\"><span class=\"notification-icon\">" + errIcon + "</span><span class=\"notification-message\">" + msg.replace(/</g, "&lt;").replace(/>/g, "&gt;") + "</span></div>";
        document.body.appendChild(box);
        requestAnimationFrame(function() { requestAnimationFrame(function() { box.classList.add("show"); }); });
        setTimeout(function() { box.classList.remove("show"); setTimeout(function() { box.remove(); }, 300); }, 3000);
    }
    const profileForm = document.getElementById("cleaner-profile-form");
    if (profileForm) {
        profileForm.addEventListener("submit", function(e) {
            const regId = regionSelect ? regionSelect.value : "";
            const distId = districtSelect ? districtSelect.value : "";
            const names = profileForm.querySelectorAll("input[name=\"services[name][]\"]");
            const amounts = profileForm.querySelectorAll("input[name=\"services[price_amount][]\"]");
            let validServices = 0;
            names.forEach(function(n, i) {
                const a = amounts[i];
                const nv = (n.value || "").trim();
                const av = a ? (a.value || "").replace(/\D/g, "") : "";
                if (nv !== "" && av !== "") validServices++;
            });
            if (!regId || !distId) {
                e.preventDefault();
                showProfileErrorToast("Вкажіть область та район роботи");
                return;
            }
            if (validServices < 1) {
                e.preventDefault();
                showProfileErrorToast("Додайте щонайменше одну послугу з назвою та ціною");
                return;
            }
        });
    }
});
</script>');
}



if ($md == 73 && isset($_SESSION['logged']) && $_SESSION['logged'] == 1) {
    $dblink = DbConnect();
    $userId = (int)$_SESSION['uzver'];
    $resetToken = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $resetToken !== '') {
        $newPassword = $_POST['new_pass'] ?? '';
        $newPasswordConfirm = $_POST['new_pass_confirm'] ?? '';
        $passwordError = '';
        $ep1 = valide1($newPassword, 'password', $passwordError);

        if ($ep1 === '') {
            $_SESSION['message'] = $passwordError ?: 'Пароль має містити від 8 до 64 символів, латиницю, малу літеру';
            $_SESSION['messageType'] = 'error';
            header('Location: /profile.php?md=73&token=' . urlencode($resetToken));
            exit;
        }
        if ($newPassword !== $newPasswordConfirm) {
            $_SESSION['message'] = 'Паролі не збігаються';
            $_SESSION['messageType'] = 'error';
            header('Location: /profile.php?md=73&token=' . urlencode($resetToken));
            exit;
        }

        $stmt = mysqli_prepare($dblink, "SELECT user_id FROM password_reset_tokens WHERE token=? AND expires_at > NOW() AND user_id=? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'si', $resetToken, $userId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $foundUserId);
        if (!mysqli_stmt_fetch($stmt)) {
            mysqli_stmt_close($stmt);
            $_SESSION['message'] = 'Посилання недійсне або застаріло. Запитуйте скидання пароля ще раз.';
            $_SESSION['messageType'] = 'error';
            header('Location: /profile.php?md=2');
            exit;
        }
        mysqli_stmt_close($stmt);

        $passhash = md5($ep1);
        mysqli_query($dblink, 'UPDATE users SET pasw="' . mysqli_real_escape_string($dblink, $passhash) . '" WHERE idx=' . $userId);
        $stmtDel = mysqli_prepare($dblink, "DELETE FROM password_reset_tokens WHERE token=?");
        mysqli_stmt_bind_param($stmtDel, 's', $resetToken);
        mysqli_stmt_execute($stmtDel);
        mysqli_stmt_close($stmtDel);

        $_SESSION['message'] = 'Пароль успішно змінено.';
        $_SESSION['messageType'] = 'success';
        header('Location: /profile.php?md=2');
        exit;
    }

    if ($resetToken === '') {
        $_SESSION['message'] = 'Недійсне посилання. Запитуйте скидання пароля в налаштуваннях профілю.';
        $_SESSION['messageType'] = 'error';
        header('Location: /profile.php?md=2');
        exit;
    }

    $stmt = mysqli_prepare($dblink, "SELECT user_id FROM password_reset_tokens WHERE token=? AND expires_at > NOW() AND user_id=? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'si', $resetToken, $userId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $foundUserId);
    if (!mysqli_stmt_fetch($stmt)) {
        mysqli_stmt_close($stmt);
        $_SESSION['message'] = 'Посилання недійсне або застаріло. Запитуйте скидання пароля ще раз.';
        $_SESSION['messageType'] = 'error';
        header('Location: /profile.php?md=2');
        exit;
    }
    mysqli_stmt_close($stmt);

    $resetMessage = '';
    if (!empty($_SESSION['message'])) {
        $resetMessage = $_SESSION['message'];
        $resetMessageType = $_SESSION['messageType'] ?? 'error';
        unset($_SESSION['message'], $_SESSION['messageType']);
    }

    View_Add('<link rel="stylesheet" href="/assets/css/profile.css">');
    View_Add('
<div class="settings-profile">
    <div class="user-info-block">
        <div class="stg-title-block">
            <h2 class="stg-title">Скидання пароля</h2>
            <p class="stg-desc">Встановіть новий пароль для вашого облікового запису.</p>
        </div>
        <form action="/profile.php" method="post">
            <input type="hidden" name="md" value="73">
            <input type="hidden" name="token" value="' . htmlspecialchars($resetToken, ENT_QUOTES, 'UTF-8') . '">
            <div class="settings-uinfo">
                ' . (!empty($resetMessage) ? '<div class="' . ($resetMessageType === 'success' ? 'regsuccess1' : 'regerror1') . '">' . htmlspecialchars($resetMessage, ENT_QUOTES, 'UTF-8') . '</div>' : '') . '
                <div class="form-group">
                    <label>Новий пароль</label>
                    <input type="password" name="new_pass" required minlength="8" autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label>Підтвердити пароль</label>
                    <input type="password" name="new_pass_confirm" required minlength="8" autocomplete="new-password">
                </div>
                <div class="stg-buttons-row">
                <button class="btn-save" type="submit">Зберегти новий пароль</button>
                <a href="/profile.php?md=2" class="btn-back">Повернутися до налаштувань</a>
                </div>
              
            </div>
        </form>
    </div>
</div>');
}
// Налаштування профілю
elseif ($md == 2 && $_SESSION['logged'] == 1) {

    if (isset($_GET['region']) && isset($_GET['ajax_districts'])) {
        $region_id = intval($_GET['region']);
        $res = mysqli_query($dblink, "SELECT idx, title FROM district WHERE region = $region_id ORDER BY title ASC");
        $districts = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $districts[] = $row;
        }
        header('Content-Type: application/json');
        echo json_encode($districts);
        exit;
    }
    

    $sql = 'SELECT * FROM users WHERE idx=' . $_SESSION['uzver'];
    $res = mysqli_query($dblink, $sql);
    if (mysqli_num_rows($res) == 1) {
        $p = mysqli_fetch_assoc($res);

        if (!empty($_SESSION['message'])) {
            $messageText = $_SESSION['message'];
            $messageType = $_SESSION['messageType'] ?? 'alert-error';
            unset($_SESSION['message'], $_SESSION['messageType']);
        }

        if (!empty($messageText)) {
            if ($messageType === 'success') {
                $background = '#e6ffed';
                $border = '#28a745';
                $color = '#155724';
            } else {
                $background = '#ffe6e6';
                $border = '#dc3545';
                $color = '#721c24';
            }
        }

        $sql = "SELECT idx, title FROM region ORDER BY title ASC";
        $res_regions = mysqli_query($dblink, $sql);
        $regions = [];
        while ($row = mysqli_fetch_assoc($res_regions)) {
            $regions[] = $row;
        }

        $user_region   = null;
        $user_district = null;

        if (!empty($p['mesto'])) {
            $sql = "
        SELECT d.idx AS district_id, d.region AS region_id
        FROM district d
        WHERE d.idx = " . (int)$p['mesto'] . "
        LIMIT 1
    ";
            $r = mysqli_query($dblink, $sql);
            if ($row = mysqli_fetch_assoc($r)) {
                $user_district = $row['district_id'];
                $user_region   = $row['region_id'];
            }
        }

        $regionSelectHtml = '<div class="form-grid"><div class="form-group">
<label>Область</label>
<select name="location" id="location-select" style="display:none;">
<option value="">Виберіть область</option>';

        foreach ($regions as $region) {
            $selected = ($user_region == $region['idx']) ? 'selected' : '';
            $regionSelectHtml .= '<option value="'.$region['idx'].'" '.$selected.'>'.$region['title'].'</option>';
        }

        $regionSelectHtml .= '</select>
<div class="custom-select-wrapper">
    <div class="custom-select-trigger">'.(
            $user_region
                ? htmlspecialchars($regions[array_search($user_region, array_column($regions, 'idx'))]['title'])
                : 'Виберіть область'
            ).'</div>
    <div class="custom-options">';

        foreach ($regions as $region) {
            $regionSelectHtml .= '<span class="custom-option" data-value="'.$region['idx'].'">'.$region['title'].'</span>';
        }

        $regionSelectHtml .= '</div></div></div>';

        $districtSelectHtml = '<div class="form-group">
<label>Район</label>
<select name="district" id="district-select" style="display:none;"></select>
<div class="custom-select-wrapper disabled"> 
    <div class="custom-select-trigger">Виберіть спочатку область</div>
    <div class="custom-options"></div>
</div></div></div>';

        $avatarSrc = $p['avatar'] !== ''
            ? htmlspecialchars($p['avatar'], ENT_QUOTES, 'UTF-8')
            : '/avatars/ava.png';

        $deleteAvatarHtml = '';
        if ($p['avatar'] !== '') {
            $deleteAvatarHtml = '
        <button 
            type="submit"
            name="delete_avatar"
            formaction="/edit-avatar.php"
            formmethod="post"
            class="del-ava-btn"
            onclick="return confirm(\'Видалити аватар?\')"
        >
            Видалити
        </button>
    ';
        }



        $html = '
<link rel="stylesheet" href="/assets/css/profile.css">

<div class="settings-profile">

    <div class="user-info-block">
        <div class="stg-title-block">
            <h2 class="stg-title">Особиста інформація</h2>
            <p class="stg-desc">Тут ви можете змінити ваше ім’я, прізвище, та інші особисті дані.</p>
        </div>

        <form action="/profile.php/?md=778" method="post" enctype="multipart/form-data">
            <div class="settings-uinfo">

                <!-- Аватар -->
             
<div class="avatar-row">
    <img src="' . $avatarSrc . '" alt="Аватар" class="avatar-preview">

   <div class="avatar-info">
    <div class="avatar-actions">
        <button type="button" class="btn-change-avatar" id="open-avatar-popup">
            Змінити аватар
        </button>

        ' . $deleteAvatarHtml . '
    </div>

    <div class="avatar-note">JPG, JPEG або PNG</div>
</div>

</div>

                <!-- Имя и фамилия -->
                <div class="form-grid">
                    <div class="form-group">
                        <label>Прізвище</label>
                        <input type="text" name="lname" value="' . htmlspecialchars($p['lname'], ENT_QUOTES, 'UTF-8') . '">
                    </div>
                    <div class="form-group">
                        <label>Імʼя</label>
                        <input type="text" name="fname" value="' . htmlspecialchars($p['fname'], ENT_QUOTES, 'UTF-8') . '">
                    </div>
                </div>

                <!-- Email -->
                <div class="form-group">
    <label>Email</label>
    <div class="email-div email-tooltip" data-tooltip="Email змінити тимчасово неможливо">
        ' . htmlspecialchars($p['email'], ENT_QUOTES, 'UTF-8') . '
    </div>
</div>



                <!-- Местоположение -->
                ' . $regionSelectHtml . $districtSelectHtml . '

                <button class="btn-save mrg" type="submit">Зберегти зміни</button>
            </div>
        </form>
    </div>

    <!-- Номер телефону -->
    <div class="user-info-block">
        <div class="stg-title-block">
            <h2 class="stg-title">Номер телефону</h2>
            <p class="stg-desc">Використовується для зв’язку з вами та підтвердження безпеки облікового запису.</p>
        </div>

        <form action="/profile.php/?md=779" method="post">
            <div class="settings-uinfo">
                <div class="form-group">
                    <label>Номер телефону</label>
                    <div class="phone-input-wrapper">
                        <div class="country-select">
                            <span class="country-flag">🇺🇦</span>
                            <span class="country-code">+380</span>
                        </div>
                        <input type="text" id="phone-input" class="phone-input" value="" placeholder="(XX) XXX XX XX">
                        <input type="hidden" name="tel" id="phone-full">
                    </div>
                </div>
                <button class="btn-save mrg" type="submit">Зберегти зміни</button>
            </div>
        </form>
    </div>

    <!-- Смена пароля -->
    <div class="user-info-block">
        <div class="stg-title-block">
            <h2 class="stg-title">Зміна паролю</h2>
            <p class="stg-desc">Використовуйте надійний пароль, щоб захистити свій обліковий запис.</p>
      
        </div>

        <form action="/profile.php/?md=24" method="post">
            <div class="settings-uinfo">
                <div class="form-group">
                    <label>Старий пароль</label>
                    <input type="password" name="old_pass">
                </div>
                <div class="form-group">
                    <label>Новий пароль</label>
                    <input type="password" name="new_pass">
                </div>
                <div class="stg-buttons-row">
                    <button class="btn-save" type="submit" name="change_pass" value="1">Зберегти зміни</button>
                    <button class="btn-reset" type="submit" name="reset_pass" value="1">Скинути пароль</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Вихід з акаунта -->
    <div class="user-info-block settings-logout-block">
        <div class="stg-title-block">
            <h2 class="stg-title">Вихід з акаунта</h2>
            <p class="stg-desc">Завершіть поточну сесію на цьому пристрої.</p>
        </div>
        <div class="settings-uinfo">
            <div class="settings-logout-actions">
                <a href="/profile.php?exit=1" class="btn-logout-account">Вийти з акаунта</a>
            </div>
        </div>
    </div>



</div>';

        View_Add('
    <div id="avatar-popup" class="avatar-popup" aria-hidden="true">
        <div class="avatar-popup-content" role="dialog" aria-modal="true" aria-labelledby="avatar-popup-title">
            <div class="avatar-popup-header">
                <span class="avatar-popup-title" id="avatar-popup-title">Оновити аватар</span>
             <button class="avatar-popup-close" type="button" aria-label="Закрити">
    <svg xmlns="http://www.w3.org/2000/svg"
         width="24"
         height="24"
         viewBox="0 0 24 24"
         fill="none"
         stroke="currentColor"
         stroke-width="1.75"
         stroke-linecap="round"
         stroke-linejoin="round"
         class="icon icon-tabler icon-tabler-x">
        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
        <path d="M18 6l-12 12" />
        <path d="M6 6l12 12" />
    </svg>
</button>


            </div>
            <hr class="avatar-popup-divider">

            <form id="avatar-form" action="/edit-avatar.php" method="post" enctype="multipart/form-data">
                <div class="avatar-popup-body">
                    <div class="ava-upload-card" id="avatar-upload-box">
                        <span class="ava-upload-title">Фото</span>
                        <input id="avatar-input" class="ava-file-input" type="file" name="avatar" accept=".jpg,.jpeg,.png">
                        <label for="avatar-input" id="avatar-dropzone" class="ava-upload-dropzone">
                            <div id="avatar-upload-preview" class="ava-upload-preview">
                                <img id="avatar-upload-preview-img" alt="Попередній перегляд фото">
                                <div class="ava-upload-empty">
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
                        <div class="ava-file-control">
                            <span id="avatar-file-badge" class="ava-upload-badge" hidden>Фото завантажено</span>
                            <button type="button" id="avatar-file-trigger" class="ava-file-btn">Вибрати фото</button>
                            <span id="avatar-file-name" class="ava-file-name">Файл не обрано</span>
                        </div>
                        <small class="ava-upload-note">PNG / JPG</small>
                    </div>
                </div>
                <div id="avatar-preview-popup" class="avatar-preview-popup" style="display:none;">
                    <div class="avatar-cropper-stage">
                        <img id="avatar-img" alt="Превʼю аватара" />
                    </div>
                    <div class="avatar-preview-buttons">
                        <button type="submit" class="avatar-save-btn" id="avatar-save-btn">Зберегти</button>
                        <button type="button" id="avatar-cancel-btn" class="avatar-cancel-btn">Скасувати</button>
                    </div>
                    <div class="avatar-hint">Перевірте фото та натисніть "Зберегти".</div>
                </div>
            </form>
        </div>
    </div>
');


        View_Add('
            <script>
document.addEventListener("DOMContentLoaded", function() {
    const popup = document.getElementById("avatar-popup");
    const closeBtn = popup.querySelector(".avatar-popup-close");
    const input = document.getElementById("avatar-input");
    const previewWrapper = document.getElementById("avatar-preview-popup");
    const previewImg = document.getElementById("avatar-img");
    const uploadBox = document.getElementById("avatar-upload-box");
    const dropzone = document.getElementById("avatar-dropzone");
    const fileName = document.getElementById("avatar-file-name");
    const fileBadge = document.getElementById("avatar-file-badge");
    const fileTrigger = document.getElementById("avatar-file-trigger");
    const uploadPreviewImg = document.getElementById("avatar-upload-preview-img");
    const cancelBtn = document.getElementById("avatar-cancel-btn");

    function openPopup() {
        popup.classList.add("show");
        popup.setAttribute("aria-hidden", "false");
        resetState();
        window.requestAnimationFrame(() => input.focus());
    }

    function closePopup() {
        popup.classList.remove("show");
        popup.setAttribute("aria-hidden", "true");
    }

    function resetState() {
        previewWrapper.style.display = "none";
        uploadBox.style.display = "";
        input.value = "";
        previewImg.removeAttribute("src");
        uploadPreviewImg.removeAttribute("src");
        uploadBox.classList.remove("has-preview");
        fileName.textContent = "Файл не обрано";
        fileBadge.hidden = true;
    }

    document.querySelectorAll(".btn-change-avatar").forEach(btn => {
        btn.addEventListener("click", openPopup);
    });

    closeBtn.addEventListener("click", closePopup);
    popup.addEventListener("click", e => {
        if (e.target === popup) closePopup();
    });
    document.addEventListener("keydown", (e) => {
        if (e.key === "Escape" && popup.classList.contains("show")) closePopup();
    });

    input.addEventListener("change", function() {
        const file = this.files && this.files[0] ? this.files[0] : null;
        if (!file) {
            resetState();
            return;
        }

        const lowerName = (file.name || "").toLowerCase();
        const looksOkByName = lowerName.endsWith(".jpg") || lowerName.endsWith(".jpeg") || lowerName.endsWith(".png");
        const looksOkByType = /^image\\/(png|jpeg)$/.test(file.type || "");
        if (!looksOkByType && !looksOkByName) {
            alert("Будь ласка, оберіть JPG або PNG.");
            resetState();
            return;
        }

        const reader = new FileReader();
        reader.onload = function(ev) {
            const dataUrl = String(ev.target && ev.target.result ? ev.target.result : "");
            if (!dataUrl) {
                resetState();
                return;
            }

            uploadPreviewImg.src = dataUrl;
            previewImg.src = dataUrl;
            previewWrapper.style.display = "flex";
            uploadBox.style.display = "none";
            uploadBox.classList.add("has-preview");
            fileName.textContent = file.name || "Файл обрано";
            fileBadge.hidden = false;
        };
        reader.onerror = function() {
            resetState();
        };
        reader.readAsDataURL(file);
    });

    if (cancelBtn) {
        cancelBtn.addEventListener("click", resetState);
    }

    fileTrigger.addEventListener("click", () => input.click());

    function setDragState(isOver) {
        uploadBox.classList.toggle("dragover", Boolean(isOver));
    }

    ["dragenter", "dragover"].forEach(evt => {
        dropzone.addEventListener(evt, (e) => {
            e.preventDefault();
            e.stopPropagation();
            setDragState(true);
        });
    });
    ["dragleave", "dragend", "drop"].forEach(evt => {
        dropzone.addEventListener(evt, (e) => {
            e.preventDefault();
            e.stopPropagation();
            setDragState(false);
        });
    });
    dropzone.addEventListener("drop", (e) => {
        const files = e.dataTransfer && e.dataTransfer.files ? e.dataTransfer.files : null;
        if (!files || !files.length) return;
        try {
            const dt = new DataTransfer();
            dt.items.add(files[0]);
            input.files = dt.files;
            input.dispatchEvent(new Event("change", { bubbles: true }));
        } catch (err) {
            console.error(err);
        }
    });
});
</script>
        ');

        View_Add($html);

        View_Add('<script>
document.addEventListener("DOMContentLoaded", function () {

    let USER_DISTRICT = '.json_encode($user_district).';

    function initCustomSelect(wrapper) {
        const trigger = wrapper.querySelector(".custom-select-trigger");
        const options = wrapper.querySelector(".custom-options");
        const select  = wrapper.previousElementSibling;

        trigger.addEventListener("click", function (e) {
            if (wrapper.classList.contains("disabled")) return;
            e.stopPropagation();

            document.querySelectorAll(".custom-select-wrapper").forEach(w => {
                if (w !== wrapper) {
                    w.classList.remove("open");
                    w.querySelector(".custom-options").style.display = "none";
                }
            });

            wrapper.classList.toggle("open");
            options.style.display = wrapper.classList.contains("open") ? "flex" : "none";
        });

        options.addEventListener("click", e => {
            if (!e.target.classList.contains("custom-option")) return;
            trigger.textContent = e.target.textContent;
            select.value = e.target.dataset.value;
            select.dispatchEvent(new Event("change", { bubbles:true }));
            wrapper.classList.remove("open");
            options.style.display = "none";
        });
    }

    document.querySelectorAll(".custom-select-wrapper").forEach(initCustomSelect);

    const regionSelect   = document.getElementById("location-select");
    const districtSelect = document.getElementById("district-select");

    const districtWrapper = districtSelect.parentElement.querySelector(".custom-select-wrapper");
    const districtTrigger = districtWrapper.querySelector(".custom-select-trigger");
    const districtOptions = districtWrapper.querySelector(".custom-options");

    function disableDistricts(msg = "Виберіть спочатку область") {
        districtSelect.innerHTML = "";
        districtOptions.innerHTML = "";
        districtTrigger.textContent = msg;
        districtWrapper.classList.add("disabled");
    }

    function enableDistricts() {
        districtWrapper.classList.remove("disabled");
    }

    function loadDistricts(regionId, selectedDistrict = null) {
        districtTrigger.textContent = "Завантаження...";

        fetch("/profile.php?md=2&ajax_districts=1&region=" + regionId)
            .then(r => r.json())
            .then(data => {

                districtSelect.innerHTML = "";
                districtOptions.innerHTML = "";

                if (!data.length) {
                    disableDistricts("Райони не знайдені");
                    return;
                }

                enableDistricts();

                districtSelect.innerHTML = "<option value=\'\'>Виберіть район</option>";
                districtTrigger.textContent = "Виберіть район";

                data.forEach(d => {
                    const opt = document.createElement("option");
                    opt.value = d.idx;
                    opt.textContent = d.title;

                    if (selectedDistrict && String(d.idx) === String(selectedDistrict)) {
                        opt.selected = true;
                        districtTrigger.textContent = d.title;
                    }

                    districtSelect.append(opt);

                    const span = document.createElement("span");
                    span.className = "custom-option";
                    span.dataset.value = d.idx;
                    span.textContent = d.title;
                    districtOptions.append(span);
                });
            })
            .catch(() => disableDistricts("Помилка завантаження"));
    }

    if (regionSelect.value) {
        loadDistricts(regionSelect.value, USER_DISTRICT);
    } else {
        disableDistricts();
    }

    regionSelect.addEventListener("change", () => {
        if (!regionSelect.value) {
            disableDistricts();
            return;
        }
        loadDistricts(regionSelect.value);
    });

    document.addEventListener("click", () => {
        document.querySelectorAll(".custom-select-wrapper").forEach(w => {
            w.classList.remove("open");
            w.querySelector(".custom-options").style.display = "none";
        });
    });

    // Маска для телефона
    const phoneInput = document.getElementById("phone-input");
    const phoneFullInput = document.getElementById("phone-full");
    if (phoneInput && phoneFullInput) {
        let telValue = ' . json_encode($p['tel'] ?? '') . ';
        
        function formatPhone(value) {
        
            let numbers = value.replace(/\D/g, "");
            
            if (numbers.startsWith("380")) {
                numbers = numbers.substring(3);
            }
            
            if (numbers.length > 9) {
                numbers = numbers.substring(0, 9);
            }
            
            let formatted = "";
            if (numbers.length > 0) {
                formatted = "(" + numbers.substring(0, 2);
                if (numbers.length > 2) {
                    formatted += ") " + numbers.substring(2, 5);
                    if (numbers.length > 5) {
                        formatted += " " + numbers.substring(5, 7);
                        if (numbers.length > 7) {
                            formatted += " " + numbers.substring(7, 9);
                        }
                    }
                } else {
                    formatted += ")";
                }
            }
            
            return formatted;
        }
        
        function extractPhoneNumber(value) {
            if (!value) return "";
          
            let numbers = value.replace(/\D/g, "");
           
            if (numbers.startsWith("380")) {
                numbers = numbers.substring(3);
            }
            return numbers;
        }
        
        if (telValue) {
            const phoneDigits = extractPhoneNumber(telValue);
            if (phoneDigits.length > 0) {
                phoneInput.value = formatPhone(phoneDigits);
                phoneFullInput.value = "+380" + phoneDigits;
            } else {
                phoneInput.value = "";
                phoneFullInput.value = "";
            }
        } else {
            phoneInput.value = "";
            phoneFullInput.value = "";
        }
        
        phoneInput.addEventListener("input", function(e) {
            const formatted = formatPhone(e.target.value);
            phoneInput.value = formatted;
            
            const digits = e.target.value.replace(/\D/g, "");
            const phoneDigits = digits.startsWith("380") ? digits.substring(3) : digits;
            
            if (phoneDigits.length > 0) {
                phoneFullInput.value = "+380" + phoneDigits;
            } else {
                phoneFullInput.value = "";
            }
        });
        
        const form = phoneInput.closest("form");
        if (form) {
            form.addEventListener("submit", function(e) {
                const digits = phoneInput.value.replace(/\D/g, "");
                const phoneDigits = digits.startsWith("380") ? digits.substring(3) : digits;
                
                if (phoneDigits.length > 0) {
                    phoneFullInput.value = "+380" + phoneDigits;
                } else {
                    phoneFullInput.value = "";
                }
            });
        }
    }
});
</script>');
    }
}

if ($md == 24 && $_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!empty($_POST['reset_pass'])) {
        $userId = (int)$_SESSION['uzver'];
        $res = mysqli_query($dblink, "SELECT email, fname FROM users WHERE idx=" . $userId . " LIMIT 1");
        $user = mysqli_fetch_assoc($res);
        if ($user && !empty($user['email'])) {
            $email = $user['email'];
            $fname = $user['fname'] ?: 'Користувач';

            mysqli_query($dblink, "DELETE FROM password_reset_tokens WHERE user_id=" . $userId);
            $resetToken = bin2hex(random_bytes(32));
            $stmt = mysqli_prepare($dblink, "INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))");
            mysqli_stmt_bind_param($stmt, 'is', $userId, $resetToken);
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                $resetLink = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
                    . '/auth.php?reset_token=' . urlencode($resetToken);
                sendProfilePasswordResetEmail($userId, $email, $fname, $resetLink);
            }

            $_SESSION['message'] = 'На вашу пошту надіслано посилання для скидання пароля. Перевірте листи (також папку «Спам»).';
            $_SESSION['messageType'] = 'success';
        } else {
            $_SESSION['message'] = 'У облікового запису немає email. Скидання пароля неможливе.';
            $_SESSION['messageType'] = 'error';
        }
        header('Location: /profile.php?md=2');
        exit;
    }

    $oldPassword = $_POST['old_pass'] ?? '';
    $newPassword = $_POST['new_pass'] ?? '';

    if ($oldPassword === '' || $newPassword === '') {
        $_SESSION['message'] = 'Заповніть всі поля';
        $_SESSION['messageType'] = 'error';
        header('Location: /profile.php?md=2');
        exit;
    }

    $sql = 'SELECT pasw FROM users WHERE idx=' . (int)$_SESSION['uzver'];
    $res = mysqli_query($dblink, $sql);
    $user = mysqli_fetch_assoc($res);

    if (!$user) {
        header('Location: /profile.php?md=2');
        $_SESSION['message'] = 'Користувача не знайдено';
        $_SESSION['messageType'] = 'error';

        exit;
    }

    if (md5($oldPassword) !== $user['pasw']) {
        $_SESSION['message'] = 'Старий пароль не співпадає';
        $_SESSION['messageType'] = 'error';
        header('Location: /profile.php?md=2');
        exit;
    }

    mysqli_query(
        $dblink,
        'UPDATE users SET pasw="' . md5($newPassword) . '" WHERE idx=' . (int)$_SESSION['uzver']
    );

    $_SESSION['message'] = 'Пароль успішно змінено';
    $_SESSION['messageType'] = 'success';

    header('Location: /profile.php?md=2');
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && $md == 778 && $_SESSION['logged'] == 1) {
    $fname = trim($_POST['fname'] ?? '');
    $lname = trim($_POST['lname'] ?? '');
    $district = isset($_POST['district']) && $_POST['district'] !== ''
        ? (int)$_POST['district']
        : null;


    if ($fname === '' || $lname === '') {
        $_SESSION['message'] = 'Імʼя і прізвище не можуть бути порожніми.';
        $_SESSION['messageType'] = 'error';
        header('Location: /profile.php/?md=2');
        exit;
    }

    $a = mysqli_prepare($dblink, "UPDATE users SET fname = ?, lname = ?, mesto = ? WHERE idx = ?");
    mysqli_stmt_bind_param(
        $a,
        'ssii',
        $fname,
        $lname,
        $district,
        $_SESSION['uzver']
    );

    mysqli_stmt_execute($a);
    mysqli_stmt_close($a);

    $_SESSION['message'] = "Дані успішно оновлено!";
    $_SESSION['messageType'] = "success";
    header("Location: /profile.php/?md=2");
    exit;
}

// Обновление телефона в настройках профиля
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $md == 779 && $_SESSION['logged'] == 1) {
    $tel = trim($_POST['tel'] ?? '');
    
    // Если поле пустое, устанавливаем NULL или пустую строку
    if ($tel === '' || $tel === '+380') {
        $tel = '';
    }
    
    $a = mysqli_prepare($dblink, "UPDATE users SET tel = ? WHERE idx = ?");
    mysqli_stmt_bind_param($a, 'si', $tel, $_SESSION['uzver']);
    
    mysqli_stmt_execute($a);
    mysqli_stmt_close($a);
    
    $_SESSION['message'] = "Номер телефону успішно оновлено!";
    $_SESSION['messageType'] = "success";
    header("Location: /profile.php/?md=2");
    exit;
}

/*Для того чтобы не дергало страницу при обновлении*/
View_Add('<script>
document.addEventListener("DOMContentLoaded", function () {

    const pos = sessionStorage.getItem("scrollY");
    if (pos !== null) {
        window.scrollTo(0, parseInt(pos));
        sessionStorage.removeItem("scrollY");
    }

    document.querySelectorAll("form").forEach(form => {
        form.addEventListener("submit", () => {
            sessionStorage.setItem("scrollY", window.scrollY);
        });
    });

});
</script>
');

// Фінансова інформація (md=4)
if ($md == 4 && $_SESSION['logged'] == 1) {
    View_Add('<link rel="stylesheet" href="/assets/css/profile.css">');

    $userId = (int)$_SESSION['uzver'];
    $paymentsAvailable = false;
    $walletInternalBalance = 0.0;
    $walletMonthIncome = 0.0;
    $walletMonthExpense = 0.0;
    $walletTransactions = [];
    $walletId = 0;
    $walletsTableExists = false;
    $walletTxTableExists = false;

    if ($dblink) {
        $checkRes = mysqli_query(
            $dblink,
            "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('wallets', 'wallet_transactions')"
        );
        if ($checkRes) {
            while ($row = mysqli_fetch_assoc($checkRes)) {
                if ($row['TABLE_NAME'] === 'wallets') {
                    $walletsTableExists = true;
                }
                if ($row['TABLE_NAME'] === 'wallet_transactions') {
                    $walletTxTableExists = true;
                }
            }
        }
    }

    if ($walletsTableExists) {
        $walletRes = mysqli_query(
            $dblink,
            "SELECT id, internal_balance FROM wallets WHERE user_id = " . $userId . " LIMIT 1"
        );
        if ($walletRes && ($walletRow = mysqli_fetch_assoc($walletRes))) {
            $walletId = (int)$walletRow['id'];
            $walletInternalBalance = (float)$walletRow['internal_balance'];
        }
    }

    if ($walletId && $walletTxTableExists) {
        $monthStart = date('Y-m-01 00:00:00');
        $sumRes = mysqli_query(
            $dblink,
            "SELECT
                COALESCE(SUM(CASE WHEN direction = 'in' THEN amount ELSE 0 END), 0) AS income,
                COALESCE(SUM(CASE WHEN direction = 'out' THEN amount ELSE 0 END), 0) AS expense
            FROM wallet_transactions
            WHERE wallet_id = " . $walletId . " AND created_at >= '" . $monthStart . "' AND currency = 'INTERNAL'"
        );
        if ($sumRes && ($sumRow = mysqli_fetch_assoc($sumRes))) {
            $walletMonthIncome = (float)$sumRow['income'];
            $walletMonthExpense = (float)$sumRow['expense'];
        }

        $txRes = mysqli_query(
            $dblink,
            "SELECT direction, amount, currency, title, meta, created_at
            FROM wallet_transactions
            WHERE wallet_id = " . $walletId . " AND currency = 'INTERNAL'
            ORDER BY created_at DESC
            LIMIT 5"
        );
        if ($txRes) {
            while ($txRow = mysqli_fetch_assoc($txRes)) {
                $direction = $txRow['direction'] === 'out' ? 'expense' : 'income';
                $title = trim((string)($txRow['title'] ?? ''));
                if ($title === '') {
                    $title = $direction === 'expense' ? 'Списання' : 'Надходження';
                }
                $meta = trim((string)($txRow['meta'] ?? ''));
                if ($meta === '' && !empty($txRow['created_at'])) {
                    $timestamp = strtotime($txRow['created_at']);
                    $meta = $timestamp ? date('d.m.Y H:i', $timestamp) : '';
                }

                $currencyLabel = '';

                $walletTransactions[] = [
                    'direction' => $direction,
                    'title' => $title,
                    'meta' => $meta,
                    'amount' => (float)($txRow['amount'] ?? 0),
                    'currency' => $currencyLabel,
                ];
            }
        }
    }

    $formatAmount = static function (float $amount): string {
        return number_format($amount, 0, '.', ' ');
    };

    $internalBalanceLabel = $formatAmount($walletInternalBalance);
    $monthLabel = date('m.Y');
    $balanceLabel = 'Недоступно';
    $balanceNote = 'В розробці.';
    $cardsNote = 'В розробці.';
    $ctaDisabled = $paymentsAvailable ? '' : ' disabled';
    $balanceActionsDisabled = $paymentsAvailable ? '' : ' disabled';
    $balanceStateClass = $paymentsAvailable ? '' : ' is-unavailable';
    $balanceDisabledClass = $paymentsAvailable ? '' : ' wallet-v2-disabled';
    $cardsDisabledClass = $paymentsAvailable ? '' : ' wallet-v2-disabled';

    $transactionsHtml = '';
    if (empty($walletTransactions)) {
        $transactionsHtml = '<div class="wallet-v2-empty">Операцій поки немає</div>';
    } else {
        foreach ($walletTransactions as $tx) {
            $sign = $tx['direction'] === 'expense' ? '-' : '+';
            $transactionsHtml .= '
            <div class="wallet-v2-transaction wallet-v2-transaction-' . $tx['direction'] . '">
                <div class="wallet-v2-transaction-icon">' . ($tx['direction'] === 'expense' ? '-' : '+') . '</div>
                <div class="wallet-v2-transaction-info">
                    <div class="wallet-v2-transaction-title">' . htmlspecialchars($tx['title']) . '</div>
                    <div class="wallet-v2-transaction-meta">' . htmlspecialchars($tx['meta']) . '</div>
                </div>
                <div class="wallet-v2-transaction-amount">' . $sign . $formatAmount($tx['amount']) . $tx['currency'] . '</div>
            </div>';
        }
    }

    View_Add('
<div class="wallet-v2">
    <div class="wallet-v2-hero">
        <div class="wallet-v2-hero-text">
            <div class="wallet-v2-kicker">Гаманець</div>
            <div class="wallet-v2-title">Фінансовий центр</div>
            <div class="wallet-v2-subtitle">Керуйте внутрішньою валютою та історією операцій</div>
        </div>
        <button class="wallet-v2-cta" type="button"' . $ctaDisabled . '>Поповнити баланс</button>
    </div>

    <div class="wallet-v2-balance">
        <div class="wallet-v2-balance-main' . $balanceDisabledClass . '">
            <div class="wallet-v2-balance-label">Поточний баланс</div>
            <div class="wallet-v2-balance-amount' . $balanceStateClass . '">' . $balanceLabel . '</div>
            <div class="wallet-v2-balance-meta">' . $balanceNote . '</div>
            <div class="wallet-v2-balance-actions">
                <button class="wallet-v2-btn wallet-v2-btn-primary" type="button"' . $balanceActionsDisabled . '>Поповнити</button>
                <button class="wallet-v2-btn wallet-v2-btn-ghost" type="button"' . $balanceActionsDisabled . '>Вивести</button>
            </div>
        </div>
        <div class="wallet-v2-balance-side">
            <div class="wallet-v2-token">
                <div class="wallet-v2-token-title">Внутрішня валюта</div>
                <div class="wallet-v2-token-value">' . $internalBalanceLabel . '</div>
            </div>
            <div class="wallet-v2-insight">
                <div class="wallet-v2-insight-title">Надходження за місяць</div>
                <div class="wallet-v2-insight-value">' . $formatAmount($walletMonthIncome) . '</div>
                <div class="wallet-v2-insight-meta">Витрати: ' . $formatAmount($walletMonthExpense) . '</div>
            </div>
        </div>
    </div>

    <div class="wallet-v2-grid">
        <section class="wallet-v2-panel' . $cardsDisabledClass . '">
            <div class="wallet-v2-panel-header">
                <div>
                    <div class="wallet-v2-panel-title">Платіжні картки</div>
                    <div class="wallet-v2-panel-subtitle">Підключені способи оплати</div>
                </div>
                <button class="wallet-v2-link-btn" type="button"' . $ctaDisabled . '>Додати картку</button>
            </div>
            <div class="wallet-v2-empty">' . $cardsNote . '</div>
        </section>
        <section class="wallet-v2-panel">
            <div class="wallet-v2-panel-header">
                <div>
                    <div class="wallet-v2-panel-title">Статистика місяця</div>
                    <div class="wallet-v2-panel-subtitle">' . $monthLabel . '</div>
                </div>
            </div>
            <div class="wallet-v2-stats">
                <div class="wallet-v2-stat">
                    <div class="wallet-v2-stat-label">Надходження</div>
                    <div class="wallet-v2-stat-value wallet-v2-positive">+' . $formatAmount($walletMonthIncome) . '</div>
                </div>
                <div class="wallet-v2-stat">
                    <div class="wallet-v2-stat-label">Витрати</div>
                    <div class="wallet-v2-stat-value wallet-v2-negative">-' . $formatAmount($walletMonthExpense) . '</div>
                </div>
                <div class="wallet-v2-stat">
                    <div class="wallet-v2-stat-label">Резерв</div>
                    <div class="wallet-v2-stat-value">' . $formatAmount(max(0, $walletMonthIncome - $walletMonthExpense)) . '</div>
                </div>
            </div>
        </section>
    </div>

    <section class="wallet-v2-panel wallet-v2-transactions">
        <div class="wallet-v2-panel-header">
            <div>
                <div class="wallet-v2-panel-title">Останні операції</div>
                <div class="wallet-v2-panel-subtitle">Актуальний стан</div>
            </div>
            <button class="wallet-v2-link-btn wallet-v2-link-btn-secondary" type="button">Всі операції</button>
        </div>
        <div class="wallet-v2-transactions-list">
            ' . $transactionsHtml . '
        </div>
    </section>
</div>
');
}

// Повідомлення (md=11)
if ($md == 11 && $_SESSION['logged'] == 1) {
    View_Add('<link rel="stylesheet" href="/assets/css/profile.css">');

    $userId = (int)$_SESSION['uzver'];
    $filter = (string)($_GET['filter'] ?? 'all');
    if (!in_array($filter, ['all', 'unread', 'read'], true)) {
        $filter = 'all';
    }
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 20;
    $offset = ($page - 1) * $perPage;
    $tableExists = function_exists('notificationsTableExists') ? notificationsTableExists($dblink) : false;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['notify_action']) && $tableExists && $dblink) {
        $action = (string)($_POST['notify_action'] ?? '');
        $notificationId = (int)($_POST['notification_id'] ?? 0);

        if ($action === 'mark_read' && $notificationId > 0) {
            $stmt = mysqli_prepare($dblink, "UPDATE user_notifications SET status = 'read', read_at = NOW() WHERE id = ? AND user_id = ? LIMIT 1");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'ii', $notificationId, $userId);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        } elseif ($action === 'mark_unread' && $notificationId > 0) {
            $stmt = mysqli_prepare($dblink, "UPDATE user_notifications SET status = 'unread', read_at = NULL WHERE id = ? AND user_id = ? LIMIT 1");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'ii', $notificationId, $userId);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        } elseif ($action === 'mark_all_read') {
            mysqli_query(
                $dblink,
                "UPDATE user_notifications SET status = 'read', read_at = NOW() WHERE user_id = " . $userId . " AND status = 'unread'"
            );
        }

        header('Location: /profile.php?md=11&filter=' . urlencode($filter) . '&page=' . $page);
        exit;
    }

    $notifications = [];
    $totalCount = 0;
    $totalPages = 1;
    $statusSql = '';
    if ($filter === 'unread') {
        $statusSql = " AND status = 'unread'";
    } elseif ($filter === 'read') {
        $statusSql = " AND status = 'read'";
    }

    if ($tableExists && $dblink) {
        $countRes = mysqli_query(
            $dblink,
            "SELECT COUNT(*) AS cnt FROM user_notifications WHERE user_id = " . $userId . $statusSql
        );
        if ($countRes && ($countRow = mysqli_fetch_assoc($countRes))) {
            $totalCount = (int)($countRow['cnt'] ?? 0);
        }

        $totalPages = max(1, (int)ceil($totalCount / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
            $offset = ($page - 1) * $perPage;
        }

        $sql = "SELECT id, title, body, category, status, priority, action_url, action_label, created_at, read_at
                FROM user_notifications
                WHERE user_id = ? $statusSql
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?";
        $stmt = mysqli_prepare($dblink, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'iii', $userId, $perPage, $offset);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_bind_result(
                $stmt,
                $noteId,
                $noteTitle,
                $noteBody,
                $noteCategory,
                $noteStatus,
                $notePriority,
                $noteActionUrl,
                $noteActionLabel,
                $noteCreatedAt,
                $noteReadAt
            );
            while (mysqli_stmt_fetch($stmt)) {
                $notifications[] = [
                    'id' => $noteId,
                    'title' => $noteTitle,
                    'body' => $noteBody,
                    'category' => $noteCategory,
                    'status' => $noteStatus,
                    'priority' => $notePriority,
                    'action_url' => $noteActionUrl,
                    'action_label' => $noteActionLabel,
                    'created_at' => $noteCreatedAt,
                    'read_at' => $noteReadAt,
                ];
            }
            mysqli_stmt_close($stmt);
        }
    }

    $categoryLabels = [
        'system' => 'Системне',
        'account' => 'Акаунт',
        'moderation' => 'Модерація',
        'wallet' => 'Гаманець',
        'manual' => 'Повідомлення',
        'campaign' => 'Розсилка',
    ];

    $listHtml = '';
    if (!$tableExists) {
        $listHtml = '<div class="notify-empty">Розділ повідомлень ще не готовий.</div>';
    } elseif (empty($notifications)) {
        $listHtml = '<div class="notify-empty">Поки що немає повідомлень.</div>';
    } else {
        foreach ($notifications as $note) {
            $noteId = (int)($note['id'] ?? 0);
            $status = (string)($note['status'] ?? 'unread');
            $categoryKey = (string)($note['category'] ?? 'system');
            $createdAt = (string)($note['created_at'] ?? '');
            $timeAgo = $createdAt !== '' ? formatTimeAgo($createdAt) : '';
            $actionUrl = trim((string)($note['action_url'] ?? ''));
            $actionLabel = trim((string)($note['action_label'] ?? ''));
            if ($actionLabel === '') {
                $actionLabel = 'Відкрити';
            }

            $iconHtml = '';
            switch ($categoryKey) {
                case 'wallet':
                    $iconHtml = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-wallet"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M17 8v-3a1 1 0 0 0 -1 -1h-10a2 2 0 0 0 0 4h12a1 1 0 0 1 1 1v3m0 4v3a1 1 0 0 1 -1 1h-12a2 2 0 0 1 -2 -2v-12" /><path d="M20 12v4h-4a2 2 0 0 1 0 -4h4" /></svg>';
                    break;
                case 'moderation':
                    $iconHtml = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-shield-half"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 3a12 12 0 0 0 8.5 3a12 12 0 0 1 -8.5 15a12 12 0 0 1 -8.5 -15a12 12 0 0 0 8.5 -3" /><path d="M12 3v18" /></svg>';
                    break;
                case 'account':
                    $iconHtml = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-user-circle"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 12a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" /><path d="M9 10a3 3 0 1 0 6 0a3 3 0 1 0 -6 0" /><path d="M6.168 18.849a4 4 0 0 1 3.832 -2.849h4a4 4 0 0 1 3.834 2.855" /></svg>';
                    break;
                case 'system':
                    $iconHtml = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-settings"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M10.325 4.317c.426 -1.756 2.924 -1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543 -.94 3.31 .826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756 .426 1.756 2.924 0 3.35a1.724 1.724 0 0 0 -1.066 2.573c.94 1.543 -.826 3.31 -2.37 2.37a1.724 1.724 0 0 0 -2.572 1.065c-.426 1.756 -2.924 1.756 -3.35 0a1.724 1.724 0 0 0 -2.573 -1.066c-1.543 .94 -3.31 -.826 -2.37 -2.37a1.724 1.724 0 0 0 -1.065 -2.572c-1.756 -.426 -1.756 -2.924 0 -3.35a1.724 1.724 0 0 0 1.066 -2.573c-.94 -1.543 .826 -3.31 2.37 -2.37c1 .608 2.296 .07 2.572 -1.065" /><path d="M9 12a3 3 0 1 0 6 0a3 3 0 0 0 -6 0" /></svg>';
                    break;
                case 'manual':
                    $iconHtml = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-bell"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M10 5a2 2 0 1 1 4 0a7 7 0 0 1 4 6v3a4 4 0 0 0 2 3h-16a4 4 0 0 0 2 -3v-3a7 7 0 0 1 4 -6" /><path d="M9 17v1a3 3 0 0 0 6 0v-1" /></svg>';
                    break;
                case 'campaign':
                    $iconHtml = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-mail"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 7a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v10a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-10" /><path d="M3 7l9 6l9 -6" /></svg>';
                    break;
                default:
                    $iconHtml = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-bell"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M10 5a2 2 0 1 1 4 0a7 7 0 0 1 4 6v3a4 4 0 0 0 2 3h-16a4 4 0 0 0 2 -3v-3a7 7 0 0 1 4 -6" /><path d="M9 17v1a3 3 0 0 0 6 0v-1" /></svg>';
                    break;
            }

            $listHtml .= '
            <article class="notify-item' . ($status === 'unread' ? ' is-unread' : '') . '">
                <div class="notify-icon">' . $iconHtml . '</div>
                <div class="notify-content">
                    <div class="notify-title-row">
                        <span class="notify-title">' . htmlspecialchars((string)($note['title'] ?? ''), ENT_QUOTES, 'UTF-8') . '</span>'
                        . ($status === 'unread' ? '<span class="notify-pill">Нове</span>' : '') . '
                    </div>
                    <div class="notify-body">' . nl2br(htmlspecialchars((string)($note['body'] ?? ''), ENT_QUOTES, 'UTF-8')) . '</div>
                    <div class="notify-meta">
                        ' . ($timeAgo !== '' ? '<span class="notify-time">' . htmlspecialchars($timeAgo, ENT_QUOTES, 'UTF-8') . '</span>' : '') . '
                        <div class="notify-meta-actions">';

            if ($actionUrl !== '') {
                $listHtml .= '<a class="notify-action-link" href="' . htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($actionLabel, ENT_QUOTES, 'UTF-8') . '</a>';
            }

            $listHtml .= '
                    <form method="post" class="notify-action-form">
                        <input type="hidden" name="notify_action" value="' . ($status === 'unread' ? 'mark_read' : 'mark_unread') . '">
                        <input type="hidden" name="notification_id" value="' . $noteId . '">
                        <button type="submit" class="notify-mark-btn">' . ($status === 'unread' ? 'Позначити прочитаним' : 'Позначити непрочитаним') . '</button>
                    </form>
                        </div>
                    </div>
                </div>
            </article>';
        }
    }

    $paginationHtml = '';
    if ($totalPages > 1) {
        $paginationHtml .= '<div class="profile-notify-pagination">';
        if ($page > 1) {
            $paginationHtml .= '<a class="profile-notify-page" href="/profile.php?md=11&filter=' . urlencode($filter) . '&page=' . ($page - 1) . '">Назад</a>';
        }
        $paginationHtml .= '<span class="profile-notify-page-current">Сторінка ' . $page . ' з ' . $totalPages . '</span>';
        if ($page < $totalPages) {
            $paginationHtml .= '<a class="profile-notify-page" href="/profile.php?md=11&filter=' . urlencode($filter) . '&page=' . ($page + 1) . '">Далі</a>';
        }
        $paginationHtml .= '</div>';
    }

    $unreadCount = function_exists('getUnreadNotificationCount') ? getUnreadNotificationCount($userId) : 0;
    $markAllHtml = '';
    if ($tableExists && $totalCount > 0) {
        $markAllHtml = '
            <form method="post" class="notify-markall">
                <input type="hidden" name="notify_action" value="mark_all_read">
                <button type="submit" class="notify-markall-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12l5 5l11 -11"></path></svg>
                    Прочитати всі
                </button>
            </form>';
    }

    View_Add('
    <section class="notify-page">
        <div class="notify-header">
            <div>
                <h1>Повідомлення</h1>
                <p>У вас ' . (int)$unreadCount . ' непрочитаних</p>
            </div>
            ' . $markAllHtml . '
        </div>
        <div class="notify-card">
            <div class="notify-card-head">
                <h2>Усі повідомлення</h2>
                <p>Останні оновлення та повідомлення</p>
            </div>
            <div class="notify-list">
                ' . $listHtml . '
            </div>
        </div>
        ' . $paginationHtml . '
    </section>
    ');
}

// Безпека - Активні сесії
if ($md == 6 && $_SESSION['logged'] == 1) {
    $dblink = DbConnect();
    $userId = (int)$_SESSION['uzver'];
    $currentSessionId = session_id();
    
    // Перевіряємо та створюємо сесію, якщо її немає
    if (function_exists('getUserSessions')) {
        $sessions = getUserSessions($userId);
        $sessionExists = false;
        foreach ($sessions as $sess) {
            if ($sess['session_id'] == $currentSessionId) {
                $sessionExists = true;
                break;
            }
        }
        if (!$sessionExists && function_exists('createUserSession')) {
            $ip = $_SERVER['REMOTE_ADDR'];
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            createUserSession($userId, $currentSessionId, $ip, $userAgent);
        }
    }
    
    // Оновлюємо активність поточної сесії
    if (function_exists('updateSessionActivity')) {
        updateSessionActivity($currentSessionId);
    }
    
    // Обробка завершення сесії
    if (isset($_POST['end_session']) && isset($_POST['session_id'])) {
        $sessionIdToEnd = $_POST['session_id'];
        if ($sessionIdToEnd == $currentSessionId) {
            // Якщо завершуємо поточну сесію - виходимо
            deleteSession($sessionIdToEnd, $userId);
            session_destroy();
            header('Location: /auth.php');
            exit;
        } else {
            deleteSession($sessionIdToEnd, $userId);
            header('Location: /profile.php?md=6');
            exit;
        }
    }
    
    // Обробка завершення всіх сесій
    if (isset($_POST['end_all_sessions'])) {
        deleteAllUserSessions($userId, $currentSessionId);
        header('Location: /profile.php?md=6');
        exit;
    }
    
    // Отримуємо всі сесії користувача
    $sessions = getUserSessions($userId);
    
    View_Add('
<link rel="stylesheet" href="/assets/css/profile.css">
<div class="security-page">
    <div class="security-section">
        <div class="security-section-header">
            <div class="security-header-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
            </div>
            <div class="security-header-text">
                <h2 class="security-section-title">Активні сесії</h2>
                <p class="security-section-subtitle">Пристрої, на яких виконано вхід</p>
            </div>
            ' . (count($sessions) > 1 ? '
            <form method="post" style="margin-left: auto;" onsubmit="return confirm(\'Ви впевнені, що хочете завершити всі інші сесії?\');">
                <input type="hidden" name="end_all_sessions" value="1">
                <button type="submit" class="security-end-all-btn">Завершити всі</button>
            </form>
            ' : '') . '
        </div>
        
        <div class="security-sessions-list">');
    
    if (empty($sessions)) {
        View_Add('
            <div class="security-empty-state">
                <p>Активних сесій не знайдено</p>
            </div>');
    } else {
        foreach ($sessions as $session) {
            $isCurrent = ($session['session_id'] == $currentSessionId) || ($session['is_current'] == 1);
            $timeAgo = formatTimeAgo($session['last_activity']);
            
            View_Add('
            <div class="security-session-item">
                <div class="security-session-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                        <line x1="8" y1="21" x2="16" y2="21"></line>
                        <line x1="12" y1="17" x2="12" y2="21"></line>
                    </svg>
                </div>
                <div class="security-session-info">
                    <div class="security-session-device">
                        ' . htmlspecialchars($session['device_name'] ?: 'Невідомий пристрій') . '
                        ' . ($isCurrent ? '<span class="security-current-badge">Поточна</span>' : '') . '
                    </div>
                    <div class="security-session-details">
                        ' . htmlspecialchars($session['location'] ?: 'Локація не визначена') . ' • ' . $timeAgo . '
                    </div>
                </div>
                ' . (!$isCurrent ? '
                <form method="post" style="margin-left: auto;" onsubmit="return confirm(\'Ви впевнені, що хочете завершити цю сесію?\');">
                    <input type="hidden" name="session_id" value="' . htmlspecialchars($session['session_id']) . '">
                    <input type="hidden" name="end_session" value="1">
                    <button type="submit" class="security-end-session-btn">Завершити</button>
                </form>
                ' : '') . '
            </div>');
        }
    }
    
    View_Add('
        </div>
    </div>
</div>');
}

// Меню профиля
function Menu_Profile(): string
{
    $mdParam = isset($_GET['md']) ? (string)$_GET['md'] : '0';
    $currentMd = ($mdParam === '010') ? 4 : (int)$mdParam;
    $status    = $_SESSION['status'] ?? ROLE_GUEST;
    $userId = (int)($_SESSION['uzver'] ?? 0);
    $unreadCount = function_exists('getUnreadNotificationCount') ? getUnreadNotificationCount($userId) : 0;

    $links = [
        0 => 'Загальна інформація',
        11 => 'Повідомлення',
        2 => 'Налаштування профілю',
        4 => 'Фінансова інформація',
        5 => 'Бухгалтерія',
        10 => 'Кабінет прибиральника',
       // 6 => 'Безпека'
    ];

    $icons = [
        0 => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="pr-icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" /><path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2" /></svg>',
        11 => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="pr-icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M10 5a2 2 0 1 1 4 0a7 7 0 0 1 4 6v3a4 4 0 0 0 2 3h-16a4 4 0 0 0 2 -3v-3a7 7 0 0 1 4 -6" /><path d="M9 17v1a3 3 0 0 0 6 0v-1" /></svg>',
        2 => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="pr-icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M10.325 4.317c.426 -1.756 2.924 -1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543 -.94 3.31 .826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756 .426 1.756 2.924 0 3.35a1.724 1.724 0 0 0 -1.066 2.573c.94 1.543 -.826 3.31 -2.37 2.37a1.724 1.724 0 0 0 -2.572 1.065c-.426 1.756 -2.924 1.756 -3.35 0a1.724 1.724 0 0 0 -2.573 -1.066c-1.543 .94 -3.31 -.826 -2.37 -2.37a1.724 1.724 0 0 0 -1.065 -2.572c-1.756 -.426 -1.756 -2.924 0 -3.35a1.724 1.724 0 0 0 1.066 -2.573c-.94 -1.543 .826 -3.31 2.37 -2.37c1 .608 2.296 .07 2.572 -1.065" /><path d="M9 12a3 3 0 1 0 6 0a3 3 0 0 0 -6 0" /></svg>',
        4 => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="pr-icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M17 8v-3a1 1 0 0 0 -1 -1h-10a2 2 0 0 0 0 4h12a1 1 0 0 1 1 1v3m0 4v3a1 1 0 0 1 -1 1h-12a2 2 0 0 1 -2 -2v-12" /><path d="M20 12v4h-4a2 2 0 0 1 0 -4h4" /></svg>',
        5 => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="pr-icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 12h18" /><path d="M3 6h18" /><path d="M5 18h14" /><path d="M7 14h10" /></svg>',
        10 => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="pr-icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 9a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v9a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2l0 -9" /><path d="M8 7v-2a2 2 0 0 1 2 -2h4a2 2 0 0 1 2 2v2" /><path d="M12 12l0 .01" /><path d="M3 13a20 20 0 0 0 18 0" /></svg>',
    ];

    $out = '<div class="Menu_Profile">';

    foreach ($links as $md => $title) {

        if ($md === 5 && !hasRole($status, ROLE_ACCOUNTANT)) {
            continue;
        }

        if ($md === 10 && !hasRole($status, ROLE_CLEANER)) {
            continue;
        }

        $activeClass = ($md === $currentMd) ? 'active' : '';
        $icon = $icons[$md] ?? '';
        $badge = ($md === 11 && $unreadCount > 0)
            ? '<span class="profile-menu-badge">' . (int)$unreadCount . '</span>'
            : '';
        $out .= '<a href="profile.php?md='.$md.'" class="menu-link '.$activeClass.'">'
            . $icon . htmlspecialchars($title) . $badge
            . '</a>';

        if ($md === 2 || $md === 4 || $md === 6 || $md === 10) {
            $out .= '<div class="divider"></div>';
        }
    }
    $out .= '<!-- STATUS: '.($_SESSION['status'] ?? 'NO SESSION').' -->';

    $out .= '</div>';

    return $out;
}


function Menu_Profile_Mobile(): void
{
    $userId = (int)($_SESSION['uzver'] ?? 0);
    $unreadCount = function_exists('getUnreadNotificationCount') ? getUnreadNotificationCount($userId) : 0;
    $unreadBadge = $unreadCount > 0 ? ' <span class="profile-menu-badge">' . (int)$unreadCount . '</span>' : '';
    View_Add('
    <div class="profile-menu-btn-wrap">
    <button class="profile-menu-btn" id="openProfileMenu">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
             class="bi bi-columns-gap" viewBox="0 0 16 16">
            <path d="M6 1v3H1V1zM1 0a1 1 0 0 0-1 1v3a1 1 0 0 0 1 1h5a1 1 0 0 0 1-1V1a1 1 0 0 0-1-1zm14 12v3h-5v-3zm-5-1a1 1 0 0 0-1 1v3a1 1 0 0 0 1 1h5a1 1 0 0 0 1-1v-3a1 1 0 0 0-1-1zM6 8v7H1V8zM1 7a1 1 0 0 0-1 1v7a1 1 0 0 0 1 1h5a1 1 0 0 0 1-1V8a1 1 0 0 0-1-1zm14-6v7h-5V1zm-5-1a1 1 0 0 0-1 1v7a1 1 0 0 0 1 1h5a1 1 0 0 0 1-1V1a1 1 0 0 0-1-1z"/>
        </svg> Меню профілю
    </button>
    </div>

    <div id="profileMenu" class="profile-menu-overlay">
        <span class="close-btn" id="closeProfileMenu">&times;</span>
        <div class="profile-menu-title">Меню профілю</div>
        <hr class="profile-menu-separator">
        <div class="profile-menu-list">
            <a class="profile-menu-item" href="profile.php?md=0">Загальна інформація</a>
            <a class="profile-menu-item" href="profile.php?md=11">Повідомлення' . $unreadBadge . '</a>
            <a class="profile-menu-item" href="profile.php?md=2">Налаштування профілю</a>
            <a class="profile-menu-item" href="profile.php?md=4">Фінансова інформація</a>
    ');

    $status = $_SESSION['status'] ?? 0;

    if (hasRole($status, ROLE_ACCOUNTANT)) {
        View_Add('<a class="profile-menu-item" href="profile.php?md=5">Бухгалтерія</a>');
    }

    if (hasRole($status, ROLE_CLEANER)) {
        View_Add('<a class="profile-menu-item" href="profile.php?md=10">Кабінет прибиральника</a>');
    }

    View_Add('
        </div>
        <hr class="profile-menu-separator">
        <a class="profile-menu-logout-btn" href="profile.php?exit=1">Вихід</a>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const openBtn = document.getElementById("openProfileMenu");
            const closeBtn = document.getElementById("closeProfileMenu");
            const menu = document.getElementById("profileMenu");

            function openMenu() {
                menu.style.display = "flex";
                document.body.classList.add("profile-menu-open");
            }

            function closeMenu() {
                menu.style.display = "none";
                document.body.classList.remove("profile-menu-open");
            }

            document.querySelectorAll(".mobile-open-profile-btn").forEach(function(btn) {
                btn.addEventListener("click", openMenu);
            });
            if (openBtn && closeBtn && menu) {
                openBtn.addEventListener("click", openMenu);
                closeBtn.addEventListener("click", closeMenu);
                menu.addEventListener("click", function(e) {
                    if (e.target === menu) closeMenu();
                });
            }
        });
    </script>
    ');
}


if (isset($_GET['exit']) && $_GET['exit'] == 1) {
    session_start();
    $_SESSION = [];
    session_destroy();
    header("Location: /index.php");
    exit;
}

View_Add('</div>');
View_Add('</div>');
View_Add('</main>');

View_Add('</div>'); // .layout
View_Add(Page_Down());

View_Out();
View_Clear();
