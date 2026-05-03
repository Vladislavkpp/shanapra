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
    header('Location: ' . PublicUrl('/profile.php'));
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
$pageUpTitle = $activeMode === 'register' ? 'Реєстрація' : 'Вхід';
$pageTitleLogin = 'ІПС Shana | Вхід';
$pageTitleRegister = 'ІПС Shana | Реєстрація';

$loginEmail = '';
$registerEmail = '';
$registerLname = '';
$registerFname = '';
$forgotEmail = '';

$loginError = '';
$loginSuccess = '';
$registerError = '';
$registerSuccess = '';
$registerSuccessTone = 'success';
$registerAwaitingActivation = false;
$registerPendingEmail = '';
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
        if ($action === 'register' || $action === 'confirm_activation_code') {
            $activeMode = 'register';
            if ($action === 'confirm_activation_code') {
                $registerAwaitingActivation = true;
                $registerPendingEmail = trim((string)($_POST['activation_email'] ?? ''));
                $registerSuccessTone = 'success';
                $registerSuccess = 'Введіть 6-значний код, який ми надіслали на вашу пошту.';
            }
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
                            if (function_exists('ensureUserWalletWithWelcomeBonus')) {
                                ensureUserWalletWithWelcomeBonus((int)$userId, 500.0, $dblink);
                            }
                            if (function_exists('grantDailyLoginInternalBonus')) {
                                grantDailyLoginInternalBonus((int)$userId, 10.0, $dblink);
                            }
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

                            header('Location: ' . PublicUrl('/profile.php'));
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
                                $activationEmailSent = false;
                                if (function_exists('sendRegistrationActivationCode')) {
                                    $activationEmailSent = sendRegistrationActivationCode($newUserId, $normalizedEmail, $normalizedFname, $dblink);
                                }

                                $registerAwaitingActivation = true;
                                $registerPendingEmail = $normalizedEmail;
                                $registerSuccessTone = $activationEmailSent ? 'success' : 'warning';
                                $registerSuccess = $activationEmailSent
                                    ? 'Введіть 6-значний код, який ми надіслали на вашу пошту.'
                                    : 'Акаунт створено, але код підтвердження тимчасово не вдалося надіслати. Спробуйте підтвердити акаунт пізніше.';
                                $activeMode = 'register';
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
        } elseif ($action === 'confirm_activation_code') {
            $activeMode = 'register';
            $registerAwaitingActivation = true;
            $registerPendingEmail = trim((string)($_POST['activation_email'] ?? ''));
            $registerSuccessTone = 'success';
            $registerSuccess = 'Введіть 6-значний код, який ми надіслали на вашу пошту.';

            $activationCodeRaw = (string)($_POST['activation_code'] ?? '');
            $activationCode = preg_replace('/\D+/', '', $activationCodeRaw);
            $emailError = '';
            $normalizedEmail = validateEmailValue($registerPendingEmail, $emailError);

            if ($normalizedEmail === '') {
                $registerAwaitingActivation = false;
                $registerPendingEmail = '';
                $registerError = 'Почніть реєстрацію ще раз, щоб отримати новий код.';
            } elseif (strlen($activationCode) !== 6) {
                $registerPendingEmail = $normalizedEmail;
                $registerError = 'Введіть 6-значний код підтвердження.';
            } else {
                $registerPendingEmail = $normalizedEmail;
                $dblink = DbConnect();
                $stmtConfirm = mysqli_prepare(
                    $dblink,
                    "SELECT idx FROM users WHERE email=? AND token=? AND activ=0 LIMIT 1"
                );

                if (!$stmtConfirm) {
                    $registerError = 'Не вдалося перевірити код. Спробуйте ще раз.';
                } else {
                    mysqli_stmt_bind_param($stmtConfirm, 'ss', $normalizedEmail, $activationCode);
                    mysqli_stmt_execute($stmtConfirm);
                    mysqli_stmt_bind_result($stmtConfirm, $pendingUserId);

                    if (mysqli_stmt_fetch($stmtConfirm)) {
                        $pendingUserId = (int)$pendingUserId;
                        mysqli_stmt_close($stmtConfirm);

                        $stmtActivate = mysqli_prepare(
                            $dblink,
                            "UPDATE users SET activ=1, token=NULL WHERE idx=? AND activ=0 LIMIT 1"
                        );

                        if (!$stmtActivate) {
                            $registerError = 'Не вдалося активувати акаунт. Спробуйте ще раз.';
                        } else {
                            mysqli_stmt_bind_param($stmtActivate, 'i', $pendingUserId);

                            if (mysqli_stmt_execute($stmtActivate)) {
                                if (function_exists('createUserNotification')) {
                                    createUserNotification(
                                        $pendingUserId,
                                        'Акаунт активовано',
                                        'Ваш акаунт підтверджено. Тепер ви можете користуватися сервісом.',
                                        'account',
                                        'normal',
                                        '/profile.php',
                                        'Відкрити профіль',
                                        'activation',
                                        null,
                                        null,
                                        null,
                                        null,
                                        1,
                                        $dblink
                                    );
                                }
                                $activeMode = 'login';
                                $loginEmail = $normalizedEmail;
                                $loginSuccess = 'Акаунт підтверджено. Тепер увійдіть у свій профіль.';
                                $registerAwaitingActivation = false;
                                $registerPendingEmail = '';
                                $registerSuccess = '';
                            } else {
                                $registerError = 'Не вдалося активувати акаунт. Спробуйте ще раз.';
                            }

                            mysqli_stmt_close($stmtActivate);
                        }
                    } else {
                        $registerError = 'Невірний код підтвердження.';
                        mysqli_stmt_close($stmtConfirm);
                    }
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
                                $resetLink = $protocol . '://' . $host . PublicUrl('/auth.php?reset_token=' . urlencode($resetTokenValue));
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

$loginHeading = 'Ласкаво просимо знову';
$loginSubtitle = 'Введіть e-mail і пароль, щоб продовжити роботу.';
$registerHeading = $registerAwaitingActivation ? 'Підтвердіть e-mail' : 'Створіть акаунт';
$registerSubtitle = $registerAwaitingActivation
    ? ($registerSuccessTone === 'warning'
        ? 'Ми створили акаунт, але не змогли одразу доставити код підтвердження.'
        : 'Введіть 6-значний код із листа, щоб завершити активацію.')
    : 'Заповніть поля, щоб зареєструвати новий профіль.';
$currentHeading = $activeMode === 'login' ? $loginHeading : $registerHeading;
$currentSubtitle = $activeMode === 'login' ? $loginSubtitle : $registerSubtitle;
$registerStateTitle = $registerSuccessTone === 'warning'
    ? 'Не вдалося надіслати код'
    : 'Код підтвердження надіслано';
$registerStateMessage = $registerSuccessTone === 'warning'
    ? 'Спробуйте увійти пізніше та активувати акаунт у профілі через лист із посиланням.'
    : 'Ми надіслали код на вказану пошту. Якщо лист не видно, перевірте папку "Спам".';
$registerStateIcon = $registerSuccessTone === 'warning'
    ? '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M12 9v4"></path><path d="M12 16v.01"></path><path d="M5.07 19h13.86a2 2 0 0 0 1.75 -2.98l-6.93 -12.02a2 2 0 0 0 -3.5 0l-6.93 12.02a2 2 0 0 0 1.75 2.98"></path></svg>'
    : '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M5 12l5 5l10 -10"></path></svg>';

$cssVersion = is_file(__DIR__ . '/assets/css/auth.css') ? (string)filemtime(__DIR__ . '/assets/css/auth.css') : '1';
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($activeMode === 'register' ? $pageTitleRegister : $pageTitleLogin) ?></title>
    <link rel="icon" type="image/png" href="/assets/images/shana-logo.png">
    <link rel="stylesheet" href="/assets/css/auth.css?v=<?= h($cssVersion) ?>">
</head>
<body class="<?= $activeMode === 'register' ? 'ta-theme-register' : '' ?>">
<div class="ta-bg" aria-hidden="true">
    <span class="ta-bg__blob ta-bg__blob--1"></span>
    <span class="ta-bg__blob ta-bg__blob--2"></span>
</div>
<main class="ta-shell"
      data-mode="<?= h($activeMode) ?>"
      data-title-login="<?= h($pageTitleLogin) ?>"
      data-title-register="<?= h($pageTitleRegister) ?>"
      data-heading-login="<?= h($loginHeading) ?>"
      data-heading-register="<?= h($registerHeading) ?>"
      data-subtitle-login="<?= h($loginSubtitle) ?>"
      data-subtitle-register="<?= h($registerSubtitle) ?>"
      id="ta-shell">
    <section class="ta-brand">
        <div class="ta-logo">
            <img src="/assets/images/shana-logo.png" alt="Логотип Shana">
        </div>
        <h1>ІПС Shana<br>доступ до акаунта</h1>
        <p class="ta-brand-desc ta-brand-desc--desktop">Увійдіть або створіть акаунт, щоб керувати профілем, публікаціями та персональними налаштуваннями.</p>
        <p class="ta-brand-desc ta-brand-desc--mobile">Увійдіть або зареєструйтесь, щоб отримати доступ до можливостей системи.</p>
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

        <h2 class="ta-title" id="ta-title"><?= h($currentHeading) ?></h2>
        <p class="ta-subtitle" id="ta-subtitle"><?= h($currentSubtitle) ?></p>

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
                <a class="ta-alt" href="/oauth">
                    <img src="/assets/images/Google_Favicon_2025.png" alt="Google">
                    Увійти через Google
                </a>
            </form>

            <form class="ta-form <?= $activeMode === 'register' ? 'is-active' : '' ?>" id="form-register" method="post" novalidate data-register-state="<?= $registerAwaitingActivation ? 'awaiting-activation' : 'default' ?>">
                <input type="hidden" name="action" value="<?= $registerAwaitingActivation ? 'confirm_activation_code' : 'register' ?>">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">

                <?php if ($registerError !== ''): ?>
                    <div class="ta-alert ta-alert--error ta-alert--server"><?= h($registerError) ?></div>
                <?php endif; ?>
                <div class="ta-alert ta-alert--error ta-alert--client" data-form-error hidden></div>

                <?php if ($registerAwaitingActivation): ?>
                    <section class="ta-register-state ta-register-state--<?= h($registerSuccessTone) ?>">
                        <div class="ta-register-state__head">
                            <span class="ta-register-state__icon"><?= $registerStateIcon ?></span>
                            <div class="ta-register-state__copy">
                                <span class="ta-register-state__badge">ПІДТВЕРДЖЕННЯ EMAIL</span>
                                <h3 class="ta-register-state__title"><?= h($registerStateTitle) ?></h3>
                            </div>
                        </div>
                        <p class="ta-register-state__text"><?= h($registerSuccess) ?></p>
                        <div class="ta-register-state__email"><?= h($registerPendingEmail) ?></div>
                        <p class="ta-register-state__hint"><?= h($registerStateMessage) ?></p>
                        <input type="hidden" name="activation_email" value="<?= h($registerPendingEmail) ?>">
                        <input type="hidden" name="activation_code" id="ta-activation-code" value="">

                        <?php if ($registerSuccessTone !== 'warning'): ?>
                            <div class="ta-code-field">
                                <label class="ta-code-field__label" for="ta-activation-digit-1">Код підтвердження</label>
                                <div class="ta-code-inputs" data-code-inputs>
                                    <input id="ta-activation-digit-1" class="ta-code-input" type="text" inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code" maxlength="1" data-code-digit>
                                    <input class="ta-code-input" type="text" inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code" maxlength="1" data-code-digit>
                                    <input class="ta-code-input" type="text" inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code" maxlength="1" data-code-digit>
                                    <input class="ta-code-input" type="text" inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code" maxlength="1" data-code-digit>
                                    <input class="ta-code-input" type="text" inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code" maxlength="1" data-code-digit>
                                    <input class="ta-code-input" type="text" inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code" maxlength="1" data-code-digit>
                                </div>
                            </div>
                            <div class="ta-register-actions ta-register-actions--split">
                                <button type="submit" class="ta-btn">Підтвердити акаунт</button>
                                <button type="button" class="ta-btn ta-btn--ghost ta-btn--later" data-register-later>Підтвердити пізніше</button>
                            </div>
                        <?php else: ?>
                            <div class="ta-register-actions">
                                <button type="button" class="ta-btn ta-btn--ghost ta-btn--later" data-register-later>Підтвердити пізніше</button>
                            </div>
                        <?php endif; ?>
                    </section>
                <?php else: ?>
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
                    <a class="ta-alt" href="/oauth">
                        <img src="/assets/images/Google_Favicon_2025.png" alt="Google">
                        Продовжити через Google
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <div class="ta-note">Потрібна допомога? <a href="/messenger?type=3" class="ta-link">Звернутися в технічну підтримку</a></div>
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
        var compactMobileQuery = window.matchMedia ? window.matchMedia("(max-width: 540px)") : null;

        function isCompactMobile() {
            return !!(compactMobileQuery && compactMobileQuery.matches);
        }

        function getActiveForm() {
            return activeMode === "login" ? formLogin : formRegister;
        }

        function getStageHeight(form) {
            return form.offsetHeight + formStageExtra;
        }

        function animateStageHeight(nextHeight) {
            if (isCompactMobile()) {
                formStage.style.height = "";
                return;
            }
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

            if (!isCompactMobile()) {
                formStage.style.height = getStageHeight(prevForm) + "px";
            } else {
                formStage.style.height = "";
            }
            activeMode = mode;

            shell.setAttribute("data-mode", isLogin ? "login" : "register");
            tabLogin.setAttribute("aria-selected", isLogin ? "true" : "false");
            tabRegister.setAttribute("aria-selected", isLogin ? "false" : "true");

            prevForm.classList.remove("is-active");
            nextForm.classList.add("is-active");
            animateStageHeight(getStageHeight(nextForm));
            syncThemeClass(mode);
            applyModeCopy(mode);
        }

        function syncThemeClass(mode) {
            document.body.classList.toggle("ta-theme-register", mode === "register");
            var nextTitle = mode === "register"
                ? (shell.getAttribute("data-title-register") || "ІПС Shana | Реєстрація")
                : (shell.getAttribute("data-title-login") || "ІПС Shana | Вхід");
            document.title = nextTitle;
        }

        function getModeHeading(mode) {
            return mode === "register"
                ? (shell.getAttribute("data-heading-register") || "Створіть акаунт")
                : (shell.getAttribute("data-heading-login") || "Ласкаво просимо знову");
        }

        function getModeSubtitle(mode) {
            return mode === "register"
                ? (shell.getAttribute("data-subtitle-register") || "Заповніть поля, щоб зареєструвати новий профіль.")
                : (shell.getAttribute("data-subtitle-login") || "Введіть e-mail і пароль, щоб продовжити роботу.");
        }

        function applyModeCopy(mode) {
            title.textContent = getModeHeading(mode);
            subtitle.textContent = getModeSubtitle(mode);
        }

        function scrollToForms() {
            if (isCompactMobile()) return;
            var target = document.querySelector(".ta-forms");
            if (!target) return;
            requestAnimationFrame(function () {
                target.scrollIntoView({ behavior: "smooth", block: "start" });
            });
        }

        function syncShellCopyFromDocument(doc) {
            var sourceShell = doc.getElementById("ta-shell");
            if (!sourceShell) return;

            [
                "data-title-login",
                "data-title-register",
                "data-heading-login",
                "data-heading-register",
                "data-subtitle-login",
                "data-subtitle-register"
            ].forEach(function (attrName) {
                var attrValue = sourceShell.getAttribute(attrName);
                if (attrValue !== null) {
                    shell.setAttribute(attrName, attrValue);
                }
            });
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

        function getRegisterState() {
            return formRegister.getAttribute("data-register-state") === "awaiting-activation"
                ? "awaiting-activation"
                : "default";
        }

        function getPendingActivationEmail() {
            var emailInput = formRegister.querySelector("input[name='activation_email']");
            return emailInput ? (emailInput.value || "").trim() : "";
        }

        function getCodeInputs() {
            return Array.prototype.slice.call(formRegister.querySelectorAll("[data-code-digit]"));
        }

        function syncActivationCodeValue() {
            var hiddenInput = document.getElementById("ta-activation-code");
            if (!hiddenInput) return "";
            var code = getCodeInputs().map(function (input) {
                return ((input.value || "").replace(/\D/g, "").slice(0, 1));
            }).join("");
            hiddenInput.value = code;
            return code;
        }

        function clearCodeInvalid() {
            getCodeInputs().forEach(function (input) {
                markInvalid(input, false);
            });
        }

        function focusCodeInput(index) {
            var codeInputs = getCodeInputs();
            if (!codeInputs[index]) return;
            codeInputs[index].focus();
            if (typeof codeInputs[index].select === "function") {
                codeInputs[index].select();
            }
        }

        function maybeAutoSubmitActivationCode() {
            if (getRegisterState() !== "awaiting-activation") return;
            var codeInputs = getCodeInputs();
            if (codeInputs.length === 0) return;
            if (syncActivationCodeValue().length !== codeInputs.length) return;

            var submitButton = formRegister.querySelector("button[type='submit']");
            if (submitButton && submitButton.disabled) return;

            if (typeof formRegister.requestSubmit === "function") {
                formRegister.requestSubmit();
            } else {
                formRegister.dispatchEvent(new Event("submit", {cancelable: true}));
            }
        }

        function replaceRegisterFormState(doc) {
            var sourceRegisterForm = doc.getElementById("form-register");
            if (!sourceRegisterForm) return false;

            formRegister.innerHTML = sourceRegisterForm.innerHTML;
            formRegister.setAttribute(
                "data-register-state",
                sourceRegisterForm.getAttribute("data-register-state") || "default"
            );
            syncShellCopyFromDocument(doc);
            applyModeCopy("register");
            syncThemeClass("register");
            animateStageHeight(getStageHeight(formRegister));
            return true;
        }

        function applyLoginResponse(doc, fallbackEmail) {
            var sourceShell = doc.getElementById("ta-shell");
            var sourceRegisterForm = doc.getElementById("form-register");
            var loginAlert = readAlertFromDocument(doc, "#form-login");
            if (!sourceShell || sourceShell.getAttribute("data-mode") !== "login" || !loginAlert || !loginAlert.text) {
                return false;
            }

            if (sourceRegisterForm) {
                formRegister.innerHTML = sourceRegisterForm.innerHTML;
                formRegister.setAttribute(
                    "data-register-state",
                    sourceRegisterForm.getAttribute("data-register-state") || "default"
                );
            }

            syncShellCopyFromDocument(doc);
            setMode("login");
            var sourceLoginEmail = doc.querySelector("#form-login input[name='emailForLogin']");
            document.getElementById("ta-login-email").value = sourceLoginEmail && sourceLoginEmail.value
                ? sourceLoginEmail.value
                : fallbackEmail;
            showServerAlert(formLogin, loginAlert.type, loginAlert.text);
            animateStageHeight(getStageHeight(formLogin));
            return true;
        }

        tabLogin.addEventListener("click", function () {
            setMode("login");
        });

        tabRegister.addEventListener("click", function () {
            setMode("register");
        });

        formRegister.addEventListener("click", function (event) {
            var laterButton = event.target.closest("[data-register-later]");
            if (!laterButton) return;
            window.location.href = "/auth";
        });

        formRegister.addEventListener("input", function (event) {
            var codeInput = event.target.closest("[data-code-digit]");
            if (!codeInput) return;

            clearClientError(formRegister);
            clearServerAlert(formRegister);
            markInvalid(codeInput, false);

            var normalizedValue = (codeInput.value || "").replace(/\D/g, "");
            codeInput.value = normalizedValue ? normalizedValue.slice(-1) : "";
            syncActivationCodeValue();

            if (codeInput.value !== "") {
                var codeInputs = getCodeInputs();
                var currentIndex = codeInputs.indexOf(codeInput);
                if (currentIndex > -1 && currentIndex < codeInputs.length - 1) {
                    focusCodeInput(currentIndex + 1);
                }
            }

            maybeAutoSubmitActivationCode();
        });

        formRegister.addEventListener("keydown", function (event) {
            var codeInput = event.target.closest("[data-code-digit]");
            if (!codeInput) return;

            var codeInputs = getCodeInputs();
            var currentIndex = codeInputs.indexOf(codeInput);
            if (currentIndex === -1) return;

            if (event.key === "Backspace" && codeInput.value === "" && currentIndex > 0) {
                focusCodeInput(currentIndex - 1);
                codeInputs[currentIndex - 1].value = "";
                syncActivationCodeValue();
                event.preventDefault();
                return;
            }

            if (event.key === "ArrowLeft" && currentIndex > 0) {
                focusCodeInput(currentIndex - 1);
                event.preventDefault();
                return;
            }

            if (event.key === "ArrowRight" && currentIndex < codeInputs.length - 1) {
                focusCodeInput(currentIndex + 1);
                event.preventDefault();
            }
        });

        formRegister.addEventListener("paste", function (event) {
            var codeInput = event.target.closest("[data-code-digit]");
            if (!codeInput) return;

            var clipboard = event.clipboardData || window.clipboardData;
            if (!clipboard) return;

            var pastedDigits = (clipboard.getData("text") || "").replace(/\D/g, "");
            if (pastedDigits === "") return;

            event.preventDefault();
            var codeInputs = getCodeInputs();
            pastedDigits.slice(0, codeInputs.length).split("").forEach(function (digit, index) {
                codeInputs[index].value = digit;
                markInvalid(codeInputs[index], false);
            });
            syncActivationCodeValue();
            focusCodeInput(Math.min(pastedDigits.length, codeInputs.length) - 1);
            maybeAutoSubmitActivationCode();
        });

        var initialForm = getActiveForm();
        formLogin.classList.toggle("is-active", activeMode === "login");
        formRegister.classList.toggle("is-active", activeMode === "register");
        if (isCompactMobile()) {
            formStage.style.height = "";
        } else {
            formStage.style.height = getStageHeight(initialForm) + "px";
        }
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
            if (isCompactMobile()) {
                formStage.style.height = "";
                return;
            }
            formStage.style.height = getStageHeight(activeForm) + "px";
        });

        document.addEventListener("click", function (event) {
            var button = event.target.closest("[data-toggle-pass]");
            if (!button) return;
            var targetId = button.getAttribute("data-toggle-pass");
            var input = document.getElementById(targetId);
            if (!input) return;
            var show = input.type === "password";
            input.type = show ? "text" : "password";
            button.textContent = show ? "СХОВ" : "ПОКАЗ";
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
            var registerState = getRegisterState();
            var emailForLogin = "";

            if (registerState === "awaiting-activation") {
                var codeInputs = getCodeInputs();
                var hiddenCodeInput = document.getElementById("ta-activation-code");
                var activationCode = syncActivationCodeValue();
                emailForLogin = getPendingActivationEmail();
                clearCodeInvalid();

                if (activationCode.length !== codeInputs.length) {
                    codeInputs.forEach(function (input) {
                        if ((input.value || "").trim() === "") {
                            markInvalid(input, true);
                        }
                    });
                    showClientError(formRegister, "Введіть повний 6-значний код підтвердження.");
                    return;
                }

                if (hiddenCodeInput) {
                    hiddenCodeInput.value = activationCode;
                }
            } else {
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

                emailForLogin = email;

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
            }

            setFormPending(formRegister, true);
            postFormAndParse(formRegister).then(function (result) {
                if (result.redirected) {
                    window.location.href = result.url;
                    return;
                }

                syncCsrfFromDocument(result.doc);
                if (applyLoginResponse(result.doc, emailForLogin)) {
                    return;
                }

                if (replaceRegisterFormState(result.doc)) {
                    if (registerState === "default" && emailForLogin) {
                        document.getElementById("ta-login-email").value = emailForLogin;
                    }
                    return;
                }

                showServerAlert(formRegister, "error", "Сталася помилка. Спробуйте ще раз.");
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

        document.addEventListener("input", function (event) {
            var input = event.target.closest(".ta-field input");
            if (!input) return;
            input.classList.remove("is-invalid");
            var form = input.closest("form");
            if (form) {
                clearClientError(form);
            }
        });
    })();
</script>
</body>
</html>
