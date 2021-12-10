<?php

namespace F3CMS;

class Module
{
    /**
     * _escape
     *
     * @param mixed $array - obj need to escape
     *
     * @return mixed
     */
    protected static function _escape($array, $quote = true)
    {
        if (is_array($array)) {
            foreach ($array as $k => $v) {
                if (is_string($v)) {
                    if ($quote) {
                        $array[$k] = f3()->get('DB')->quote(self::protectedXss($v));
                    } else {
                        $array[$k] = self::protectedXss($v);
                    }
                } elseif (is_array($v)) {
                    $array[$k] = self::_escape($v, $quote);
                }
            }
        } else {
            if ($quote) {
                $array = f3()->get('DB')->quote(self::protectedXss($array));
            } else {
                $array = self::protectedXss($array);
            }
        }

        return $array;
    }

    /**
     * @param $str
     */
    private static function protectedXss($str)
    {
        return htmlentities($str, ENT_QUOTES, 'UTF-8');
    }

    /**
     * @param $name
     * @param $target
     */
    public static function _shift($name, $target)
    {
        $name = str_replace(['F3CMS\\', '\\'], ['', ''], $name);

        [$type, $className] = preg_split('/(?<=[rfo])(?=[A-Z])/', $name);

        return '\\F3CMS\\' . \F3CMS_Autoloader::getPrefix()[$target] . $className;
    }

    /**
     * handle angular post data
     *
     * @return array - post data
     */
    public static function _getReq()
    {
        $rtn = [];

        if (1 == f3()->get('isCORS')) {
            $str = f3()->get('BODY');
            if (empty($str)) {
                $str = file_get_contents('php://input');
            }

            $rtn = json_decode($str, true);
            if (!(JSON_ERROR_NONE == json_last_error())) {
                parse_str($str, $rtn);
            }
        }

        if (empty($rtn)) {
            $rtn = f3()->get('REQUEST');
        }

        return $rtn;
    }

    /**
     * @param array $args
     */
    public static function _lang($args = [])
    {
        if (!f3()->exists('lang') || !empty($args)) {
            $lang = f3()->get('defaultLang');

            if (f3()->exists('COOKIE.user_lang')) {
                $lang = f3()->get('COOKIE.user_lang');
            }

            if (!empty($args) && !empty($args['lang'])) {
                $lang = $args['lang'];
            }

            f3()->set('lang', $lang);
        }

        return f3()->get('lang');
    }

    /**
     * @return mixed
     */
    public static function _mobile_user_agent()
    {
        if (!f3()->exists('device')) {
            $device = 'unknown';

            if (stristr($_SERVER['HTTP_USER_AGENT'], 'ipad')) {
                $device = 'ipad';
            } elseif (stristr($_SERVER['HTTP_USER_AGENT'], 'iphone') || strstr($_SERVER['HTTP_USER_AGENT'], 'iphone')) {
                $device = 'iphone';
            } elseif (stristr($_SERVER['HTTP_USER_AGENT'], 'blackberry')) {
                $device = 'blackberry';
            } elseif (stristr($_SERVER['HTTP_USER_AGENT'], 'android')) {
                $device = 'android';
            }

            f3()->set('device', $device);
        } else {
            $device = f3()->get('device');
        }

        return $device;
    }

    /**
     * @param $text
     *
     * @return mixed
     */
    public static function _slugify($text)
    {
        $text = str_replace('//', '/', $text);
        $text = str_replace(' ', '-', $text);

        // replace non letter or digits by -
        $text = preg_replace('~[^\\pL\d%/-]+~u', '-', $text);

        // trim
        $text = trim($text, '-');

        // transliterate
        // $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);

        // lowercase
        $text = strtolower($text);

        // remove unwanted characters
        // $text = preg_replace('~[^-\w]+~', '', $text);

        if (empty($text)) {
            return 'n-a';
        }

        return $text;
    }
}
