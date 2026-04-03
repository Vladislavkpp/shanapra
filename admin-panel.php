<?php

require_once $_SERVER['DOCUMENT_ROOT'] . "/roles.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/function.php";

$dblink = DbConnect();

View_Clear();
View_Add(Page_Up('Адмін-Панель'));
View_Add(Menu_Up());
View_Add('<link rel="stylesheet" href="/assets/css/admin.css">');
View_Add('<div class="layout">');

// Левое меню
View_Add('<aside class="sidebar-admin">');
View_Add(Menu_Panel());
View_Add('</aside>');

// Контент
View_Add('<main class="content-admin">');
View_Add('<div class="content-inner">');
View_Add('<div class="out-admin">');



if (!isset($_SESSION['status']) || !hasAnyRole((int)$_SESSION['status'], [ROLE_CREATOR, ROLE_WEBMASTER])) {
    View_Clear();
    View_Add(Page_Up('Access Denied'));
    View_Add(Menu_Up());
    View_Add('<div class="error-message">Відмовлено у доступі.</div>');
    View_Add(Page_Down());
    View_Out();
    exit;
}


$md = isset($_GET['md']) ? (int)$_GET['md'] : 0;

$dblink = DbConnect();
$res = mysqli_query($dblink, 'SELECT * FROM users WHERE idx=' . (int)$_SESSION['uzver']);
$userData = mysqli_fetch_assoc($res);
$noticeMessage = '';
$noticeType = 'success';

