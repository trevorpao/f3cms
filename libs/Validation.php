<?php

namespace F3CMS;

use Rakit\Validation\Validator;

// https://github.com/rakit/validation

class Validation extends Module
{
    /**
     * @var instance
     */
    private static $_instance = false;

    /**
     * @param $input
     * @param $rule
     */
    public static function init($input, $rule)
    {
        if (!self::$_instance) {
            self::$_instance = new Validator();

            self::$_instance->setMessages([
                'required'  => ':attribute 為必填欄位',
                'email'     => ':email 格式不符',
                'max'       => ':attribute 長度過長',
                'min'       => ':attribute 長度過短',
                'alpha_num' => ':attribute 限用英數字',
                'regex'     => ':attribute 不符合格式要求',
                'numeric'   => ':attribute 限用數字',
                // etc
            ]);
        }

        // make it
        self::$_instance = self::$_instance->make($input, $rule);

        // then validate
        self::$_instance->validate();
    }

    /**
     * @param $input
     * @param $rule
     */
    public static function check($input, $rule)
    {
        self::init($input, $rule);

        var_dump(self::$_instance->errors()->all());
        exit;

        return (self::$_instance->fails()) ? false : true;
    }

    /**
     * @param $input
     * @param $rule
     */
    public static function return($input, $rule)
    {
        self::init($input, $rule);

        if (self::$_instance->fails()) {
            if (isAjax()) {
                Reaction::_return(8004, ['msg' => self::$_instance->errors()->all()]);
            } else {
                return self::$_instance->errors()->all('<li>:message</li>');
            }
        } else {
            return 1;
        }
    }

    private static function _defaultRule()
    {
        return [
            'name'             => 'min:6',
            'email'            => 'email',
            'password'         => 'min:6',
            'confirm_password' => 'same:password',
        ];
    }
}
