<?php

namespace F3CMS;

/**
 * React any request
 */
class rAuthor extends Reaction
{
    static function handleRow($row = array())
    {
        $row['tags'] = fAuthor::lotsTag($row['id']);
        // echo mh()->last();
        return $row;
    }
}
