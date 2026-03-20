<?php
//phpinfo();
require_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/roles.php';
session_start();
$auth_lifetime = 1800;
$auth_cookie_name = 'user_auth';
$hide_page_down = false;
$account_not_found_notice = false;


$envFile = $_SERVER['DOCUMENT_ROOT'] . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue; 
        [$name, $value] = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

if (isset($_GET['exit']) && $_GET['exit'] == 1) {
    session_unset();
    session_destroy();

    if (isset($_COOKIE[$auth_cookie_name])) {
        setcookie($auth_cookie_name, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        unset($_COOKIE[$auth_cookie_name]);
    }

    header("Location: /");
    exit;
}

if (!empty($_SESSION['account_not_found_notice'])) {
    $account_not_found_notice = true;
    unset($_SESSION['account_not_found_notice']);
}

if ((!isset($_SESSION['logged']) || $_SESSION['logged'] != 1) && isset($_COOKIE[$auth_cookie_name])) {
    $cookieUserId = (int)$_COOKIE[$auth_cookie_name];
    $accountExists = false;

    if ($cookieUserId !== 0) {
        $dblink = DbConnect();
        $sql = 'SELECT idx FROM users WHERE idx = ' . $cookieUserId . ' LIMIT 1';
        $res = mysqli_query($dblink, $sql);
        $accountExists = $res && mysqli_num_rows($res) === 1;
    }

    if ($accountExists) {
        $_SESSION['logged'] = 1;
        $_SESSION['uzver'] = $cookieUserId;

        // Створюємо сесію в базі даних, якщо її немає
        if (function_exists('createUserSession')) {
            $sessionId = session_id();
            $existingSession = getUserSessions($cookieUserId);
            $sessionExists = false;
            foreach ($existingSession as $sess) {
                if ($sess['session_id'] == $sessionId) {
                    $sessionExists = true;
                    break;
                }
            }
            if (!$sessionExists) {
                $ip = $_SERVER['REMOTE_ADDR'];
                $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                createUserSession($cookieUserId, $sessionId, $ip, $userAgent);
            }
        }
    } else {
        $_SESSION['account_not_found_notice'] = 1;
        $account_not_found_notice = true;

        setcookie($auth_cookie_name, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        unset($_COOKIE[$auth_cookie_name]);
    }
}

if (isset($_SESSION['logged']) && (int)$_SESSION['logged'] === 1) {
    $currentUserId = (int)($_SESSION['uzver'] ?? 0);
    $accountExists = false;

    if ($currentUserId !== 0) {
        $dblink = DbConnect();
        $sql = 'SELECT idx FROM users WHERE idx = ' . $currentUserId . ' LIMIT 1';
        $res = mysqli_query($dblink, $sql);
        $accountExists = $res && mysqli_num_rows($res) === 1;
    }

    if (!$accountExists) {
        $_SESSION['account_not_found_notice'] = 1;
        $account_not_found_notice = true;

        unset($_SESSION['logged'], $_SESSION['uzver'], $_SESSION['status'], $_SESSION['last_activity']);

        if (isset($_COOKIE[$auth_cookie_name])) {
            setcookie($auth_cookie_name, '', [
                'expires' => time() - 3600,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            unset($_COOKIE[$auth_cookie_name]);
        }
    }
}

if (isset($_SESSION['logged']) && $_SESSION['logged'] == 1) {
    setcookie($auth_cookie_name, $_SESSION['uzver'], [
        'expires' => time() + $auth_lifetime,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_SESSION['last_activity'] = time();
    
    // Перевіряємо та створюємо сесію, якщо її немає
    if (function_exists('createUserSession') && function_exists('getUserSessions')) {
        $sessionId = session_id();
        $userId = (int)$_SESSION['uzver'];
        $sessions = getUserSessions($userId);
        $sessionExists = false;
        foreach ($sessions as $sess) {
            if ($sess['session_id'] == $sessionId) {
                $sessionExists = true;
                break;
            }
        }
        
        if (!$sessionExists) {
            // Створюємо сесію, якщо її немає
            $ip = $_SERVER['REMOTE_ADDR'];
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            createUserSession($userId, $sessionId, $ip, $userAgent);
        } else {
            // Оновлюємо активність сесії в базі даних
            if (function_exists('updateSessionActivity')) {
                updateSessionActivity($sessionId);
            }
        }
    }
}

const xbr = "\n";
const no_grave_photo = '/graves/no_image_0.png';
$buf = '';
$dblink = null;
$start = getmicrotime();
$md = isset($_GET['md']) ? $_GET['md'] : '';
$md = isset($_POST['md']) ? $_POST['md'] : $md;
$md = strtolower($md);
$ip = $_SERVER['REMOTE_ADDR'];
$login = '';
$password = '';
$loginix = 0;
$today = date('d-m-Y', time());
$todaysql = date('Y-m-d', time());
$todaydot = date('d.m.Y', time());
$yestertodaydot = date('d.m.Y', time() - 86400);



/*Для обработки выхода с каждой страницы*/
if (isset($_GET['exit']) && $_GET['exit'] == 1) {
    session_destroy();
    header("Location: /index.php");
    exit;
}

function rus2lat($string)
{
    $rus = array('ё', 'ж', 'ц', 'ч', 'ш', 'щ', 'ю', 'я', 'Ё', 'Ж', 'Ц', 'Ч', 'Ш', 'Щ', 'Ю', 'Я', 'Ъ', 'Ь', 'ъ', 'ь');
    $lat = array('e', 'zh', 'c', 'ch', 'sh', 'sh', 'ju', 'ja', 'E', 'ZH', 'C', 'CH', 'SH', 'SH', 'JU', 'JA', '', '', '', '');
    $string = str_replace($rus, $lat, $string);
    return strtr($string, "АБВГДЕЗИЙКЛМНОПРСТУФХЫЭабвгдезийклмнопрстуфхыэ ", "ABVGDEZIJKLMNOPRSTUFHIEabvgdezijklmnoprstufhie_");
}

function getmicrotime()
{
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
}

function warn($x = ''): string
{
    return '<div class="warn">' . $x . '</div>';
}

/**Створює підключення до бази даних
 * @return int|null|bool|mysqli
 */
function DbConnect(): mysqli|bool|null
{
    if (!class_exists('Dotenv\Dotenv')) {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';
    }


    // Загружаем .env
    if (!isset($_ENV['DB_HOST'])) {
        $dotenv = Dotenv::createImmutable($_SERVER['DOCUMENT_ROOT']);
        $dotenv->load();
    }

    $dbhost = $_ENV['DB_HOST'] ?? 'localhost';
    $dbuser = $_ENV['DB_USER'] ?? '';
    $dbpass = $_ENV['DB_PASS'] ?? '';
    $dbname = $_ENV['DB_NAME'] ?? '';

    $dblink = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

    if (!$dblink) {
        die('Ошибка подключения к базе данных: ' . mysqli_connect_error());
    }

    mysqli_query($dblink, "SET NAMES 'utf8'");
    mysqli_query($dblink, "SET CHARACTER SET 'utf8'");
    mysqli_query($dblink, "SET SESSION collation_connection = 'utf8_general_ci'");

    return $dblink;
}

// Функції для роботи з сесіями
function createUserSession($userId, $sessionId, $ip, $userAgent, $deviceInfo = null): bool
{
    $dblink = DbConnect();
    
    // Створюємо таблицю якщо її немає
    $createTable = "CREATE TABLE IF NOT EXISTS user_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        session_id VARCHAR(255) NOT NULL UNIQUE,
        ip_address VARCHAR(45) NOT NULL,
        user_agent TEXT,
        device_name VARCHAR(255),
        device_type VARCHAR(50),
        location VARCHAR(255),
        last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        is_current TINYINT(1) DEFAULT 0,
        INDEX idx_user_id (user_id),
        INDEX idx_session_id (session_id),
        INDEX idx_last_activity (last_activity)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    mysqli_query($dblink, $createTable);
    
    // Визначаємо тип пристрою
    if (!$deviceInfo) {
        $deviceInfo = detectDevice($userAgent);
    }
    
    // Визначаємо локацію (спрощено, можна використати API)
    $location = getLocationFromIP($ip);
    
    // Встановлюємо is_current=0 для всіх інших сесій цього користувача
    mysqli_query($dblink, "UPDATE user_sessions SET is_current = 0 WHERE user_id = " . (int)$userId);
    
    // Додаємо нову сесію
    $sessionIdEscaped = mysqli_real_escape_string($dblink, $sessionId);
    $ipEscaped = mysqli_real_escape_string($dblink, $ip);
    $userAgentEscaped = mysqli_real_escape_string($dblink, $userAgent);
    $deviceNameEscaped = mysqli_real_escape_string($dblink, $deviceInfo['name']);
    $deviceTypeEscaped = mysqli_real_escape_string($dblink, $deviceInfo['type']);
    $locationEscaped = mysqli_real_escape_string($dblink, $location);
    
    $sql = "INSERT INTO user_sessions (user_id, session_id, ip_address, user_agent, device_name, device_type, location, is_current) 
            VALUES (" . (int)$userId . ", '$sessionIdEscaped', '$ipEscaped', '$userAgentEscaped', '$deviceNameEscaped', '$deviceTypeEscaped', '$locationEscaped', 1)
            ON DUPLICATE KEY UPDATE 
            last_activity = CURRENT_TIMESTAMP,
            is_current = 1";
    
    return mysqli_query($dblink, $sql);
}

function updateSessionActivity($sessionId): bool
{
    $dblink = DbConnect();
    $sessionIdEscaped = mysqli_real_escape_string($dblink, $sessionId);
    $sql = "UPDATE user_sessions SET last_activity = CURRENT_TIMESTAMP WHERE session_id = '$sessionIdEscaped'";
    return mysqli_query($dblink, $sql);
}

function getUserSessions($userId): array
{
    $dblink = DbConnect();
    $sql = "SELECT * FROM user_sessions WHERE user_id = " . (int)$userId . " ORDER BY is_current DESC, last_activity DESC";
    $res = mysqli_query($dblink, $sql);
    
    $sessions = [];
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $sessions[] = $row;
        }
    }
    
    return $sessions;
}

function deleteSession($sessionId, $userId): bool
{
    $dblink = DbConnect();
    $sessionIdEscaped = mysqli_real_escape_string($dblink, $sessionId);
    $sql = "DELETE FROM user_sessions WHERE session_id = '$sessionIdEscaped' AND user_id = " . (int)$userId;
    return mysqli_query($dblink, $sql);
}

function deleteAllUserSessions($userId, $exceptSessionId = null): bool
{
    $dblink = DbConnect();
    $sql = "DELETE FROM user_sessions WHERE user_id = " . (int)$userId;
    if ($exceptSessionId) {
        $exceptSessionIdEscaped = mysqli_real_escape_string($dblink, $exceptSessionId);
        $sql .= " AND session_id != '$exceptSessionIdEscaped'";
    }
    return mysqli_query($dblink, $sql);
}

function notificationsTableExists($dblink): bool
{
    if (!$dblink) {
        return false;
    }
    $res = mysqli_query($dblink, "SHOW TABLES LIKE 'user_notifications'");
    return $res && mysqli_num_rows($res) > 0;
}

function getUnreadNotificationCount(int $userId): int
{
    $userId = (int)$userId;
    if ($userId <= 0) {
        return 0;
    }

    $dblink = DbConnect();
    if (!$dblink || !notificationsTableExists($dblink)) {
        return 0;
    }

    $res = mysqli_query(
        $dblink,
        "SELECT COUNT(*) AS cnt FROM user_notifications WHERE user_id = " . $userId . " AND status = 'unread'"
    );
    if ($res && ($row = mysqli_fetch_assoc($res))) {
        return (int)$row['cnt'];
    }
    return 0;
}

function normalizeNotificationCategory(string $category): string
{
    $allowed = ['system', 'account', 'moderation', 'wallet', 'manual', 'campaign'];
    return in_array($category, $allowed, true) ? $category : 'system';
}

function normalizeNotificationPriority(string $priority): string
{
    $allowed = ['low', 'normal', 'high'];
    return in_array($priority, $allowed, true) ? $priority : 'normal';
}

function createUserNotification(
    int $userId,
    string $title,
    string $body,
    string $category = 'system',
    string $priority = 'normal',
    ?string $actionUrl = null,
    ?string $actionLabel = null,
    ?string $sourceType = null,
    $sourceId = null,
    $senderUserId = null,
    ?string $senderRole = null,
    $campaignId = null,
    int $isSystem = 1,
    $dblink = null
): int {
    $userId = (int)$userId;
    if ($userId <= 0) {
        return 0;
    }

    if (!$dblink) {
        $dblink = DbConnect();
    }
    if (!$dblink || !notificationsTableExists($dblink)) {
        return 0;
    }

    $title = trim($title);
    $body = trim($body);
    if ($title === '' || $body === '') {
        return 0;
    }

    $category = normalizeNotificationCategory($category);
    $priority = normalizeNotificationPriority($priority);
    $isSystem = $isSystem ? 1 : 0;

    $sourceIdVal = ($sourceId === null || $sourceId === '') ? null : (int)$sourceId;
    $senderUserIdVal = ($senderUserId === null || $senderUserId === '') ? null : (int)$senderUserId;
    $campaignIdVal = ($campaignId === null || $campaignId === '') ? null : (int)$campaignId;

    $stmt = mysqli_prepare(
        $dblink,
        "INSERT INTO user_notifications
            (user_id, category, priority, title, body, action_url, action_label, source_type, source_id, sender_user_id, sender_role, campaign_id, is_system)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt) {
        return 0;
    }

    mysqli_stmt_bind_param(
        $stmt,
        "isssssssiisii",
        $userId,
        $category,
        $priority,
        $title,
        $body,
        $actionUrl,
        $actionLabel,
        $sourceType,
        $sourceIdVal,
        $senderUserIdVal,
        $senderRole,
        $campaignIdVal,
        $isSystem
    );

    $ok = mysqli_stmt_execute($stmt);
    $newId = $ok ? (int)mysqli_insert_id($dblink) : 0;
    mysqli_stmt_close($stmt);
    return $newId;
}

function createNotificationCampaign(
    $createdByUserId,
    string $title,
    string $body,
    string $category = 'campaign',
    string $priority = 'normal',
    string $targetType = 'all',
    ?string $targetPayload = null,
    int $totalRecipients = 0,
    $dblink = null
): int {
    if (!$dblink) {
        $dblink = DbConnect();
    }
    if (!$dblink) {
        return 0;
    }

    $title = trim($title);
    $body = trim($body);
    if ($title === '' || $body === '') {
        return 0;
    }

    $category = in_array($category, ['manual', 'campaign', 'system'], true) ? $category : 'campaign';
    $priority = normalizeNotificationPriority($priority);
    $targetType = in_array($targetType, ['all', 'role', 'user_ids'], true) ? $targetType : 'all';
    $creatorIdVal = ($createdByUserId === null || $createdByUserId === '') ? null : (int)$createdByUserId;
    $totalRecipients = max(0, (int)$totalRecipients);

    $stmt = mysqli_prepare(
        $dblink,
        "INSERT INTO notification_campaigns
            (created_by_user_id, channel, title, body, category, priority, target_type, target_payload, total_recipients)
         VALUES (?, 'internal', ?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt) {
        return 0;
    }

    mysqli_stmt_bind_param(
        $stmt,
        "issssssi",
        $creatorIdVal,
        $title,
        $body,
        $category,
        $priority,
        $targetType,
        $targetPayload,
        $totalRecipients
    );

    $ok = mysqli_stmt_execute($stmt);
    $newId = $ok ? (int)mysqli_insert_id($dblink) : 0;
    mysqli_stmt_close($stmt);
    return $newId;
}

function addWalletTransaction(
    int $walletId,
    string $direction,
    float $amount,
    string $currency,
    ?string $title = null,
    ?string $meta = null,
    ?string $sourceType = null,
    $sourceId = null,
    $dblink = null
): int {
    $walletId = (int)$walletId;
    if ($walletId <= 0) {
        return 0;
    }
    $direction = in_array($direction, ['in', 'out'], true) ? $direction : 'in';
    $currency = in_array($currency, ['UAH', 'INTERNAL'], true) ? $currency : 'INTERNAL';
    $amount = (float)$amount;

    if (!$dblink) {
        $dblink = DbConnect();
    }
    if (!$dblink) {
        return 0;
    }

    $stmt = mysqli_prepare(
        $dblink,
        "INSERT INTO wallet_transactions (wallet_id, direction, amount, currency, title, meta, source_type, source_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt) {
        return 0;
    }

    $titleVal = $title !== null ? trim($title) : null;
    $metaVal = $meta !== null ? trim($meta) : null;
    $sourceIdVal = ($sourceId === null || $sourceId === '') ? null : (int)$sourceId;

    mysqli_stmt_bind_param(
        $stmt,
        "isdssssi",
        $walletId,
        $direction,
        $amount,
        $currency,
        $titleVal,
        $metaVal,
        $sourceType,
        $sourceIdVal
    );

    $ok = mysqli_stmt_execute($stmt);
    $txId = $ok ? (int)mysqli_insert_id($dblink) : 0;
    mysqli_stmt_close($stmt);

    if ($txId > 0 && $currency === 'INTERNAL' && $direction === 'in' && function_exists('createUserNotification')) {
        $userId = 0;
        $res = mysqli_query($dblink, "SELECT user_id FROM wallets WHERE id = " . $walletId . " LIMIT 1");
        if ($res && ($row = mysqli_fetch_assoc($res))) {
            $userId = (int)($row['user_id'] ?? 0);
        }

        if ($userId > 0) {
            $amountLabel = number_format($amount, 0, '.', ' ');
            createUserNotification(
                $userId,
                'Надходження внутрішньої валюти',
                'На ваш рахунок зараховано ' . $amountLabel . ' внутрішньої валюти.',
                'wallet',
                'normal',
                '/profile.php?md=4',
                'Перейти до гаманця',
                'wallet_transaction',
                $txId,
                null,
                null,
                null,
                1,
                $dblink
            );
        }
    }

    return $txId;
}

function ensureUserWalletWithWelcomeBonus(int $userId, float $amount = 500.0, $dblink = null): bool
{
    $userId = (int)$userId;
    if ($userId <= 0) {
        return false;
    }

    if (!$dblink) {
        $dblink = DbConnect();
    }
    if (!$dblink) {
        return false;
    }

    $amount = (float)$amount;
    if ($amount <= 0) {
        return false;
    }

    // Wallet table existence check without requiring SHOW TABLES privilege.
    $walletProbe = @mysqli_query(
        $dblink,
        "SELECT id, internal_balance FROM wallets WHERE user_id = " . $userId . " LIMIT 1"
    );
    if ($walletProbe === false) {
        return false;
    }

    $walletRow = mysqli_fetch_assoc($walletProbe);
    $walletId = $walletRow ? (int)($walletRow['id'] ?? 0) : 0;

    // wallet_transactions table existence check (may be missing on some installs).
    $txTableExists = (@mysqli_query($dblink, "SELECT 1 FROM wallet_transactions LIMIT 1") !== false);

    // If wallet exists, grant bonus only if it has not been granted yet.
    if ($walletId > 0) {
        if ($txTableExists) {
            $bonusRes = @mysqli_query(
                $dblink,
                "SELECT 1 FROM wallet_transactions
                 WHERE wallet_id = " . $walletId . " AND source_type = 'welcome_bonus'
                 LIMIT 1"
            );
            if ($bonusRes && mysqli_fetch_assoc($bonusRes)) {
                return false;
            }

            // If the wallet already has at least the welcome amount and no transactions at all,
            // assume the bonus is already included in the starting balance (legacy installs).
            $currentBalance = (float)($walletRow['internal_balance'] ?? 0);
            if ($currentBalance >= $amount) {
                $anyTxRes = @mysqli_query(
                    $dblink,
                    "SELECT 1 FROM wallet_transactions WHERE wallet_id = " . $walletId . " LIMIT 1"
                );
                if ($anyTxRes && !mysqli_fetch_assoc($anyTxRes)) {
                    return false;
                }
            }
        } else {
            // Best-effort idempotency when transactions table is absent.
            $currentBalance = (float)($walletRow['internal_balance'] ?? 0);
            if ($currentBalance >= $amount) {
                return false;
            }
        }

        $stmtUpdate = mysqli_prepare(
            $dblink,
            "UPDATE wallets SET internal_balance = COALESCE(internal_balance, 0) + ? WHERE id = ? LIMIT 1"
        );
        if (!$stmtUpdate) {
            return false;
        }

        mysqli_stmt_bind_param($stmtUpdate, "di", $amount, $walletId);
        $okUpdate = mysqli_stmt_execute($stmtUpdate);
        mysqli_stmt_close($stmtUpdate);

        if (!$okUpdate) {
            return false;
        }

        if ($txTableExists && function_exists('addWalletTransaction')) {
            addWalletTransaction(
                $walletId,
                'in',
                $amount,
                'INTERNAL',
                'Бонус за реєстрацію',
                'Стартовий бонус',
                'welcome_bonus',
                $userId,
                $dblink
            );
        } elseif (function_exists('createUserNotification')) {
            $amountLabel = number_format($amount, 0, '.', ' ');
            createUserNotification(
                $userId,
                'Бонус за реєстрацію',
                'На ваш рахунок зараховано ' . $amountLabel . ' внутрішньої валюти.',
                'wallet',
                'normal',
                '/profile.php?md=4',
                'Перейти до гаманця',
                'welcome_bonus',
                null,
                null,
                null,
                null,
                1,
                $dblink
            );
        }

        return true;
    }

    // Wallet does not exist: create it with the bonus as starting internal balance.
    $stmtWallet = mysqli_prepare(
        $dblink,
        "INSERT INTO wallets (user_id, internal_balance) VALUES (?, ?)"
    );
    if (!$stmtWallet) {
        return false;
    }

    mysqli_stmt_bind_param($stmtWallet, "id", $userId, $amount);
    $okWallet = mysqli_stmt_execute($stmtWallet);
    $newWalletId = $okWallet ? (int)mysqli_insert_id($dblink) : 0;
    mysqli_stmt_close($stmtWallet);

    if (!$okWallet || $newWalletId <= 0) {
        return false;
    }

    if ($txTableExists && function_exists('addWalletTransaction')) {
        addWalletTransaction(
            $newWalletId,
            'in',
            $amount,
            'INTERNAL',
            'Бонус за реєстрацію',
            'Стартовий бонус',
            'welcome_bonus',
            $userId,
            $dblink
        );
        return true;
    }

    if (function_exists('createUserNotification')) {
        $amountLabel = number_format($amount, 0, '.', ' ');
        createUserNotification(
            $userId,
            'Бонус за реєстрацію',
            'На ваш рахунок зараховано ' . $amountLabel . ' внутрішньої валюти.',
            'wallet',
            'normal',
            '/profile.php?md=4',
            'Перейти до гаманця',
            'welcome_bonus',
            null,
            null,
            null,
            null,
            1,
            $dblink
        );
    }

    return true;
}

function detectDevice($userAgent): array
{
    $device = [
        'name' => 'Невідомий пристрій',
        'type' => 'desktop'
    ];
    
    if (preg_match('/iPhone|iPod/i', $userAgent)) {
        $device['type'] = 'mobile';
        if (preg_match('/iPhone (\d+)/i', $userAgent, $matches)) {
            $device['name'] = 'iPhone ' . ($matches[1] ?? '');
        } else {
            $device['name'] = 'iPhone';
        }
    } elseif (preg_match('/iPad/i', $userAgent)) {
        $device['type'] = 'tablet';
        $device['name'] = 'iPad';
    } elseif (preg_match('/Android/i', $userAgent)) {
        $device['type'] = 'mobile';
        if (preg_match('/Android.*?; (.*?)\)/i', $userAgent, $matches)) {
            $device['name'] = trim($matches[1]) . ' (Android)';
        } else {
            $device['name'] = 'Android пристрій';
        }
    } elseif (preg_match('/Macintosh|Mac OS/i', $userAgent)) {
        $device['type'] = 'desktop';
        if (preg_match('/Mac OS X (\d+[._]\d+)/i', $userAgent, $matches)) {
            $device['name'] = 'MacBook';
        } else {
            $device['name'] = 'Mac';
        }
    } elseif (preg_match('/Windows/i', $userAgent)) {
        $device['type'] = 'desktop';
        $device['name'] = 'Windows PC';
    } elseif (preg_match('/Linux/i', $userAgent)) {
        $device['type'] = 'desktop';
        $device['name'] = 'Linux PC';
    }
    
    return $device;
}

function getLocationFromIP($ip): string
{
    // Спрощена версія - можна використати API для визначення локації
    // Наприклад, ipapi.co, ip-api.com тощо
    // Поки що повертаємо базову інформацію
    return 'Україна';
}

function formatTimeAgo($timestamp): string
{
    $now = time();
    $diff = $now - strtotime($timestamp);
    
    if ($diff < 60) {
        return 'Зараз';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' ' . ($minutes == 1 ? 'хвилину' : ($minutes < 5 ? 'хвилини' : 'хвилин')) . ' тому';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' ' . ($hours == 1 ? 'годину' : ($hours < 5 ? 'години' : 'годин')) . ' тому';
    } elseif ($diff < 2592000) {
        $days = floor($diff / 86400);
        return $days . ' ' . ($days == 1 ? 'день' : ($days < 5 ? 'дні' : 'днів')) . ' тому';
    } else {
        $months = ['січ', 'лют', 'бер', 'квіт', 'трав', 'черв', 'лип', 'серп', 'вер', 'жовт', 'лист', 'груд'];
        $date = date('j', strtotime($timestamp));
        $month = $months[date('n', strtotime($timestamp)) - 1];
        $year = date('Y', strtotime($timestamp));
        return $date . ' ' . $month . ' ' . $year;
    }
}

function View_Out()
{global $start;
/*
    $end = getmicrotime();
    $elapsed = $end - $start;

    $seconds = floor($elapsed);
    $milliseconds = round(($elapsed - $seconds) * 1000);

    echo '<div class="execution-time">';
    echo 'Сторінку завантажено за: ' . $seconds . ' сек ' . $milliseconds . ' мс';
    echo '</div>';
*/
    echo View_Get();
    View_Clear();
}

function NormalizePublicPath(string $path): string
{
    $path = trim($path);
    if ($path === '' || $path === '/') {
        return '/';
    }

    $path = '/' . ltrim($path, '/');
    $path = preg_replace('~/+~', '/', $path) ?? $path;

    if (preg_match('~^/index(?:\.php)?$~i', $path)) {
        return '/';
    }

    $path = preg_replace('~\.php$~i', '', $path) ?? $path;
    $path = rtrim($path, '/');

    return $path === '' ? '/' : $path;
}

function PublicUrl(string $url): string
{
    $url = trim($url);
    if ($url === '' || $url === '#') {
        return $url;
    }

    if (preg_match('~^(?:[a-z][a-z0-9+.-]*:|//|mailto:|tel:|javascript:|data:)~i', $url)) {
        return $url;
    }

    $fragment = '';
    $fragmentPos = strpos($url, '#');
    if ($fragmentPos !== false) {
        $fragment = substr($url, $fragmentPos);
        $url = substr($url, 0, $fragmentPos);
    }

    $query = '';
    $queryPos = strpos($url, '?');
    if ($queryPos !== false) {
        $query = substr($url, $queryPos + 1);
        $url = substr($url, 0, $queryPos);
    }

    $normalizedPath = NormalizePublicPath($url === '' ? '/' : $url);

    if ($query !== '') {
        $query = preg_replace_callback(
            '~(?<=^|[=&])(/?[A-Za-z0-9_-]+(?:/[A-Za-z0-9_-]+)*\.php)(?=$|[&#&])~i',
            static function (array $matches): string {
                return NormalizePublicPath($matches[1]);
            },
            $query
        ) ?? $query;

        return $normalizedPath . '?' . $query . $fragment;
    }

    return $normalizedPath . $fragment;
}

function CurrentPublicRequestPath(): string
{
    $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '/');
    $requestPath = (string)(parse_url($requestUri, PHP_URL_PATH) ?? '/');

    return NormalizePublicPath($requestPath);
}

function NormalizePublicMarkup(string $markup): string
{
    if ($markup === '' || stripos($markup, '.php') === false) {
        return $markup;
    }

    $pattern = '~(?<=[\'"`])(/?[A-Za-z0-9_-]+(?:/[A-Za-z0-9_-]+)*\.php(?:\?[^\'"`<>\s)]*)?(?:#[^\'"`<>\s)]*)?)~i';
    $normalized = preg_replace_callback(
        $pattern,
        static function (array $matches): string {
            return PublicUrl($matches[1]);
        },
        $markup
    );

    return $normalized ?? $markup;
}

function View_Get(): string
{
    global $buf;
    return NormalizePublicMarkup($buf);
}

/**Підготовлює буфер сторінки для нового виводу
 */
function View_Clear()
{
    global $buf;
    $buf = '';
}

function View_Add(null|string $a = '')
{
    global $buf;
    $buf .= $a;
}


/**Функція виводу системних повідомлень
 */
function showMessage(): void
{
    if (empty($_SESSION['message'])) {
        return;
    }

    $text = htmlspecialchars($_SESSION['message'], ENT_QUOTES, 'UTF-8');
    $messageType = $_SESSION['messageType'] ?? 'success';
    unset($_SESSION['message'], $_SESSION['messageType']);

    // Определяем класс типа уведомления
    $typeClass = ($messageType === 'success' || $messageType === 'alert-success') ? 'notification-success' : 'notification-error';
    
    // SVG иконка для success
    $successIcon = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M20 6L9 17L4 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    
    // SVG иконка для error
    $errorIcon = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    
    $icon = ($messageType === 'success' || $messageType === 'alert-success') ? $successIcon : $errorIcon;

    echo '
    <div id="global-alert" class="notification ' . $typeClass . '">
        <div class="notification-content">
            <span class="notification-icon">' . $icon . '</span>
            <span class="notification-message">' . $text . '</span>
        </div>
    </div>

    <script>
    (function () {
        const alertBox = document.getElementById("global-alert");
        if (!alertBox) return;

        setTimeout(() => {
            requestAnimationFrame(() => {
                alertBox.classList.add("show");
            });
        }, 1);

        const hideTimeout = setTimeout(hideAlert, 3000); 
        
        function hideAlert() {
            alertBox.classList.remove("show");
            setTimeout(() => alertBox.remove(), 300);
        }
    })();
    </script>
    ';
}


function Menu_Up_Old(): string {
    $isLogged = isset($_SESSION['logged']) && $_SESSION['logged'] == 1;
    $isOnProfilePage = (CurrentPublicRequestPath() === NormalizePublicPath('/profile.php'));
    $mobileAuthText = $isLogged ? ($isOnProfilePage ? 'Меню профілю' : 'Профіль') : 'Увійти';
    $mobileAuthHref = $isLogged ? PublicUrl('/profile.php') : PublicUrl('/auth.php');
    
    // Получаем данные пользователя для блока профиля
    $userFullName = '';
    if ($isLogged) {
        $dblink = DbConnect();
        $sql = 'SELECT fname, lname FROM users WHERE idx = ' . intval($_SESSION['uzver']);
        $res = mysqli_query($dblink, $sql);
        if ($res && $user = mysqli_fetch_assoc($res)) {
            $userFullName = trim($user['fname'] . ' ' . $user['lname']);
        }
    }

    $out = '<div class="Menu_Up">';

    $out .= '
    <div class="mobile-menu-topbar">
        <a href="/" class="mobile-menu-brand">Shana</a>
        <div class="mobile-menu-actions">
            ' . ($isLogged && $isOnProfilePage
                ? '<button class="mobile-menu-auth mobile-open-profile-btn" type="button">' . $mobileAuthText . '</button>'
                : '<a class="mobile-menu-auth" href="' . $mobileAuthHref . '">' . $mobileAuthText . '</a>'
            ) . '

            <div class="dropdown nav mobile-burger">
                <input type="checkbox" id="menu-main" class="dropdown-toggle">
                <label for="menu-main" class="menu-button nav" data-tooltip="Меню" aria-label="Меню">
                    <div class="burger">
                      <span></span>
                      <span></span>
                      <span></span>
                    </div>
                </label>

                <div class="dropdown-menu nav">
                    <div class="mobile-menu-header">
                        <span class="menu-name">Меню</span>
                        <label for="menu-main" class="mobile-menu-close-btn" aria-label="Закрити меню">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </label>
                    </div>
                    <ul class="menu_ups_mobile">
                        <li><a href="/">Головна</a></li>
                        <li class="has-submenu">
                            <input type="checkbox" id="mobile-work-toggle" class="submenu-toggle">
                            <label for="mobile-work-toggle" class="submenu-label">Робота</label>
                            <ul class="submenu-mobile">
                                <li><a href="/clean-cemeteries.php">Прибирання кладовищ</a></li>
                                <li><a href="/in-dev.php?from=/prod-monuments.php">Виготовлення пам`ятників</a></li>
                                <li><a href="/in-dev.php?from=/other-job.php">Інші роботи</a></li>
                            </ul>
                        </li>
                        <li><a href="/in-dev.php?from=/church.php">Церкви</a></li>
                        <li><a href="/in-dev.php?from=/clients.php">Наші клієнти</a></li>
                        <li><a href="/graveaddform.php">Додати поховання</a></li>
                    </ul>
                    <hr class="mobile-menu-divider">
                    <div class="mobile-menu-blocks' . ($isLogged ? '' : ' mobile-menu-blocks-single') . '">
                        ' . ($isLogged ? '<a href="/messenger.php" class="mobile-menu-block">
                            <svg class="mobile-menu-block-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                            <span>Месенджер</span>
                        </a>' : '') . '
                        <a href="/messenger.php?type=3" class="mobile-menu-block">
                            <svg class="mobile-menu-block-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 18v-6a9 9 0 0 1 18 0v6"/><path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"/></svg>
                            <span>Технічна підтримка</span>
                        </a>
                    </div>';


    // Нижний блок аккаунта в бургер-меню
  /*  if ($isLogged && $userFullName) {
        $out .= '
                    <div class="mobile-account-panel">
                        <div class="mobile-account-title">Мій профіль</div>
                        <div class="container-info-block">
                        <a href="/profile.php" class="mobile-account-name-link">' . htmlspecialchars($userFullName, ENT_QUOTES, 'UTF-8') . '</a>
                        <a href="/profile.php?exit=1" class="mobile-account-logout btn">Вийти</a>
                        </div>
                    </div>';
    } else {
        $out .= '
                    <div class="mobile-account-panel">
                        <a href="/auth.php" class="mobile-account-login">Увійти</a>
                    </div>';
    }
*/
    $out .= '
                </div>
            </div>
        </div>
    </div>
    ';

    $out .= '<div class="menu-left">';

    $out .= '<div class="logo"><a href="/"><img src="/assets/images/logobrand3.png" alt="Логотип"></a></div>';
    $out .= '<a href="/" class="title">ІПС Шана</a>';

    $out .= '</div>'; // menu-left

    $out .= '<div class="menu-divider"></div>';

    // Центральное меню
    $out .= '<div class="menu-center">';
    $out .= '<ul class="menu_ups"> 
        <li><a href="/">Головна</a></li>
        <li><a>Послуги</a> 
            <ul class="submenu"> 
                <li><a href="/clean-cemeteries.php">Прибирання кладовищ</a></li>
                <li><a href="/in-dev.php?from=/prod-monuments.php">Виготовлення пам\'ятників</a></li>
                <li><a href="/in-dev.php?from=/other-job.php">Інші роботи</a></li>
            </ul>
        </li>
        <li><a href="/in-dev.php?from=/church.php">Церкви</a></li>
        <li><a href="/in-dev.php?from=/clients.php">Наші клієнти</a></li>
        <li><a href="/graveaddform.php">Додати поховання</a></li>
        </ul>';
    $out .= '</div>';

    // Правая часть — вход / аватар
    if (isset($_SESSION['logged']) && $_SESSION['logged'] == 1) {
        $dblink = DbConnect();
        $sql = 'SELECT avatar, cash, fname, lname FROM users WHERE idx = ' . intval($_SESSION['uzver']);
        $res = mysqli_query($dblink, $sql);
        if ($res && $user = mysqli_fetch_assoc($res)) {
            $avatar = ($user['avatar'] != '') ? $user['avatar'] : '/avatars/ava.png';
            $formattedCash = number_format($user['cash'], 0, '', '.');
            $firstName = $user['fname'];
            $lastName = $user['lname'];
            $lastNameShort = mb_substr($lastName, 0, 1) . '.';
            $fullname = ($firstName . ' ' . $lastName);
        }


        $out .= '<div class="header-right">';

        if (isset($_SESSION['status']) && hasRole($_SESSION['status'], ROLE_CREATOR)) {
            $out .= '
   <a href="/admin-panel.php" class="menu-button" data-tooltip="Адмін-панель">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-user-cog">
            <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
            <path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" />
            <path d="M6 21v-2a4 4 0 0 1 4 -4h2.5" />
            <path d="M17.001 19a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
            <path d="M19.001 15.5v1.5" />
            <path d="M19.001 21v1.5" />
            <path d="M22.032 17.25l-1.299 .75" />
            <path d="M17.27 20l-1.3 .75" />
            <path d="M15.97 17.25l1.3 .75" />
            <path d="M20.733 20l1.3 .75" />
        </svg>
    </a>';
        }

        $out .= '
<a href="/messenger.php" class="menu-button" data-tooltip="Чати">
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 13.5997 2.37562 15.1116 3.04346 16.4525C3.22094 16.8088 3.28001 17.2161 3.17712 17.6006L2.58151 19.8267C2.32295 20.793 3.20701 21.677 4.17335 21.4185L6.39939 20.8229C6.78393 20.72 7.19121 20.7791 7.54753 20.9565C8.88837 21.6244 10.4003 22 12 22Z" fill="#000000"/>
        <path d="M15 12C15 12.5523 15.4477 13 16 13C16.5523 13 17 12.5523 17 12C17 11.4477 16.5523 11 16 11C15.4477 11 15 11.4477 15 12Z" fill="white"/>
        <path d="M11 12C11 12.5523 11.4477 13 12 13C12.5523 13 13 12.5523 13 12C13 11.4477 12.5523 11 12 11C11.4477 11 11 11.4477 11 12Z" fill="white"/>
        <path d="M7 12C7 12.5523 7.44772 13 8 13C8.55228 13 9 12.5523 9 12C9 11.4477 8.55228 11 8 11C7.44772 11 7 11.4477 7 12Z" fill="white"/>
    </svg>
</a>

<a href="/profile.php?md=0&tab=saved" class="menu-button" data-tooltip="Збережене">
    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
         class="bi bi-bookmark-fill" viewBox="0 0 16 16">
        <path d="M2 2v13.5a.5.5 0 0 0 .74.439L8 13.069l5.26 2.87A.5.5 0 0 0 14 15.5V2
        a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2"/>
    </svg>
</a>

<div class="dropdown" id="support-menu" style="position:absolute; visibility:hidden; pointer-events:none;">
    <input type="checkbox" id="menu-support" class="dropdown-toggle">
    <label for="menu-support" class="menu-button" data-tooltip="Підтримка" style="display:none;"></label>

    <div class="dropdown-menu">
        <div class="menu-friends">
    <svg id="open-support-arrow" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-left" viewBox="0 0 16 16" style="cursor:pointer;">
        <path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8"/>
    </svg>
    <span class="menu-name">Підтримка</span>
</div>

        <div class="menu-separator"></div>
        
        <a href="/messenger.php?type=3">
    <span class="icon-wrapper">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="dpms-icon bi bi-gear-fill" viewBox="0 0 16 16">
            <path d="M11 5a3 3 0 1 1-6 0 3 3 0 0 1 6 0m-9 8c0 1 1 1 1 1h5.256A4.5 4.5 0 0 1 8 12.5a4.5 4.5 0 0 1 1.544-3.393Q8.844 9.002 8 9c-5 0-6 3-6 4m9.886-3.54c.18-.613 1.048-.613 1.229 0l.043.148a.64.64 0 0 0 .921.382l.136-.074c.561-.306 1.175.308.87.869l-.075.136a.64.64 0 0 0 .382.92l.149.045c.612.18.612 1.048 0 1.229l-.15.043a.64.64 0 0 0-.38.921l.074.136c.305.561-.309 1.175-.87.87l-.136-.075a.64.64 0 0 0-.92.382l-.045.149c-.18.612-1.048.612-1.229 0l-.043-.15a.64.64 0 0 0-.921-.38l-.136.074c-.561.305-1.175-.309-.87-.87l.075-.136a.64.64 0 0 0-.382-.92l-.148-.045c-.613-.18-.613-1.048 0-1.229l.148-.043a.64.64 0 0 0 .382-.921l-.074-.136c-.306-.561.308-1.175.869-.87l.136.075a.64.64 0 0 0 .92-.382zM14 12.5a1.5 1.5 0 1 0-3 0 1.5 1.5 0 0 0 3 0"/>
        </svg>
    </span>
    Зв`язатися з розробником
</a>

        <a href="/messenger.php?type=3">
    <span class="icon-wrapper">
         <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="dpms-icon bi bi-gear-fill" viewBox="0 0 16 16">
            <path d="M.05 3.555A2 2 0 0 1 2 2h12a2 2 0 0 1 1.95 1.555L8 8.414zM0 4.697v7.104l5.803-3.558zM6.761 8.83l-6.57 4.026A2 2 0 0 0 2 14h6.256A4.5 4.5 0 0 1 8 12.5a4.49 4.49 0 0 1 1.606-3.446l-.367-.225L8 9.586zM16 4.697v4.974A4.5 4.5 0 0 0 12.5 8a4.5 4.5 0 0 0-1.965.45l-.338-.207z"/>
            <path d="M12.5 16a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7m.5-5v1.5a.5.5 0 0 1-1 0V11a.5.5 0 0 1 1 0m0 3a.5.5 0 1 1-1 0 .5.5 0 0 1 1 0"/>
        </svg>
    </span>
    Повідомити про проблему
</a>

    </div>
</div>

    <div class="dropdown">
        <input type="checkbox" id="menu-avatar" class="dropdown-toggle">
        <label for="menu-avatar" class="avatar-button" data-tooltip="Акаунт">
        <div class="avatar-wrapper">
            <img src="' . $avatar . '" alt="Аватар" class="header-avatar">
            <div class="avatar-arrow">
            <img src="/assets/images/avaarrow.png" alt="Стрелка">
             </div>
            </div>
        </label>

        <div class="dropdown-menu block">
            <div class="menu-profile" onclick="window.location.href=\'/profile.php\'">
                <img src="' . $avatar . '" class="menu-avatar">
                <span class="menu-name">' . $fullname . '</span>
            </div>
          
            <div class="menu-separator"></div>

<a href="/profile.php?md=2">
    <span class="icon-wrapper">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="dpm-icon bi bi-gear-fill" viewBox="0 0 16 16">
          <path d="M9.405 1.05c-.413-1.4-2.397-1.4-2.81 0l-.1.34a1.464 1.464 0 0 1-2.105.872l-.31-.17c-1.283-.698-2.686.705-1.987 1.987l.169.311c.446.82.023 1.841-.872 2.105l-.34.1c-1.4.413-1.4 2.397 0 2.81l.34.1a1.464 1.464 0 0 1 .872 2.105l-.17.31c-.698 1.283.705 2.686 1.987 1.987l.311-.169a1.464 1.464 0 0 1 2.105.872l.1.34c.413 1.4 2.397 1.4 2.81 0l.1-.34a1.464 1.464 0 0 1 2.105-.872l.31.17c1.283.698 2.686-.705 1.987-1.987l-.169-.311a1.464 1.464 0 0 1 .872-2.105l.34-.1c1.4-.413 1.4-2.397 0-2.81l-.34-.1a1.464 1.464 0 0 1-.872-2.105l.17-.31c.698-1.283-.705-2.686-1.987-1.987l-.311.169a1.464 1.464 0 0 1-2.105-.872zM8 10.93a2.929 2.929 0 1 1 0-5.86 2.929 2.929 0 0 1 0 5.858z"/>
        </svg>
    </span>
    Налаштування профілю
</a>

<a href="/profile.php?md=11">
    <span class="icon-wrapper">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="dpm-icon" viewBox="0 0 24 24"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M10 5a2 2 0 1 1 4 0a7 7 0 0 1 4 6v3a4 4 0 0 0 2 3h-16a4 4 0 0 0 2 -3v-3a7 7 0 0 1 4 -6" /><path d="M9 17v1a3 3 0 0 0 6 0v-1" /></svg>
    </span>
    Повідомлення
</a>

<a href="/profile.php?md=4">
    <span class="icon-wrapper">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="dpm-icon bi bi-credit-card-2-back-fill" viewBox="0 0 16 16">
          <path d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v5H0zm11.5 1a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h2a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zM0 11v1a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-1z"/>
        </svg>
    </span>
    Баланс: ' . $formattedCash . ' ₴
</a>

<a href="/messenger.php?type=3"> 
   <span class="icon-wrapper">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
           class="dpm-icon bi bi-chat-square-dots-fill" viewBox="0 0 16 16">
        <path d="M0 2a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-2.5a1 1 0 0 0-.8.4l-1.9 2.533a1 1 0 0 1-1.6 0L5.3 12.4a1 1 0 0 0-.8-.4H2a2 2 0 0 1-2-2zm5 4a1 1 0 1 0-2 0 1 1 0 0 0 2 0m4 0a1 1 0 1 0-2 0 1 1 0 0 0 2 0m3 1a1 1 0 1 0 0-2 1 1 0 0 0 0 2"/>
      </svg>
   </span>
   Підтримка
</a>


<div class="menu-separator"></div>

<a href="/profile.php?exit=1">
    <span class="icon-wrapper">
        <img src="/assets/images/logout.png" alt="Вийти" class="dpm-icon">
    </span>
    Вийти
</a>


        </div>
    </div>

</div>
';



        $out .= '<script>
document.addEventListener("DOMContentLoaded", () => {
    const toggles = document.querySelectorAll(".dropdown-toggle");
    const dropdowns = document.querySelectorAll(".dropdown");

    function closeAll() {
        toggles.forEach(t => t.checked = false);
    }

    document.addEventListener("click", (e) => {
        let inside = false;
        dropdowns.forEach(drop => {
            if (drop.contains(e.target)) inside = true;
        });
        if (!inside) closeAll();
    });

    toggles.forEach(toggle => {
        toggle.addEventListener("change", () => {
            if (toggle.checked) {
                toggles.forEach(t => { if (t !== toggle) t.checked = false; });
            }
        });
    });

    document.querySelectorAll(".open-support").forEach(btn => {
        btn.addEventListener("click", (e) => {
            e.preventDefault();
            closeAll();
            const sup = document.getElementById("menu-support");
            if (sup) {
                sup.checked = true;
                const dropdown = document.getElementById("support-menu");
                dropdown.style.visibility = "visible";
                dropdown.style.pointerEvents = "auto";
            }
        });
    });

    const arrowBtn = document.getElementById("open-support-arrow");
    if (arrowBtn) {
        arrowBtn.addEventListener("click", (e) => {
            e.preventDefault();
            closeAll();
            const avatar = document.getElementById("menu-avatar");
            if (avatar) avatar.checked = true;
        });
    }

});
</script>';

    } else {
        $out .= '
<div class="support-login-container">
    <button type="button" class="support-btn" data-tooltip="Технічна Підтримка" id="openSupport" style="padding: 0;">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
             class="dpm-icon bi bi-chat-square-dots-fill" viewBox="0 0 16 16">
            <path d="M0 2a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-2.5a1 1 0 0 0-.8.4l-1.9 2.533a1 1 0 0 1-1.6 0L5.3 12.4a1 1 0 0 0-.8-.4H2a2 2 0 0 1-2-2zm5 4a1 1 0 1 0-2 0 1 1 0 0 0 2 0m4 0a1 1 0 1 0-2 0 1 1 0 0 0 2 0m3 1a1 1 0 1 0 0-2 1 1 0 0 0 0 2"/>
        </svg>
    </button>
    <div class="login-btn">
        <a class="login-link" href="/auth.php">Увійти</a>
    </div>
</div>

<!-- каптча -->
<div class="captcha-modal" id="captchaModal">
  <div class="captcha-box">
  
     <div class="captcha-modal-header">
      <span class="captcha-modal-title">Підтвердіть, що ви не бот</span>
       <span class="captcha-close">
        <img src="/assets/images/closemodal.png" alt="Закрити" class="captcha-close-icon">
      </span>
    </div>
    
    <p class="captcha-title">Скільки буде <span id="captchaQuestion"></span> ?</p>

    <div class="logform-input-container">
      <input type="text" id="captchaAnswer" class="logform-input" placeholder="" required>
      <label for="captchaAnswer">Ваша відповідь</label>
    </div>

    <div class="captcha-error" style="display:none;"></div>
    
    <div class="captcha-actions">
      <button class="logform-button" id="captchaSubmit">Підтвердити</button>
    </div>
   
  </div>
</div>

';
        $out .= <<<HTML
<script>
document.addEventListener("DOMContentLoaded", () => {
  const openBtn = document.getElementById("openSupport");
  const modal = document.getElementById("captchaModal");
  const box = modal.querySelector(".captcha-box");
  const question = document.getElementById("captchaQuestion");
  const answer = document.getElementById("captchaAnswer");
  const submit = document.getElementById("captchaSubmit");
  const errorMsg = modal.querySelector(".captcha-error");
  const closeBtn = modal.querySelector(".captcha-close");
  
  let correctAnswer = 0;

  function setCookie(name, value, days) {
    const expires = new Date(Date.now() + days * 864e5).toUTCString();
    document.cookie = name + '=' + encodeURIComponent(value) + '; expires=' + expires + '; path=/';
  }

  function getCookie(name) {
    return document.cookie.split('; ').reduce((r, v) => {
      const parts = v.split('=');
      return parts[0] === name ? decodeURIComponent(parts[1]) : r;
    }, '');
  }

  function generateCaptcha() {
    const a = Math.floor(Math.random() * 5) + 5;
    const b = Math.floor(Math.random() * 5) + 1;
    correctAnswer = a + b;
    question.textContent = a + " + " + b;
    answer.value = "";
  }

  function showError(msg) {
    if(msg) {
      errorMsg.style.display = "block";
      errorMsg.textContent = msg;
    } else {
      errorMsg.style.display = "none";
      errorMsg.textContent = "";
    }
  }

  function closeCaptchaModal() {
    modal.classList.remove("show");
    showError("");
  }

  // открыть модалку
  openBtn.addEventListener("click", () => {
    const captchaPassed = getCookie("captcha_passed") === "true";
    if (captchaPassed) {
      window.location.href = "/messenger.php?type=3";
    } else {
      generateCaptcha();
      showError(""); 
      modal.classList.add("show");
    }
  });

  closeBtn.addEventListener("click", closeCaptchaModal);

  modal.addEventListener("click", (e) => {
    if (!box.contains(e.target)) {
      closeCaptchaModal();
    }
  });

  submit.addEventListener("click", () => {
    const userAnswer = parseInt(answer.value);
    if (userAnswer === correctAnswer) {
      setCookie("captcha_passed", "true", 30);
      closeCaptchaModal();
      window.location.href = "/messenger.php?type=3";
    } else {
      showError("Невірна відповідь. Спробуйте ще раз.");
      generateCaptcha(); 
    }
  });
});
</script>

HTML;

    }

    // --- Общая логика для мобильного бургера: запрет скролла + закрытие по Esc ---
    $out .= '
<script>
document.addEventListener("DOMContentLoaded", () => {
  const burgerToggle = document.getElementById("menu-main");
  if (!burgerToggle) return;

  const setState = () => {
    document.body.classList.toggle("burger-open", burgerToggle.checked);
  };

  setState();
  burgerToggle.addEventListener("change", setState);

  // Закрыть меню по Esc
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && burgerToggle.checked) {
      burgerToggle.checked = false;
      setState();
    }
  });

  // Закрыть меню при клике на пункт
  const parent = burgerToggle.parentElement;
  const burgerMenu = parent ? parent.querySelector(".dropdown-menu.nav") : null;
  if (burgerMenu) {
    burgerMenu.addEventListener("click", (e) => {
      const a = e.target && e.target.closest ? e.target.closest("a") : null;
      if (a) {
        burgerToggle.checked = false;
        setState();
      }
    });
  }
});
</script>
';

        $out .= '</div>';

        return $out;
    }


