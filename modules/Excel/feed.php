<?php

namespace F3CMS;

/**
 * data feed
 */
class fExcel extends Feed
{
    public const MTB = 'excel';

    /**
     * @return mixed
     */
    public static function getAll()
    {
        $result = self::exec(
            'SELECT `id`, `status`, `name`, `phone`, `email`, `counter`, `last_ts` FROM `' .
            self::fmTbl() . '` ORDER BY insert_ts DESC '
        );

        foreach ($result as &$row) {
            $row['position'] = self::getPositions()[$row['position_id']]['title'];
        }

        return $result;
    }
}
