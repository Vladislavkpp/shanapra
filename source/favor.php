<?php
require_once $_SERVER['DOCUMENT_ROOT'] .'/vendor/autoload.php';
use vendor\vodovra\Page;
use vendor\vodovra\View;
//use vendor\vodovra\ViewX;

//use vendor\vodovra\Dbs;
use vendor\vodovra\Region;

Page::SetTitle('Обране');
View::Add(Page::OutTop());
//View::Add(''); //
View::Add(Region::SelectRegion('a'));

View::Add('Перелік осіб під наглядом');


View::Add(Page::OutBottom());
View::Out();