function Menu_Left(): string
{
    $out = '<div class="Menu_Left">';
    $out .= '<a href="index.php" class="menu-link">Головна</a>';
    $out .= '<a href="graveadd.php" class="menu-link">Поховання</a>';
    $out .= '</div>';
    return $out;
}

/**
 * Генерирует HTML для календаря дат
 * @param string $name Имя input поля
 * @param string $value Значение по умолчанию
 * @param string $label Текст метки
 * @param bool $required Обязательное поле
 * @return string HTML код для datepicker
 */
function generateDatepicker($name, $value = '', $label = 'Дата', $required = false): string
{
    $requiredAttr = $required ? ' required' : '';
    return '<div class="input-container datepicker">
        <input type="text" name="' . htmlspecialchars($name) . '" class="login-Input date-input" placeholder="дд.мм.рррр" inputmode="numeric" autocomplete="off" value="' . htmlspecialchars($value) . '"' . $requiredAttr . '>
        <label>' . htmlspecialchars($label) . '</label>
    </div>';
}


function Page_Up($ttl = ''): string
{
    global $account_not_found_notice;

    $accountMissingModal = '';
    if (!empty($account_not_found_notice)) {
        $accountMissingModal = AccountNotFoundModal();
        unset($_SESSION['account_not_found_notice']);
    }

    $out = '<!DOCTYPE html>' . xbr .
        '<html lang="uk">' . xbr .
        '<head>' . xbr .
        '<title>ІПС Shana | ' . $ttl . '</title>' . xbr .
        '<base href="https://shanapra.com/">' . xbr .
        '<link rel="icon" type="image/x-icon" href="/assets/images/logobrand3.png">' . xbr .
        '<meta charset="utf-8"><link rel="canonical" href="https://shanapra.com/">' . xbr .
        '<meta http-equiv="Content-Type" content="text/html">' . xbr .
        '<meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=1, shrink-to-fit=yes, viewport-fit=cover">' . xbr .
        '<meta name="theme-color" content="#101826">
' . xbr .
        '<meta name="robots" content="all">' . xbr .
        '<link rel="stylesheet" href="/assets/css/common.css">' . xbr .
        '<link rel="stylesheet" href="/assets/css/datepicker.css">' . xbr .
        '<script>
(function() {
  
    history.pushState(null, null, location.href);
    history.pushState(null, null, location.href);

    window.addEventListener("popstate", function(event) {
  
        history.pushState(null, null, location.href);
    });

  
    window.addEventListener("beforeunload", function() {
        history.pushState(null, null, location.href);
    });
})();
</script>' . xbr .
        '<script src="/assets/js/datepicker.js"></script>' . xbr .
        '</head>' . xbr .
        '<body class="bg-dark">' . xbr .
        '<div id="wrapper" class="wrapper">' . xbr .
        $accountMissingModal;
    return $out;
}