if ($md === 1 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['notify_action'] ?? '');
    $currentUserId = (int)($_SESSION['uzver'] ?? 0);
    $senderRole = 'admin';

    if (!function_exists('createUserNotification')) {
        $noticeMessage = 'Функції повідомлень не знайдено.';
        $noticeType = 'error';
    } elseif ($action === 'send_single') {
        $targetUserId = (int)($_POST['user_id'] ?? 0);
        $title = trim((string)($_POST['title'] ?? ''));
        $body = trim((string)($_POST['body'] ?? ''));
        $category = normalizeNotificationCategory((string)($_POST['category'] ?? 'manual'));
        $priority = normalizeNotificationPriority((string)($_POST['priority'] ?? 'normal'));
        $actionUrl = trim((string)($_POST['action_url'] ?? ''));
        $actionLabel = trim((string)($_POST['action_label'] ?? ''));
        $isSystem = isset($_POST['is_system']) ? 1 : 0;

        if ($targetUserId <= 0 || $title === '' || $body === '') {
            $noticeMessage = 'Заповніть ID користувача, заголовок та текст.';
            $noticeType = 'error';
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
                $currentUserId,
                $senderRole,
                null,
                $isSystem,
                $dblink
            );

            if ($notificationId > 0) {
                $noticeMessage = 'Повідомлення надіслано користувачу.';
            } else {
                $noticeMessage = 'Не вдалося надіслати повідомлення.';
                $noticeType = 'error';
            }
        }
    } elseif ($action === 'send_campaign') {
        $title = trim((string)($_POST['title'] ?? ''));
        $body = trim((string)($_POST['body'] ?? ''));
        $actionUrl = trim((string)($_POST['action_url'] ?? ''));
        $actionLabel = trim((string)($_POST['action_label'] ?? ''));
        $priority = normalizeNotificationPriority((string)($_POST['priority'] ?? 'normal'));
        $targetType = (string)($_POST['target_type'] ?? 'all');
        $targetPayload = null;
        $recipientIds = [];

        if ($title === '' || $body === '') {
            $noticeMessage = 'Заповніть заголовок та текст розсилки.';
            $noticeType = 'error';
        } else {
            if ($targetType === 'role') {
                $roleBit = (int)($_POST['target_role'] ?? 0);
                if ($roleBit <= 0) {
                    $noticeMessage = 'Оберіть роль для розсилки.';
                    $noticeType = 'error';
                } else {
                    $targetPayload = (string)$roleBit;
                    $resUsers = mysqli_query(
                        $dblink,
                        "SELECT idx FROM users WHERE (status & " . $roleBit . ") = " . $roleBit
                    );
                    if ($resUsers) {
                        while ($row = mysqli_fetch_assoc($resUsers)) {
                            $recipientIds[] = (int)$row['idx'];
                        }
                    }
                }
            } elseif ($targetType === 'user_ids') {
                $idsRaw = trim((string)($_POST['target_user_ids'] ?? ''));
                $parts = preg_split('/[^\d]+/', $idsRaw);
                $ids = [];
                if (is_array($parts)) {
                    foreach ($parts as $part) {
                        $idVal = (int)$part;
                        if ($idVal > 0) {
                            $ids[$idVal] = true;
                        }
                    }
                }
                $recipientIds = array_keys($ids);
                $targetPayload = implode(',', $recipientIds);
                if (empty($recipientIds)) {
                    $noticeMessage = 'Вкажіть коректні ID користувачів.';
                    $noticeType = 'error';
                }
            } else {
                $targetType = 'all';
                $targetPayload = 'all';
                $resUsers = mysqli_query($dblink, "SELECT idx FROM users");
                if ($resUsers) {
                    while ($row = mysqli_fetch_assoc($resUsers)) {
                        $recipientIds[] = (int)$row['idx'];
                    }
                }
            }

            if ($noticeType !== 'error') {
                $recipientIds = array_values(array_unique($recipientIds));
                if (empty($recipientIds)) {
                    $noticeMessage = 'Не знайдено отримувачів для розсилки.';
                    $noticeType = 'error';
                } else {
                    $campaignId = createNotificationCampaign(
                        $currentUserId,
                        $title,
                        $body,
                        'campaign',
                        $priority,
                        $targetType,
                        $targetPayload,
                        count($recipientIds),
                        $dblink
                    );

                    if ($campaignId <= 0) {
                        $noticeMessage = 'Не вдалося створити розсилку.';
                        $noticeType = 'error';
                    } else {
                        $insertRecipientStmt = mysqli_prepare(
                            $dblink,
                            "INSERT INTO notification_campaign_recipients (campaign_id, user_id, notification_id, delivery_status, error_text)
                             VALUES (?, ?, ?, ?, ?)"
                        );
                        $createdCount = 0;

                        foreach ($recipientIds as $recipientId) {
                            $recipientId = (int)$recipientId;
                            if ($recipientId <= 0) {
                                continue;
                            }
                            $notificationId = createUserNotification(
                                $recipientId,
                                $title,
                                $body,
                                'campaign',
                                $priority,
                                $actionUrl !== '' ? $actionUrl : null,
                                $actionLabel !== '' ? $actionLabel : null,
                                'campaign',
                                null,
                                $currentUserId,
                                $senderRole,
                                $campaignId,
                                1,
                                $dblink
                            );

                            if ($insertRecipientStmt) {
                                $deliveryStatus = $notificationId > 0 ? 'created' : 'failed';
                                $errorText = $notificationId > 0 ? null : 'Не вдалося створити повідомлення';
                                $notificationIdVal = $notificationId > 0 ? $notificationId : null;
                                mysqli_stmt_bind_param(
                                    $insertRecipientStmt,
                                    'iiiss',
                                    $campaignId,
                                    $recipientId,
                                    $notificationIdVal,
                                    $deliveryStatus,
                                    $errorText
                                );
                                mysqli_stmt_execute($insertRecipientStmt);
                            }

                            if ($notificationId > 0) {
                                $createdCount++;
                            }
                        }

                        if ($insertRecipientStmt) {
                            mysqli_stmt_close($insertRecipientStmt);
                        }

                        mysqli_query(
                            $dblink,
                            "UPDATE notification_campaigns SET total_recipients = " . (int)$createdCount . " WHERE id = " . (int)$campaignId
                        );

                        $noticeMessage = 'Розсилку створено. Отримувачів: ' . $createdCount . '.';
                    }
                }
            }
        }
    }
}

