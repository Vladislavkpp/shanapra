<?php

namespace vendor\Vodovra;
/**
 * Class View
 *
 * Written 2023-02-21 12:56
 */

class view
{
    private static string $buf = '';

    public static function Out()
    {
        echo self::Get();
        self::Clear();
    }

    public static function Get(): string
    {
        return self::$buf;
    }

    public static function Clear()
    {
        self::$buf = '';
    }

    public static function Add(string $a = '')
    {
        self::$buf .= $a;
    }
}
