<?php

namespace Vendor\vodovra;

class Utils
{
    public static function DateFormat(string $d = ''): string
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

    public static function GetMicroTime(): float
    {
        list($usec, $sec) = explode(" ", microtime());
        return ((float)$usec + (float)$sec);
    }

    public static function FormatFileSize($filesize): string
    {
        // Returns the size of the passed file in the appropriate measurement format.
        // Setup some common file size measurements.
        $kb = 1024;         // Kilobyte
        $mb = 1048576;      // Megabyte
        $gb = 1073741824;   // Gigabyte
        $tb = 1099511627776;// Terabyte
        // Check if the last character is a letter
        // Example: 8M = 8 Megabyte
        $lastletter1 = strtolower(substr($filesize, -1));
        $lastletter2 = strtolower(substr($filesize, -2));
        if ($lastletter1 == "k") {
            $filesize = substr($filesize, 0, strlen($filesize) - 1) * $kb;
        } elseif ($lastletter1 == "m") {
            $filesize = substr($filesize, 0, strlen($filesize) - 1) * $mb;
        } elseif ($lastletter1 == "g") {
            $filesize = substr($filesize, 0, strlen($filesize) - 1) * $gb;
        } elseif ($lastletter1 == "t") {
            $filesize = substr($filesize, 0, strlen($filesize) - 1) * $tb;
        } elseif ($lastletter2 == "kb") {
            $filesize = substr($filesize, 0, strlen($filesize) - 2) * $kb;
        } elseif ($lastletter2 == "mb") {
            $filesize = substr($filesize, 0, strlen($filesize) - 2) * $mb;
        } elseif ($lastletter2 == "gb") {
            $filesize = substr($filesize, 0, strlen($filesize) - 2) * $gb;
        } elseif ($lastletter2 == "tb") {
            $filesize = substr($filesize, 0, strlen($filesize) - 2) * $tb;
        }
        // If it's less than a kb we just return the size, otherwise we keep going until
        //   the size is in the appropriate measurement range.
        if ($filesize == "") {
            return "0 B";
        } elseif ($filesize < $kb) {
            return $filesize . " B";
        } elseif ($filesize < $mb) {
            return round($filesize / $kb, 2) . " kB";
        } elseif ($filesize < $gb) {
            return round($filesize / $mb, 2) . " MB";
        } elseif ($filesize < $tb) {
            return round($filesize / $gb, 2) . " GB";
        } else {
            return round($filesize / $tb, 2) . " TB";
        }
    } // end formatFilesize

    public static function compressCSS(string $input = ''): string
    {
        // remove comments
        $input = preg_replace("/\/\*.*\*\//Us", "", $input);
        // remove unnecessary characters
        $input = str_replace(":0px", ":0", $input);
        $input = str_replace(":0em", ":0", $input);
        $input = str_replace(" 0px", " 0", $input);
        $input = str_replace(" 0em", " 0", $input);
        $input = str_replace(";}", "}", $input);
        // remove spaces, etc
        $input = preg_replace('/\s\s+/', ' ', $input);
        $input = str_replace(" {", "{", $input);
        $input = str_replace("{ ", "{", $input);
        $input = str_replace("\n{", "{", $input);
        $input = str_replace("{\n", "{", $input);
        $input = str_replace(" }", "}", $input);
        $input = str_replace("} ", "}", $input);
        $input = str_replace(": ", ":", $input);
        $input = str_replace(" :", ":", $input);
        $input = str_replace(";\n", ";", $input);
        $input = str_replace(" ;", ";", $input);
        $input = str_replace("; ", ";", $input);
        $input = str_replace(", ", ",", $input);
        return trim($input);
    }

    public static function compressJS(string $input = ''): string
    {
        // remove comments
        $input = preg_replace("/\/\/.*\n/Us", "", $input);
        $input = preg_replace("/\/\*.*\*\//Us", "", $input);
        // remove spaces, etc
        $input = preg_replace("/\t/", "", $input);
        $input = preg_replace("/\n\n+/m", "\n", $input);
        $input = str_replace(";\n", ";", $input);
        $input = str_replace(" = ", "=", $input);
        $input = str_replace(" == ", "==", $input);
        $input = str_replace(" || ", "||", $input);
        $input = str_replace(" && ", "&&", $input);
        $input = str_replace(")\n{", "){", $input);
        $input = str_replace("if (", "if(", $input);
        return trim($input);
    }
}