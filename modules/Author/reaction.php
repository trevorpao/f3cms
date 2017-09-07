<?php

namespace F3CMS;

/**
 * React any request
 */
class rAuthor extends Reaction
{

    static function handleRow($row = array())
    {
        $row['rel_tag'] = json_decode($row['rel_tag']);
        return $row;
    }
}
