<?php

namespace F3CMS;

/**
 * data feed
 */
class fOption extends Feed
{
    const MTB       = 'option';
    const MULTILANG = 0;

    const COUNTYTB  = 'county';
    const ZIPCODETB = 'zipcode';

    const ST_ON  = 'Enabled';
    const ST_OFF = 'Disabled';

    const PV_R = 'mgr.site';
    const PV_U = 'mgr.site';
    const PV_D = 'mgr.site';

    const BE_COLS   = 'id,group,loader,status,name,content';
    const PAGELIMIT = 100;

    /**
     * @param $group
     * @param $mode
     *
     * @return mixed
     */
    public static function load($group = '', $mode = 'Demand')
    {
        $filter = [
            'status' => self::ST_ON,
            'loader' => $mode,
        ];

        if ('' != $group) {
            $filter['group'] = $group;
        }

        $rows = mh()->select(self::fmTbl(), [
            'group', 'name', 'content',
        ], $filter);

        $options = [];

        foreach ($rows as $row) {
            if ('' != $group) {
                $options[$row['name']] = $row['content'];
            } else {
                $options[$row['group']][$row['name']] = $row['content'];
            }
        }

        return $options;
    }

    /**
     * get one row by name
     *
     * @param int $name - option name
     *
     * @return array
     */
    public static function get($name)
    {
        $rows = self::exec('SELECT * FROM `' . self::fmTbl() . '` WHERE `name`=:name AND `status`=:status LIMIT 1 ', [
            ':name'   => $name,
            ':status' => self::ST_ON,
        ]);

        if (1 != count($rows)) {
            return null;
        } else {
            return $rows[0]['content'];
        }
    }

    /**
     * @param $ids
     * @param $page
     * @param $limit
     */
    public static function listCounties($ids = '')
    {
        $filter = ['ORDER' => 'm.id'];

        if ('' != $ids) {
            $filter['m.id'] = (is_string($ids)) ? explode(',', $ids) : $ids;
        }

        return mh()->select(tpf() . self::COUNTYTB . '(m)', ['m.id', 'm.county(title)', 'm.county'], $filter);
    }

    /**
     * @param $county
     *
     * @return mixed
     */
    public static function loadZipcodes($county)
    {
        $rows = self::exec('SELECT `zipcode`, `town`, CONCAT(`county`, `town`) AS `full_name` FROM `' . tpf() . self::ZIPCODETB . '` WHERE `county`= :county ORDER BY `zipcode`', [
            ':county' => $county,
        ]);

        return $rows;
    }

    /**
     * @param $queryStr
     *
     * @return mixed
     */
    public static function genQuery($queryStr = '')
    {
        $query = parent::genQuery($queryStr);

        if (array_key_exists('all', $query)) {
            $query['OR']['name[~]']  = $query['all'];
            $query['OR']['group[~]'] = $query['all'];
            unset($query['all']);
        }

        if (array_key_exists('all[!]', $query)) {
            $query['AND']['name[!]']  = $query['all[!]'];
            $query['AND']['group[!]'] = $query['all[!]'];
            unset($query['all[!]']);
        }

        if (array_key_exists('all[!~]', $query)) {
            $query['AND']['name[!~]']  = $query['all[!~]'];
            $query['AND']['group[!~]'] = $query['all[!~]'];
            unset($query['all[!~]']);
        }

        return $query;
    }

    /**
     * @param       $str
     * @param array $cols
     *
     * @return mixed
     */
    public static function split($str, $cols = [])
    {
        $rtn = [];
        if (!empty($str)) {
            $ary = explode("\n", $str);
            foreach ($ary as $idx => $value) {
                $tmp = explode(':', $value);
                foreach ($tmp as $k => $v) {
                    if (isset($cols[$k])) {
                        $rtn[$idx][$cols[$k]] = $v;
                    } else {
                        $rtn[$idx][$k] = $v;
                    }
                }
            }
        }

        return $rtn;
    }
}
