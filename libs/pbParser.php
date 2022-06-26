<?php

namespace F3CMS;

class pbParser extends Helper
{
    /**
     * @param $text
     */
    public static function mapping($event)
    {
        parse_str($event->getPostbackData(), $data);
        $parmas = $event->getPostbackParams();

        if (!empty($parmas)) {
            $parmas = array_merge($data, $parmas);
        } else {
            $parmas = $data;
        }

        $handler = '';

        $postback = f3()->get('bot.postback');

        foreach ($postback as $idx => $key) {
            if ($parmas['postback'] == $idx) {
                $handler = $key;
            }
        }

        $methods = f3()->get('bot.methods');

        if ('' != $handler && !empty($methods[$handler])) {
            return [$parmas, $methods[$handler]];
        } else {
            return ['', ''];
        }
    }

    /**
     * @param $parmas
     * @param $handler
     *
     * @return mixed
     */
    public static function run($parmas, $handler)
    {
        [$module, $method] = explode('::', $handler);

        // Create an instance of the module class.
        $class = '\\F3CMS\\' . $module;

        // Check if the action has a corresponding method.
        if (!method_exists($class, $method)) {
            throw new \Exception('No such method(' . $class . '::' . $method . ')', 1004);
        }

        // Create a reflection instance of the module, and obtaining the action method.
        $reflectionClass = new \ReflectionClass($class);

        $reflectionInstance = $reflectionClass->newInstance();
        $reflectionMethod   = $reflectionClass->getMethod($method);

        // Invoke module action.
        $msg = $reflectionMethod->invokeArgs(
            $reflectionInstance,
            [$parmas]
        );

        return $msg;
    }
}
