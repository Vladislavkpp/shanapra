<?php
require_once $_SERVER['DOCUMENT_ROOT'] .'/vendor/autoload.php';
use vendor\vodovra\Page;
use vendor\vodovra\View;
//use vendor\vodovra\Dbs;
use vendor\vodovra\Region;

Page::SetTitle('Деталізований пошук');
View::Add(Page::OutTop());
//View::Add(''); //
//View::Add(Region::SelectRegion('a',7,0));

View::Add('check Вся Україна');
View::Add('check Область та район'.Region::SelectRegion());
View::Add('check Населенний пункт');

View::Add('Додати знайдене до обраного');


View::Add('<div class="login-formContainer text2center"><b><u>Пошук по базі</u></b>');
View::Add('<form action="/searchout.php" method="get">');
View::Add('<input type="hidden" name="page" value="1">');
View::Add('<input type="hidden" name="s" value="2">');
View::Add('<input type="text" name="" class="login-Input brdr" placeholder="Прізвище"><br>');
View::Add('<input type="text" name="" class="login-Input brdr" placeholder="І\'мя"><br>');
View::Add('<input type="text" name="" class="login-Input brdr" placeholder="По-батькові"><br>');
View::Add('<input type="date" name="" class="login-Input brdr" placeholder="Дата народження"><br>');
View::Add('<input type="date" name="" class="login-Input brdr" placeholder="Дата смерті"><br>');
View::Add('<input type="submit" class="login-Binput" value="Знайти">');
View::Add('</form>');
View::Add('</div>');



View::Add(Page::OutBottom());
View::Out();
