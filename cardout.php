<?php
require_once "function.php";

Page::SetTitle('Персональна сторінка');
View::Add(Page::OutTop());
//View::Add(''); //
View::Add('Персональна сторінка');


View::Add(Page::OutBottom());
View::Out();
