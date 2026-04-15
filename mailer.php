<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';
require_once $_SERVER['DOCUMENT_ROOT'] . "/function.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendActivationEmail($userId, $email, $name, $dblink)
{
    $token = bin2hex(random_bytes(32));

    // сохраняем токен
    $stmt = $dblink->prepare("UPDATE users SET token=? WHERE idx=?");
    $stmt->bind_param("si", $token, $userId);
    $stmt->execute();

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'shanapra.activation@gmail.com';
        $mail->Password   = 'yzsa bdpp tefm wbnp';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('shanapra.activation@gmail.com', 'ShanaPra');
        $mail->addAddress($email, $name);

        $mail->addReplyTo('shanapra.activation@gmail.com', 'Support');
        $mail->CharSet = 'UTF-8';
        $mail->isHTML(true);

        $mail->Subject = 'Активація облікового запису';

        $mail->Body = "
<html>
<head>
  <meta charset='UTF-8'>
  <style>
    body {
      font-family: Arial, sans-serif;
      background-color: #f4f4f7;
      color: #333333;
      margin: 0;
      padding: 0;
    }
    .container {
      max-width: 600px;
      margin: 30px auto;
      padding: 20px;
      background-color: #ffffff;
      border-radius: 10px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    h1 {
      color: #2563eb;
      font-size: 24px;
      margin-bottom: 20px;
    }
    p {
      font-size: 16px;
      line-height: 1.5;
      margin-bottom: 20px;
    }
    a.button {
      display: inline-block;
      padding: 12px 20px;
      background-color: #2563eb;
      color: #ffffff !important;
      text-decoration: none;
      border-radius: 6px;
      font-weight: bold;
    }
    a.button:hover {
      background-color: #1e4fc1;
    }
  </style>
</head>
<body>
  <div class='container'>
    <h1>Вітаємо, $name!</h1>
    <p>Для активації облікового запису перейдіть за посиланням:</p>
    <p>
      <a href='https://shanapra.com/activate.php?token=$token' class='button'>
        Активувати обліковий запис
      </a>
    </p>
    <p>Якщо ви не створювали обліковий запис, просто проігноруйте це повідомлення.</p>
  </div>
</body>
</html>
";


        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log('Mail error: ' . $mail->ErrorInfo);
        return false;
    }
}

