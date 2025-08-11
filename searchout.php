<?php
/**
 * @var $md
 * @var $buf
 */
//require_once $_SERVER['DOCUMENT_ROOT'] .'/vendor/autoload.php';
require_once "function.php";
require_once "function_vra.php";

$cp= $_GET['page'] ?? 1;
$perpage=3;
View_Clear();
View_Add(Page_Up('Результати пошуку'));
View_Add(Menu_Up());
View_Add('<div class="out">');

View_Add(Menu_Left());

View_Add('<div class="search-out">');
View_Add('Пошук за параметрами:<br>');
View_Add('Результатів всього:<br>');
$dblink = DbConnect();
$sql='SELECT * FROM grave';
$res = mysqli_query($dblink, $sql);
$us = mysqli_fetch_assoc($res);
$cout=DbsCount();
$out=Dbs_Select($sql.' LIMIT '.(($cp-1)*$perpage).','.$perpage);
View_Add('Картки '.$cout.'<br>');
View_Add('</div>');
View_Add('<div class="cards-out">');
foreach ($out as $i=>$c) {
    View_Add(Cards_Card($c['idx'],$c['lname'],$c['fname'],$c['mname'],$c['dt1'],$c['dt2'],$c['photo1']));
}
View_Add('</div><br>'.xbr);
View_Add('<div class="paginator-out">');
View_Add(Paginate::Show($cp,$cout,$perpage));
View_Add('</div><br>'.xbr);
View_Add($sql.' LIMIT '.(($cp-1)*$perpage).','.$perpage);

View_Add(Page_Down());
View_Out();