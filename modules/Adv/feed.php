<?php

namespace F3CMS;

/**
 * data feed
 */
class fAdv extends Feed
{
    const MTB = "adv";
    const ST_ON = "Enabled";
    const ST_OFF = "Disabled";

    static function getAll()
    {


        $result = f3()->get('DB')->exec(
            "SELECT a.id, a.title, a.position_id, a.end_date, a.counter, a.last_ts FROM `".
            f3()->get('tpf') . self::MTB ."` a "
        );

        foreach ($result as &$row) {
            $row['position'] = self::getPositions()[$row['position_id']]['title'];
        }

        return $result;
    }

    static function getPositions()
    {
        return array(
            '1' => array('id' => '1', 'title' => '首頁/HERO大圖(1600*800)'),
        );
    }

    static function getAdvs($position_id, $limit = 10)
    {


        $condition = " WHERE `position_id` = '". $position_id ."' AND `status` = '". self::ST_ON ."' ";
        $condition .= " AND `end_date` > '". date('Y-m-d') ."' ";

        $result = f3()->get('DB')->exec(
            "SELECT `id`, `title`, `status`, `pic`, `uri`, `background`, `summary` FROM `". f3()->get('tpf') . self::MTB .
            "` ". $condition ."  ORDER BY rand() LIMIT ". $limit
        );

        return (1 === $limit && !empty($result)) ? $result[0] : $result;
    }
}
