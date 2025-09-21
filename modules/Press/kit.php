<?php

namespace F3CMS;

/**
 * kit lib
 */
class kPress extends Kit
{
    /**
     * @param $email
     * @param $verify_code
     */
    public static function fartherData($id)
    {
        \PCMS\kPress::fartherData($id);
    }

    public static function rules()
    {
        return [
            'save'        => [
                'lang' => [
                    'required',
                    function ($value) {
                        // false = invalid, string = massage, :attribute :value

                        if (!empty($value['tw']['title'])) {
                            $width = self::strWidth($value['tw']['title']);

                            return ($width > 70) ? '中文標題太長(' . $width . ')' : true;
                        } else {
                            return true;
                        }
                    },
                ],
                // 'meta' => 'required'
            ]
        ];
    }
}
