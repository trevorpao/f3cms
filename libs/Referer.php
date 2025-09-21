<?php

namespace F3CMS;

class Referer extends Module
{
    /**
     * setting referer url
     */
    public static function set($uri = '')
    {
        if (empty($uri)) {
            $referer = $_SERVER['HTTP_REFERER'];

            if (!empty($referer)) {
                $uri = $referer;
            } else {
                $parse = parse_url($_SERVER['REQUEST_URI']);
                $uri   = ((!empty($parse['canonical'])) ? $parse['canonical'] : '/');
            }
        }

        f3()->set('SESSION.referer', $uri);
    }

    /**
     * get setted referer url
     *
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
     *
     * @return null
     */
    public static function del()
    {
        f3()->clear('SESSION.referer');
    }
}
