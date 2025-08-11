<?php
/**
 * @var $md
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

use vendor\vodovra\Page;
use vendor\vodovra\View;
//use vendor\vodovra\Dbs;

Page::SetTitle('Відновлення паролю');
View::Add(Page::OutTop());
if ($md=='34') {

    // send out mail to E-mail & write to DB
    // ...................................
    // ...................................

    View::Add('<br><div class="login-formContainer" >
        <form action = "/" method = "get" >
            <div class="login-formline text2center textunderline fontbold" >Відновлення паролю</div >
            <div class="login-formline" >
            На вказаний E-mail було надіслано листа з посиланням для формування нового паролю
            </div>
            <div class="login-formline" >
                <br><input class="login-Binput" type = "submit" value = "Продовжити" >
            </div>
            </form>
   
    </div><br>');
}
else
{
    View::Add('<br><div class="login-formContainer" >
        <form action = "/strepair.php" method = "post" >
            <div class="login-formline text2center textunderline" >Відновлення паролю</div >
            <input type="hidden" name="md" value="34">
            <div class="login-formline" >
                <fieldset class="login-fieldset">
                    <legend class="login-fieldset-legend" > E-mail</legend >
                    <input class="login-Input" id = "emailForLogin" type = "email" placeholder = "E-mail" required>
                </fieldset>
            </div>
            <div class="login-formline" >
                <br><input class="login-Binput" type = "submit" value = "Продовжити" >
            </div>
            </form>
   
    </div><br>');
}
View::Add(Page::OutBottom());
View::Out();