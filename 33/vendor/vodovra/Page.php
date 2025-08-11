<?php

namespace vendor\Vodovra;
require_once $_SERVER['DOCUMENT_ROOT'] .'/libraries/init.php';
use vendor\vodovra\Config;

/*** Class Page
 *
 *  Written 2023-02-21 12:38
 */
class Page
{
    private static string $title = '';
    private static string $keyword = '';
    private static string $css = ''; //'/assets/css/bootstrap.min.css';
    private static string $style = '';

    public static function OutTop(): string
    {
        $out = '<!DOCTYPE html>' . xbr .
            '<html lang="uk">' . xbr .
            '<head>' . xbr .
            '<title>ІПС Shana | ' . self::$title . '</title>' . xbr .
            '<meta charset="utf-8">' . xbr .
            '<meta http-equiv="Content-Type" content="text/html">' . xbr .
            '<meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=1, shrink-to-fit=yes">' . xbr .
            '<link rel="icon" href="/assets/images/favicon_ico.jpg" type="image/x-icon">' . xbr .
            '<meta name="robots" content="all">' . xbr .
            //'<link rel="stylesheet" href="/assets/css/bootstrap.min.css">' . xbr .
            //'<link rel="stylesheet" href="/assets/css/font-awesome.min.css">' . xbr .
            '<link rel="stylesheet" href="/assets/css/shana.css">' . xbr .
            //'<link rel="stylesheet" href="/assets/css/style.css">' . xbr .
            '</head>' . xbr .
            '<body class="bg-dark">' . xbr .
            '<div id="wrapper" class="wrapper">' . xbr .
            '<div class="w100 logo-h logo-txt">' . xbr .
            '<div class="logo">' . xbr .
            '<a class="" href="/"><img src="/assets/images/logobrand.jpg" alt="Our logo" title="Our logo" class="logo-img logo-h"></a></div>' . xbr .
            '<div class="page-login logo-h">LOGiN</div>'.xbr .
            '</div>' . xbr .
            '<nav id="wrapper-menu" class="bg-dark" role="navigation">' . xbr .
            '<ul class="side-menu">' . xbr .
            '<li><a href="/"><i class="fa fa-diamond"></i> <span class="nav-label">Головна</span></a></li>' . xbr .
            '<li><a href="/userpage.php"><i class="fa fa-diamond"></i> <span class="nav-label">Профіль</span></a></li>' . xbr .
            '<li><a href="/agency.php"><i class="fa fa-diamond"></i> <span class="nav-label">Ритуальні бюро</span></a></li>' . xbr .
            '<li><a href="/"><i class="fa fa-diamond"></i> <span class="nav-label">Кладовища</span></a></li>' . xbr .
            '<li><a href="/service.php"><i class="fa fa-diamond"></i> <span class="nav-label">Послуги</span></a></li>' . xbr .
            '<li><a href="/obituary.php"><i class="fa fa-diamond"></i> <span class="nav-label">Некрологи</span></a></li>' . xbr .
            '<li><a href="/favor.php"><i class="fa fa-diamond"></i> <span class="nav-label">Обране</span></a></li>' . xbr .
            '<li><a href="/contacts.php"><i class="fa fa-diamond"></i> <span class="nav-label">Контакти</span></a></li>' . xbr .
            '<li><a href="/"><i class="fa fa-diamond"></i> <span class="nav-label">Робота</span></a></li>' . xbr .
            '<li><a href="/"><i class="fa fa-diamond"></i> <span class="nav-label">Оголошення</span></a></li>' . xbr .
            '<li><a href="/"><i class="fa fa-diamond"></i> <span class="nav-label">Реклама</span></a></li>' . xbr .
            '<li><a href="/"><i class="fa fa-diamond"></i> <span class="nav-label">Церкви</span></a></li>' . xbr .
            '</ul>' . xbr .
            '</nav>' . xbr .
            '<div id="wrapper-page" class="bg-gray">' . xbr .
            '<div class="wrapper-content">' . xbr;
        return $out;
    }

    public static function OutBottom(): string
    {
        return ' </div></div><div class="footer text2center"><div><strong> Copyright</strong> VodovRA Company &copy; 2024 </div></div>' . xbr .
            '</body > ' . xbr .
            '</html > ';
    }

    public static function SetTitle(string $a = '')
    {
        self::$title = $a;
    }

    public static function SetKeyword(string $a = '')
    {
        self::$keyword = $a;
    }

    public static function SetCSS(string $a = '')
    {
        self::$css = $a;
    }

    public static function SetStyle(string $a = '')
    {
        self::$style = $a;
    }

}


