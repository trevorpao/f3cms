<?php

namespace F3CMS;

/**
 * React any request
 */
class rProject extends Reaction
{
    static function handleRow($row = array())
    {
        $row['relateds'] = fProject::lotsRelated($row['id']);
        return $row;
    }
}
