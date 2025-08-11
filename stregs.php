<?php
/**
 * @var $md
 */
//require_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';
require_once "function.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/validator.php";

//use vendor\vodovra\Page;
//use vendor\vodovra\View;
//use vendor\vodovra\Dbs;

View_Clear();
View_Add(Page_Up());
View_Add(Menu_Up());
View_Add('<div class="out">');
//View_Add(Menu_Left());

$em = '';
$ep = '';

if ($md == 10) {
    if (isset($_POST['email'])) {
        $em = $_POST['email'];
    }

    if (isset($_POST['password'])) {
        $ep = $_POST['password'];
    }

    $em1 = valide1($em);
    $ep1 = valide1($ep);

    if ($em1 != '' && $ep1 != '') {
        $dblink = DbConnect();

        $res = mysqli_query($dblink, 'SELECT idx FROM users WHERE email="' . $em . '"');
        if (mysqli_num_rows($res) > 0) {
            View_Add('<div class="warn">' . $em1 . ' </div>'); // пользователь уже есть
        } else {
            $passhash = md5($ep);
            $sql = 'INSERT INTO users (email, pasw) VALUES ("' . $em . '", "' . $passhash . '")';
            View_Add('===' . $sql);
            $ok = mysqli_query($dblink, $sql);
            if ($ok) {
                View_Add('<div class="warn">' . $em1 . ' </div>'); // Успешно
            } else {
                View_Add('<div class="warn">' . $em1 . ' </div>'); // Ошибка при записи
            }
        }

        mysqli_close($dblink);
    } else {
        View_Add('<div class="warn">' . $em1 . ' </div>'); // заполните поля
    }
}




View_Add('
<div class="regform-container">
    <form action="?" method="post">
       <input type="hidden" name="md" value="10">
        <div class="regform-title regform-text-center">Реєстрація користувача</div>
        <hr class="regform-divider">
        
        <div class="regform-row regform-center regform-login-line">
            <span>Вже зареєстровані? <a class="regform-reg-link" href="/auth.php">Авторизуватися</a></span>
        </div>

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

        <div class="regform-row regform-vertical">
            <input class="regform-button" type="submit" value="Зареєструватися">
        </div>
    </form>
</div>

');


View_Add('</div></div>');
View_Add(Page_Down());
View_Out();
View_Clear();