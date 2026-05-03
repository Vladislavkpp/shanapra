<?php
/**
 * Відновлення паролю
 * - Форма введення email -> надсилання листа з посиланням
 * - Посилання з токеном -> форма встановлення нового паролю
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/function.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/validator.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/mailer.php';

// Якщо вже авторизований — скидання пароля тільки в профілі (md=73)
if (isset($_SESSION['logged']) && $_SESSION['logged'] == 1) {
    $token = trim((string)($_GET['token'] ?? ''));
    if ($token !== '') {
        header('Location: /profile.php?md=73&token=' . urlencode($token));
    } else {
        header('Location: /profile.php');
    }
    exit;
}

$md = $_POST['md'] ?? $_GET['md'] ?? '';
$errorMsg = '';
$successMsg = '';
$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$showEmailForm = true;
$showPasswordForm = false;
$showSuccessBlock = false;

View_Clear();
View_Add(Page_Up('Відновлення паролю'));
View_Add(Menu_Up());
View_Add('<div class="out">');

// === Крок 1: Запит відновлення (POST з email) ===
if ($md == '34' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $emailError = '';
    $em1 = valide1($email, 'email', $emailError);

    if ($em1 === '') {
        $errorMsg = $emailError ?: 'Введіть коректний e-mail';
    } else {
        $dblink = DbConnect();
        $emEscaped = mysqli_real_escape_string($dblink, $em1);
        $res = mysqli_query($dblink, "SELECT idx, fname FROM users WHERE email='" . $emEscaped . "' LIMIT 1");

        if (mysqli_num_rows($res) == 0) {
            // З міркувань безпеки не повідомляємо, чи існує email
            $showSuccessBlock = true;
            mysqli_close($dblink);
        } else {
            $user = mysqli_fetch_assoc($res);
            $userId = (int)$user['idx'];
            $fname = $user['fname'] ?: 'Користувач';

            // Видаляємо старі токени цього користувача (таблиця створена за database/password_reset_tokens.sql)
            $stmt = mysqli_prepare($dblink, "DELETE FROM password_reset_tokens WHERE user_id=?");
            mysqli_stmt_bind_param($stmt, 'i', $userId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            // Генеруємо новий токен; expires_at задаємо в MySQL, щоб збігався з NOW() (одна часова зона)
            $resetToken = bin2hex(random_bytes(32));

            $stmt = mysqli_prepare($dblink, "INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))");
            mysqli_stmt_bind_param($stmt, 'is', $userId, $resetToken);
            $ok = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            if ($ok) {
                $resetLink = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
                    . '/strepair.php?token=' . urlencode($resetToken);
                sendPasswordResetEmail($userId, $em1, $fname, $resetLink);
            }

            mysqli_close($dblink);
            $showSuccessBlock = true;
        }
    }
}

// === Крок 2: Перехід по посиланню з токеном (GET) ===
elseif (!empty($token) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $dblink = DbConnect();
    $stmt = mysqli_prepare($dblink, "SELECT user_id FROM password_reset_tokens WHERE token=? AND expires_at > NOW() LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $token);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $foundUserId);
    if (mysqli_stmt_fetch($stmt)) {
        $showEmailForm = false;
        $showPasswordForm = true;
    } else {
        $errorMsg = 'Посилання недійсне або застаріло. Запитуйте відновлення паролю ще раз.';
    }
    mysqli_stmt_close($stmt);
    mysqli_close($dblink);
}

// === Крок 3: Встановлення нового паролю (POST з token + password) ===
elseif ($md == '35' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = trim($_POST['token'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';

    $passwordError = '';
    $ep1 = valide1($password, 'password', $passwordError);

    if ($ep1 === '') {
        $errorMsg = $passwordError ?: 'Пароль має містити від 8 до 64 символів, латиницю, малу літеру';
        $showPasswordForm = true;
    } elseif ($password !== $passwordConfirm) {
        $errorMsg = 'Паролі не збігаються';
        $showPasswordForm = true;
    } elseif (empty($token)) {
        $errorMsg = 'Недійсне посилання. Запитуйте відновлення паролю ще раз.';
        $showEmailForm = true;
    } else {
        $dblink = DbConnect();
        $stmt = mysqli_prepare($dblink, "SELECT user_id FROM password_reset_tokens WHERE token=? AND expires_at > NOW() LIMIT 1");
        mysqli_stmt_bind_param($stmt, 's', $token);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $userId);

        if (!mysqli_stmt_fetch($stmt)) {
            mysqli_stmt_close($stmt);
            mysqli_close($dblink);
            $errorMsg = 'Посилання недійсне або застаріло.';
            $showEmailForm = true;
        } else {
            $userId = (int)$userId;
            mysqli_stmt_close($stmt);

            $passhash = md5($ep1);
            $stmtUp = mysqli_prepare($dblink, "UPDATE users SET pasw=? WHERE idx=?");
            mysqli_stmt_bind_param($stmtUp, 'si', $passhash, $userId);
            mysqli_stmt_execute($stmtUp);
            mysqli_stmt_close($stmtUp);

            $stmtDel = mysqli_prepare($dblink, "DELETE FROM password_reset_tokens WHERE token=?");
            mysqli_stmt_bind_param($stmtDel, 's', $token);
            mysqli_stmt_execute($stmtDel);
            mysqli_stmt_close($stmtDel);
            mysqli_close($dblink);
            $successMsg = 'Пароль успішно змінено. Перенаправлення на сторінку входу...';
            $showEmailForm = false;
            $showPasswordForm = false;

            View_Add('
<div class="logform-container has-success">
    <div class="logform-title logform-text-center">Відновлення паролю</div>
    <div class="regsuccess1">' . htmlspecialchars($successMsg, ENT_QUOTES, 'UTF-8') . '</div>
    <div class="logform-row logform-vertical butt">
        <a href="/auth.php" class="logform-button">Увійти</a>
    </div>
</div>
<script>setTimeout(function(){ window.location.href="/auth.php"; }, 3000);</script>
');
        }
    }
}

// === Вивід форм ===
if ($showSuccessBlock) {
    View_Add('
<div class="logform-container has-success">
    <div class="strepform-title logform-text-center">Відновлення паролю</div>
    <div class="regsuccess1">
        Якщо обліковий запис існує, на вказаний e-mail надіслано листа з посиланням для встановлення нового паролю.<br>
        Перевірте поштову скриньку (також папку «Спам»).
    </div>
    <div class="logform-row logform-vertical butt">
        <a href="/auth.php" class="logform-button">Повернутися до входу</a>
    </div>
</div>
');
} elseif ($showPasswordForm && !$successMsg) {
    View_Add('
<div class="logform-container ' . (!empty($errorMsg) ? 'has-error' : '') . '">
    <form action="/strepair.php" method="post">
        <input type="hidden" name="md" value="35">
        <input type="hidden" name="token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">
        <div class="logform-title logform-text-center">Встановити новий пароль</div>
        ' . (!empty($errorMsg) ? '<div class="regerror1">' . htmlspecialchars($errorMsg, ENT_QUOTES, 'UTF-8') . '</div>' : '') . '
        <div class="logform-row logform-vertical">
            <div class="logform-input-container">
                <input class="logform-input" name="password" type="password" placeholder=" " required minlength="8" autocomplete="new-password">
                <label>Новий пароль</label>
            </div>
        </div>
        <div class="logform-row logform-vertical">
            <div class="logform-input-container">
                <input class="logform-input" name="password_confirm" type="password" placeholder=" " required minlength="8" autocomplete="new-password">
                <label>Підтвердити пароль</label>
            </div>
        </div>
        <div class="logform-row logform-vertical butt">
            <input class="logform-button" type="submit" value="Зберегти пароль">
        </div>
        <div class="logform-row logform-right but">
            <a class="logform-advanced-link" href="/auth.php">Повернутися до входу</a>
        </div>
    </form>
</div>
');
} elseif ($showEmailForm && !$successMsg) {
    View_Add('
<div class="logform-container ' . (!empty($errorMsg) ? 'has-error' : '') . '">
    <form action="/strepair.php" method="post">
        <input type="hidden" name="md" value="34">
        <div class="logform-title logform-text-center butt">Відновлення паролю</div>
        <div class="title-inp-cont">
            <span class="title-inp">Введіть e-mail вашого облікового запису</span>
        </div>
        ' . (!empty($errorMsg) ? '<div class="regerror1">' . htmlspecialchars($errorMsg, ENT_QUOTES, 'UTF-8') . '</div>' : '') . '
        <div class="logform-row">
            <div class="logform-input-container">
                <input class="logform-input" name="email" type="email" placeholder=" " required value="' . htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') . '">
                <label>E-mail</label>
            </div>
        </div>
        <div class="logform-row logform-vertical butt">
            <input class="logform-button" type="submit" value="Надіслати посилання">
        </div>
        <div class="logform-row logform-right but">
            <a class="logform-advanced-link" href="/auth.php">Повернутися до входу</a>
        </div>
    </form>
</div>
');
}

View_Add('</div>');
View_Add(Page_Down());
View_Out();
View_Clear();