function Menu_Panel(): string
{
    $currentMd = isset($_GET['md']) ? (int)$_GET['md'] : 0;
    $status    = $_SESSION['status'] ?? ROLE_GUEST;

    $links = [
        0 => 'Користувачі',
        1 => 'Повідомлення',
    ];

    $out = '<div class="menu-admin">';

    foreach ($links as $md => $title) {
        $activeClass = ($md === $currentMd) ? 'active' : '';
        $out .= '<a href="admin-panel.php?md='.$md.'" class="menu-link '.$activeClass.'">'
            . htmlspecialchars($title)
            . '</a>';
    }
    $out .= '<!-- STATUS: '.($_SESSION['status'] ?? 'NO SESSION').' -->';

    $out .= '</div>';

    return $out;
}

function adminPanelFormatPersonName(?string $fname, ?string $lname, int $userId = 0): string
{
    $name = trim((string)$lname . ' ' . (string)$fname);
    if ($name !== '') {
        return $name;
    }

    return $userId > 0 ? 'Користувач #' . $userId : 'Користувач';
}

function adminPanelInitials(?string $fname, ?string $lname, int $userId = 0): string
{
    $parts = [trim((string)$fname), trim((string)$lname)];
    $initials = '';

    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }

        if (function_exists('mb_substr')) {
            $initials .= mb_strtoupper(mb_substr($part, 0, 1, 'UTF-8'), 'UTF-8');
        } else {
            $initials .= strtoupper(substr($part, 0, 1));
        }

        if (strlen($initials) >= 2) {
            break;
        }
    }

    if ($initials !== '') {
        return $initials;
    }

    return $userId > 0 ? '#' . $userId : 'U';
}

function adminPanelRolePills(array $labels): string
{
    if (empty($labels)) {
        return '<span class="admin-role-pill admin-role-pill--guest">Гість</span>';
    }

    $out = '';
    foreach ($labels as $label) {
        $out .= '<span class="admin-role-pill">' . htmlspecialchars((string)$label, ENT_QUOTES, 'UTF-8') . '</span>';
    }

    return $out;
}

function adminPanelHero(int $md, array $userData): string
{
    $config = [
        0 => [
            'title' => 'Центр керування користувачами',
            'subtitle' => 'Усі основні дії з профілями, ролями та контактними даними зібрані в одному місці.',
        ],
        1 => [
            'title' => 'Адміністративні повідомлення',
            'subtitle' => 'Персональні повідомлення та розсилки для комунікації з користувачами системи.',
        ],
    ];

    $active = $config[$md] ?? $config[0];

    return '
    <section class="admin-hero-panel">
        <div class="admin-hero-copy">
            <h1 class="admin-hero-title">' . htmlspecialchars($active['title'], ENT_QUOTES, 'UTF-8') . '</h1>
            <p class="admin-hero-subtitle">' . htmlspecialchars($active['subtitle'], ENT_QUOTES, 'UTF-8') . '</p>
        </div>
    </section>';
}

function adminPanelTopBar(): string
{
    return '
    <div class="admin-page-topbar">
        <div class="admin-page-topbar__title">Адмін-панель</div>
        <div class="admin-page-topbar__note">Керування користувачами, ролями та комунікаціями системи.</div>
    </div>';
}