function AccountNotFoundModal(): string
{
    return <<<HTML
<div class="account-missing-modal is-visible" id="account-missing-modal" role="dialog" aria-modal="true" aria-labelledby="account-missing-title">
    <div class="account-missing-modal__backdrop" data-account-missing-close></div>
    <div class="account-missing-modal__card">
        <button type="button" class="account-missing-modal__close" aria-label="Закрити" data-account-missing-close>&times;</button>
        <h2 id="account-missing-title" class="account-missing-modal__title">Помилка: акаунт не знайдено</h2>
        <p class="account-missing-modal__text">Ваш обліковий запис не знайдено. Спробуйте авторизуватися знову або зверніться в технічну підтримку.</p>
        <div class="account-missing-modal__actions">
            <a href="/auth.php" class="account-missing-modal__btn account-missing-modal__btn--primary">Авторизуватися знову</a>
            <a href="/messenger.php?type=3" class="account-missing-modal__btn account-missing-modal__btn--secondary">Технічна підтримка</a>
        </div>
    </div>
</div>
<style>
.account-missing-modal {
    position: fixed;
    inset: 0;
    display: grid;
    place-items: center;
    z-index: 13000;
}

.account-missing-modal__backdrop {
    position: absolute;
    inset: 0;
    background: rgba(10, 24, 38, 0.58);
    backdrop-filter: blur(2px);
}

.account-missing-modal__card {
    position: relative;
    width: min(92vw, 520px);
    border-radius: 18px;
    border: 1px solid #d8e3f0;
    background: #ffffff;
    box-shadow: 0 24px 54px rgba(8, 29, 48, 0.26);
    padding: 26px 22px 22px;
}

.account-missing-modal__close {
    position: absolute;
    top: 10px;
    right: 10px;
    width: 32px;
    height: 32px;
    border: 0;
    border-radius: 50%;
    background: #f2f6fb;
    color: #45607c;
    font-size: 22px;
    line-height: 1;
    cursor: pointer;
}

.account-missing-modal__title {
    margin: 0;
    color: #12304d;
    font-size: 23px;
    line-height: 1.25;
}

.account-missing-modal__text {
    margin: 12px 0 0;
    color: #3f5d7b;
    font-size: 15px;
    line-height: 1.55;
}

.account-missing-modal__actions {
    margin-top: 18px;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.account-missing-modal__btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 42px;
    padding: 0 16px;
    border-radius: 10px;
    font-weight: 700;
    font-size: 14px;
    text-decoration: none;
    transition: transform 0.18s ease, box-shadow 0.18s ease, background-color 0.18s ease;
}

.account-missing-modal__btn:hover {
    transform: translateY(-1px);
}

.account-missing-modal__btn--primary {
    color: #ffffff;
    background: linear-gradient(135deg, #2f77bf, #1f5b9a);
    box-shadow: 0 8px 16px rgba(28, 81, 136, 0.24);
}

.account-missing-modal__btn--secondary {
    color: #184266;
    background: #edf4fb;
}

@media (max-width: 560px) {
    .account-missing-modal__card {
        border-radius: 14px;
        padding: 22px 16px 16px;
    }

    .account-missing-modal__title {
        font-size: 20px;
    }

    .account-missing-modal__actions {
        flex-direction: column;
    }

    .account-missing-modal__btn {
        width: 100%;
    }
}
</style>
<script>
(function () {
    var modal = document.getElementById("account-missing-modal");
    if (!modal) {
        return;
    }

    function closeModal() {
        modal.remove();
    }

    var closeElements = modal.querySelectorAll("[data-account-missing-close]");
    closeElements.forEach(function (element) {
        element.addEventListener("click", closeModal);
    });

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape") {
            closeModal();
        }
    });
})();
</script>
HTML;
}

