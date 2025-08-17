<?php
/**
 * @var $md
 * @var $bufView_Add(SetTitle('Персональна сторінка'));
 */
require_once $_SERVER['DOCUMENT_ROOT'] ."/function.php";

View_Clear();
View_Add(Page_Up());

View_Add(Menu_Up());
View_Add('<div class="out">');
View_Add(Menu_Left());





View_Add('</div>');
View_Add(Page_Down());
View_Out();
View_Clear();