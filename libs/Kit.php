<?php

namespace F3CMS;

// The Kit class provides utility methods and validation rules for the application.
// It includes methods for handling rules and calculating string widths.

class Kit extends Module
{
    /**
     * Retrieves validation rules for a specific group.
     *
     * @param string $group The name of the group (default: 'default').
     * @return array The validation rules for the specified group.
     */
    public static function rule($group = 'default')
    {
        // Get the current class and its rules.
        $that   = get_called_class();
        $rules  = $that::rules();

        // Get the parent class rules.
        $parent = self::rules();

        // Default to the 'default' group rules.
        $rtn = $parent['default'];

        // Check if the group exists in the parent rules.
        if (array_key_exists($group, $parent)) {
            $rtn = $parent[$group];
        }

        // Check if the group exists in the current class rules.
        if (array_key_exists($group, $rules)) {
            $rtn = $rules[$group];
        }

        return $rtn;
    }

    /**
     * Calculates the display width of a string, considering multibyte characters.
     *
     * @param string $str The input string.
     * @return int The calculated display width of the string.
     */
    public static function strWidth($str = '')
    {
        // Calculate the length of the string in multibyte and single-byte terms.
        $mblen = mb_strlen($str);
        $len   = strlen($str);

        // Calculate the display width by accounting for multibyte characters.
        $clen = ($len - $mblen) / 2;

        return $clen + $mblen;
    }

    /**
     * Defines validation rules for various operations.
     *
     * @return array The validation rules.
     */
    public static function rules()
    {
        return [
            'default'    => [
                // Default rules can be defined here.
            ],
            'del'        => [
                'pid' => 'required|integer', // Rule for deleting: 'pid' must be an integer and is required.
            ],
            'upload'     => [
                'photo' => 'required|uploaded_file|max:5M|mimes:jpeg,png', // Rule for uploading photos.
            ],
            'uploadFile' => [
                'file' => 'required|uploaded_file|max:10M|mimes:pdf,zip,xls,xlsx', // Rule for uploading files.
            ],
        ];
    }
}
