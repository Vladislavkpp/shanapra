<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
/**
 * @var $md
 */
//require_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';
require_once "function.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/validator.php";

//use vendor\vodovra\Page;
//use vendor\vodovra\View;
//use vendor\vodovra\Dbs;

if (isset($_SESSION['logged']) && $_SESSION['logged'] == 1) {
    header('Location: /profile.php');
    exit;
}

View_Clear();
View_Add(Page_Up());
View_Add(Menu_Up());
View_Add('<div class="out">');
//View_Add(Menu_Left());

$em = '';
$ep = '';
$errorMsg = '';
$successMsg = '';

if ($md == 10) {

    $em = $_POST['email'] ?? '';
    $ep = $_POST['password'] ?? '';

    $emError = '';
    $epError = '';

    $em1 = valide1($em, 'email', $emError);
    $ep1 = valide1($ep, 'password', $epError);

    if ($em1 !== '' && $ep1 !== '') {
        $dblink = DbConnect();

        // Проверяем есть ли уже пользователь с такой почтой
        $emEscaped = mysqli_real_escape_string($dblink, $em1);
        $res = mysqli_query($dblink, 'SELECT idx FROM users WHERE email="' . $emEscaped . '"');

        if (mysqli_num_rows($res) > 0) {
            $errorMsg = "Користувач уже зареєстрований";
        } else {
            $passhash = md5($ep1);
            $sql = 'INSERT INTO users (email, pasw) VALUES ("' . $emEscaped . '", "' . $passhash . '")';
            $ok = mysqli_query($dblink, $sql);

            if ($ok) {
                $userId = mysqli_insert_id($dblink);

                $_SESSION['logged'] = 1;
                $_SESSION['uzver'] = $userId;
                $_SESSION['last_activity'] = time();

                $successMsg = "Реєстрація успішна!";

                echo '<meta http-equiv="refresh" content="2;url=/profile.php">';
            } else {
                $errorMsg = "Помилка при збереженні користувача";
            }
        }

        mysqli_close($dblink);
    } else {
        // Если валидация не прошла — выводим ошибку
        $errorMsg = $emError ?: $epError ?: "Заповніть усі поля";
    }
}


View_Add('
<div class="regform-container ' .
    (!empty($errorMsg) ? 'has-error' : (!empty($successMsg) ? 'has-success' : '')) . '">
    <form action="/stregs.php" method="post">
        <input type="hidden" name="md" value="10">
        <div class="regform-title regform-text-center">Реєстрація користувача</div>
       
     
        <div class="regform-row regform-vertical">
            <div class="regform-input-container">
                <input class="regform-input" id="regEmail" name="email" type="email" value="' . $em . '" placeholder=" " required>
                <label for="regEmail">E-mail</label>
            </div>
        </div>

        <div class="regform-row regform-vertical">
            <div class="regform-input-container" style="position: relative;">
                <input class="regform-input" id="regPass" name="password" type="password" value="' . $ep . '" placeholder=" " required>
                <label for="regPass">Пароль</label>
                <button type="button" id="togglePass" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; padding: 0;">
                </button>
            </div>
        </div>

        ' . (!empty($errorMsg) ? '<div class="regerror1">' . $errorMsg . '</div>' : '') . '
        ' . (!empty($successMsg) ? '<div class="regsuccess1">' . $successMsg . '</div>' : '') . '

        <div class="regform-row regform-vertical but">
            <input class="regform-button" type="submit" value="Зареєструватися">
        </div>
          <div class="regform-row regform-center regform-login-line a">
            <span>Вже зареєстровані? <a class="regform-reg-link" href="/auth.php">Авторизуватися</a></span>
        </div>
    </form>
</div>
');

View_Add('</div>'); // .out

View_Add(Page_Down());
View_Out();
View_Clear();