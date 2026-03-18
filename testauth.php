<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/function.php';
require_once __DIR__ . '/validator.php';
require_once __DIR__ . '/mailer.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = (string)$_SESSION['csrf_token'];

if (isset($_SESSION['logged']) && (int)$_SESSION['logged'] === 1 && empty($_GET['reset_token'])) {
    header('Location: /profile.php');
    exit;
}

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function validateEmailValue(string $email, string &$error): string
{
    $email = trim($email);
    if ($email === '') {
        $error = 'Введіть e-mail';
        return '';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Некоректний формат e-mail';
        return '';
    }

    return strtolower($email);
}

function validateNameValue(string $name, string $fieldLabel, string &$error): string
{
    $name = trim($name);
    if ($name === '') {
        $error = 'Вкажіть ' . $fieldLabel;
        return '';
    }

    if (!preg_match('/^[A-Za-zА-Яа-яІіЇїЄєЁё\'\-\s]{2,}$/u', $name)) {
        $error = 'Некоректне поле: ' . $fieldLabel;
        return '';
    }

    return $name;
}

function validatePasswordValue(string $password, string &$error): string
{
    if ($password === '') {
        $error = 'Введіть пароль';
        return '';
    }

    if (strlen($password) < 8 || strlen($password) > 64) {
        $error = 'Пароль має містити від 8 до 64 символів';
        return '';
    }

    if (preg_match('/[А-Яа-яЁёІіЇїЄє]/u', $password)) {
        $error = 'Пароль повинен бути лише латиницею';
        return '';
    }

    if (!preg_match('/[a-z]/', $password)) {
        $error = 'У паролі має бути мінімум одна мала літера';
        return '';
    }

    $forbidden = ['password', '123456', 'qwerty', 'admin', 'user', 'test'];
    foreach ($forbidden as $badWord) {
        if (stripos($password, $badWord) !== false) {
            $error = 'Пароль містить занадто просту комбінацію';
            return '';
        }
    }

    return $password;
}

function ensurePasswordResetTable(mysqli $dblink): void
{
    $sql = "CREATE TABLE IF NOT EXISTS password_reset_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token VARCHAR(128) NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_token (token),
        INDEX idx_expires_at (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    mysqli_query($dblink, $sql);
}

function getResetUserId(mysqli $dblink, string $token): int
{
    $stmt = mysqli_prepare(
        $dblink,
        "SELECT user_id FROM password_reset_tokens WHERE token=? AND expires_at > NOW() LIMIT 1"
    );

    if (!$stmt) {
        return 0;
    }

    mysqli_stmt_bind_param($stmt, 's', $token);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $userId);
    $found = mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    return $found ? (int)$userId : 0;
}

$activeMode = (($_GET['mode'] ?? '') === 'register') ? 'register' : 'login';

$loginEmail = '';
$registerEmail = '';
$registerLname = '';
$registerFname = '';
$forgotEmail = '';

$loginError = '';
$loginSuccess = '';
$registerError = '';
$registerSuccess = '';
$forgotError = '';
$forgotSuccess = '';
$resetError = '';
$resetSuccess = '';

$openForgotModal = false;
$forgotView = 'request';
$resetToken = trim((string)($_GET['reset_token'] ?? $_POST['reset_token'] ?? ''));

