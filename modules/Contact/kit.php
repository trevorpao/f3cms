<?php

namespace F3CMS;

/**
 * kit lib
 */
class kContact extends Kit
{
    public static function _captchaRule()
    {
        return [
            'required',
            'min:6',
            function ($value) {
                return (f3()->get('SESSION.captcha') == strtolower($value)) ? true : '未通過人機驗証!!';
            },
        ];
    }

    public static function rules()
    {
        return [
            'add_new' => [
                'name'    => 'required|max:250',
                'captcha' => self::_captchaRule(),
                'email'   => 'required|email|max:250',
                // 'mobile'  => 'required|min:6|max:16',
                // 'subject' => 'required|max:250',
                // 'company' => 'required|max:250',
                'message' => 'required|max:250',
            ],
        ];
    }
}
