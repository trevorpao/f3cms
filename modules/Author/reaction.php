<?php

namespace F3CMS;

/**
 * React any request
 */
class rAuthor extends Reaction
{
    /**
     * @param array $row
     * @return mixed
     */
    public static function handleRow($row = [])
    {
        $row['tags'] = fAuthor::lotsTag($row['id']);
        // echo mh()->last();
        return $row;
    }
}
