<?php
require_once $_SERVER['DOCUMENT_ROOT'] .'/vendor/autoload.php';
use vendor\vodovra\Page;
use vendor\vodovra\View;
//use vendor\vodovra\Dbs;
//use vendor\vodovra\Region;

Page::SetTitle('Некрологи');
View::Add(Page::OutTop());
//View::Add(''); //
View::Add('Останні записи:'); // останні 20 записів
//View::Add(''); //
//View::Add(Region::SelectRegion('a',7,0));









View::Add(Page::OutBottom());
View::Out();
