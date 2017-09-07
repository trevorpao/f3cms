<?php

namespace F3CMS;

class rPress extends Reaction
{
    static function handleRow($row = array())
    {
        $row['categories'] = rCategory::sort_categories(4, 0 , '~');
        $row['authors'] = fAuthor::get_opts('');
        $row['rel_tag'] = json_decode($row['rel_tag']);
        $row['rel_dict'] = json_decode($row['rel_dict']);
        return $row;
    }
}
