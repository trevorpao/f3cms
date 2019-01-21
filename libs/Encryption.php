<?php
namespace F3CMS;

class Encryption extends Module
{
    const MEMBERKEY = 'sgIBSfjak8clVmUQeMhGT3b2wAF5WpN7qPdzO4DHYJK6iuo0xZLnXt1yR9EvCr+/';

    /**
     * get random string
     * @param  [integer]  $len [string length max is 32 char]
     * @return [string]
     */
    public static function salt($len = 22)
    {
        if ($len > 32) {
            $len = 32;
        }

        return substr(md5(uniqid(microtime() . rand() . time(), true)), 0, $len);
    }

    /**
     * hash string
     * @param  [string]   $input [origin]
     * @param  [string]   $salt  [22 char string]
     * @return [string]
     */
    public static function hash($input, $salt)
    {
        return secure()->hash($input . $salt . parent::HASHSALT, $salt, 10);
    }

    /**
     * [verify description]
     * @param  [string] $input [origin string and salt]
     * @param  [string] $hash  [hashed string]
     * @return [bool]
     */
    public static function verify($input, $hash)
    {
        return secure()->verify($input . parent::HASHSALT, $hash);
    }

    /**
     * encode string
     * @param  [string]   $input [be encode string]
     * @return [string]
     */
    public static function encode($input)
    {
        $output = '';
        $chr1 = $chr2 = $chr3 = $enc1 = $enc2 = $enc3 = $enc4 = null;
        $i = 0;

        while ($i < strlen($input)) {
            $chr1 = isset($input[$i]) ? ord($input[$i++]) : 0;
            @$chr2 = isset($input[$i]) ? ord($input[$i++]) : 0;
            @$chr3 = isset($input[$i]) ? ord($input[$i++]) : 0;

            $enc1 = $chr1 >> 2;
            $enc2 = (($chr1 & 3) << 4) | ($chr2 >> 4);
            $enc3 = (($chr2 & 15) << 2) | ($chr3 >> 6);
            $enc4 = $chr3 & 63;

            if (is_nan($chr2) || $chr2 == 0) {
                $enc3 = $enc4 = 64;
            } else if (is_nan($chr3) || $chr3 == 0) {
                $enc4 = 64;
            }

            $output .= self::mimeEncode($enc1)
            . self::mimeEncode($enc2)
            . self::mimeEncode($enc3)
            . self::mimeEncode($enc4);
        }

        return $output;
    }

    /**
     * decode string
     * @param  [string]   $input [be decode string]
     * @return [string]
     */
    public static function decode($input)
    {
        $output = '';
        $chr1 = $chr2 = $chr3 = $enc1 = $enc2 = $enc3 = $enc4 = null;
        $i = 0;

        $baseKey = self::MEMBERKEY;

        while ($i < strlen($input)) {
            $enc1 = strpos($baseKey, substr($input, $i++, 1));
            $enc2 = strpos($baseKey, substr($input, $i++, 1));
            $enc3 = strpos($baseKey, substr($input, $i++, 1));
            $enc4 = strpos($baseKey, substr($input, $i++, 1));

            if ($enc2 == 64) {
                $enc2 = 0;
            }

            if ($enc3 == 64) {
                $enc3 = 0;
            }

            if ($enc4 == 64) {
                $enc4 = 0;
            }

            $chr1 = chr(($enc1 << 2) | ($enc2 >> 4));
            $chr2 = chr(($enc2 << 4) | ($enc3 >> 2));
            $chr3 = chr(($enc3 << 6) | $enc4);

            if (ord($chr3) == 0) {
                $chr3 = null;
            }

            if (ord($chr2) == 0) {
                $chr2 = null;
            }

            $output .= $chr1 . $chr2 . $chr3;
        }
        return $output;
    }

    /**
     * @param $w
     */
    private static function mimeEncode($w)
    {
        if ($w >= 0) {
            return substr(self::MEMBERKEY, $w, 1);
        }

        return '';
    }
}