function AuthRequired_Content(string $loginUrl = '/auth.php', string $loginText = 'Увійти'): string
{
    global $hide_page_down;
    $hide_page_down = true;

    $url = htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8');
    $text = htmlspecialchars($loginText, ENT_QUOTES, 'UTF-8');

    $out = '<style>html, body { overflow: hidden; height: 100%; }</style>';
    $out .= '<div class="page-404">';
    $out .= '<div class="page-404__inner">';
    $out .= '<div class="page-404__icon">';
    $out .= '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 13a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v6a2 2 0 0 1 -2 2h-10a2 2 0 0 1 -2 -2v-6"/><path d="M11 16a1 1 0 1 0 2 0a1 1 0 0 0 -2 0"/><path d="M8 11v-4a4 4 0 1 1 8 0v4"/></svg>';
    $out .= '</div>';
    $out .= '<h1 class="page-404__title">Для доступу потрібна авторизація</h1>';
    $out .= '<p class="page-404__text">Увійдіть у свій обліковий запис, щоб отримати доступ до цієї сторінки.</p>';
    $out .= '<div class="page-404__actions">';
    $out .= '<a href="' . $url . '" class="page-404__btn page-404__btn--primary">' . $text . '</a>';
    $out .= '</div>';
    $out .= '</div>';
    $out .= '</div>';

    return $out;
}

