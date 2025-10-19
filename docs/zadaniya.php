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
View_Add('<pre style="color:white;font-weight: bold;"><a href="/gravelist.php" style="color:white;font-weight: bold;">Spisok</a> <br/>');
View_Add('<a href="/backup.php" style="color:white;font-weight: bold;">BackUp</a> <br/>');
View_Add('<a href="/graveupdate.php" style="color:white;font-weight: bold;">Updatelist</a> <br/>');
View_Add('* <br/>');
View_Add('* <br/>');
View_Add('* <br/>');
View_Add('* <br/>');
View_Add('* <br/>');
//-----------------------------------------------------------------------------------------------
//11.08.2025 - github
/* git add .
git commit -m "19.10"
git push origin master */   // master - рабочая ветка

//git branch  - список веток , git checkout имя_ветки - переключиться на рабочую ветку.


//-----------------------------------------------------------------------------------------------
View_Add('</div>');
View_Add(Page_Down());
View_Out();
View_Clear();