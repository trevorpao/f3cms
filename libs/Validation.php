<?php

namespace F3CMS;

use Rakit\Validation\Validator;

/**
 * Validation class provides methods for input validation using the Rakit Validation library.
 * It supports custom error messages and validation rules.
 */
class Validation extends Module
{
    /**
     * Singleton instance of the Validator class.
     *
     * @var Validator|false
     */
    private static $_instance = false;

    /**
     * Initializes the Validator instance with input data and validation rules.
     *
     * @param array $input The input data to validate.
     * @param array $rule The validation rules to apply.
     */
    public static function init($input, $rule)
    {
        if (!self::$_instance) {
            self::$_instance = new Validator();

            // Set custom error messages for validation rules
            self::$_instance->setMessages([
                'required'  => ':attribute 為必填欄位',
                'email'     => ':email 格式不符',
                'max'       => ':attribute 長度過長',
                'min'       => ':attribute 長度過短',
                'alpha_num' => ':attribute 限用英數字',
                'regex'     => ':attribute 不符合格式要求',
                'numeric'   => ':attribute 限用數字',
                // Additional custom messages can be added here
            ]);
        }

        // Create a validation instance with the input and rules
        self::$_instance = self::$_instance->make($input, $rule);

        // Perform validation
        self::$_instance->validate();
    }

    /**
     * Validates the input data against the rules and outputs errors if validation fails.
     *
     * @param array $input The input data to validate.
     * @param array $rule The validation rules to apply.
     * @return bool True if validation passes, false otherwise.
     */
    public static function check($input, $rule)
    {
        self::init($input, $rule);

        // Output validation errors for debugging purposes
        var_dump(self::$_instance->errors()->all());
        exit;

        return (self::$_instance->fails()) ? false : true;
    }

    /**
     * Validates the input data and returns errors or success response.
     *
     * @param array $input The input data to validate.
     * @param array $rule The validation rules to apply.
     * @return mixed Validation errors or success response.
     */
    public static function return($input, $rule)
    {
        self::init($input, $rule);

        if (self::$_instance->fails()) {
            if (isAjax()) {
                // Return errors in JSON format for AJAX requests
                Reaction::_return(8003, ['msg' => self::$_instance->errors()->all()]);
            } else {
                // Return errors as an HTML list for non-AJAX requests
                return self::$_instance->errors()->all('<li>:message</li>');
            }
        } else {
            return 1; // Validation passed
        }
    }

    /**
     * Provides a default set of validation rules.
     *
     * @return array The default validation rules.
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