function RedirectLoadingOverlay(string $title = 'Зачекайте', string $subtitle = 'Триває перенаправлення...'): string
{
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $safeSubtitle = htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8');

    return <<<HTML
<div class="redirect-loading-overlay" id="redirect-loading-overlay" aria-hidden="true">
    <div class="redirect-loading-overlay__backdrop"></div>
    <div class="redirect-loading-overlay__card" role="status" aria-live="polite" aria-atomic="true">
        <div class="redirect-loading-overlay__ring" aria-hidden="true"></div>
        <div class="redirect-loading-overlay__title" data-rlo-title>{$safeTitle}</div>
        <div class="redirect-loading-overlay__subtitle" data-rlo-subtitle>{$safeSubtitle}</div>
    </div>
</div>
<style>
body.redirect-loading-open {
    overflow: hidden;
}

.redirect-loading-overlay {
    position: fixed;
    top: 0;
    right: 0;
    bottom: 0;
    left: 0;
    width: 100vw;
    height: 100dvh;
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transition: opacity 0.24s ease, visibility 0.24s ease;
    z-index: 12000;
    font-family: "Manrope", "Segoe UI", Tahoma, sans-serif !important;
}

.redirect-loading-overlay.is-visible {
    opacity: 1;
    visibility: visible;
    pointer-events: auto;
}

.redirect-loading-overlay__backdrop {
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, #e8eaed 0%, #d8dbe0 50%, #cbd0d6 100%);
}

.redirect-loading-overlay__card {
    position: fixed;
    top: 50%;
    left: 50%;
    width: min(94vw, 520px);
    border: 0;
    background: transparent;
    box-shadow: none;
    padding: 0 18px;
    display: grid;
    justify-items: center;
    gap: 12px;
    text-align: center;
    transform: translate(-50%, calc(-50% + 8px));
    transition: transform 0.3s ease;
}

.redirect-loading-overlay.is-visible .redirect-loading-overlay__card {
    transform: translate(-50%, -50%);
}

.redirect-loading-overlay__ring {
    width: 58px;
    height: 58px;
    border-radius: 50%;
    border: 3px solid rgba(46, 96, 146, 0.22);
    border-top-color: #2f76be;
    border-right-color: #5092d4;
    animation: redirect-loading-spin 0.9s linear infinite;
}

.redirect-loading-overlay__title {
    color: #16395d;
    font-size: 26px;
    font-weight: 800;
    line-height: 1.2;
}

.redirect-loading-overlay__subtitle {
    color: #355474;
    font-size: 16px;
    line-height: 1.45;
    max-width: 36ch;
}

@keyframes redirect-loading-spin {
    to {
        transform: rotate(360deg);
    }
}

@media (max-width: 640px) {
    .redirect-loading-overlay__card {
        padding: 0 14px;
    }

    .redirect-loading-overlay__title {
        font-size: 22px;
    }

    .redirect-loading-overlay__subtitle {
        font-size: 14px;
    }
}
</style>
<script>
(function () {
    if (window.showRedirectLoadingOverlay) {
        return;
    }

    window.showRedirectLoadingOverlay = function (options) {
        var overlay = document.getElementById("redirect-loading-overlay");
        if (!overlay) {
            return;
        }

        var titleEl = overlay.querySelector("[data-rlo-title]");
        var subtitleEl = overlay.querySelector("[data-rlo-subtitle]");
        var titleText = options && options.title ? options.title : "";
        var subtitleText = options && options.subtitle ? options.subtitle : "";

        if (titleEl && titleText) {
            titleEl.textContent = titleText;
        }
        if (subtitleEl && subtitleText) {
            subtitleEl.textContent = subtitleText;
        }

        overlay.classList.add("is-visible");
        overlay.setAttribute("aria-hidden", "false");
        document.body.classList.add("redirect-loading-open");
    };
})();
</script>
HTML;
}

function InDev_Content(string $requestedUri = '', string $backUrl = '', string $backText = 'На головну'): string
{
    if ($requestedUri === '') {
        $requestedUri = isset($_GET['from']) ? $_GET['from'] : ($_SERVER['REQUEST_URI'] ?? '');
    }
    $requestedUri = htmlspecialchars($requestedUri, ENT_QUOTES, 'UTF-8');
    $url = $backUrl !== '' ? $backUrl : '/index.php';
    $text = $backText;

    $out = '<div class="page-404">';
    $out .= '<div class="page-404__inner">';
    $out .= '<div class="page-404__icon">';
    $out .= '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77 -3.77a6 6 0 0 1 -7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1 -3 -3l6.91 -6.91a6 6 0 0 1 7.94 -7.94l-3.76 3.76z"/></svg>';
    $out .= '</div>';
    $out .= '<h1 class="page-404__title">Сторінка в розробці</h1>';
    $out .= '<p class="page-404__text">Тимчасово недоступно.</p>';
    if ($requestedUri !== '') {
        $out .= '<div class="page-404__uri">' . $requestedUri . '</div>';
    }
    $out .= '<div class="page-404__actions">';
    $out .= '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" class="page-404__btn page-404__btn--primary">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</a>';
    $out .= '</div>';
    $out .= '</div>';
    $out .= '</div>';

    return $out;
}

function Page_Down(): string
{
    global $hide_page_down;

    if ($hide_page_down) {
        return '</body></html>';
    }

    $currentYear = date('Y');
    $out = <<<HTML
<footer class="site-footer-v2">
    <div class="site-footer-v2__wrap">
        <div class="site-footer-v2__top">
            <section class="site-footer-v2__brand">
                <a href="/" class="site-footer-v2__logo">
                    <span class="site-footer-v2__logo-mark">
                        <img src="/assets/images/logobrand3.png" alt="Shana logo">
                    </span>
                    <span class="site-footer-v2__logo-text">
                        <strong>ІПС Шана</strong>
                        <span>Shanapra.com</span>
                    </span>
                </a>
                <p class="site-footer-v2__note">
                    Інформаційно-пошукова система пам'яті: пошук поховань і збереження пам'яті
                </p>
            </section>

            <details class="site-footer-v2__dropdown" open>
                <summary class="site-footer-v2__dropdown-summary">
                    <h2 class="site-footer-v2__col-title">Розділи</h2>
                </summary>
                <ul class="site-footer-v2__links">
                    <li><a href="/">Головна</a></li>
                    <li><a href="/graveaddform.php">Додати поховання</a></li>
                    <li><a href="/clean-cemeteries.php">Послуги</a></li>
                    <li><a href="/faq.php">FAQ</a></li>
                </ul>
            </details>

            <details class="site-footer-v2__dropdown" open>
                <summary class="site-footer-v2__dropdown-summary">
                    <h2 class="site-footer-v2__col-title">Інформація</h2>
                </summary>
                <ul class="site-footer-v2__links">
                    <li><a href="/about_us.php">Про нас</a></li>
                    <li><a href="/contacts.php">Контакти</a></li>
                    <li><a href="/in-dev.php?from=/privacy">Конфіденційність</a></li>
                    <li><a href="/in-dev.php?from=/copyright">Авторське право</a></li>
                </ul>
            </details>

            <section>
                <h2 class="site-footer-v2__col-title">Підтримка</h2>
                <p class="site-footer-v2__contact">
                    Технічна підтримка:<br>
                    <a href="/messenger.php?type=3">Написати в чат підтримки</a>
                </p>
                <div class="site-footer-v2__chips">
                    <span class="site-footer-v2__chip">24/7 Онлайн</span>
                    <span class="site-footer-v2__chip">Безпечний доступ</span>
                </div>
            </section>
        </div>

        <div class="site-footer-v2__bottom">
            <p class="site-footer-v2__copyright">© 2014-{$currentYear} shanapra.com. Всі права захищені.</p>
            <div class="site-footer-v2__legal">
                <a href="/in-dev.php?from=/links">Посилання</a>
                <a href="/in-dev.php?from=/npinfo">Нормативно-правова інформація</a>
            </div>
        </div>
    </div>
</footer>
<script>
(function () {
    var media = window.matchMedia('(max-width: 680px)');
    var dropdowns = document.querySelectorAll('.site-footer-v2__dropdown');

    function closeOtherDropdowns(activeDropdown) {
        dropdowns.forEach(function (dropdown) {
            if (dropdown !== activeDropdown) {
                dropdown.removeAttribute('open');
            }
        });
    }

    function syncFooterDropdowns() {
        dropdowns.forEach(function (dropdown) {
            if (media.matches) {
                dropdown.removeAttribute('open');
            } else {
                dropdown.setAttribute('open', 'open');
            }
        });
    }

    dropdowns.forEach(function (dropdown) {
        dropdown.addEventListener('toggle', function () {
            if (!media.matches || !dropdown.open) {
                return;
            }

            closeOtherDropdowns(dropdown);
        });
    });

    syncFooterDropdowns();
    if (media.addEventListener) {
        media.addEventListener('change', syncFooterDropdowns);
    } else if (media.addListener) {
        media.addListener(syncFooterDropdowns);
    }
})();
</script>
HTML;
    $out .= xbr;
    $out .= '</body></html>';
    return $out;
}


function Contentx(): string
{
    $out = '<div class = "content">';

    $out .= '</div>';
    return $out;
}

function View_Add_Warn($mes = ''): string
{
    global $md;
    $out = '<div class="warn">md=' . $md . '** ' . $mes . '</div>';

    return $out;
}

function DbsCount(): int
{
    global $dblink;
    $dblink = DbConnect();
    $sql = 'SELECT count(idx) as t1 FROM grave';
    $res = mysqli_query($dblink, $sql);
    if (!$res) {
        $out = 0;
    } else {
        $ou = mysqli_fetch_assoc($res);
        $out = $ou['t1'];
    }
    return $out;
}

/*function RegionSelect($n="",$c=""):string
{
    return
    '<select name="'.$n.'" class="'.$c.'" required> ' .
    ' <option value="" disabled selected>Виберіть місто</option> ' .
    ' <option>Київ</option> ' .
    '<option>Вінниця</option> ' .
    '<option>Дніпро</option> ' .
    '<option>Донецьк</option> ' .
    '<option>Житомир</option>' .
    '<option>Запоріжжя</option>' .
    '<option>Івано‑Франківськ</option>' .
    '<option>Кропивницький</option>' .
    '<option>Луганськ</option>' .
    '<option>Луцьк</option>' .
    '<option>Львів</option>' .
    '<option>Миколаїв</option>' .
    '<option>Одеса</option>' .
    '<option>Полтава</option>' .
    '<option>Рівне</option>' .
    '<option>Сімферополь</option>' .
    '<option>Суми</option>' .
    '<option>Тернопіль</option>' .
    '<option>Ужгород</option>' .
    '<option>Харків</option>' .
    '<option>Херсон</option>' .
    '<option>Хмельницький</option>' .
    '<option>Черкаси</option>' .
    '<option>Чернівці</option>' .
    '<option>Чернігів</option>' .

    '</select>' ;
}*/

if (isset($_GET['ajax_districts']) && isset($_GET['region_id'])) {

    $region_id = (int)$_GET['region_id'];

    if ($region_id <= 0) {
        echo '<option value="">Область не обрана</option>';
        exit;
    }

    $dblink = DbConnect();

    $res = mysqli_query(
        $dblink,
        "SELECT idx, title FROM district WHERE region = $region_id ORDER BY title"
    );

    mysqli_close($dblink);

    if ($res && mysqli_num_rows($res) > 0) {
        echo '<option value="">Виберіть район</option>';
        while ($row = mysqli_fetch_assoc($res)) {
            echo '<option value="' . (int)$row['idx'] . '">' .
                htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8') .
                '</option>';
        }
    } else {
        echo '<option value="">Райони не знайдено</option>';
    }

    exit;
}

// AJAX: список кладовищ по району (для фільтра пошуку)
if (isset($_GET['ajax_cemeteries']) && isset($_GET['district_id'])) {

    $district_id = (int)$_GET['district_id'];

    if ($district_id <= 0) {
        echo '<option value="">Район не обраний</option>';
        exit;
    }

    echo CemeterySelect($district_id);
    exit;
}


function RegionSelect($n = "region", $c = "")
{
    $dblink = DbConnect();
    $res = mysqli_query($dblink, "SELECT idx, title FROM region ORDER BY title");
    mysqli_close($dblink);

    $out = '<select name="' . $n . '" id="region" class="' . $c . '" onchange="loadDistricts(this.value)" required>';
    $out .= '<option value="" disabled selected>Виберіть область</option>';

    while ($row = mysqli_fetch_assoc($res)) {
        $out .= '<option value="' . $row['idx'] . '">' . $row['title'] . '</option>';
    }

    $out .= '</select>';
    return $out;
}

function RegionForKladb($n = "region", $c = "", $selectedRegion = null, $selectedDistrict = null)
{
    static $scriptAdded = false;

    $dblink = DbConnect();
    $res = mysqli_query($dblink, "SELECT idx, title FROM region ORDER BY title");
    mysqli_close($dblink);

    $regions = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $regions[] = $row;
    }

    $selectedRegion = $selectedRegion ?? ''; // если null, то пусто
    $currentTitle = $selectedRegion ? '' : 'Виберіть область';
    foreach ($regions as $r) {
        if ($selectedRegion == $r['idx']) {
            $currentTitle = htmlspecialchars($r['title'], ENT_QUOTES, 'UTF-8');
            break;
        }
    }

    $out = '<div class="form-group">
