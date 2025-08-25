<?php
//const DEBUG = 1;
//define("BASE", dirname(__DIR__));
define("BASE", $_SERVER['DOCUMENT_ROOT']);
//const WWW = 'http://127.0.0.1';
//define("WWW", 'http://shanapra.com');
//const ASSETS = WWW . '/assets';
//const UPLOADSWWW = WWW . '/uploads';
//const UPLOADSLOC = BASE . '/uploads';
//const NO_IMAGE = ASSETS . '/images/no_image.jpg';
//const VENDOR = BASE . '/vendor/';


class Paginate
{
    private static int $countPages = 0;
    private static string $uri = '';

    public static function getParams(): string
    {
        $url = $_SERVER['REQUEST_URI'];
        $url = explode('?', $url);
        $uri = $url[0];
        if (isset($url[1]) && $url[1] != '') {
            $uri .= '?';
            $params = explode('&', $url[1]);
            foreach ($params as $param) {
                if (!preg_match("#page=#", $param)) $uri .= $param . "&";
            }
        }
        return $uri;
    }

    public static function getLink($page):string
    {
        if ($page == 1) {
            return rtrim(self::$uri, '?&');
        }
        if (str_contains(self::$uri, '&')) {
            return self::$uri . "page=$page";
        } else {
            if (str_contains(self::$uri, '?')) {
                return self::$uri . "page=$page";
            } else {
                return self::$uri . "?page=$page";
            }
        }
    }

    /**Створює посилання на сторінки перегляду (пагінатор)
     * @param int $current номер поточної сторінки
     * @param int $total загальна кількість рядків даних
     * @param int $perpage кількість рядків (результатів) на сторінку
     * @return string
     */
    public static function Show($current = 0, $total = 0, $perpage = 1): string
    {
        self::$uri = self::getParams();
        $startpage = '';
        $back = '';
        $page2left = '';
        $page1left = '';
        $currentPage = $current;
        $page1right = '';
        $page2right = '';
        $forward = '';
        $endpage = '';

        self::$countPages = ceil($total / $perpage) ?: 1;
        // $back
        if ($currentPage > 1) {
            $back = "<li class='paginat-item'><a class='paginat-link' href='" . self::getLink($currentPage - 1) . "'>&lt;</a></li>";
        }
        // $forward
        if ($currentPage < self::$countPages) {
            $forward = "<li class='paginat-item'><a class='paginat-link' href='" . self::getLink($currentPage + 1) . "'>&gt;</a></li>";
        }
        // $startpage
        if ($currentPage > 3) {
            $startpage = "<li class='paginat-item'><a class='paginat-link' href='" . self::getLink(1) . "'>&laquo;</a></li>";
        }
        // $endpage
        if ($currentPage < (self::$countPages - 2)) {
            $endpage = "<li class='paginat-item'><a class='paginat-link' href='" . self::getLink(self::$countPages) . "'>&raquo;</a></li>";
        }
        // $page2left
        if ($currentPage - 2 > 0) {
            $page2left = "<li class='paginat-item'><a class='paginat-link' href='" . self::getLink($currentPage - 2) . "'>" . ($currentPage - 2) . "</a></li>";
        }
        // $page1left
        if ($currentPage - 1 > 0) {
            $page1left = "<li class='paginat-item'><a class='paginat-link' href='" . self::getLink($currentPage - 1) . "'>" . ($currentPage - 1) . "</a></li>";
        }
        // $page1right
        if ($currentPage + 1 <= self::$countPages) {
            $page1right = "<li class='paginat-item'><a class='paginat-link' href='" . self::getLink($currentPage + 1) . "'>" . ($currentPage + 1) . "</a></li>";
        }
        // $page2right
        if ($currentPage + 2 <= self::$countPages) {
            $page2right = "<li class='paginat-item'><a class='paginat-link' href='" . self::getLink($currentPage + 2) . "'>" . ($currentPage + 2) . "</a></li>";
        }
        return '<ul class="paginat">' . xbr .
            $startpage . xbr . $back . xbr . $page2left . xbr . $page1left . xbr .
            '<li class="paginat-item active"><a class="paginat-link">' . $currentPage . '</a></li>' . xbr .
            $page1right . xbr . $page2right . xbr . $forward . xbr . $endpage . xbr .
            '</ul>' . xbr;
    }
}

function DateFormat(string $d = ''): string
{
    $r = explode('.', str_replace('-', '.', $d));
    $out = $r[0];
    if (($r[1] == '') || ($r[1] == 0)) {
        $out = '-' . $out;
    } else {
        $out = $r[1] . '.' . $out;
    }
    if (($r[2] == '') || ($r[2] == 0)) {
        $out = '-' . $out;
    } else {
        $out = $r[2] . '.' . $out;
    }
    return $out;
}

function Cards(int $idx=0, string $f='',string $i='',string $o='', string $d1='', string $d2='', string $img=''):string
{
    // прямоугольная форма, серая граница, закругленные края, тень,
    // фотография на белом фоне, остальное на сером фоне, ФИО, дата1-дата2, детали...
    $out='<div class="cardx" style="margin-right: 10px;margin-bottom: 10px;">';
    $out.='<div class="cardx-img">';
    // no-foto
    if (!is_file($_SERVER['DOCUMENT_ROOT'].$img))
    {
        $img='/graves/no_image.png';
    }
    $out.='<img src="'.$img.'" class="cardx-image" alt="'.$f.' '.$i.' '.$o.'" title="'.$f.' '.$i.' '.$o.'">';
    $out.='</div>';
    $out.='<div class="cardx-data">';
    $out.='<div class="text2center font-bold font-white height50">';
    $out.=$f.' ';
    $out.=$i.' ';
    $out.=$o.'<br>';
    $out.='</div>';
    $out.='<div class="text2center font-white">';
    $out.=DateFormat($d1).' - ';
    $out.=DateFormat($d2).'<br>';
    $out.='</div>';
    $out.='<div class="text2right">';
    $out.='<a href="/cardout.php?idx='.$idx.'">детали...</a>';
    $out.='</div>';
    $out.='</div></div>';
    return $out;
}