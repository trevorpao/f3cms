<?php

namespace F3CMS;

/**
 * data feed
 */
class fExcel extends Feed
{
    const MTB = "excel";

    static function getAll()
    {


        $result = f3()->get('DB')->exec(
            "SELECT `id`, `status`, `name`, `phone`, `email`, `counter`, `last_ts` FROM `".
            f3()->get('tpf') . self::MTB . "` ORDER BY insert_ts DESC "
        );

        foreach ($result as &$row) {
            $row['position'] = self::getPositions()[$row['position_id']]['title'];
        }

        return $result;
    }
}