if ($resetToken !== '' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $dblink = DbConnect();
    ensurePasswordResetTable($dblink);
    $resetUserId = getResetUserId($dblink, $resetToken);
    mysqli_close($dblink);

    if ($resetUserId > 0) {
        $openForgotModal = true;
        $forgotView = 'reset';
    } else {
        $openForgotModal = true;
        $forgotView = 'request';
        $forgotError = 'Посилання недійсне або вже застаріло.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));
    $postedCsrf = (string)($_POST['csrf_token'] ?? '');

    if (!hash_equals($csrfToken, $postedCsrf)) {
        if ($action === 'register') {
            $activeMode = 'register';
            $registerError = 'Сесію оновлено. Повторіть надсилання форми.';
        } elseif ($action === 'forgot_request' || $action === 'reset_password') {
            $openForgotModal = true;
            $forgotView = 'request';
            $forgotError = 'Сесію оновлено. Повторіть дію.';
        } else {
            $activeMode = 'login';
            $loginError = 'Сесію оновлено. Повторіть вхід.';
        }
    } else {
        if ($action === 'login') {
            $activeMode = 'login';
            $loginEmail = trim((string)($_POST['emailForLogin'] ?? ''));
            $loginPassword = (string)($_POST['paswForLogin'] ?? '');

            $emailError = '';
            $normalizedEmail = validateEmailValue($loginEmail, $emailError);

            if ($normalizedEmail === '') {
                $loginError = $emailError;
            } elseif ($loginPassword === '') {
                $loginError = 'Введіть пароль';
            } else {
                $dblink = DbConnect();
                $stmt = mysqli_prepare($dblink, "SELECT idx, pasw, status FROM users WHERE email=? LIMIT 1");

                if (!$stmt) {
                    $loginError = 'Помилка запиту до бази даних';
                } else {
                    mysqli_stmt_bind_param($stmt, 's', $normalizedEmail);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_bind_result($stmt, $userId, $storedHash, $userStatus);

                    if (mysqli_stmt_fetch($stmt)) {
                        $passwordRawHash = md5($loginPassword);
                        $passwordEscapedHash = md5(htmlspecialchars($loginPassword, ENT_QUOTES, 'UTF-8'));
                        $isPasswordValid = hash_equals($storedHash, $passwordRawHash) || hash_equals($storedHash, $passwordEscapedHash);

                        if ($isPasswordValid) {
                            mysqli_stmt_close($stmt);
                            mysqli_close($dblink);

                            session_regenerate_id(true);
                            $_SESSION['logged'] = 1;
                            $_SESSION['uzver'] = (int)$userId;
                            $_SESSION['status'] = (int)$userStatus;
                            $_SESSION['last_activity'] = time();

                            if (function_exists('createUserSession')) {
                                $sessionId = session_id();
                                $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                                $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                                createUserSession((int)$userId, $sessionId, $ip, $userAgent);
                            }

                            header('Location: /profile.php');
                            exit;
                        }

                        $loginError = 'Користувача з таким e-mail або паролем не знайдено.';
                    } else {
                        $loginError = 'Користувача з таким e-mail або паролем не знайдено.';
                    }

                    mysqli_stmt_close($stmt);
                }

                mysqli_close($dblink);
            }
        } elseif ($action === 'register') {
            $activeMode = 'register';
            $registerLname = trim((string)($_POST['lname'] ?? ''));
            $registerFname = trim((string)($_POST['fname'] ?? ''));
            $registerEmail = trim((string)($_POST['email'] ?? ''));
            $registerPassword = (string)($_POST['password'] ?? '');
            $registerPasswordConfirm = (string)($_POST['password_confirm'] ?? '');

            $lnameError = '';
            $fnameError = '';
            $emailError = '';
            $passwordError = '';

            $normalizedLname = validateNameValue($registerLname, 'прізвище', $lnameError);
            $normalizedFname = validateNameValue($registerFname, "ім'я", $fnameError);
            $normalizedEmail = validateEmailValue($registerEmail, $emailError);
            $normalizedPassword = validatePasswordValue($registerPassword, $passwordError);

            if ($normalizedLname === '') {
                $registerError = $lnameError;
            } elseif ($normalizedFname === '') {
                $registerError = $fnameError;
            } elseif ($normalizedEmail === '') {
                $registerError = $emailError;
            } elseif ($normalizedPassword === '') {
                $registerError = $passwordError;
            } elseif ($registerPassword !== $registerPasswordConfirm) {
                $registerError = 'Паролі не збігаються';
            } else {
                $dblink = DbConnect();

                $stmtCheck = mysqli_prepare($dblink, "SELECT idx FROM users WHERE email=? LIMIT 1");
                if (!$stmtCheck) {
                    $registerError = 'Помилка перевірки користувача';
                } else {
                    mysqli_stmt_bind_param($stmtCheck, 's', $normalizedEmail);
                    mysqli_stmt_execute($stmtCheck);
                    mysqli_stmt_store_result($stmtCheck);

                    if (mysqli_stmt_num_rows($stmtCheck) > 0) {
                        $registerError = 'Користувач з таким e-mail вже існує';
                    } else {
                        $passwordHash = md5($normalizedPassword);
                        $userStatus = 1;
                        $stmtInsert = mysqli_prepare(
                            $dblink,
                            "INSERT INTO users (email, pasw, fname, lname, status) VALUES (?, ?, ?, ?, ?)"
                        );

                        if (!$stmtInsert) {
                            $registerError = 'Помилка створення акаунта';
                        } else {
                            mysqli_stmt_bind_param(
                                $stmtInsert,
                                'ssssi',
                                $normalizedEmail,
                                $passwordHash,
                                $normalizedFname,
                                $normalizedLname,
                                $userStatus
                            );

                            if (mysqli_stmt_execute($stmtInsert)) {
                                $newUserId = (int)mysqli_insert_id($dblink);
                                if (function_exists('sendActivationEmail')) {
                                    sendActivationEmail($newUserId, $normalizedEmail, $normalizedFname, $dblink);
                                }

                                $registerSuccess = 'Реєстрація успішна. Тепер увійдіть у свій акаунт.';
                                $loginSuccess = $registerSuccess;
                                $activeMode = 'login';
                                $loginEmail = $normalizedEmail;
                                $registerLname = '';
                                $registerFname = '';
                                $registerEmail = '';
                            } else {
                                $registerError = 'Не вдалося створити акаунт. Спробуйте ще раз.';
                            }

                            mysqli_stmt_close($stmtInsert);
                        }
                    }

                    mysqli_stmt_close($stmtCheck);
                }

                mysqli_close($dblink);
            }
        } elseif ($action === 'forgot_request') {
            $openForgotModal = true;
            $forgotView = 'request';
            $forgotEmail = trim((string)($_POST['email'] ?? ''));

            $emailError = '';
            $normalizedEmail = validateEmailValue($forgotEmail, $emailError);

            if ($normalizedEmail === '') {
                $forgotError = $emailError;
            } else {
                $dblink = DbConnect();
                ensurePasswordResetTable($dblink);

                $stmtUser = mysqli_prepare($dblink, "SELECT idx, fname FROM users WHERE email=? LIMIT 1");
                if ($stmtUser) {
                    mysqli_stmt_bind_param($stmtUser, 's', $normalizedEmail);
                    mysqli_stmt_execute($stmtUser);
                    mysqli_stmt_bind_result($stmtUser, $userId, $fname);

                    if (mysqli_stmt_fetch($stmtUser)) {
                        $userId = (int)$userId;
                        $userName = trim((string)$fname) !== '' ? (string)$fname : 'Користувач';
                        mysqli_stmt_close($stmtUser);

                        $stmtDelete = mysqli_prepare($dblink, "DELETE FROM password_reset_tokens WHERE user_id=?");
                        if ($stmtDelete) {
                            mysqli_stmt_bind_param($stmtDelete, 'i', $userId);
                            mysqli_stmt_execute($stmtDelete);
                            mysqli_stmt_close($stmtDelete);
                        }

                        $resetTokenValue = bin2hex(random_bytes(32));
                        $stmtInsertToken = mysqli_prepare(
                            $dblink,
                            "INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))"
                        );

                        if ($stmtInsertToken) {
                            mysqli_stmt_bind_param($stmtInsertToken, 'is', $userId, $resetTokenValue);
                            $tokenSaved = mysqli_stmt_execute($stmtInsertToken);
                            mysqli_stmt_close($stmtInsertToken);

                            if ($tokenSaved && function_exists('sendPasswordResetEmail')) {
                                $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' && $_SERVER['HTTPS'] !== '';
                                $protocol = $isHttps ? 'https' : 'http';
                                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                                $resetLink = $protocol . '://' . $host . '/testauth.php?reset_token=' . urlencode($resetTokenValue);
                                sendPasswordResetEmail($userId, $normalizedEmail, $userName, $resetLink);
                            }
                        }
                    } else {
                        mysqli_stmt_close($stmtUser);
                    }
                }

                mysqli_close($dblink);
                $forgotView = 'success';
                $forgotSuccess = 'Якщо обліковий запис існує, на пошту надіслано посилання для відновлення пароля.';
            }
        } elseif ($action === 'reset_password') {
            $openForgotModal = true;
            $forgotView = 'reset';
            $resetToken = trim((string)($_POST['reset_token'] ?? ''));
            $newPassword = (string)($_POST['new_password'] ?? '');
            $newPasswordConfirm = (string)($_POST['new_password_confirm'] ?? '');

            $passwordError = '';
            $normalizedPassword = validatePasswordValue($newPassword, $passwordError);

            if ($resetToken === '') {
                $forgotView = 'request';
                $forgotError = 'Недійсне посилання. Повторіть запит на відновлення.';
            } elseif ($normalizedPassword === '') {
                $resetError = $passwordError;
            } elseif ($newPassword !== $newPasswordConfirm) {
                $resetError = 'Паролі не збігаються';
            } else {
                $dblink = DbConnect();
                ensurePasswordResetTable($dblink);
                $resetUserId = getResetUserId($dblink, $resetToken);

                if ($resetUserId <= 0) {
                    $forgotView = 'request';
                    $forgotError = 'Посилання недійсне або вже застаріло.';
                } else {
                    $newHash = md5($normalizedPassword);

                    $stmtUpdate = mysqli_prepare($dblink, "UPDATE users SET pasw=? WHERE idx=?");
                    if ($stmtUpdate) {
                        mysqli_stmt_bind_param($stmtUpdate, 'si', $newHash, $resetUserId);
                        mysqli_stmt_execute($stmtUpdate);
                        mysqli_stmt_close($stmtUpdate);
                    }

                    $stmtDelete = mysqli_prepare($dblink, "DELETE FROM password_reset_tokens WHERE token=?");
                    if ($stmtDelete) {
                        mysqli_stmt_bind_param($stmtDelete, 's', $resetToken);
                        mysqli_stmt_execute($stmtDelete);
                        mysqli_stmt_close($stmtDelete);
                    }

                    $forgotView = 'success';
                    $resetSuccess = 'Пароль успішно змінено. Тепер увійдіть із новим паролем.';
                    $activeMode = 'login';
                }

                mysqli_close($dblink);
            }
        }
    }
}

if ($forgotError !== '' || $forgotSuccess !== '' || $resetError !== '' || $resetSuccess !== '') {
    $openForgotModal = true;
}

$cssVersion = is_file(__DIR__ . '/auth.css') ? (string)filemtime(__DIR__ . '/auth.css') : '1';
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ІПС Shana | Вхід та реєстрація</title>
    <link rel="stylesheet" href="/auth.css?v=<?= h($cssVersion) ?>">
</head>
<body class="<?= $activeMode === 'register' ? 'ta-theme-register' : '' ?>">
<main class="ta-shell" data-mode="<?= h($activeMode) ?>" id="ta-shell">
    <section class="ta-brand">
        <div class="ta-logo">S</div>
        <h1>ІПС Shana<br>доступ до акаунта</h1>
        <p>Увійдіть або створіть акаунт, щоб керувати профілем, публікаціями та персональними налаштуваннями.</p>
        <ul class="ta-points">
            <li>Доступ до керування похованнями та профілем</li>
            <li>Швидкий вхід через e-mail або Google</li>
            <li>Безпечне відновлення доступу через пошту</li>
        </ul>
    </section>

    <section class="ta-forms">
        <div class="ta-switch" role="tablist" aria-label="Перемикач форм">
            <button type="button" id="tab-login" role="tab" aria-selected="<?= $activeMode === 'login' ? 'true' : 'false' ?>" aria-controls="form-login">Вхід</button>
            <button type="button" id="tab-register" role="tab" aria-selected="<?= $activeMode === 'register' ? 'true' : 'false' ?>" aria-controls="form-register">Реєстрація</button>
        </div>

        <h2 class="ta-title" id="ta-title"><?= $activeMode === 'login' ? 'Ласкаво просимо знову' : 'Створіть акаунт' ?></h2>
        <p class="ta-subtitle" id="ta-subtitle"><?= $activeMode === 'login' ? 'Введіть e-mail і пароль, щоб продовжити роботу.' : 'Заповніть поля, щоб зареєструвати новий профіль.' ?></p>

        <div class="ta-form-stage" id="ta-form-stage">
            <form class="ta-form <?= $activeMode === 'login' ? 'is-active' : '' ?>" id="form-login" method="post" novalidate>
                <input type="hidden" name="action" value="login">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">

                <?php if ($loginError !== ''): ?>
                    <div class="ta-alert ta-alert--error ta-alert--server"><?= h($loginError) ?></div>
                <?php elseif ($loginSuccess !== ''): ?>
                    <div class="ta-alert ta-alert--success ta-alert--server"><?= h($loginSuccess) ?></div>
                <?php endif; ?>
                <div class="ta-alert ta-alert--error ta-alert--client" data-form-error hidden></div>

                <div class="ta-field">
                    <label for="ta-login-email">E-mail</label>
                    <input id="ta-login-email" type="email" name="emailForLogin" placeholder="name@example.com" required value="<?= h($loginEmail) ?>">
                </div>
                <div class="ta-field">
                    <label for="ta-login-password">Пароль</label>
                    <div class="ta-pass-wrap">
                        <input id="ta-login-password" type="password" name="paswForLogin" placeholder="Введіть пароль" required autocomplete="current-password">
                        <button type="button" class="ta-eye" data-toggle-pass="ta-login-password">ПОКАЗ</button>
                    </div>
                </div>
                <div class="ta-help">
                    <button type="button" class="ta-link ta-link-btn" id="ta-forgot-open">Забули пароль?</button>
                </div>
                <button type="submit" class="ta-btn">Увійти</button>
                <a class="ta-alt" href="/oauth.php">
                    <img src="/assets/images/Google_Favicon_2025.png" alt="Google">
                    Увійти через Google
                </a>
            </form>

            <form class="ta-form <?= $activeMode === 'register' ? 'is-active' : '' ?>" id="form-register" method="post" novalidate>
                <input type="hidden" name="action" value="register">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">

                <?php if ($registerError !== ''): ?>
                    <div class="ta-alert ta-alert--error ta-alert--server"><?= h($registerError) ?></div>
                <?php elseif ($registerSuccess !== ''): ?>
                    <div class="ta-alert ta-alert--success ta-alert--server"><?= h($registerSuccess) ?></div>
                <?php endif; ?>
                <div class="ta-alert ta-alert--error ta-alert--client" data-form-error hidden></div>

                <div class="ta-row">
                    <div class="ta-field">
                        <label for="ta-lname">Прізвище</label>
                        <input id="ta-lname" type="text" name="lname" required value="<?= h($registerLname) ?>" autocomplete="family-name">
                    </div>
                    <div class="ta-field">
                        <label for="ta-fname">Ім'я</label>
                        <input id="ta-fname" type="text" name="fname" required value="<?= h($registerFname) ?>" autocomplete="given-name">
                    </div>
                </div>
                <div class="ta-field">
                    <label for="ta-reg-email">E-mail</label>
                    <input id="ta-reg-email" type="email" name="email" placeholder="name@example.com" required value="<?= h($registerEmail) ?>" autocomplete="email">
                </div>
                <div class="ta-field">
                    <label for="ta-reg-pass">Пароль</label>
                    <div class="ta-pass-wrap">
                        <input id="ta-reg-pass" type="password" name="password" placeholder="Мінімум 8 символів" required autocomplete="new-password">
                        <button type="button" class="ta-eye" data-toggle-pass="ta-reg-pass">ПОКАЗ</button>
                    </div>
                </div>
                <div class="ta-field">
                    <label for="ta-reg-pass-confirm">Підтвердження пароля</label>
                    <div class="ta-pass-wrap">
                        <input id="ta-reg-pass-confirm" type="password" name="password_confirm" placeholder="Повторіть пароль" required autocomplete="new-password">
                        <button type="button" class="ta-eye" data-toggle-pass="ta-reg-pass-confirm">ПОКАЗ</button>
                    </div>
                </div>
                <button type="submit" class="ta-btn">Створити акаунт</button>
                <a class="ta-alt" href="/oauth.php">
                    <img src="/assets/images/Google_Favicon_2025.png" alt="Google">
                    Продовжити через Google
                </a>
            </form>
        </div>

        <div class="ta-note">Потрібна допомога? <a href="/messenger.php?type=3" class="ta-link">Звернутися в технічну підтримку</a></div>
    </section>
</main>

<div class="ta-modal<?= $openForgotModal ? ' is-open' : '' ?>" id="ta-forgot-modal" aria-hidden="<?= $openForgotModal ? 'false' : 'true' ?>">
    <div class="ta-modal__backdrop" data-modal-close></div>
    <div class="ta-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="ta-modal-title">
        <button type="button" class="ta-modal__close" data-modal-close aria-label="Закрити">×</button>

        <?php if ($forgotView === 'reset' && $resetSuccess === ''): ?>
            <h3 class="ta-modal__title" id="ta-modal-title">Встановити новий пароль</h3>
            <p class="ta-modal__subtitle">Введіть новий пароль для вашого акаунта.</p>
            <?php if ($resetError !== ''): ?>
                <div class="ta-alert ta-alert--error"><?= h($resetError) ?></div>
            <?php endif; ?>
            <div class="ta-alert ta-alert--error ta-alert--client" data-form-error hidden></div>
            <form method="post" id="form-reset-password" novalidate>
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <input type="hidden" name="reset_token" value="<?= h($resetToken) ?>">
                <div class="ta-field">
                    <label for="ta-reset-pass">Новий пароль</label>
                    <div class="ta-pass-wrap">
                        <input id="ta-reset-pass" type="password" name="new_password" placeholder="Мінімум 8 символів" required autocomplete="new-password">
                        <button type="button" class="ta-eye" data-toggle-pass="ta-reset-pass">ПОКАЗ</button>
                    </div>
                </div>
                <div class="ta-field">
                    <label for="ta-reset-pass-confirm">Підтвердіть пароль</label>
                    <div class="ta-pass-wrap">
                        <input id="ta-reset-pass-confirm" type="password" name="new_password_confirm" placeholder="Повторіть пароль" required autocomplete="new-password">
                        <button type="button" class="ta-eye" data-toggle-pass="ta-reset-pass-confirm">ПОКАЗ</button>
                    </div>
                </div>
                <button type="submit" class="ta-btn">Зберегти пароль</button>
            </form>
        <?php elseif ($forgotView === 'success'): ?>
            <h3 class="ta-modal__title" id="ta-modal-title">Відновлення пароля</h3>
            <p class="ta-modal__subtitle">
                <?= h($resetSuccess !== '' ? $resetSuccess : $forgotSuccess) ?>
            </p>
            <div class="ta-modal__actions">
                <button type="button" class="ta-btn ta-btn--ghost" data-modal-close>Закрити</button>
            </div>
        <?php else: ?>
            <h3 class="ta-modal__title" id="ta-modal-title">Забули пароль?</h3>
            <p class="ta-modal__subtitle">Введіть ваш e-mail, і ми надішлемо посилання для відновлення.</p>
            <?php if ($forgotError !== ''): ?>
                <div class="ta-alert ta-alert--error"><?= h($forgotError) ?></div>
            <?php endif; ?>
            <div class="ta-alert ta-alert--error ta-alert--client" data-form-error hidden></div>
            <form method="post" id="form-forgot-password" novalidate>
                <input type="hidden" name="action" value="forgot_request">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <div class="ta-field">
                    <label for="ta-forgot-email">E-mail</label>
                    <input id="ta-forgot-email" type="email" name="email" placeholder="name@example.com" required value="<?= h($forgotEmail) ?>" autocomplete="email">
                </div>
                <button type="submit" class="ta-btn">Надіслати посилання</button>
            </form>
        <?php endif; ?>
    </div>
</div>
<script>
    (function () {
        var shell = document.getElementById("ta-shell");
        var tabLogin = document.getElementById("tab-login");
        var tabRegister = document.getElementById("tab-register");
        var formStage = document.getElementById("ta-form-stage");
        var formLogin = document.getElementById("form-login");
        var formRegister = document.getElementById("form-register");
        var title = document.getElementById("ta-title");
        var subtitle = document.getElementById("ta-subtitle");
        var forgotModal = document.getElementById("ta-forgot-modal");
        var forgotOpen = document.getElementById("ta-forgot-open");
        var formStageExtra = 16;
        var activeMode = shell.getAttribute("data-mode") === "register" ? "register" : "login";

        function getActiveForm() {
            return activeMode === "login" ? formLogin : formRegister;
        }

        function getStageHeight(form) {
            return form.offsetHeight + formStageExtra;
        }

        function animateStageHeight(nextHeight) {
            var currentHeight = formStage.offsetHeight;
            if (Math.abs(currentHeight - nextHeight) < 1) {
                formStage.style.height = nextHeight + "px";
                return;
            }
            formStage.style.height = currentHeight + "px";
            void formStage.offsetHeight;
            requestAnimationFrame(function () {
                formStage.style.height = nextHeight + "px";
            });
        }

        function setMode(mode) {
            if (mode === activeMode) return;
            var isLogin = mode === "login";
            var nextForm = isLogin ? formLogin : formRegister;
            var prevForm = isLogin ? formRegister : formLogin;

            formStage.style.height = getStageHeight(prevForm) + "px";
            activeMode = mode;

            shell.setAttribute("data-mode", isLogin ? "login" : "register");
            tabLogin.setAttribute("aria-selected", isLogin ? "true" : "false");
            tabRegister.setAttribute("aria-selected", isLogin ? "false" : "true");

            prevForm.classList.remove("is-active");
            nextForm.classList.add("is-active");
            animateStageHeight(getStageHeight(nextForm));
            syncThemeClass(mode);

            title.textContent = isLogin ? "Ласкаво просимо знову" : "Створіть акаунт";
            subtitle.textContent = isLogin
                ? "Введіть e-mail і пароль, щоб продовжити роботу."
                : "Заповніть поля, щоб зареєструвати новий профіль.";
        }

        function syncThemeClass(mode) {
            document.body.classList.toggle("ta-theme-register", mode === "register");
        }

        function clearClientError(form) {
            var block = form.querySelector("[data-form-error]");
            if (!block) return;
            block.hidden = true;
            block.textContent = "";
        }

        function showClientError(form, message) {
            var block = form.querySelector("[data-form-error]");
            if (!block) return;
            block.hidden = false;
            block.textContent = message;
        }

        function markInvalid(input, isInvalid) {
            if (!input) return;
            if (isInvalid) {
                input.classList.add("is-invalid");
            } else {
                input.classList.remove("is-invalid");
            }
        }

        function isValidEmail(value) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
        }

        function isValidName(value) {
            return /^[A-Za-zА-Яа-яІіЇїЄєЁё'\-\s]{2,}$/.test(value.trim());
        }

        function validatePasswordRule(password) {
            if (password.length < 8 || password.length > 64) {
                return "Пароль має містити від 8 до 64 символів";
            }
            if (/[А-Яа-яЁёІіЇїЄє]/u.test(password)) {
                return "Пароль повинен бути лише латиницею";
            }
            if (!/[a-z]/.test(password)) {
                return "У паролі має бути мінімум одна мала літера";
            }
            if (/(password|123456|qwerty|admin|user|test)/i.test(password)) {
                return "Пароль містить занадто просту комбінацію";
            }
            return "";
        }

        tabLogin.addEventListener("click", function () {
            setMode("login");
        });

        tabRegister.addEventListener("click", function () {
            setMode("register");
        });

        var initialForm = getActiveForm();
        formLogin.classList.toggle("is-active", activeMode === "login");
        formRegister.classList.toggle("is-active", activeMode === "register");
        formStage.style.height = getStageHeight(initialForm) + "px";
        syncThemeClass(activeMode);

        if (typeof ResizeObserver !== "undefined") {
            var resizeObserver = new ResizeObserver(function () {
                var activeForm = getActiveForm();
                animateStageHeight(getStageHeight(activeForm));
            });
            resizeObserver.observe(formLogin);
            resizeObserver.observe(formRegister);
        }

        window.addEventListener("resize", function () {
            var activeForm = getActiveForm();
            formStage.style.height = getStageHeight(activeForm) + "px";
        });

        document.querySelectorAll("[data-toggle-pass]").forEach(function (button) {
            button.addEventListener("click", function () {
                var targetId = button.getAttribute("data-toggle-pass");
                var input = document.getElementById(targetId);
                if (!input) return;
                var show = input.type === "password";
                input.type = show ? "text" : "password";
                button.textContent = show ? "СХОВ" : "ПОКАЗ";
            });
        });

        function openModal() {
            if (!forgotModal) return;
            forgotModal.classList.add("is-open");
            forgotModal.setAttribute("aria-hidden", "false");
            document.body.classList.add("ta-no-scroll");
        }

        function closeModal() {
            if (!forgotModal) return;
            forgotModal.classList.remove("is-open");
            forgotModal.setAttribute("aria-hidden", "true");
            document.body.classList.remove("ta-no-scroll");
        }

        if (forgotOpen) {
            forgotOpen.addEventListener("click", function () {
                openModal();
            });
        }

        if (forgotModal) {
            forgotModal.querySelectorAll("[data-modal-close]").forEach(function (el) {
                el.addEventListener("click", closeModal);
            });

            document.addEventListener("keydown", function (event) {
                if (event.key === "Escape" && forgotModal.classList.contains("is-open")) {
                    closeModal();
                }
            });
        }

        if (forgotModal && forgotModal.classList.contains("is-open")) {
            document.body.classList.add("ta-no-scroll");
        }

        function getServerAlert(form) {
            return form.querySelector(".ta-alert--server");
        }

        function clearServerAlert(form) {
            var alert = getServerAlert(form);
            if (alert) {
                alert.remove();
            }
        }

        function showServerAlert(form, type, message) {
            if (!message) return;
            clearServerAlert(form);
            var anchor = form.querySelector("[data-form-error]");
            var alert = document.createElement("div");
            alert.className = "ta-alert ta-alert--server " + (type === "success" ? "ta-alert--success" : "ta-alert--error");
            alert.textContent = message;
            if (anchor) {
                form.insertBefore(alert, anchor);
            } else {
                form.prepend(alert);
            }
        }

        function setFormPending(form, isPending) {
            var submitButton = form.querySelector("button[type='submit'], input[type='submit']");
            if (!submitButton) return;
            if (isPending) {
                submitButton.dataset.prevText = submitButton.textContent || submitButton.value || "";
                if (submitButton.tagName === "BUTTON") {
                    submitButton.textContent = "Обробка...";
                } else {
                    submitButton.value = "Обробка...";
                }
                submitButton.disabled = true;
            } else {
                var previousText = submitButton.dataset.prevText || "";
                if (previousText) {
                    if (submitButton.tagName === "BUTTON") {
                        submitButton.textContent = previousText;
                    } else {
                        submitButton.value = previousText;
                    }
                }
                submitButton.disabled = false;
            }
        }

        function readAlertFromDocument(doc, formSelector) {
            var alert = doc.querySelector(formSelector + " .ta-alert--server")
                || doc.querySelector(formSelector + " .ta-alert--success")
                || doc.querySelector(formSelector + " .ta-alert--error");

            if (!alert) return null;

            return {
                text: (alert.textContent || "").trim(),
                type: alert.classList.contains("ta-alert--success") ? "success" : "error"
            };
        }

        function syncCsrfFromDocument(doc) {
            var sourceTokenInput = doc.querySelector("input[name='csrf_token']");
            if (!sourceTokenInput || !sourceTokenInput.value) return;
            document.querySelectorAll("input[name='csrf_token']").forEach(function (input) {
                input.value = sourceTokenInput.value;
            });
        }

        function postFormAndParse(form) {
            return fetch(form.getAttribute("action") || window.location.href, {
                method: "POST",
                body: new FormData(form),
                credentials: "same-origin",
                headers: {
                    "X-Requested-With": "XMLHttpRequest"
                }
            }).then(function (response) {
                if (response.redirected) {
                    return {
                        redirected: true,
                        url: response.url
                    };
                }

                return response.text().then(function (html) {
                    return {
                        redirected: false,
                        doc: new DOMParser().parseFromString(html, "text/html")
                    };
                });
            });
        }

        formLogin.addEventListener("submit", function (event) {
            event.preventDefault();
            clearClientError(formLogin);
            clearServerAlert(formLogin);
            var emailInput = document.getElementById("ta-login-email");
            var passInput = document.getElementById("ta-login-password");
            var email = (emailInput.value || "").trim();
            var pass = passInput.value || "";

            markInvalid(emailInput, false);
            markInvalid(passInput, false);

            if (!isValidEmail(email)) {
                markInvalid(emailInput, true);
                showClientError(formLogin, "Вкажіть коректний e-mail");
                return;
            }

            if (pass.length === 0) {
                markInvalid(passInput, true);
                showClientError(formLogin, "Введіть пароль");
                return;
            }

            setFormPending(formLogin, true);
            postFormAndParse(formLogin).then(function (result) {
                if (result.redirected) {
                    window.location.href = result.url;
                    return;
                }

                syncCsrfFromDocument(result.doc);
                var loginAlert = readAlertFromDocument(result.doc, "#form-login");
                if (loginAlert && loginAlert.text) {
                    showServerAlert(formLogin, loginAlert.type, loginAlert.text);
                } else {
                    showServerAlert(formLogin, "error", "Сталася помилка. Спробуйте ще раз.");
                }
            }).catch(function () {
                showServerAlert(formLogin, "error", "Помилка з'єднання. Перевірте мережу і спробуйте знову.");
            }).finally(function () {
                setFormPending(formLogin, false);
            });
        });

        formRegister.addEventListener("submit", function (event) {
            event.preventDefault();
            clearClientError(formRegister);
            clearServerAlert(formRegister);
            var lnameInput = document.getElementById("ta-lname");
            var fnameInput = document.getElementById("ta-fname");
            var emailInput = document.getElementById("ta-reg-email");
            var passInput = document.getElementById("ta-reg-pass");
            var passConfirmInput = document.getElementById("ta-reg-pass-confirm");

            var lname = (lnameInput.value || "").trim();
            var fname = (fnameInput.value || "").trim();
            var email = (emailInput.value || "").trim();
            var pass = passInput.value || "";
            var passConfirm = passConfirmInput.value || "";

            [lnameInput, fnameInput, emailInput, passInput, passConfirmInput].forEach(function (input) {
                markInvalid(input, false);
            });

            if (!isValidName(lname)) {
                markInvalid(lnameInput, true);
                showClientError(formRegister, "Перевірте поле «Прізвище»");
                return;
            }

            if (!isValidName(fname)) {
                markInvalid(fnameInput, true);
                showClientError(formRegister, "Перевірте поле «Ім'я»");
                return;
            }

            if (!isValidEmail(email)) {
                markInvalid(emailInput, true);
                showClientError(formRegister, "Вкажіть коректний e-mail");
                return;
            }

            var passwordError = validatePasswordRule(pass);
            if (passwordError) {
                markInvalid(passInput, true);
                showClientError(formRegister, passwordError);
                return;
            }

            if (pass !== passConfirm) {
                markInvalid(passInput, true);
                markInvalid(passConfirmInput, true);
                showClientError(formRegister, "Паролі не збігаються");
                return;
            }

            setFormPending(formRegister, true);
            postFormAndParse(formRegister).then(function (result) {
                if (result.redirected) {
                    window.location.href = result.url;
                    return;
                }

                syncCsrfFromDocument(result.doc);

                var loginSuccessAlert = result.doc.querySelector("#form-login .ta-alert--success");
                if (loginSuccessAlert) {
                    clearServerAlert(formRegister);
                    setMode("login");
                    animateStageHeight(getStageHeight(formLogin));
                    document.getElementById("ta-login-email").value = email;
                    showServerAlert(formLogin, "success", (loginSuccessAlert.textContent || "").trim());
                    return;
                }

                var registerAlert = readAlertFromDocument(result.doc, "#form-register");
                if (registerAlert && registerAlert.text) {
                    showServerAlert(formRegister, registerAlert.type, registerAlert.text);
                } else {
                    showServerAlert(formRegister, "error", "Сталася помилка. Спробуйте ще раз.");
                }
            }).catch(function () {
                showServerAlert(formRegister, "error", "Помилка з'єднання. Перевірте мережу і спробуйте знову.");
            }).finally(function () {
                setFormPending(formRegister, false);
            });
        });

        var forgotForm = document.getElementById("form-forgot-password");
        if (forgotForm) {
            forgotForm.addEventListener("submit", function (event) {
                clearClientError(forgotForm);
                var emailInput = document.getElementById("ta-forgot-email");
                var email = (emailInput.value || "").trim();
                markInvalid(emailInput, false);

                if (!isValidEmail(email)) {
                    event.preventDefault();
                    markInvalid(emailInput, true);
                    showClientError(forgotForm, "Вкажіть коректний e-mail");
                }
            });
        }

        var resetForm = document.getElementById("form-reset-password");
        if (resetForm) {
            resetForm.addEventListener("submit", function (event) {
                clearClientError(resetForm);
                var passInput = document.getElementById("ta-reset-pass");
                var passConfirmInput = document.getElementById("ta-reset-pass-confirm");
                var pass = passInput.value || "";
                var passConfirm = passConfirmInput.value || "";

                markInvalid(passInput, false);
                markInvalid(passConfirmInput, false);

                var passwordError = validatePasswordRule(pass);
                if (passwordError) {
                    event.preventDefault();
                    markInvalid(passInput, true);
                    showClientError(resetForm, passwordError);
                    return;
                }

                if (pass !== passConfirm) {
                    event.preventDefault();
                    markInvalid(passInput, true);
                    markInvalid(passConfirmInput, true);
                    showClientError(resetForm, "Паролі не збігаються");
                }
            });
        }

        document.querySelectorAll(".ta-field input").forEach(function (input) {
            input.addEventListener("input", function () {
                input.classList.remove("is-invalid");
                var form = input.closest("form");
                if (form) {
                    clearClientError(form);
                }
            });
        });
    })();
</script>
</body>
</html>
