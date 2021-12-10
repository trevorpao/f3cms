<?php

namespace F3CMS;

/**
 * React any request
 */
class rProject extends Reaction
{
    /**
     * @param array $row
     *
     * @return mixed
     */
    public static function handleRow($row = [])
    {
        $row['relateds'] = fProject::lotsRelated($row['id']);

        return $row;
    }
}
