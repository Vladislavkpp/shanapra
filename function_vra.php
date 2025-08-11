<?php
//const DEBUG = 1;
//define("BASE", dirname(__DIR__));
define("BASE", $_SERVER['DOCUMENT_ROOT']);
const WWW = 'http://127.0.0.1';
//define("WWW", 'http://shanapra.com');
const ASSETS = WWW . '/assets';
const UPLOADSWWW = WWW . '/uploads';
const UPLOADSLOC = BASE . '/uploads';
const NO_IMAGE = ASSETS . '/images/no_image.jpg';
const VENDOR = BASE . '/vendor/';


class functions_vra
{

}

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

use mysqli;

/**
 * Class Dbs
 *
 *  Written 2023-02-21 12:38
 */
class Dbs
{
    private static string $dbname = 'shana2k4';
    private static string $dbuserro = 'root';
    private static string $dbuserrw = 'root';
    private static string $dbpaswro = '958189';
    private static string $dbpaswrw = '958189';
    private static false|mysqli|null|string $connro = NULL;
    private static false|mysqli|null|string $connrw = NULL;

    /**
     * @return int Total records in database
     */
    public static function Count($zap = ''): int
    {
        self::ConnectReadOnly();
        $sql = 'SELECT count(idx) as t1 FROM grave';
        if ($zap != '') {
            $sql .= $zap;
        }
        $res = mysqli_query(self::$connro, $sql);
        if (!$res) {
            $out = 0;
        } else {
            $out = mysqli_fetch_assoc($res);
        }

        return $out;
    }

    private static function ConnectReadOnly()
    {
        if (is_null(self::$connro)) {
            self::$connro = @mysqli_connect('localhost', self::$dbuserro, self::$dbpaswro, self::$dbname);
            if (!self::$connro) {
                die('Database not connected. Shutdown ...');
            }
            mysqli_query(self::$connro, "set character_set_client='utf8'");
            mysqli_query(self::$connro, "set character_set_results='utf8'");
            mysqli_query(self::$connro, "set collation_connection='utf8_general_ci'");
        }
    }

    /**
     * @param string $query
     * @return array|bool
     */
    public static function Select(string $query = ''): array|bool
    {
        self::ConnectReadOnly();
        $res = mysqli_query(self::$connro, 'SELECT ' . $query);
        if ($res != false) {
            $out = [];
            while ($r = mysqli_fetch_assoc($res)) {
                $out[] = $r;
            }
            return $out;
        }
        return $res;
    }

    /**
     * @param string $query
     * @return bool
     */
    public static function Insert(string $query = ''): bool
    {
        self::ConnectReadWrite();
        return mysqli_query(self::$connrw, 'INSERT INTO ' . $query);
    }

    private static function ConnectReadWrite()
    {
        if (is_null(self::$connrw)) {
            self::$connrw = @mysqli_connect('localhost', self::$dbuserrw, self::$dbpaswrw, self::$dbname);
            if (!self::$connrw) {
                die('Database not connected. Shutdown ...');
            }
            mysqli_query(self::$connrw, "set character_set_client='utf8'");
            mysqli_query(self::$connrw, "set character_set_results='utf8'");
            mysqli_query(self::$connrw, "set collation_connection='utf8_general_ci'");
        }
    }

    /**
     * @param string $query
     * @return bool
     */
    public static function Update(string $query = ''): bool
    {
        self::ConnectReadWrite();
        return mysqli_query(self::$connrw, 'UPDATE ' . $query);
    }

    /**
     * @param string $query
     * @return bool
     */
    public static function Delete(string $query = ''): bool
    {
        self::ConnectReadWrite();
        return mysqli_query(self::$connrw, 'DELETE ' . $query);
    }
}