<label>Область</label>
<select name="' . htmlspecialchars($n) . '"
        id="region"
        class="' . htmlspecialchars($c) . '"
        style="display:none;">
    <option value=""' . ($selectedRegion === '' ? ' selected' : '') . '>Виберіть область</option>';

    foreach ($regions as $region) {
        $id = htmlspecialchars($region['idx'], ENT_QUOTES, 'UTF-8');
        $title = htmlspecialchars($region['title'], ENT_QUOTES, 'UTF-8');
        $selected = ($selectedRegion == $region['idx']) ? ' selected' : '';
        $out .= "<option value=\"{$id}\"{$selected}>{$title}</option>";
    }

    $out .= '</select>

<div class="custom-select-wrapper">
    <div class="custom-select-trigger">' . $currentTitle . '</div>
    <div class="custom-options">';

    foreach ($regions as $region) {
        $id = htmlspecialchars($region['idx'], ENT_QUOTES, 'UTF-8');
        $title = htmlspecialchars($region['title'], ENT_QUOTES, 'UTF-8');
        $out .= "<span class=\"custom-option\" data-value=\"{$id}\">{$title}</span>";
    }

    $out .= '</div>
</div>
</div>';
    if (!$scriptAdded) {
        $scriptAdded = true;

        View_Add('<script>
document.addEventListener("DOMContentLoaded", function () {

    function initCustomSelect(wrapper) {
        const trigger = wrapper.querySelector(".custom-select-trigger");
        const options = wrapper.querySelector(".custom-options");
        const select  = wrapper.previousElementSibling;

        if (!trigger || !options || !select) return;

        trigger.addEventListener("click", function (e) {
            e.stopPropagation();

            document.querySelectorAll(".custom-select-wrapper").forEach(w => {
                if (w !== wrapper) {
                    w.classList.remove("open");
                    w.querySelector(".custom-options").style.display = "none";
                }
            });

            const open = wrapper.classList.toggle("open");
            options.style.display = open ? "flex" : "none";
        });

        function bindOptions() {
            options.querySelectorAll(".custom-option").forEach(opt => {
                opt.onclick = function () {
                    trigger.textContent = opt.textContent;
                    select.value = opt.dataset.value;
                    select.dispatchEvent(new Event("change"));
                    wrapper.classList.remove("open");
                    options.style.display = "none";
                };
            });
        }

        bindOptions();
        wrapper._bindOptions = bindOptions;
    }

    document.querySelectorAll(".custom-select-wrapper").forEach(initCustomSelect);

    document.addEventListener("click", function () {
        document.querySelectorAll(".custom-select-wrapper").forEach(w => {
            w.classList.remove("open");
            w.querySelector(".custom-options").style.display = "none";
        });
    });

    const regionSelect   = document.getElementById("location-select");
    const districtSelect = document.getElementById("district");
    const districtWrap   = document.getElementById("district-wrapper");

    if (!regionSelect || !districtSelect || !districtWrap) return;

    const districtTrigger = districtWrap.querySelector(".custom-select-trigger");
    const districtOptions = districtWrap.querySelector(".custom-options");

    function loadDistricts(regionId, selected = null) {
    districtSelect.innerHTML = "";
    districtOptions.innerHTML = "";

    if (!regionId) {

        const opt = document.createElement("option");
        opt.value = "";
        opt.textContent = "Область не обрана";
        districtSelect.appendChild(opt);
        districtSelect.value = "";

        const span = document.createElement("span");
        span.className = "custom-option disabled";
        span.dataset.value = "";
        span.textContent = "Область не обрана";
        districtOptions.appendChild(span);

        districtTrigger.textContent = "Виберіть спочатку область";
        districtWrap._bindOptions = function () {};
        return;
    }

    districtTrigger.textContent = "Виберіть район";

    fetch("?ajax_districts=1&region_id=" + encodeURIComponent(regionId))
        .then(res => res.text())
        .then(html => {
            const tmp = document.createElement("select");
            tmp.innerHTML = html;

            tmp.querySelectorAll("option").forEach(opt => {
                if (!opt.value) return;

                const o = opt.cloneNode(true);
                districtSelect.appendChild(o);

                const span = document.createElement("span");
                span.className = "custom-option";
                span.dataset.value = opt.value;
                span.textContent = opt.textContent;
                districtOptions.appendChild(span);

                if (selected && opt.value == selected) {
                    districtSelect.value = opt.value;
                    districtTrigger.textContent = opt.textContent;
                }
            });

            if (!districtSelect.value) {
                districtTrigger.textContent = "Виберіть район";
            }

            districtWrap._bindOptions();
        })
        .catch(() => {
            districtTrigger.textContent = "Помилка завантаження";
        });
}


    if (regionSelect.value !== "") {
    loadDistricts(regionSelect.value, districtSelect.value || null);
} else {
    loadDistricts(null);
}


    regionSelect.addEventListener("change", function () {
    if (this.value !== "") {
        loadDistricts(this.value);
    } else {
        loadDistricts(null);
    }
});


});
</script>');


    }

    return $out;
}



function RegionForCem($n = "region", $c = "")
{
    $dblink = DbConnect();
    $res = mysqli_query($dblink, "SELECT idx, title FROM region ORDER BY title");
    mysqli_close($dblink);

    $out = '<select name="' . $n . '" id="region" class="' . $c . '" required onchange="loadDistricts(this.value)">';

    $out .= '<option value="" selected hidden>Виберіть область</option>';

    while ($row = mysqli_fetch_assoc($res)) {
        $out .= '<option value="' . $row['idx'] . '">' . $row['title'] . '</option>';
    }

    $out .= '</select>';
    return $out;
}


// Районы по области
function getDistricts($region_id)
{
    $dblink = DbConnect();
    $region_id = (int)$region_id;

    $res = mysqli_query($dblink, "SELECT idx, title FROM district WHERE region = $region_id ORDER BY title");

    $out = '<option value="">Оберіть район</option>';
    while ($row = mysqli_fetch_assoc($res)) {
        $out .= '<option value="' . (int)$row['idx'] . '">' . htmlspecialchars($row['title']) . '</option>';
    }

    mysqli_close($dblink);
    return $out;
}

// Населенные пункты по району и области
function getSettlements($region_id, $district_id)
{
    $dblink = DbConnect();
    $region_id = (int)$region_id;
    $district_id = (int)$district_id;

    $sql = "SELECT idx, title 
            FROM misto 
            WHERE idxregion = $region_id 
              AND idxdistrict = $district_id 
            ORDER BY title";

    $res = mysqli_query($dblink, $sql);

    $out = '<option value="">Оберіть нас. пункт</option>';
    while ($row = mysqli_fetch_assoc($res)) {

        $out .= '<option value="' . (int)$row['idx'] . '">' . htmlspecialchars($row['title']) . '</option>';
    }

    mysqli_close($dblink);
    return $out;
}



// Добавление нового населённого пункта
function addSettlement($region_id, $district_id, $name)
{
    $dblink = DbConnect();
    $region_id = (int)$region_id;
    $district_id = (int)$district_id;
    $name = mysqli_real_escape_string($dblink, trim($name));

    if ($name === "") {
        return "Помилка: пуста назва";
    }

    $sql = "INSERT INTO misto (title, idxdistrict, idxregion) VALUES ('$name', $district_id, $region_id)";
    if (mysqli_query($dblink, $sql)) {
        $out = "OK: додано";
    } else {
        $out = "Помилка: " . mysqli_error($dblink);
    }

    mysqli_close($dblink);
    return $out;
}



function Cardsx(
    int $idx = 0,
    string $f = '',
    string $i = '',
    string $o = '',
    string $d1 = '',
    string $d2 = '',
    string $img = '',
    ?string $district = '',
    ?string $region = '',
    ?string $moderationStatus = ''

): string {

    $district = $district ?? '';
    $region   = $region ?? '';
    $moderationStatus = strtolower(trim((string)($moderationStatus ?? '')));

    if (!is_file($_SERVER['DOCUMENT_ROOT'].$img)) {
        $img = '/graves/noimage.jpg';
    }

    $d1Unknown = empty($d1) || $d1 === '0000-00-00' || $d1 === '0000-00-00';
    $d2Unknown = empty($d2) || $d2 === '0000-00-00' || $d2 === '0000-00-00';

    if ($d1Unknown && $d2Unknown) {
        $dates = 'Дати не вказані';
    } elseif ($d1Unknown) {
        $dates = 'Дата не вказана - '.DateFormat($d2);
    } elseif ($d2Unknown) {
        $dates = DateFormat($d1).' - Дата не вказана';
    } else {
        $dates = DateFormat($d1).' - '.DateFormat($d2);
    }

    $moderationBadgeHtml = '';
    if ($moderationStatus === 'pending') {
        $moderationBadgeHtml = '<span class="cardx-moderation cardx-moderation--pending">На модерації</span>';
    } elseif ($moderationStatus === 'approved') {
        $moderationBadgeHtml = '<span class="cardx-moderation cardx-moderation--approved" aria-label="Перевірено модерацією"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-circle-check"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 12a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" /><path d="M9 12l2 2l4 -4" /></svg><span class="cardx-moderation-text">Перевірено</span></span>';
    }

    $out  = '<div class="cardx">';

    // фото
    $out .= '  <div class="cardx-img">';
    $out .= '      <img src="'.$img.'" class="cardx-image" alt="'.$f.' '.$i.' '.$o.'" data-tooltip="'.$f.' '.$i.' '.$o.'">';
    $out .=        $moderationBadgeHtml;
    $out .= '  </div>';

    // блок с данными
    $out .= '  <div class="cardx-data">';

    $out .= '      <div class="text2center font-bold font-white height50">';
    $out .=            $f.' '.$i.' '.$o.'<br>';
    $out .= '      </div>';

    $out .= '      <div class="text2center font-white">';
    $out .=            $dates.'<br>';
    $out .= '      </div>';

    if ($district === '' && $region === '') {
        $location = 'Місцезнаходження не вказано';
    } else {
        $locationParts = [];
        if ($district !== '') {
            $locationParts[] = $district . ' район';
        }
        if ($region !== '') {
            $locationParts[] = $region . ' область';
        }
        $location = implode(', ', $locationParts);
    }

    $out .= '      <div class="cardx-location">';
    $out .=            $location;
    $out .= '      </div>';

    $out .= '      <div class="text2right">';
    $out .= '          <a href="/cardout.php?idx='.$idx.'">Переглянути</a>';
    $out .= '      </div>';

    $out .= '  </div>';


    $out .= '</div>';

    return $out;
}

function DateFormatUnknown(string $date): string {
    if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00') {
        return 'Дата не вказана';
    }
    return DateFormat($date);
}


function CardsK(
    int $idx = 0,
    string $title = '',
    string $town = '',
    string $district = '',
    string $adress = '',
    string $scheme = ''
): string {

    $dblink = DbConnect();

//район
    if (!empty($district) && ctype_digit((string)$district)) {
        $res = mysqli_query($dblink, "SELECT title FROM district WHERE idx=".(int)$district." LIMIT 1");
        if ($res && $row = mysqli_fetch_assoc($res)) {
            $district = $row['title'];
        }
    }


    //нас пункт
    if (!empty($town) && ctype_digit((string)$town)) {
        $res = mysqli_query($dblink, "SELECT title FROM misto WHERE idx=".(int)$town." LIMIT 1");
        if ($res && $row = mysqli_fetch_assoc($res)) {
            $town = $row['title'];
        }
    }

    if (!is_file($_SERVER['DOCUMENT_ROOT'].$scheme) || empty($scheme)) {
        $scheme = '/cemeteries/noscheme.png';
    }

    $out  = '<div class="cardk">';

    // фото
    $out .= '  <div class="cardk-img">';
    $out .= '      <img src="'.$scheme.'" class="cardk-image" alt="'.htmlspecialchars($title).'" data-tooltip="'.htmlspecialchars($title).'">';
    $out .= '  </div>';

    // блок с данными
    $out .= '  <div class="cardk-data">';

    // Заголовок
    $out .= '      <div class="cardk-title font-bold">'.$title.'</div>';


    if ($town !== '') {
        $out .= '      <div class="cardk-town"><b>Місто:</b> '.$town.'</div>';
    }

    // Район
    if ($district !== '') {
        $out .= '      <div class="cardk-district"><b>Район:</b> '.$district.'</div>';
    }

    // Адрес
    if ($adress !== '') {
        $out .= '      <div class="cardk-adress"><b>Адреса:</b> '.$adress.'</div>';
    }


    $out .= '  </div>'; // .cardk-data
    $out .= '  <div class="cardk-footer">';
    $out .= '      <a href="/cemetery.php?idx='.$idx.'" class="cardk-link">Деталі</a>';
    $out .= '  </div>';
    $out .= '</div>';   // .cardk

    return $out;
}



class Paginatex
{
    public static function Showx(int $current, int $total, int $perpage): string
    {
        $countPages = ceil($total / $perpage);
        if ($countPages <= 1) return '';

        $prevIcon = '
<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" 
    fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
    <path d="M15 18l-6-6 6-6"/>
</svg>';

        $nextIcon = '
<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" 
    fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
    <path d="M9 18l6-6-6-6"/>
</svg>';

        $firstIcon = '
<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" 
    fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
    <path d="M11 19l-7-7 7-7M18 5v14"/>
</svg>';

        $lastIcon = '
<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" 
    fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
    <path d="M13 5l7 7-7 7M6 19V5"/>
</svg>';


        $html = '<ul>';

        // для текущего путя.
        $baseUrl = strtok($_SERVER["REQUEST_URI"], '?');

        $query = $_GET;
        unset($query['page']);
        $zapr = http_build_query($query);
        $zapr = $zapr ? "&$zapr" : "";

        // В начало
        if ($current > 1) {
            $html .= '<li><a href="' . $baseUrl . '?page=1' . $zapr . '">' . $firstIcon . '</a></li>';
        } else {
            $html .= '<li><span class="disabled">' . $firstIcon . '</span></li>';
        }

        // Назад
        if ($current > 1) {
            $html .= '<li><a href="' . $baseUrl . '?page=' . ($current - 1) . $zapr . '">' . $prevIcon . '</a></li>';
        } else {
            $html .= '<li><span class="disabled">' . $prevIcon . '</span></li>';
        }

        // Номера страниц
        $start = max(1, $current);
        $end = min($start + 2, $countPages);
        if ($end - $start < 2 && $start > 1) {
            $start = max(1, $end - 2);
        }

        for ($i = $start; $i <= $end; $i++) {
            if ($i == $current) {
                $html .= '<li><span class="current">' . $i . '</span></li>';
            } else {
                $html .= '<li><a href="' . $baseUrl . '?page=' . $i . $zapr . '">' . $i . '</a></li>';
            }
        }

        // Вперед
        if ($current < $countPages) {
            $html .= '<li><a href="' . $baseUrl . '?page=' . ($current + 1) . $zapr . '">' . $nextIcon . '</a></li>';
        } else {
            $html .= '<li><span class="disabled">' . $nextIcon . '</span></li>';
        }

        // В конец
        if ($current < $countPages) {
            $html .= '<li><a href="' . $baseUrl . '?page=' . $countPages . $zapr . '">' . $lastIcon . '</a></li>';
        } else {
            $html .= '<li><span class="disabled">' . $lastIcon . '</span></li>';
        }

        $html .= '</ul>';
        return $html;
    }
}