if (($md == 0) || ($md == '')) {
    $dblink = DbConnect();

    // Количество
    $resCount = mysqli_query($dblink, "SELECT COUNT(*) as total FROM users");
    $countData = mysqli_fetch_assoc($resCount);
    $userCount = $countData['total'];
    
    // Активные пользователи
    $resActive = mysqli_query($dblink, "SELECT COUNT(*) as total FROM users WHERE activ = 1");
    $activeData = mysqli_fetch_assoc($resActive);
    $activeCount = $activeData['total'];

    $resUsers = mysqli_query($dblink, "SELECT
            u.idx, u.fname, u.lname, u.tel, u.email, u.dttmreg, u.activ, u.status, u.mesto, u.avatar, u.rest, u.rate, u.cash, u.token,
            d.title AS district_title,
            r.title AS region_title
        FROM users u
        LEFT JOIN district d ON u.mesto = d.idx
        LEFT JOIN region r ON d.region = r.idx");
    $users = [];
    while ($row = mysqli_fetch_assoc($resUsers)) {
        $users[] = $row;
    }

    global $rolesList;
    
    $html = '<div class="dashboard-container">';
    $html .= adminPanelTopBar();
    $html .= adminPanelHero(0, is_array($userData) ? $userData : []);

    // Статистика
    $html .= '
    <div class="dashboard-top-blocks">
        <div class="dashboard-block dashboard-block-primary">
            <h3>Всього користувачів</h3>
            <p class="dashboard-number">'.$userCount.'</p>
        </div>
        <div class="dashboard-block dashboard-block-success">
            <h3>Активних</h3>
            <p class="dashboard-number">'.$activeCount.'</p>
        </div>
        <div class="dashboard-block dashboard-block-info">
            <h3>Неактивних</h3>
            <p class="dashboard-number">'.($userCount - $activeCount).'</p>
        </div>
    </div>';

    // Таблица
    $html .= '<div class="table-container">
        <div class="table-header table-header--users">
            <div class="table-header-copy">
                <div class="table-header-kicker">Реєстр користувачів</div>
                <h2>Користувачі системи</h2>
                <p class="table-header-note">Швидкий доступ до профілів, ролей і базових контактних даних.</p>
            </div>
            <div class="table-actions">
                <div class="users-summary-pill"><span id="usersVisibleCount">' . count($users) . '</span> / ' . count($users) . ' відображено</div>
                <input type="text" id="searchUsers" class="search-input" placeholder="Пошук користувачів...">
            </div>
        </div>
        <div class="users-table-wrapper">
        <table class="users-table">
            <thead>
                <tr>
                    <th>Користувач</th>
                    <th>Контакти</th>
                    <th>Реєстрація</th>
                    <th>Статус</th>
                    <th>Ролі</th>
                    <th class="actions-col">Дії</th>
                </tr>
            </thead>
            <tbody id="adminUsersTableBody">';

    foreach ($users as $u) {
        $statusLabels = [];
        foreach ($rolesList as $bit => $label) {
            if (($u['status'] & $bit) === $bit) {
                $statusLabels[] = $label;
            }
        }
        $rolesDisplay = !empty($statusLabels) ? implode(', ', $statusLabels) : 'Гість';
        $activeBadge = $u['activ'] == 1 ? '<span class="badge badge-success">Так</span>' : '<span class="badge badge-danger">Ні</span>';
        $regDate = $u['dttmreg'] ? date('d.m.Y', strtotime($u['dttmreg'])) : '-';
        $regTime = $u['dttmreg'] ? date('H:i', strtotime($u['dttmreg'])) : '';
        $displayName = adminPanelFormatPersonName((string)($u['fname'] ?? ''), (string)($u['lname'] ?? ''), (int)$u['idx']);
        $initials = adminPanelInitials((string)($u['fname'] ?? ''), (string)($u['lname'] ?? ''), (int)$u['idx']);
        $email = trim((string)($u['email'] ?? ''));
        $tel = trim((string)($u['tel'] ?? ''));
        $avatar = trim((string)($u['avatar'] ?? ''));
        $districtTitle = trim((string)($u['district_title'] ?? ''));
        $regionTitle = trim((string)($u['region_title'] ?? ''));
        $locationParts = [];
        if ($regionTitle !== '') {
            $locationParts[] = $regionTitle . ' область';
        }
        if ($districtTitle !== '') {
            $locationParts[] = $districtTitle . ' район';
        }
        $locationLabel = !empty($locationParts) ? implode(', ', $locationParts) : 'Район не вказано';
        $rolesPills = adminPanelRolePills($statusLabels);
        
        $html .= '<tr data-user-id="'.$u['idx'].'">';
        $html .= '<td class="admin-user-cell">
            <div class="admin-user-card">
                <div class="admin-user-avatar">' . ($avatar !== ''
                    ? '<img src="' . htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8') . '" alt="Аватар користувача" class="admin-user-avatar-image">'
                    : htmlspecialchars($initials, ENT_QUOTES, 'UTF-8')) . '</div>
                <div class="admin-user-main">
                    <div class="admin-user-name">'.htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8').'</div>
                    <div class="admin-user-meta">
                        <span class="admin-user-id">ID #'.(int)$u['idx'].'</span>
                        <span class="admin-user-email">'.htmlspecialchars($email !== '' ? $email : 'E-mail не вказано', ENT_QUOTES, 'UTF-8').'</span>
                    </div>
                </div>
            </div>
        </td>';
        $html .= '<td class="admin-contact-cell">
            <div class="admin-contact-stack">
                <span class="admin-contact-line">'.htmlspecialchars($tel !== '' ? $tel : 'Телефон не вказано', ENT_QUOTES, 'UTF-8').'</span>
                <span class="admin-contact-subline">'.htmlspecialchars($locationLabel, ENT_QUOTES, 'UTF-8').'</span>
            </div>
        </td>';
        $html .= '<td class="admin-registration-cell">
            <div class="admin-registration-date">'.$regDate.'</div>
            <div class="admin-registration-time">'.htmlspecialchars($regTime !== '' ? $regTime : '—', ENT_QUOTES, 'UTF-8').'</div>
        </td>';
        $html .= '<td class="admin-activation-cell">'.$activeBadge.'</td>';
        $html .= '<td class="status-cell">
            <button class="status-btn" onclick="openRoleModal('.$u['idx'].', '.$u['status'].')">
                <span class="status-btn-label">Керувати</span>
                <span class="status-btn-tags">'.$rolesPills.'</span>
            </button>
        </td>';
        $html .= '<td class="actions-cell">
            <button class="btn btn-edit" onclick="openEditModal('.$u['idx'].')" data-tooltip="Редагувати">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        </td>';
        $html .= '</tr>';
    }

    $html .= '</tbody></table></div><div id="adminUsersEmptyState" class="admin-users-empty" hidden>Нічого не знайдено. Спробуйте інший запит.</div></div>';
    $html .= '</div>';

    // Модальное окно редактирования
    $html .= '<div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Редагування користувача</h3>
                <span class="close-modal" onclick="closeEditModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="editUserForm">
                    <input type="hidden" id="edit_user_id" name="user_id">
                    <div class="form-group">
                        <div class="input-container">
                            <input type="text" id="edit_fname" name="fname" class="login-Input" placeholder=" " autocomplete="off">
                            <label>Ім\'я</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="input-container">
                            <input type="text" id="edit_lname" name="lname" class="login-Input" placeholder=" " autocomplete="off">
                            <label>Прізвище</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="input-container">
                            <input type="text" id="edit_tel" name="tel" class="login-Input" placeholder=" " autocomplete="off">
                            <label>Телефон</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="input-container">
                            <input type="email" id="edit_email" name="email" class="login-Input" placeholder=" " autocomplete="off">
                            <label>Email</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="input-container">
                            <input type="text" id="edit_mesto" name="mesto" class="login-Input" placeholder=" " autocomplete="off">
                            <label>Місто</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="input-container">
                            <select id="edit_activ" name="activ" class="login-Input">
                                <option value="1">Так</option>
                                <option value="0">Ні</option>
                            </select>
                            <label class="label-active">Активовано</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="input-container">
                            <input type="number" id="edit_rest" name="rest" class="login-Input" placeholder=" " autocomplete="off" step="0.01">
                            <label>Rest</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="input-container">
                            <input type="number" id="edit_rate" name="rate" class="login-Input" placeholder=" " autocomplete="off" step="0.01">
                            <label>Rate</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="input-container">
                            <input type="number" id="edit_cash" name="cash" class="login-Input" placeholder=" " autocomplete="off" step="0.01">
                            <label>Cash</label>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Скасувати</button>
                        <button type="submit" class="btn btn-primary">Зберегти</button>
                    </div>
                </form>
            </div>
        </div>
    </div>';

    // Модальное окно ролей
    $html .= '<div id="roleModal" class="modal">
        <div class="modal-content modal-content-small">
            <div class="modal-header">
                <h3>Налаштування ролей</h3>
                <span class="close-modal" onclick="closeRoleModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="roleForm">
                    <input type="hidden" id="role_user_id" name="user_id">
                    <div class="roles-list">';
    
    foreach ($rolesList as $bit => $label) {
        $html .= '<label class="custom-checkbox-label">
            <input type="checkbox" name="roles[]" value="'.$bit.'" class="custom-checkbox role-checkbox">
            <span class="checkmark"></span>
            <span class="checkbox-text">'.htmlspecialchars($label).'</span>
        </label>';
    }
    
    $html .= '</div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeRoleModal()">Скасувати</button>
                        <button type="submit" class="btn btn-primary">Зберегти ролі</button>
                    </div>
                </form>
            </div>
        </div>
    </div>';

    // Кастомные уведомления
    $html .= '<div id="notification" class="notification">
        <div class="notification-content">
            <span class="notification-icon"></span>
            <span class="notification-message"></span>
        </div>
    </div>';
    
    View_Add($html);
    
    // Добавляем JavaScript
    View_Add('<script src="/assets/js/admin-panel.js"></script>');
} elseif ($md == 1) {
    $noticeHtml = '';
    if ($noticeMessage !== '') {
        $noticeClass = $noticeType === 'success' ? 'notification-success' : 'notification-error';
        $noticeHtml = '
        <div class="notification ' . $noticeClass . ' show">
            <div class="notification-content">
                <span class="notification-icon"></span>
                <span class="notification-message">' . htmlspecialchars($noticeMessage, ENT_QUOTES, 'UTF-8') . '</span>
            </div>
        </div>';
    }

    global $rolesList;
    $roleOptions = '';
    if (is_array($rolesList)) {
        foreach ($rolesList as $roleBit => $label) {
            $roleOptions .= '<option value="' . (int)$roleBit . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</option>';
        }
    }

    $html = '<div class="dashboard-container admin-notify">';
    $html .= adminPanelTopBar();
    $html .= adminPanelHero(1, is_array($userData) ? $userData : []);
    $html .= $noticeHtml;
    $html .= '<div class="admin-notify-grid">';

    $html .= '
    <section class="admin-notify-card">
        <h3>Особисте повідомлення</h3>
        <form method="post" class="admin-notify-form" data-admin-notify-form="single">
            <input type="hidden" name="notify_action" value="send_single">
            <div class="admin-notify-row admin-notify-row--two">
                <label class="admin-notify-field"><span>ID користувача</span>
                    <input type="number" name="user_id" placeholder="Напр. 42" required>
                </label>
                <label class="admin-notify-field"><span>Категорія</span>
                    <select id="admin-notify-category" name="category">
                        <option value="manual">Повідомлення</option>
                        <option value="system">Системне</option>
                        <option value="account">Акаунт</option>
                        <option value="moderation">Модерація</option>
                        <option value="wallet">Гаманець</option>
                    </select>
                </label>
            </div>
            <div class="admin-notify-row admin-notify-row--two">
                <label class="admin-notify-field"><span>Пріоритет</span>
                    <select id="admin-notify-priority" name="priority">
                        <option value="normal">Звичайний</option>
                        <option value="high">Високий</option>
                        <option value="low">Низький</option>
                    </select>
                </label>
                <label class="admin-notify-field admin-notify-field--checkbox"><span>Системне повідомлення</span>
                    <span class="admin-notify-checkbox">
                        <input type="checkbox" name="is_system" value="1" checked>
                        <span>Позначити як системне</span>
                    </span>
                </label>
            </div>
            <div class="admin-notify-row admin-notify-row--single">
                <label class="admin-notify-field"><span>Заголовок</span>
                    <input type="text" name="title" required>
                </label>
            </div>
            <div class="admin-notify-row admin-notify-row--single">
                <label class="admin-notify-field"><span>Текст</span>
                    <textarea name="body" rows="4" required></textarea>
                </label>
            </div>
            <div class="admin-notify-row admin-notify-row--two">
                <label class="admin-notify-field"><span>Action URL</span>
                    <input type="text" name="action_url" placeholder="/profile.php?md=11">
                </label>
                <label class="admin-notify-field"><span>Action label</span>
                    <input type="text" name="action_label" placeholder="Відкрити">
                </label>
            </div>
            <div class="admin-notify-row admin-notify-row--single">
                <button type="submit" class="btn btn-primary admin-notify-submit">Надіслати</button>
            </div>
        </form>
    </section>';

    $html .= '
    <section class="admin-notify-card">
        <h3 class="admin-notify-title">
          
            Масова розсилка
        </h3>
        <form method="post" class="admin-notify-form" data-admin-notify-form="campaign">
            <input type="hidden" name="notify_action" value="send_campaign">
            <div class="admin-notify-row admin-notify-row--single">
                <label class="admin-notify-field"><span>Заголовок</span>
                    <input type="text" name="title" required>
                </label>
            </div>
            <div class="admin-notify-row admin-notify-row--single">
                <label class="admin-notify-field"><span>Текст</span>
                    <textarea name="body" rows="4" required></textarea>
                </label>
            </div>
            <div class="admin-notify-row admin-notify-row--two">
                <label class="admin-notify-field"><span>Action URL</span>
                    <input type="text" name="action_url" placeholder="/profile.php?md=11">
                </label>
                <label class="admin-notify-field"><span>Action label</span>
                    <input type="text" name="action_label" placeholder="Відкрити">
                </label>
            </div>
            <div class="admin-notify-row admin-notify-row--two">
                <label class="admin-notify-field"><span>Пріоритет</span>
                    <select id="admin-campaign-priority" name="priority">
                        <option value="normal">Звичайний</option>
                        <option value="high">Високий</option>
                        <option value="low">Низький</option>
                    </select>
                </label>
                <label class="admin-notify-field"><span>Тип аудиторії</span>
                    <select id="admin-campaign-target-type" name="target_type">
                        <option value="all">Усі користувачі</option>
                        <option value="role">За роллю</option>
                        <option value="user_ids">За списком ID</option>
                    </select>
                </label>
            </div>
            <div class="admin-notify-row admin-notify-row--single">
                <label class="admin-notify-field" data-target-field="role"><span>Роль (якщо вибрано "За роллю")</span>
                    <select id="admin-campaign-target-role" name="target_role">
                        <option value="" disabled selected>Оберіть роль</option>
                        ' . $roleOptions . '
                    </select>
                </label>
            </div>
            <div class="admin-notify-row admin-notify-row--single">
                <label class="admin-notify-field" data-target-field="user_ids"><span>ID користувачів (якщо вибрано "За списком ID")</span>
                    <textarea name="target_user_ids" rows="3" placeholder="1,2,3"></textarea>
                </label>
            </div>
            <div class="admin-notify-row admin-notify-row--single">
                <button type="submit" class="btn btn-primary admin-notify-submit">Запустити розсилку</button>
            </div>
        </form>
    </section>';

    $html .= '</div></div>';
    View_Add($html);
    View_Add('<script src="/assets/js/admin-panel.js"></script>');
}

View_Add('</div>');
View_Add('</div>');
View_Add(Page_Down());
View_Add('</main>');

View_Add('</div>'); // .layout

View_Out();
View_Clear();
