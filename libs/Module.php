<?php

namespace F3CMS;

/**
 * Module 類別提供了多種輔助功能，包括資料轉換、語言設定、裝置檢測等。
 */
class Module
{
    /**
     * 對輸入的陣列或字串進行轉義處理，防止 XSS 攻擊。
     *
     * @param mixed $array 要轉義的資料
     * @param bool $quote 是否添加引號
     * @return mixed 轉義後的資料
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
     * 防止 XSS 攻擊，將字串轉換為 HTML 實體。
     *
     * @param string $str 要處理的字串
     * @return string 處理後的字串
     */
    private static function protectedXss($str)
    {
        return htmlentities($str, ENT_QUOTES, 'UTF-8');
    }

    /**
     * 根據名稱和目標生成對應的類名。
     *
     * @param string $name 類名
     * @param string $target 目標前綴
     * @return string 生成的類名
     */
    public static function _shift($name, $target)
    {
        $name = str_replace(['F3CMS\\', '\\'], ['', ''], $name);

        [$type, $className] = preg_split('/(?<=[rfo])(?=[A-Z])/', $name);

        return '\\F3CMS\\' . \F3CMS_Autoloader::getPrefix()[$target] . $className;
    }

    /**
     * 處理 Angular 的 POST 資料，並返回解析後的陣列。
     *
     * @return array POST 資料
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
     * 設定或取得語言設定。
     *
     * @param array $args 語言參數
     * @return string 當前語言
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
     * 檢測使用者的裝置類型。
     *
     * @return string 裝置類型
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
     * 將文字轉換為 URL 友好的 slug。
     *
     * @param string $text 要轉換的文字
     * @return string 轉換後的 slug
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