function CemeterySelect($districtId = 0, $selectedId = null) {
    $out = '<option value="">Виберіть кладовище</option>';

    if ($districtId > 0) {
        $dblink = DbConnect();
        $districtId = intval($districtId);

        $sql = "SELECT idx, title FROM cemetery WHERE district = $districtId ORDER BY title ASC";
        $res = mysqli_query($dblink, $sql);

        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $sel = ($selectedId && $selectedId == $row['idx']) ? ' selected' : '';
                $out .= '<option value="' . $row['idx'] . '"' . $sel . '>' . htmlspecialchars($row['title']) . '</option>';
            }
        }
    }

    return $out;
}

function uploadImageCreateResource(string $sourcePath, string $mime)
{
    switch ($mime) {
        case 'image/jpeg':
            return imagecreatefromjpeg($sourcePath);
        case 'image/png':
            return imagecreatefrompng($sourcePath);
        case 'image/gif':
            return imagecreatefromgif($sourcePath);
        default:
            return false;
    }
}

function uploadImageResizeResource($image, int $targetWidth, int $targetHeight, string $mime)
{
    $sourceWidth = imagesx($image);
    $sourceHeight = imagesy($image);

    if ($sourceWidth === $targetWidth && $sourceHeight === $targetHeight) {
        return $image;
    }

    $resized = imagecreatetruecolor($targetWidth, $targetHeight);

    if ($mime === 'image/png') {
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
        imagefilledrectangle($resized, 0, 0, $targetWidth, $targetHeight, $transparent);
    } elseif ($mime === 'image/gif') {
        $transparentIndex = imagecolortransparent($image);
        if ($transparentIndex >= 0) {
            $transparentColor = imagecolorsforindex($image, $transparentIndex);
            $transparentIndexNew = imagecolorallocate($resized, $transparentColor['red'], $transparentColor['green'], $transparentColor['blue']);
            imagefill($resized, 0, 0, $transparentIndexNew);
            imagecolortransparent($resized, $transparentIndexNew);
        }
    }

    imagecopyresampled($resized, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);

    return $resized;
}

function uploadImageWriteResource($image, string $targetPath, string $mime, int $quality): bool
{
    if ($mime === 'image/jpeg') {
        return imagejpeg($image, $targetPath, max(30, min(95, $quality)));
    }

    if ($mime === 'image/png') {
        return imagepng($image, $targetPath, 9);
    }

    if ($mime === 'image/gif') {
        return imagegif($image, $targetPath);
    }

    return false;
}

function uploadImageCompressSmart($sourcePath, $targetPath, $maxSizeKB = 2048, $maxWidth = 1600, $maxHeight = 1600, $quality = 90): bool
{
    $info = getimagesize($sourcePath);
    if (!$info || empty($info['mime'])) {
        return false;
    }

    $mime = (string)$info['mime'];
    $allowedMimes = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($mime, $allowedMimes, true)) {
        return false;
    }

    $sourceSize = @filesize($sourcePath);
    $origWidth = isset($info[0]) ? (int)$info[0] : 0;
    $origHeight = isset($info[1]) ? (int)$info[1] : 0;

    if ($sourceSize !== false
        && $sourceSize <= ($maxSizeKB * 1024)
        && $origWidth > 0
        && $origHeight > 0
        && $origWidth <= $maxWidth
        && $origHeight <= $maxHeight
    ) {
        return copy($sourcePath, $targetPath);
    }

    $image = uploadImageCreateResource($sourcePath, $mime);
    if (!$image) {
        return false;
    }

    $currentWidth = imagesx($image);
    $currentHeight = imagesy($image);
    if ($currentWidth <= 0 || $currentHeight <= 0) {
        imagedestroy($image);
        return false;
    }

    $ratio = min($maxWidth / $currentWidth, $maxHeight / $currentHeight, 1);
    $targetWidth = max(1, (int)round($currentWidth * $ratio));
    $targetHeight = max(1, (int)round($currentHeight * $ratio));
    $needsResize = $targetWidth !== $currentWidth || $targetHeight !== $currentHeight;

    $workingImage = uploadImageResizeResource($image, $targetWidth, $targetHeight, $mime);
    if ($workingImage !== $image) {
        imagedestroy($image);
    }

    $maxSizeBytes = $maxSizeKB * 1024;
    $currentQuality = max(82, min(92, (int)$quality));
    $minQuality = 82;
    $resizeFactor = 0.9;
    $minSide = 1000;
    $writeOk = false;

    while (true) {
        $writeOk = uploadImageWriteResource($workingImage, $targetPath, $mime, $currentQuality);
        if (!$writeOk || !file_exists($targetPath)) {
            break;
        }

        clearstatcache(true, $targetPath);
        $currentSize = @filesize($targetPath);
        if ($currentSize !== false && $currentSize <= $maxSizeBytes) {
            break;
        }

        if ($mime === 'image/jpeg' && $currentQuality > $minQuality) {
            $currentQuality -= 2;
            continue;
        }

        $nextWidth = max(1, (int)floor(imagesx($workingImage) * $resizeFactor));
        $nextHeight = max(1, (int)floor(imagesy($workingImage) * $resizeFactor));
        if (max($nextWidth, $nextHeight) < $minSide) {
            break;
        }

        $resized = uploadImageResizeResource($workingImage, $nextWidth, $nextHeight, $mime);
        if ($resized === $workingImage) {
            break;
        }

        imagedestroy($workingImage);
        $workingImage = $resized;
    }

    if ($writeOk && !$needsResize && $sourceSize !== false) {
        clearstatcache(true, $targetPath);
        $resultSize = @filesize($targetPath);
        if ($resultSize !== false && $resultSize > $sourceSize) {
            $writeOk = copy($sourcePath, $targetPath);
        }
    }

    imagedestroy($workingImage);
    return $writeOk;
}

function gravecompress($sourcePath, $targetPath, $maxSizeKB = 2048, $maxWidth = 1600, $maxHeight = 1600, $quality = 90) {
    return uploadImageCompressSmart($sourcePath, $targetPath, $maxSizeKB, $maxWidth, $maxHeight, $quality);
}

function kladbcompress($sourcePath, $targetPath, $maxSizeKB = 2048, $maxWidth = 1600, $maxHeight = 1600, $quality = 90) {
    return uploadImageCompressSmart($sourcePath, $targetPath, $maxSizeKB, $maxWidth, $maxHeight, $quality);
}

function Captcha(): string
{
    $a = rand(1, 9);
    $b = rand(1, 9);
    $operators = ['+', '-'];
    $op = $operators[array_rand($operators)];

    $question = "$a $op $b";
    $answer = ($op === '+') ? ($a + $b) : ($a - $b);

    $_SESSION['captcha_answer'] = $answer;

    return '
    <div class="captcha-block">
        <form method="post" action="">
            <label>Введіть відповідь: ' . htmlspecialchars($question) . ' = ?</label><br>
            <input type="number" name="captcha_user_answer" required>
            <button type="submit" name="check_captcha">Перевірити</button>
        </form>
    </div>';
}

function compressCard($sourcePath, $targetPath, $maxSizeKB = 300, $maxWidth = 1920, $maxHeight = 1080) {
    $info = getimagesize($sourcePath);
    if (!$info) return false;

    $mime = $info['mime'];
    switch ($mime) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($sourcePath);
            if (function_exists('exif_read_data')) {
                $exif = @exif_read_data($sourcePath);
                if (!empty($exif['Orientation'])) {
                    switch ($exif['Orientation']) {
                        case 3: $image = imagerotate($image, 180, 0); break;
                        case 6: $image = imagerotate($image, -90, 0); break;
                        case 8: $image = imagerotate($image, 90, 0); break;
                    }
                }
            }
            break;
        case 'image/png':
            $image = imagecreatefrompng($sourcePath);
            break;
        default:
            return false;
    }

    $origWidth = imagesx($image);
    $origHeight = imagesy($image);

    if ($origWidth > $maxWidth || $origHeight > $maxHeight) {
        $ratio = min($maxWidth / $origWidth, $maxHeight / $origHeight);
        $newWidth = intval($origWidth * $ratio);
        $newHeight = intval($origHeight * $ratio);
    } else {
        $newWidth = $origWidth;
        $newHeight = $origHeight;
    }

    if ($newWidth != $origWidth || $newHeight != $origHeight) {
        $resized = imagecreatetruecolor($newWidth, $newHeight);

        if ($mime === 'image/png') {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
            imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $transparent);
        }

        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
        imagedestroy($image);
        $image = $resized;
    }

    if ($mime === 'image/jpeg') {
        $quality = 90;
        imagejpeg($image, $targetPath, $quality);
        clearstatcache(true, $targetPath);
        $filesizeKB = filesize($targetPath) / 1024;

        while ($filesizeKB > $maxSizeKB && $quality > 40) {
            $quality -= 5;
            imagejpeg($image, $targetPath, $quality);
            clearstatcache(true, $targetPath);
            $filesizeKB = filesize($targetPath) / 1024;
        }
    }

    elseif ($mime === 'image/png') {
        $compression = 6;
        imagepng($image, $targetPath, $compression);
        clearstatcache(true, $targetPath);
        $filesizeKB = filesize($targetPath) / 1024;

        while ($filesizeKB > $maxSizeKB && $compression < 9) {
            $compression++;
            imagepng($image, $targetPath, $compression);
            clearstatcache(true, $targetPath);
            $filesizeKB = filesize($targetPath) / 1024;
        }
    }

    imagedestroy($image);
    return true;
}

