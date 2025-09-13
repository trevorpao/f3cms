<?php

namespace F3CMS;

/**
 * React any request
 */
class rMeta extends Reaction
{
    /**
     * @param array $row
     *
     * @return mixed
     */
    public static function handleRow($row = [])
    {
        $row['tags'] = fMeta::lotsGenus($row['id']);

        return $row;
    }

    /**
     * @param array $row
     *
     * @return mixed
     */
    public static function handleIteratee($row = [])
    {
        $row['tags']    = fMeta::lotsGenus($row['id']);

        return $row;
    }
}
