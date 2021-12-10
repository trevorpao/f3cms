<?php

namespace F3CMS;

/**
 * data feed
 */
class fAdv extends Feed
{
    public const MTB    = 'adv';
    public const ST_ON  = 'Enabled';
    public const ST_OFF = 'Disabled';

    public const BE_COLS = 'm.id,l.title,m.position_id,m.weight,m.start_date,m.end_date,m.counter,m.exposure,m.status,m.last_ts';

    /**
     * @return mixed
     */
    public static function getAll()
    {
        $result = self::exec('SELECT a.id, a.title, a.position_id, a.end_date, a.counter, a.last_ts FROM `' . self::fmTbl() . '` a ');

        foreach ($result as &$row) {
            $row['position'] = self::getPositions()[$row['position_id']]['title'];
        }

        return $result;
    }

    public static function getPositions()
    {
        return [
            '1' => [
                'id'    => '1',
                'title' => '首頁/HERO大圖(1600*800)',
            ],
            '2' => [
                'id'    => '2',
                'title' => '外部連結(400*200)',
            ],
            '3' => [
                'id'    => '3',
                'title' => '首頁跳出式提示',
            ],
            '4' => [
                'id'    => '4',
                'title' => '會員跳出式提示',
            ],
        ];
    }

    /**
     * @param $position_id
     * @param $limit
     * @param $orderby
     */
    public static function getAdvs($position_id, $limit = 10, $orderby = ' rand() ')
    {
        $condition = " WHERE `position_id` = '" . $position_id . "' AND `status` = '" . self::ST_ON . "' ";
        $condition .= " AND `end_date` > '" . date('Y-m-d') . "' ";

        $result = self::exec('SELECT `id`, `title`, `status`, `pic`, `uri`, `theme`, `background`, `summary` FROM `' . self::fmTbl() . '` ' . $condition . '  ORDER BY ' . $orderby . ' LIMIT ' . $limit);

        return (1 === $limit && !empty($result)) ? $result[0] : $result;
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

        $condition = " WHERE m.`position_id` = '" . $position_id . "' AND m.`status` = '" . self::ST_ON . "' ";
        $condition .= " AND m.`end_date` > '" . date('Y-m-d') . "' ";

        $select = 'SELECT m.`id`, l.`title`, m.`status`, m.`weight`, m.`cover`, m.`uri`, m.`theme`, m.`background`, l.`subtitle`, l.`content`';
        $from   = ' FROM `' . self::fmTbl() . '` AS m INNER JOIN `' . self::fmTbl('lang') . '` AS l ON l.`parent_id` = m.`id` AND l.`lang`="' . parent::_lang() . '" ' . $condition;

        $order   = '';
        $useRand = false;
        if ('rand()' != trim($orderby)) {
            $order = ' ORDER BY ' . $orderby . ', m.`id` DESC';
        } else {
            $useRand = true;
        }

        if ($useRand) {
            $result = self::exec($select . $from . ' LIMIT 26 ');

            if ($result) {
                $result = self::_randomByWeight($result, $limit);
            }
        } else {
            $result = self::exec($select . $from . $order . ' LIMIT ' . $limit);
        }

        return (empty($result)) ? [] : $result;
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
                $rtn[] = $ary[(ord($v) - 65)];
            }
        }

        return $rtn;
    }

    public static function filtered_column()
    {
        return ['sh', 'sm', 'eh', 'em'];
    }

    /**
     * @param $req
     */
    public static function _handleColumn($req)
    {
        $req['start_date'] = $req['start_date'] . ' ' . $req['sh'] . ':' . $req['sm'] . ':00';
        $req['end_date']   = $req['end_date'] . ' ' . $req['eh'] . ':' . $req['em'] . ':00';

        return parent::_handleColumn($req);
    }
}
