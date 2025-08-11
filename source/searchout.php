<?php
require_once $_SERVER['DOCUMENT_ROOT'] .'/vendor/autoload.php';
use vendor\vodovra\Page;
use vendor\vodovra\View;
use vendor\vodovra\Dbs;
use vendor\vodovra\Paginate;
use vendor\vodovra\Cards;

$md = $_GET['md'] ?? '';
$md = $_POST['md'] ?? $md;
$md = strtolower($md);
$cp= $_GET['page'] ?? 1;
$perpage=3;
Page::SetTitle('Результати пошуку');
View::Add(Page::OutTop());
//View::Add(''); //
View::Add('<div class="search-out">');
View::Add('Пошук за параметрами:<br>');
View::Add('Результатів всього:<br>');
$sql='* from grave';
$cout=Dbs::Count();
$out=Dbs::Select($sql.' LIMIT '.(($cp-1)*$perpage).','.$perpage);
View::Add('Картки '.$cout.'<br>');
View::Add('</div>');
View::Add('<div class="cards-out">');
foreach ($out as $i=>$c) {
    View::Add(Cards::Card($c['idx'],$c['lname'],$c['fname'],$c['mname'],$c['dt1'],$c['dt2'],$c['photo1']));
}
View::Add('</div><br>'.xbr);
View::Add('<div class="paginator-out">');
View::Add(Paginate::Show($cp,$cout,$perpage));
View::Add('</div><br>'.xbr);
//View::Add($sql.' LIMIT '.(($cp-1)*$perpage).','.$perpage);
View::Add(Page::OutBottom());
View::Out();