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

        $result = db()->exec("SELECT a.id, a.title, a.position_id, a.end_date, a.counter, a.last_ts FROM `" . self::fmTbl() . "` a ");

        foreach ($result as & $row) {
            $row['position'] = self::getPositions() [$row['position_id']]['title'];
        }

        return $result;
    }

    static function getPositions()
    {
        return array(
            '1' => array(
                'id' => '1',
                'title' => '首頁/HERO大圖(1600*800)'
            ) ,
            '2' => array(
                'id' => '2',
                'title' => '外部連結(400*200)'
            ) ,
            '3' => array(
                'id' => '3',
                'title' => '首頁跳出式提示'
            ) ,
            '4' => array(
                'id' => '4',
                'title' => '會員跳出式提示'
            )
        );
    }

    static function getAdvs($position_id, $limit = 10, $orderby = ' rand() ')
    {

        $condition = " WHERE `position_id` = '" . $position_id . "' AND `status` = '" . self::ST_ON . "' ";
        $condition.= " AND `end_date` > '" . date('Y-m-d') . "' ";

        $result = db()->exec("SELECT `id`, `title`, `status`, `pic`, `uri`, `theme`, `background`, `summary` FROM `" . self::fmTbl() . "` " . $condition . "  ORDER BY ". $orderby ." LIMIT " . $limit);

        return (1 === $limit && !empty($result)) ? $result[0] : $result;
    }
}
