<?php
/**
 * @var $md
 */
//equire_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';
require_once  $_SERVER['DOCUMENT_ROOT'] . "/function.php";
require_once "vendor/autoload.php";

//use vendor\vodovra\Page;
//use vendor\vodovra\View;
//use vendor\vodovra\Dbs;



View_Clear();
View_Add(Page_Up());
View_Add(Menu_Up());
View_Add('<div class="out">');
//View_Add(Menu_Left());

$client = new //Google_Client();
//$client->setClientId('');

$client->setRedirectUri();
$client->addScope('email');
$client->addScope('profile');

$login_url=$client->createAuthUrl();

$err='';
if ($md == "3434KF-EGT09RIT0E9I-T345T3-JF0EJF00495T-3049FE-LTR34O5634T3") {
    $em = $_POST['emailForLogin'];
    $ep = $_POST['paswForLogin'];
 //   $i = Dbs::Count('SELECT * FROM users WHERE ((email="' . $em . '")AND (pasw="' . $ep . '"))');
    if ($i == 1) {
        $_SESSION['logged'] = 1;
        //   $e=Dbs::iii['idx'];
        $_SESSION['uzver'] = 1;
        $err='';

    } else {
        $_SESSION['logged'] = 0;
        $_SESSION['uzver'] = "";
        $err=warn("Користувача з таким логіном та паролем не знайдено.");}
    /*    $e0 = '<div class="login-formline text2right"> Ще не зареєстровані ? <a href = "/stregs.php"> Зареєструватися</a></div>
            <div class="login-formline text2center"> або</div>
            <div class="login-formline">
                <button class="login-Binput">
                    <img class="" height = "17px" src = "/assets/images/google-icon.svg" width = "17px" alt="">
                    <span class=""> Увійти за допомогою Google </span>
                </button>
            </div>';*/


}

//Обработка Google входа
/*if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['credential'])) {

    $token = $_POST['credential'];
    $client = new Google_Client(['client_id' => 'тут-айди_CLIENT_ID.apps.googleusercontent.com']); // сюда client_id

    try {
        $payload = $client->verifyIdToken($token);
        if ($payload) {
            session_start();
            $_SESSION['email'] = $payload['email'];
            $_SESSION['name'] = $payload['name'];

            // Редирект после успешного входа
            header("Location: /dashboard.php");
            exit;
        } else {
            echo "Невалідний токен";
        }
    } catch (Exception $e) {
        echo "Помилка: " . $e->getMessage();
    }

    exit;*/

//https://snipp.ru/php/oauth-google
//https://console.cloud.google.com/apis/credentials?authuser=4&inv=1&invt=Ab3R9Q&project=shanapra

// iraraco@gmail.com  1qazxcde3
View_Add($err);
View_Add('
<div class="logform-container">
    <form action="/stlogin.php" method="post">
        <input type="hidden" name="md" value="3434KF-EGT09RIT0E9I-T345T3-JF0EJF00495T-3049FE-LTR34O5634T3">

        <div class="logform-title logform-text-center">Авторизація</div>
<hr class="logform-divider">


        <div class="logform-row logform-center logform-registration-line">
            <span>Ще не зареєстровані? <a class="logform-reg-link" href="/stregs.php">Зареєструватися</a></span>
        </div>

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

        <div class="logform-row logform-vertical logform-google-row">
            <button class="logform-button logform-google" type="button">
                <img src="/assets/images/google-icon.svg" alt="" width="17" height="17">
                <a href="<?=htmlspecialchars($login_url)?>">>Увійти за допомогою Google</a>
            </button>
        </div>
    </form>
</div>
');




//View_Add(Contentgrave());

View_Add('</div></div>');
View_Add(Page_Down());
View_Out();
View_Clear();