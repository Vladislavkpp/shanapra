<?php

namespace vendor\Vodovra;
require_once $_SERVER['DOCUMENT_ROOT'] . '/libraries/init.php';

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
