<?php
/**
 * @var $md
 */
require_once $_SERVER['DOCUMENT_ROOT'] .'/vendor/autoload.php';

use vendor\vodovra\Dbs;
use vendor\vodovra\Page;
use vendor\vodovra\View;

if ($md=='3434KF-EGT09RIT0E9I-T345T3-JF0EJF00495T-3049FE-LTR34O5634T3')
{
    // дані з форми Входу
    $e=$_POST['emailForLogin'] ?? '';
    $p=$_POST['paswForLogin'] ?? '';
    $q=Dbs::Select('* from users where ((email="'.$e.'")and(pasw="'.md5($p).'"))');
    if ($q!=false)
    {
        $_SESSION['logged']=1;
        $_SESSION['uzver']=$e;
    }else{
        $_SESSION['logged']=0;
        $_SESSION['uzver']='';
    }
}

if ($md=='356VBB-ER6RTY47656G-474563-6556HJYFYYU8-566YF7-56YUFGD42231')
{
    // вихід з Профілю
    $_SESSION['logged']=0;
    $_SESSION['uzver']='';
}

Page::SetTitle('Головна');
View::Add(Page::OutTop());
//View::Add(''); //
View::Add('<p>... щось на кшталт привітання ...</p>
<p>Наша мета - ... наша місія ...</p>
<p>Ми не збираємо персональні дані ...</p>
<p>Наявна інформація надана користувачами ІПС на добровільних засадах. ...</p>
<hr>');
View::Add('<div class="">Усього в базі '.Dbs::Count().' поховань</div><br>');
View::Add('<div class="login-formContainer text2center"><b><u>Спрощений пошук по базі</u></b>');
View::Add('<form action="/searchout.php" method="get">');
View::Add('<input type="hidden" name="page" value="1">');
View::Add('<input type="hidden" name="s" value="1">');
View::Add('<input type="text" name="" class="login-Input brdr" placeholder="Прізвище"><br>');
View::Add('<input type="text" name="" class="login-Input brdr" placeholder="Ім`я"><br>');
View::Add('<input type="text" name="" class="login-Input brdr" placeholder="По-батькові"><br>');
View::Add('<input type="date" name="" class="login-Input brdr" placeholder="Дата народження"><br>');
View::Add('<input type="date" name="" class="login-Input brdr" placeholder="Дата смерті"><br>');
View::Add('<input type="submit" class="login-Binput" value="Знайти">');
View::Add('</form>');
View::Add('<a href="/search.php">Деталізования пошук</a>');
View::Add('</div><br><br>');



View::Add(Page::OutBottom());
View::Out();

