<?php

namespace F3CMS;

class Kit extends Module
{
    /**
     * @param $group
     *
     * @return mixed
     */
    public static function rule($group = 'default')
    {
        $that   = get_called_class();
        $rules  = $that::rules();
        $parent = self::rules();

        $rtn = $parent['default'];

        if (array_key_exists($group, $parent)) {
            $rtn = $parent[$group];
        }

        if (array_key_exists($group, $rules)) {
            $rtn = $rules[$group];
        }

        return $rtn;
    }

    public static function rules()
    {
        return [
            'default'    => [
            ],
            'del'        => [
                'pid' => 'required|integer',
            ],
            'upload'     => [
                'photo' => 'required|uploaded_file|max:5M|mimes:jpeg,png',
            ],
            'uploadFile' => [
                'file' => 'required|uploaded_file|max:10M|mimes:pdf,zip,xls,xlsx',
            ],
        ];
    }
}
