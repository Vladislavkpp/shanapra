<?php
require_once $_SERVER['DOCUMENT_ROOT'] .'/vendor/autoload.php';

//use vendor\vodovra\Dbs;
use vendor\vodovra\Page;
use vendor\vodovra\View;

Page::SetTitle('Персональна сторінка');
View::Add(Page::OutTop());
//View::Add(''); //
View::Add('Персональна сторінка');


View::Add(Page::OutBottom());
View::Out();
