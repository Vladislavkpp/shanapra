<?php
/**
 * @var $md
 * @var $buf
 */
require_once $_SERVER['DOCUMENT_ROOT'] ."/function.php";

View_Clear();
View_Add(Page_Up());
View_Add(Menu_Up());
View_Add('<div class="out">');
View_Add(Menu_Left());

//Основной екран

$b=array('index.php', 'function.php');


View_Add('</div>');
View_Add(Page_Down());
View_Out();
View_Clear();
