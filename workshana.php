<?php
/**
 * @var $md
 * @var $buf
 */

require_once $_SERVER['DOCUMENT_ROOT'] ."/function.php";




// === Вывод страницы ===
View_Clear();
View_Add(Page_Up());
View_Add(Menu_Up());

View_Add('<div class="out">');

//контент

View_Add('</div>'); // .out

View_Add(Page_Down());
View_Out();
View_Clear();