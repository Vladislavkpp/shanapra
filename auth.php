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


View_Clear();
View_Add(Page_Up());
View_Add(Menu_Up());
View_Add('<div class="out">');
//View_Add(Menu_Left());

$err = '';

if (($md == 0) || ($md == '')) {
    View_Add('
<div class="logform-container">
    <form action="/auth.php" method="post">
        <input type="hidden" name="md" value="5">

        <div class="logform-title logform-text-center">Вхід в систему</div>

        <div class="logform-row logform-vertical">
            <div class="logform-input-container">
                <input class="logform-input" name="emailForLogin" type="email" placeholder=" " required>
                <label>E-mail</label>
            </div>
        </div>

        <div class="logform-row logform-vertical">
            <div class="logform-input-container">
                <input class="logform-input" name="paswForLogin" type="password" placeholder=" " required>
                <label>Пароль</label>
            </div>
        </div>

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



<div class="authform-container2">
    <div class="logform-row logform-vertical logform-google-row">
        <a href="" class="logform-button logform-google">
            <img src="/assets/images/google-icon.svg" alt="" width="17" height="17">
            Увійти за допомогою Google
        </a>
    </div>
    
    </div>
</div>

');
}

if ($md == 5) {
    //проверка емейл в бд, если ок то запись. если не ок выдать ошибку неверный пароль и предложить перейти на страницу сбросить пароль
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
    $sql = 'SELECT * FROM users WHERE ((email="' . $em . '")AND (pasw="' . md5($ep) . '"))';
    $res = mysqli_query($dblink, $sql);
    View_Add('===' . $sql);
    $cnt = mysqli_num_rows($res);
    if ($cnt == 1) {
        //ok
        $a = mysqli_fetch_assoc($res);
        $_SESSION['logged'] = 1;
        $_SESSION['uzver'] = $a['idx'];
        $md = 25;
    } else {
        $md = 7;
    }


}
if ($md == 7) { //Ошибки валидации
    View_Add('
<div class="logform-container">
    <form action="/auth.php" method="post">
        <input type="hidden" name="md" value="5">

        <div class="logform-title logform-text-center">Вхід в систему</div>

        <div class="logform-row logform-vertical">
            <div class="logform-input-container">
                <input class="logform-input" name="emailForLogin" type="email" value="' . $em . '" placeholder=" " required>
                <label>E-mail</label>
            </div>
        </div>
        
        <div class="warn">
        ' . $em1 . '
</div>

        <div class="logform-row logform-vertical">
            <div class="logform-input-container">
                <input class="logform-input" name="paswForLogin" type="password" value="' . $ep . '" placeholder=" " required>
                <label>Пароль</label>
            </div>
        </div>
        
         <div class="warn">
        ' . $ep1 . '
</div>

        <div class="logform-row logform-vertical">
            <input class="logform-button" type="submit" value="Увійти">
        </div>

        <div class="logform-row logform-right">
            <a class="logform-advanced-link" href="/strepair.php">Забули пароль?</a>
        </div>
        
        <div class="logform-row logform-center logform-registration-line">
            <span>Ще не зареєстровані? <a class="logform-reg-link" href="/stregs.php">Зареєструватися</a></span>
        </div>

 
 <div class="authform-container2">
<div class="logform-row logform-vertical logform-google-row">
            <button class="logform-button logform-google" type="button">
                <img src="/assets/images/google-icon.svg" alt="" width="17" height="17">
                <a href="<?=htmlspecialchars($login_url)?>">Увійти за допомогою Google</a>
            </button>
        </div>
</div>

    </form>
</div>
');
}
if ($md == 25) {
    header("refresh: 1; url=http://shanapra.com/profile.php");
    exit;
}



View_Add('</div></div>');
View_Add(Page_Down());
View_Out();
View_Clear();
