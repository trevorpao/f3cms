<?php

namespace F3CMS;

use Predis\Client;

class RHelper extends Client
{
    // TODO: reconnect
    // https://blog.jjonline.cn/phptech/241.html
    private static $_instance = false;

    public static function init()
    {
        if (!self::$_instance) {
            self::$_instance = new self([
                'scheme' => 'tcp',
                'host'   => f3()->get('redis_host'),
                'port'   => 6379,
            ]);
        }

        return self::$_instance;
    }

    public static function s($key, $value)
    {
        if (is_array($value)) {
            $value = json_encode($value);
        }
        self::$_instance->set($key, $value);
    }

    public static function g($key)
    {
        $content = self::$_instance->get($key);

        $jsonObj = json_decode($content, true);

        if (null === $jsonObj && JSON_ERROR_NONE !== json_last_error()) {
            return $content;
        } else {
            return $jsonObj;
        }
    }
}
