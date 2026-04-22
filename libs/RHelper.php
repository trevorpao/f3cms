<?php

namespace F3CMS;

use Predis\Client;

class RHelper extends Client
{
    // TODO: reconnect
    // https://blog.jjonline.cn/phptech/241.html
    private static ?self $_instance = null;

    public static function getInstance()
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

    public static function s($key, $value, $ttl = null)
    {
        $instance = self::getInstance();
        if (is_array($value)) {
            $value = json_encode($value);
        }

        if (null !== $ttl && (int) $ttl > 0) {
            $instance->setex($key, (int) $ttl, $value);
            return;
        }

        $instance->set($key, $value);
    }

    public static function g($key)
    {
        $instance = self::getInstance();
        $content = $instance->get($key);

        if (null === $content) {
            return null;
        }

        $jsonObj = jsonDecode($content);

        if (is_string($jsonObj) && JSON_ERROR_NONE !== json_last_error()) {
            return $content;
        } else {
            return $jsonObj;
        }
    }
}
