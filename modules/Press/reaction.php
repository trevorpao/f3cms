<?php

namespace F3CMS;

class rPress extends Reaction
{
    static function handleRow($row = array())
    {
        $row['categories'] = rCategory::sort_categories(4, 0 , '~');
        return $row;
    }
}