function sendRegistrationActivationCode($userId, $email, $name, $dblink)
{
    $code = '';

    for ($attempt = 0; $attempt < 10; $attempt++) {
        $candidateCode = (string)random_int(100000, 999999);
        $stmtCheck = $dblink->prepare("SELECT idx FROM users WHERE token=? LIMIT 1");

        if (!$stmtCheck) {
            return false;
        }

        $stmtCheck->bind_param("s", $candidateCode);
        $stmtCheck->execute();
        $stmtCheck->store_result();

        if ($stmtCheck->num_rows === 0) {
            $code = $candidateCode;
            $stmtCheck->close();
            break;
        }

        $stmtCheck->close();
    }

    if ($code === '') {
        return false;
    }

    $stmt = $dblink->prepare("UPDATE users SET token=? WHERE idx=?");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("si", $code, $userId);
    $stmt->execute();
    $stmt->close();

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'shanapra.activation@gmail.com';
        $mail->Password   = 'yzsa bdpp tefm wbnp';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('shanapra.activation@gmail.com', 'ShanaPra');
        $mail->addAddress($email, $name);

        $mail->addReplyTo('shanapra.activation@gmail.com', 'Support');
        $mail->CharSet = 'UTF-8';
        $mail->isHTML(true);
        $mail->Subject = 'Код підтвердження облікового запису';

        $mail->Body = "
<html>
<head>
  <meta charset='UTF-8'>
  <style>
    body {
      font-family: Arial, sans-serif;
      background-color: #f4f4f7;
      color: #333333;
      margin: 0;
      padding: 0;
    }
    .container {
      max-width: 600px;
      margin: 30px auto;
      padding: 24px;
      background-color: #ffffff;
      border-radius: 12px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    h1 {
      color: #2563eb;
      font-size: 24px;
      margin-bottom: 14px;
    }
    p {
      font-size: 16px;
      line-height: 1.5;
      margin-bottom: 16px;
    }
    .code {
      display: inline-block;
      padding: 12px 18px;
      border-radius: 10px;
      background: #eff6ff;
      color: #1d4ed8;
      font-size: 32px;
      font-weight: 700;
      letter-spacing: 0.22em;
    }
  </style>
</head>
<body>
  <div class='container'>
    <h1>Вітаємо, $name!</h1>
    <p>Щоб завершити реєстрацію, введіть цей код підтвердження на сторінці входу:</p>
    <p><span class='code'>$code</span></p>
    <p>Якщо ви не створювали обліковий запис, просто проігноруйте це повідомлення.</p>
  </div>
</body>
</html>
";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Mail error: ' . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Відправка листа з посиланням для відновлення паролю.
 * @param int $userId ID користувача
 * @param string $email Email одержувача
 * @param string $fname Ім'я користувача
 * @param string $resetLink Повне посилання для скидання паролю (strepair.php?token=...)
 * @return bool
 */
function sendPasswordResetEmail($userId, $email, $fname, $resetLink)
{
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'shanapra.activation@gmail.com';
        $mail->Password   = 'yzsa bdpp tefm wbnp';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('shanapra.activation@gmail.com', 'ShanaPra');
        $mail->addAddress($email, $fname);
        $mail->addReplyTo('shanapra.activation@gmail.com', 'Support');
        $mail->CharSet = 'UTF-8';
        $mail->isHTML(true);

        $mail->Subject = 'Відновлення паролю — ShanaPra';

        $mail->Body = "
<html>
<head>
  <meta charset='UTF-8'>
  <style>
    body { font-family: Arial, sans-serif; background-color: #f4f4f7; color: #333; margin: 0; padding: 0; }
    .container { max-width: 600px; margin: 30px auto; padding: 24px; background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); }
    h1 { color: #2563eb; font-size: 22px; margin-bottom: 16px; }
    p { font-size: 15px; line-height: 1.6; margin-bottom: 16px; }
    a.button { display: inline-block; padding: 12px 24px; background: #2563eb; color: #fff !important; text-decoration: none; border-radius: 8px; font-weight: bold; }
    a.button:hover { background: #1e4fc1; }
    .muted { font-size: 13px; color: #666; margin-top: 20px; }
  </style>
</head>
<body>
  <div class='container'>
    <h1>Відновлення паролю</h1>
    <p>Вітаємо, " . htmlspecialchars($fname, ENT_QUOTES, 'UTF-8') . "!</p>
    <p>Ви надіслали запит на відновлення паролю. Натисніть кнопку нижче, щоб встановити новий пароль:</p>
    <p><a href='" . htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8') . "' class='button'>Встановити новий пароль</a></p>
    <p class='muted'>Посилання дійсне 1 годину. Якщо ви не запитували відновлення паролю, проігноруйте цей лист.</p>
  </div>
</body>
</html>
";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Password reset mail error: ' . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Лист саме про скидання пароля (для авторизованого користувача з профілю).
 * Посилання веде на auth.php?reset_token=... — форму встановлення нового пароля.
 *
 * @param int $userId
 * @param string $email
 * @param string $fname
 * @param string $resetLink auth.php?reset_token=...
 * @return bool
 */
function sendProfilePasswordResetEmail($userId, $email, $fname, $resetLink)
{
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'shanapra.activation@gmail.com';
        $mail->Password   = 'yzsa bdpp tefm wbnp';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('shanapra.activation@gmail.com', 'ShanaPra');
        $mail->addAddress($email, $fname);
        $mail->addReplyTo('shanapra.activation@gmail.com', 'Support');
        $mail->CharSet = 'UTF-8';
        $mail->isHTML(true);

        $mail->Subject = 'Скидання пароля — ShanaPra';

        $mail->Body = "
<html>
<head>
  <meta charset='UTF-8'>
  <style>
    body { font-family: Arial, sans-serif; background-color: #f4f4f7; color: #333; margin: 0; padding: 0; }
    .container { max-width: 600px; margin: 30px auto; padding: 24px; background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); }
    h1 { color: #2563eb; font-size: 22px; margin-bottom: 16px; }
    p { font-size: 15px; line-height: 1.6; margin-bottom: 16px; }
    a.button { display: inline-block; padding: 12px 24px; background: #2563eb; color: #fff !important; text-decoration: none; border-radius: 8px; font-weight: bold; }
    a.button:hover { background: #1e4fc1; }
    .muted { font-size: 13px; color: #666; margin-top: 20px; }
  </style>
</head>
<body>
  <div class='container'>
    <h1>Скидання пароля</h1>
    <p>Вітаємо, " . htmlspecialchars($fname, ENT_QUOTES, 'UTF-8') . "!</p>
    <p>Ви запросили скидання пароля в налаштуваннях профілю. Натисніть кнопку нижче, щоб перейти до форми встановлення нового пароля:</p>
    <p><a href='" . htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8') . "' class='button'>Встановити новий пароль</a></p>
    <p class='muted'>Посилання дійсне 1 годину. Якщо ви не запитували скидання пароля, проігноруйте цей лист.</p>
  </div>
</body>
</html>
";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Profile password reset mail error: ' . $mail->ErrorInfo);
        return false;
    }
}