function Menu_Up(): string
{
    $isLogged = isset($_SESSION['logged']) && (int)$_SESSION['logged'] === 1;

    $avatar = '';
    $hasAvatar = false;
    $cash = 0;
    $firstName = '';
    $lastName = '';
    $fullName = 'Користувач';

    if ($isLogged && isset($_SESSION['uzver'])) {
        $dblink = DbConnect();
        $sql = 'SELECT avatar, cash, fname, lname FROM users WHERE idx = ' . intval($_SESSION['uzver']) . ' LIMIT 1';
        $res = mysqli_query($dblink, $sql);

        if ($res && $user = mysqli_fetch_assoc($res)) {
            $avatarCandidate = trim((string)($user['avatar'] ?? ''));
            if ($avatarCandidate !== '') {
                $avatar = $avatarCandidate;
                $hasAvatar = true;
            }
            $cash = (float)($user['cash'] ?? 0);
            $firstName = trim((string)($user['fname'] ?? ''));
            $lastName = trim((string)($user['lname'] ?? ''));
            $resolvedName = trim($firstName . ' ' . $lastName);
            if ($resolvedName !== '') {
                $fullName = $resolvedName;
            }
        }
    }

    $dropdownAvatar = $hasAvatar ? $avatar : '/avatars/ava.png';
    $safeAvatar = htmlspecialchars($dropdownAvatar, ENT_QUOTES, 'UTF-8');
    $safeName = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
    $initialSource = $lastName !== '' ? $lastName : ($firstName !== '' ? $firstName : $fullName);
    $initialRaw = mb_substr($initialSource, 0, 1);
    if ($initialRaw === '') {
        $initialRaw = 'U';
    }
    $safeInitial = htmlspecialchars(mb_strtoupper($initialRaw), ENT_QUOTES, 'UTF-8');
    $formattedCash = number_format($cash, 0, '', '.');

    $uid = substr(md5(uniqid('', true)), 0, 10);
    $profileToggleId = 'menu-up-new-profile-' . $uid;
    $mobileMenuToggleId = 'menu-main-' . $uid;
    $mobileWorkToggleId = 'mobile-work-toggle-' . $uid;

    $currentRequestPath = CurrentPublicRequestPath();
    $isOnProfilePage = ($currentRequestPath === NormalizePublicPath('/profile.php'));
    $mobileAuthText = $isLogged ? ($isOnProfilePage ? 'Меню профілю' : 'Профіль') : 'Увійти';
    $mobileAuthHref = $isLogged ? PublicUrl('/profile.php') : PublicUrl('/auth.php');

    $requestPath = $currentRequestPath;

    $fromPath = '';
    if (isset($_GET['from'])) {
        $fromRaw = trim((string)$_GET['from']);
        if ($fromRaw !== '') {
            $parsedFromPath = strtolower((string)(parse_url($fromRaw, PHP_URL_PATH) ?? ''));
            if ($parsedFromPath !== '') {
                $fromPath = NormalizePublicPath($parsedFromPath);
            }
        }
    }

    $resolvedPath = ($requestPath === NormalizePublicPath('/in-dev.php') && $fromPath !== '') ? $fromPath : $requestPath;

    $isHomeActive = ($resolvedPath === NormalizePublicPath('/index.php'));
    $isServiceCleanActive = ($resolvedPath === NormalizePublicPath('/clean-cemeteries.php'));
    $isServiceProdActive = ($resolvedPath === NormalizePublicPath('/prod-monuments.php'));
    $isServiceOtherActive = ($resolvedPath === NormalizePublicPath('/other-job.php'));
    $isServicesActive = ($isServiceCleanActive || $isServiceProdActive || $isServiceOtherActive);
    $isChurchActive = ($resolvedPath === NormalizePublicPath('/church.php'));
    $isClientsActive = ($resolvedPath === NormalizePublicPath('/clients.php'));
    $isAddGraveActive = ($resolvedPath === NormalizePublicPath('/graveaddform.php'));
    $isModerationPanelActive = ($resolvedPath === NormalizePublicPath('/moderation-panel.php'));

    $canSeeAdmin = $isLogged
        && isset($_SESSION['status'])
        && function_exists('hasRole')
        && defined('ROLE_CREATOR')
        && hasRole($_SESSION['status'], ROLE_CREATOR);

    $canSeeModeration = $isLogged
        && isset($_SESSION['status'])
        && function_exists('hasAnyRole')
        && defined('ROLE_MODERATOR')
        && defined('ROLE_WEBMASTER')
        && defined('ROLE_CREATOR')
        && hasAnyRole($_SESSION['status'], [ROLE_MODERATOR, ROLE_WEBMASTER, ROLE_CREATOR]);

    $moderationButton = '';
    if ($canSeeModeration) {
        $moderationButton = '
            <a href="/moderation-panel.php" class="menu-button menu-up-new-desktop-only" data-tooltip="Панель модерації" aria-label="Панель модерації">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-shield-half">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                    <path d="M12 3a12 12 0 0 0 8.5 3a12 12 0 0 1 -8.5 15a12 12 0 0 1 -8.5 -15a12 12 0 0 0 8.5 -3" />
                    <path d="M12 3v18" />
                </svg>
            </a>';
    }

    $adminButton = '';
    if ($canSeeAdmin) {
        $adminButton = '
            <a href="/admin-panel.php" class="menu-button menu-up-new-desktop-only" data-tooltip="Адмін-панель" aria-label="Адмін-панель">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-user-cog">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                    <path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" />
                    <path d="M6 21v-2a4 4 0 0 1 4 -4h2.5" />
                    <path d="M17.001 19a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                    <path d="M19.001 15.5v1.5" />
                    <path d="M19.001 21v1.5" />
                    <path d="M22.032 17.25l-1.299 .75" />
                    <path d="M17.27 20l-1.3 .75" />
                    <path d="M15.97 17.25l1.3 .75" />
                    <path d="M20.733 20l1.3 .75" />
                </svg>
            </a>';
    }

    $desktopActions = '';
    if ($isLogged) {
        $desktopActions = '
        <div class="header-right menu-up-new-actions menu-up-new-actions-auth">
            ' . $moderationButton . '
            ' . $adminButton . '
            <a href="/messenger.php" class="menu-button menu-up-new-desktop-only" data-tooltip="Чати" aria-label="Чати">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-message-dots">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                    <path d="M12 11v.01" />
                    <path d="M8 11v.01" />
                    <path d="M16 11v.01" />
                    <path d="M18 4a3 3 0 0 1 3 3v8a3 3 0 0 1 -3 3h-5l-5 3v-3h-2a3 3 0 0 1 -3 -3v-8a3 3 0 0 1 3 -3l12 0" />
                </svg>
            </a>

            <a href="/profile.php?md=0&tab=saved" class="menu-button menu-up-new-desktop-only" data-tooltip="Збережене" aria-label="Збережене">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-bookmarks">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                    <path d="M15 10v11l-5 -3l-5 3v-11a3 3 0 0 1 3 -3h4a3 3 0 0 1 3 3" />
                    <path d="M11 3h5a3 3 0 0 1 3 3v11" />
                </svg>
            </a>

            <div class="dropdown menu-up-new-desktop-only">
                <input type="checkbox" id="' . $profileToggleId . '" class="dropdown-toggle profile-toggle">
                <label for="' . $profileToggleId . '" class="avatar-button" data-tooltip="Акаунт" aria-label="Акаунт">
                    <span class="avatar-token">
                        ' . ($hasAvatar
                            ? '<img src="' . $safeAvatar . '" alt="Аватар" class="header-avatar">'
                            : '<span class="header-avatar-fallback">' . $safeInitial . '</span>'
                        ) . '
                    </span>
                    <span class="avatar-caret">&#9662;</span>
                </label>

                <div class="dropdown-menu block">
                    <div class="menu-profile" onclick="window.location.href=\'/profile.php\'">
                        <img src="' . $safeAvatar . '" alt="Аватар" class="menu-avatar">
                        <span class="menu-name">' . $safeName . '</span>
                    </div>

                    <div class="menu-separator"></div>

                    <a href="/profile.php?md=2">
                        <span class="icon-wrapper">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="dpm-icon icon icon-tabler icons-tabler-outline icon-tabler-settings">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                <path d="M10.325 4.317c.426 -1.756 2.924 -1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543 -.94 3.31 .826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756 .426 1.756 2.924 0 3.35a1.724 1.724 0 0 0 -1.066 2.573c.94 1.543 -.826 3.31 -2.37 2.37a1.724 1.724 0 0 0 -2.572 1.065c-.426 1.756 -2.924 1.756 -3.35 0a1.724 1.724 0 0 0 -2.573 -1.066c-1.543 .94 -3.31 -.826 -2.37 -2.37a1.724 1.724 0 0 0 -1.065 -2.572c-1.756 -.426 -1.756 -2.924 0 -3.35a1.724 1.724 0 0 0 1.066 -2.573c-.94 -1.543 .826 -3.31 2.37 -2.37c1 .608 2.296 .07 2.572 -1.065" />
                                <path d="M9 12a3 3 0 1 0 6 0a3 3 0 0 0 -6 0" />
                            </svg>
                        </span>
                        Налаштування профілю
                    </a>

                    <a href="/profile.php?md=11">
                        <span class="icon-wrapper">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="dpm-icon icon icon-tabler icons-tabler-outline icon-tabler-bell">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                <path d="M10 5a2 2 0 1 1 4 0a7 7 0 0 1 4 6v3a4 4 0 0 0 2 3h-16a4 4 0 0 0 2 -3v-3a7 7 0 0 1 4 -6" />
                                <path d="M9 17v1a3 3 0 0 0 6 0v-1" />
                            </svg>
                        </span>
                        Повідомлення
                    </a>

                    <a href="/profile.php?md=4">
                        <span class="icon-wrapper">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="dpm-icon icon icon-tabler icons-tabler-outline icon-tabler-wallet">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                <path d="M17 8v-3a1 1 0 0 0 -1 -1h-10a2 2 0 0 0 0 4h12a1 1 0 0 1 1 1v3m0 4v3a1 1 0 0 1 -1 1h-12a2 2 0 0 1 -2 -2v-12" />
                                <path d="M20 12v4h-4a2 2 0 0 1 0 -4h4" />
                            </svg>
                        </span>
                        Баланс: ' . $formattedCash . ' ₴
                    </a>

                    <a href="/messenger.php?type=3">
                        <span class="icon-wrapper">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="dpm-icon icon icon-tabler icons-tabler-outline icon-tabler-messages">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                <path d="M21 14l-3 -3h-7a1 1 0 0 1 -1 -1v-6a1 1 0 0 1 1 -1h9a1 1 0 0 1 1 1v10" />
                                <path d="M14 15v2a1 1 0 0 1 -1 1h-7l-3 3v-10a1 1 0 0 1 1 -1h2" />
                            </svg>
                        </span>
                        Підтримка
                    </a>

                    <div class="menu-separator"></div>

                    <a href="/profile.php?exit=1">
                        <span class="icon-wrapper">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="dpm-icon icon icon-tabler icons-tabler-outline icon-tabler-logout">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                <path d="M14 8v-2a2 2 0 0 0 -2 -2h-7a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h7a2 2 0 0 0 2 -2v-2" />
                                <path d="M9 12h12l-3 -3" />
                                <path d="M18 15l3 -3" />
                            </svg>
                        </span>
                        Вийти
                    </a>
                </div>
            </div>

        </div>';
    } else {
        $desktopActions = '
        <div class="menu-up-new-actions menu-up-new-actions-guest">
            <a class="menu-up-new-text-btn menu-up-new-desktop-only" href="/messenger.php?type=3">Підтримка</a>
            <a class="menu-up-new-text-btn menu-up-new-text-btn-main menu-up-new-desktop-only" href="/auth.php">Увійти</a>
        </div>';
    }

    static $cssIncluded = false;
    $cssLink = '';
    if (!$cssIncluded) {
        $cssLink = '<link rel="stylesheet" href="/assets/css/menu.css">';
        $cssIncluded = true;
    }

    static $profileScriptIncluded = false;
    $profileScript = '';
    if (!$profileScriptIncluded) {
        $profileScript = '
<script>
document.addEventListener("DOMContentLoaded", function () {
    const profileToggles = Array.from(document.querySelectorAll(".menu-up-new .profile-toggle"));
    if (!profileToggles.length) return;

    document.addEventListener("click", function (e) {
        profileToggles.forEach(function (toggle) {
            const dropdown = toggle.closest(".dropdown");
            if (dropdown && !dropdown.contains(e.target)) {
                toggle.checked = false;
            }
        });
    });

    profileToggles.forEach(function (toggle) {
        toggle.addEventListener("change", function () {
            if (!toggle.checked) return;
            profileToggles.forEach(function (other) {
                if (other !== toggle) other.checked = false;
            });
        });
    });
});
</script>';
        $profileScriptIncluded = true;
    }

    $mobileModerationLink = '';
    if ($canSeeModeration) {
        $mobileModerationLink = '<li><a' . ($isModerationPanelActive ? ' class="is-active"' : '') . ' href="/moderation-panel.php">Панель модерації</a></li>';
    }

    $mobileMenu = '
        <div class="mobile-menu-topbar">
            <a href="/" class="mobile-menu-brand">
                <span class="mobile-menu-brand-title">ІПС Шана</span>
                <span class="mobile-menu-brand-sub">SHANAPRA.COM</span>
            </a>
            <div class="mobile-menu-actions">
                ' . ($isLogged && $isOnProfilePage
                    ? '<button class="mobile-menu-auth mobile-open-profile-btn" type="button">' . $mobileAuthText . '</button>'
                    : '<a class="mobile-menu-auth" href="' . $mobileAuthHref . '">' . $mobileAuthText . '</a>'
                ) . '

                <div class="dropdown nav mobile-burger">
                    <input type="checkbox" id="' . $mobileMenuToggleId . '" class="dropdown-toggle menu-up-new-mobile-toggle">
                    <label for="' . $mobileMenuToggleId . '" class="menu-button nav" data-tooltip="Меню" aria-label="Меню">
                        <div class="burger">
                          <span></span>
                          <span></span>
                          <span></span>
                        </div>
                    </label>

                    <div class="dropdown-menu nav">
                        <div class="mobile-menu-header">
                            <span class="menu-name">Меню</span>
                            <label for="' . $mobileMenuToggleId . '" class="mobile-menu-close-btn" aria-label="Закрити меню">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </label>
                        </div>
                        <ul class="menu_ups_mobile">
                            <li><a' . ($isHomeActive ? ' class="is-active"' : '') . ' href="/">Головна</a></li>
                            <li class="has-submenu">
                                <input type="checkbox" id="' . $mobileWorkToggleId . '" class="submenu-toggle"' . ($isServicesActive ? ' checked' : '') . '>
                                <label for="' . $mobileWorkToggleId . '" class="submenu-label' . ($isServicesActive ? ' is-active' : '') . '">Робота</label>
                                <ul class="submenu-mobile">
                                    <li><a' . ($isServiceCleanActive ? ' class="is-active"' : '') . ' href="/clean-cemeteries.php">Прибирання кладовищ</a></li>
                                    <li><a' . ($isServiceProdActive ? ' class="is-active"' : '') . ' href="/in-dev.php?from=/prod-monuments.php">Виготовлення пам`ятників</a></li>
                                    <li><a' . ($isServiceOtherActive ? ' class="is-active"' : '') . ' href="/in-dev.php?from=/other-job.php">Інші роботи</a></li>
                                </ul>
                            </li>
                            <li><a' . ($isChurchActive ? ' class="is-active"' : '') . ' href="/in-dev.php?from=/church.php">Церкви</a></li>
                            <li><a' . ($isClientsActive ? ' class="is-active"' : '') . ' href="/in-dev.php?from=/clients.php">Наші клієнти</a></li>
                            <li><a' . ($isAddGraveActive ? ' class="is-active"' : '') . ' href="/graveaddform.php">Додати поховання</a></li>
                            ' . $mobileModerationLink . '
                        </ul>
                        <hr class="mobile-menu-divider">
                        <div class="mobile-menu-blocks' . ($isLogged ? '' : ' mobile-menu-blocks-single') . '">
                            ' . ($isLogged ? '<a href="/messenger.php" class="mobile-menu-block">
                                <svg class="mobile-menu-block-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                                <span>Месенджер</span>
                            </a>' : '') . '
                            <a href="/messenger.php?type=3" class="mobile-menu-block">
                                <svg class="mobile-menu-block-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 18v-6a9 9 0 0 1 18 0v6"/><path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"/></svg>
                                <span>Технічна підтримка</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>';

    static $mobileBurgerScriptIncluded = false;
    $mobileBurgerScript = '';
    if (!$mobileBurgerScriptIncluded) {
        $mobileBurgerScript = '
<script>
document.addEventListener("DOMContentLoaded", function () {
    const burgerToggles = Array.from(document.querySelectorAll(".menu-up-new .mobile-burger .menu-up-new-mobile-toggle"));
    if (!burgerToggles.length) return;

    function setBodyState() {
        const isAnyOpened = burgerToggles.some(function (toggle) { return !!toggle.checked; });
        document.body.classList.toggle("burger-open", isAnyOpened);
    }

    burgerToggles.forEach(function (toggle) {
        toggle.addEventListener("change", function () {
            if (toggle.checked) {
                burgerToggles.forEach(function (other) {
                    if (other !== toggle) other.checked = false;
                });
            }
            setBodyState();
        });
    });

    document.addEventListener("keydown", function (e) {
        if (e.key !== "Escape") return;
        burgerToggles.forEach(function (toggle) { toggle.checked = false; });
        setBodyState();
    });

    const mobileLinks = Array.from(document.querySelectorAll(".menu-up-new .mobile-burger .dropdown-menu.nav a"));
    mobileLinks.forEach(function (link) {
        link.addEventListener("click", function () {
            burgerToggles.forEach(function (toggle) { toggle.checked = false; });
            setBodyState();
        });
    });

    setBodyState();
});
</script>';
        $mobileBurgerScriptIncluded = true;
    }

    return $cssLink . $profileScript . $mobileBurgerScript . '
    <div class="menu-up-new-spacer" aria-hidden="true"></div>
    <header class="menu-up-new">
        ' . $mobileMenu . '

        <div class="menu-up-new-wrap">
            <a class="menu-up-new-brand" href="/">
                <span class="menu-up-new-brand-mark">
                    <img src="/assets/images/logobrand3.png" alt="Логотип">
                </span>
                <span class="menu-up-new-brand-text">
                    <strong>ІПС Шана</strong>
                    <small>Інформаційно Пошукова Система</small>
                </span>
            </a>

            <nav class="menu-up-new-nav" aria-label="Головна навігація">
                <a' . ($isHomeActive ? ' class="is-active"' : '') . ' href="/">Головна</a>

                <div class="menu-up-new-nav-group">
                    <span class="menu-up-new-nav-trigger' . ($isServicesActive ? ' is-active' : '') . '">Послуги</span>
                    <div class="menu-up-new-submenu" role="menu">
                        <a' . ($isServiceCleanActive ? ' class="is-active"' : '') . ' href="/clean-cemeteries.php">Прибирання кладовищ</a>
                        <a' . ($isServiceProdActive ? ' class="is-active"' : '') . ' href="/in-dev.php?from=/prod-monuments.php">Виготовлення пам\'ятників</a>
                        <a' . ($isServiceOtherActive ? ' class="is-active"' : '') . ' href="/in-dev.php?from=/other-job.php">Інші роботи</a>
                    </div>
                </div>

                <a' . ($isChurchActive ? ' class="is-active"' : '') . ' href="/in-dev.php?from=/church.php">Церкви</a>
                <a' . ($isClientsActive ? ' class="is-active"' : '') . ' href="/in-dev.php?from=/clients.php">Наші клієнти</a>
                <a' . ($isAddGraveActive ? ' class="is-active"' : '') . ' href="/graveaddform.php">Додати поховання</a>
            </nav>

            ' . $desktopActions . '
        </div>
    </header>';
}

