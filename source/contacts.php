<?php
require_once $_SERVER['DOCUMENT_ROOT'] .'/vendor/autoload.php';
use vendor\vodovra\Page;
use vendor\vodovra\View;
//use vendor\vodovra\Dbs;
//use vendor\vodovra\Region;

Page::SetTitle('Контакти');
View::Add(Page::OutTop());
View::Add('Контакти');










View::Add(Page::OutBottom());
View::Out();
