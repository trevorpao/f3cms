<?php
namespace F3CMS;

class Referer extends Module
{
    /**
     * setting referer url
     */
    public static function set()
    {
        $referer = $_SERVER['HTTP_REFERER'];

        if (!empty($referer)) {
            // $parse = parse_url($referer);
            $uri = $referer; // $parse['path'] . ((!empty($parse['query']))? '?' . $parse['query'] : '');
        } else {
            $uri = '/';
        }

        f3()->set('SESSION.referer', $uri);
    }

    /**
     * get setted referer url
     * @return string
     */
    public static function get()
    {
        if (f3()->exists('SESSION.referer')) {
            $return = f3()->get('SESSION.referer');

            self::del();

            return $return;
        }

        return '/';
    }

    /**
     * remove setted referer url
     * @return null
     */
    public static function del()
    {
        f3()->clear('SESSION.referer');
    }
}
