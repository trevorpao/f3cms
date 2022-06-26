<?php

namespace F3CMS;

class msgParser extends Helper
{
    /**
     * @param $text
     */
    public static function mapping($text)
    {
        if (!is_string($text)) {
            $text = $text->getText();
        }
        $text    = strtolower(trim($text));
        $handler = '';

        $trem = f3()->get('bot.trem');

        foreach ($trem as $idx => $key) {
            if ($text == $idx) {
                $handler = $key;
            }
        }

        $regex = f3()->get('bot.regex');

        if ('' == $handler) {
            foreach ($regex as $idx => $key) {
                preg_match_all($idx, $text, $search);
                if (!empty($search[0])) {
                    $handler = $key;
                    $text    = trim($search[1][0]);
                }
            }
        }

        $methods = f3()->get('bot.methods');

        if ('' != $handler && !empty($methods[$handler])) {
            return [$text, $methods[$handler]];
        } else {
            return ['', ''];
        }
    }

    /**
     * @param $text
     * @param $handler
     *
     * @return mixed
     */
    public static function run($text, $handler)
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
            [$text]
        );

        return $msg;
    }
}
