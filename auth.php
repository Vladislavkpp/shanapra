<?php
/**
 * @var $md
 */
//equire_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';
require_once $_SERVER['DOCUMENT_ROOT'] . "/function.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/validator.php";
//require_once "vendor/autoload.php";

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

$authErrorMsg = '';
$authSuccessMsg = '';

if (($md == 0) || ($md == '')) {
        View_Add('
<div class="logform-container ' . (!empty($authErrorMsg) ? 'has-error' : (!empty($authSuccessMsg) ? 'has-success' : '')) . '">
    <form action="/auth.php" method="post">
        <input type="hidden" name="md" value="5">

        <div class="logform-title logform-text-center">Вхід в систему</div>

        <div class="logform-row logform-vertical">
            <div class="logform-input-container">
                <input class="logform-input" name="emailForLogin" type="email" value="' . htmlspecialchars($em ?? '') . '" placeholder=" " required>
                <label>E-mail</label>
            </div>
        </div>

        <div class="logform-row logform-vertical">
            <div class="logform-input-container">
                <input class="logform-input" name="paswForLogin" type="password" value="' . htmlspecialchars($ep ?? '') . '" placeholder=" " required>
                <label>Пароль</label>
            </div>
        </div>

        ' . (!empty($authErrorMsg) ? '<div class="regerror1">' . $authErrorMsg . '</div>' : '') . '
        ' . (!empty($authSuccessMsg) ? '<div class="regsuccess1">' . $authSuccessMsg . '</div>' : '') . '

        <div class="logform-row logform-vertical">
            <input class="logform-button" type="submit" value="Увійти">
        </div>

        <div class="logform-row logform-right">
            <a class="logform-advanced-link" href="/strepair.php">Забули пароль?</a>
        </div>

        <div class="logform-row logform-center logform-registration-line">
            <span>Ще не зареєстровані? <a class="logform-reg-link" href="/stregs.php">Зареєструватися</a></span>
        </div>
    </form>

  
</div>
');
    }



/*</form>

    <div class="authform-container2">
        <div class="logform-row logform-vertical logform-google-row">
            <a href="" class="logform-button logform-google">
                <img src="/assets/images/google-icon.svg" alt="" width="17" height="17">
                Увійти за допомогою Google
            </a>
        </div>
    </div>*/

if (!empty($authSuccessMsg)) {
    echo '<script>
        setTimeout(function() {
            window.location.href = "/profile.php";
        }, 3000);
    </script>';
}


if ($md == 5) {

    if (isset($_POST['emailForLogin'])) {
        $em = $_POST['emailForLogin'];
    } else {
        $em = '';
    }

    if (isset($_POST['paswForLogin'])) {
        $ep = $_POST['paswForLogin'];
    } else {
        $ep = '';
    }

    $em1 = valide1();
    $ep1 = valide1();

    $dblink = DbConnect();
    $sql = 'SELECT * FROM users WHERE ((email="' . $em . '") AND (pasw="' . md5($ep) . '"))';
    $res = mysqli_query($dblink, $sql);


    $cnt = mysqli_num_rows($res);

    if ($cnt == 1) {
        // ok
        $a = mysqli_fetch_assoc($res);
        $_SESSION['logged'] = 1;
        $_SESSION['uzver'] = $a['idx'];

        $authSuccessMsg = "Вхід виконано успішно!";
        $md = 25;
    } else {
        // если пользователь не найден — пишем ошибку
        $authErrorMsg = "Користувача з таким e-mail або паролем не знайдено.";
        $md = 7;
    }


}
if ($md == 7) {

    $authErrorMsg = "Користувача з таким e-mail або паролем не знайдено.";

    View_Add('
<div class="logform-container ' . (!empty($authErrorMsg) ? 'has-error' : (!empty($authSuccessMsg) ? 'has-success' : '')) . '">
    <form action="/auth.php" method="post">
        <input type="hidden" name="md" value="5">

        <div class="logform-title logform-text-center">Вхід в систему</div>

        <div class="logform-row logform-vertical">
            <div class="logform-input-container">
                <input class="logform-input" name="emailForLogin" type="email" value="' . htmlspecialchars($em) . '" placeholder=" " required>
                <label>E-mail</label>
            </div>
        </div>

        <div class="logform-row logform-vertical">
            <div class="logform-input-container">
                <input class="logform-input" name="paswForLogin" type="password" value="' . htmlspecialchars($ep) . '" placeholder=" " required>
                <label>Пароль</label>
            </div>
        </div>

        ' . (!empty($authErrorMsg) ? '<div class="regerror1">' . $authErrorMsg . '</div>' : '') . '
        ' . (!empty($authSuccessMsg) ? '<div class="regsuccess1">' . $authSuccessMsg . '</div>' : '') . '

        <div class="logform-row logform-vertical">
            <input class="logform-button" type="submit" value="Увійти">
        </div>

        <div class="logform-row logform-right">
            <a class="logform-advanced-link" href="/strepair.php">Забули пароль?</a>
        </div>

        <div class="logform-row logform-center logform-registration-line">
            <span>Ще не зареєстровані? <a class="logform-reg-link" href="/stregs.php">Зареєструватися</a></span>
        </div>

        
    </form>
</div>
');
}


if ($md == 25) {
    header("Refresh:2; url=/profile.php");
    View_Add('
<div class="logform-container has-success">
    <form action="/auth.php" method="post">
        <input type="hidden" name="md" value="5">

        <div class="logform-title logform-text-center">Вхід в систему</div>

        <div class="logform-row logform-vertical">
            <div class="logform-input-container">
                <input class="logform-input" name="emailForLogin" type="email" value="' . htmlspecialchars($em ?? '') . '" placeholder=" " required>
                <label>E-mail</label>
            </div>
        </div>

        <div class="logform-row logform-vertical">
            <div class="logform-input-container">
                <input class="logform-input" name="paswForLogin" type="password" value="' . htmlspecialchars($ep ?? '') . '" placeholder=" " required>
                <label>Пароль</label>
            </div>
        </div>

        <div class="logform-row logform-vertical">
            <div class="regsuccess1" style="display:flex; justify-content:space-between; align-items:center;">
                <span>Вхід виконано успішно!</span>
                     </div>
                     
        </div>
      </form>
</div>
');
}


View_Add('</div>'); // .out

View_Add(Page_Down());
View_Out();
View_Clear();