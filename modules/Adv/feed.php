<?php

namespace F3CMS;

/**
 * data feed
 */
class fAdv extends Feed
{
    const MTB    = 'adv';
    const ST_ON  = 'Enabled';
    const ST_OFF = 'Disabled';

    const BE_COLS = 'm.id,l.title,m.position_id,m.position_id,m.weight,m.cover,m.start_date,m.end_date,m.counter,m.exposure,m.status,m.last_ts';

    public static function getPositions()
    {
        $positions = fGenus::getOpts('adv', 'm.group');
        $idArray   = array_column($positions, 'id');

        return array_combine($idArray, $positions);
    }

    /**
     * @param $id
     */
    public static function addCounter($id = 0)
    {
        mh()->update(self::fmTbl(), [
            'counter[+]' => 1,
        ], [
            'id' => $id,
        ]);
    }

    /**
     * @param array $ids
     */
    public static function addExposure($ids = [])
    {
        mh()->update(self::fmTbl(), [
            'exposure[+]' => 1,
        ], [
            'id' => $ids,
        ]);
    }

    public static function genOrder()
    {
        return ['m.position_id' => 'ASC', 'm.weight' => 'DESC', 'm.insert_ts' => 'DESC'];
    }

    /**
     * @param $position_id
     * @param $limit
     * @param $orderby
     */
    public static function getResources($position_id, $limit = 10, $orderby = ' rand() ')
    {
        if ($limit > 26) {
            return [];
        }

        $condition = ' WHERE m.`position_id` = :position_id AND m.`status` = :status ';
        $condition .= " AND m.`end_date` > '" . date('Y-m-d H:i:s') . "' ";
        $condition .= " AND m.`start_date` < '" . date('Y-m-d H:i:s') . "' ";

        $select = 'SELECT m.`id`, l.`title`, m.`status`, m.`weight`, m.`cover`, m.`uri`, m.`theme`, m.`background`, l.`subtitle`, l.`content`';
        $from   = ' FROM `' . self::fmTbl() . '` AS m INNER JOIN `' . self::fmTbl('lang') . '` AS l ON l.`parent_id` = m.`id` AND l.`lang`=\'' . parent::_lang() . '\' ' . $condition;

        $order   = '';
        $useRand = false;
        if ('rand()' != trim($orderby)) {
            $order = ' ORDER BY ' . $orderby . ', m.`id` DESC';
        } else {
            $useRand = true;
        }

        if ($useRand) {
            $result = self::exec($select . $from . ' LIMIT 26 ', [':position_id' => $position_id, ':status' => self::ST_ON]);

            if ($result) {
                $result = self::_randomByWeight($result, $limit);
            }
        } else {
            $result = self::exec($select . $from . $order . ' LIMIT ' . $limit, [':position_id' => $position_id, ':status' => self::ST_ON]);
        }

        return (empty($result)) ? [] : $result;
    }

    /**
     * @param $query
     * @param $page
     * @param $limit
     */
    public static function getOpts($query = '', $column = 'm.name')
    {
        return self::getPositions();
    }

    /**
     * @param $ary
     * @param $limit
     *
     * @return mixed
     */
    private static function _randomByWeight($ary, $limit = 4)
    {
        if (count($ary) > 26) {
            return [];
        }

        $tmp = '';
        $rtn = [];

        foreach ($ary as $k => $row) {
            $tmp .= str_repeat(chr($k + 65), $row['weight']);
        }

        $tmp = str_split($tmp);
        shuffle($tmp);

        $tmp = array_values(array_unique($tmp));
        foreach ($tmp as $i => $v) {
            if ($i < $limit) {
                $rtn[] = $ary[ord($v) - 65];
            }
        }

        return $rtn;
    }
}
