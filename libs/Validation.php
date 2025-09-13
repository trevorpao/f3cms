<?php

namespace F3CMS;

use Rakit\Validation\Validator;

// https://github.com/rakit/validation

/**
 * Validation 類別提供資料驗證功能，
 * 使用 Rakit Validation 套件進行表單輸入的驗證。
 */
class Validation extends Module
{
    /**
     * @var Validator 實例，用於執行驗證邏輯。
     */
    private static $_instance = false;

    /**
     * 初始化驗證器並設置自訂錯誤訊息。
     *
     * @param array $input 使用者輸入的資料
     * @param array $rule 驗證規則
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
     * 驗證輸入資料是否符合規則。
     *
     * @param array $input 使用者輸入的資料
     * @param array $rule 驗證規則
     * @return bool 驗證是否通過
     */
    public static function check($input, $rule)
    {
        self::init($input, $rule);

        var_dump(self::$_instance->errors()->all());
        exit;

        return (self::$_instance->fails()) ? false : true;
    }

    /**
     * 驗證輸入資料並返回錯誤訊息或成功狀態。
     *
     * @param array $input 使用者輸入的資料
     * @param array $rule 驗證規則
     * @return mixed 驗證失敗時返回錯誤訊息，成功時返回 1
     */
    public static function return($input, $rule)
    {
        self::init($input, $rule);

        if (self::$_instance->fails()) {
            if (isAjax()) {
                Reaction::_return(8003, ['msg' => self::$_instance->errors()->all()]);
            } else {
                return self::$_instance->errors()->all('<li>:message</li>');
            }
        } else {
            return 1;
        }
    }

    /**
     * 提供預設的驗證規則。
     *
     * @return array 預設的驗證規則陣列
     */
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
