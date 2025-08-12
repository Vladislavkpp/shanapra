<?php
/**
 * @var $md
 * @var $buf
 */
//require_once $_SERVER['DOCUMENT_ROOT'] .'/vendor/autoload.php';
require_once $_SERVER['DOCUMENT_ROOT'] ."/function.php";

View_Clear();
View_Add(Page_Up());
View_Add(Menu_Up());
View_Add('<div class="out">');
//View_Add(Menu_Left());
//-----------------------------------------------------------------------------------------------
//View_Add('* <br/>');
View_Add('09-08-2025<br/>');
View_Add('++ створи файл sitemap.xml<br/>');
View_Add('++ в таблиці grave після колонки idxadd додати idtadd типу timestamp, де idxadd - користувач який додав поховання, а idtadd - час додавання<br/>');
View_Add('++ при похованні поля idxadd та idtadd також мають заповнювіатись<br/>');
View_Add('* <br/>');
View_Add('10-08-2025<br/>');
View_Add('++ Чому я на цій сторінці не бачу логотип?<br/>');
View_Add('++ graveadd.php - фото лиця перенести пд фото поховання<br/>');
View_Add('* <br/>');
View_Add('12-08-2025<br/>');
View_Add('++ graveadd.php знизу добавити білу зону<br/>');
View_Add('++ на всіх сторінках зробити темніший фон<br/>');
View_Add('* <br/>');
View_Add('* <br/>');
View_Add('* <br/>');
View_Add('* <br/>');
View_Add('* <br/>');
//-----------------------------------------------------------------------------------------------
//11.08.2025 - github
/* git add .
git commit -m "Краткое описание изменений"
git push origin master */   // master - рабочая ветка

//-----------------------------------------------------------------------------------------------
View_Add('</div>');
View_Add(Page_Down());
View_Out();
View_Clear();