<?php
namespace F3CMS;

/**
 * data feed
 */
class fOption extends Feed
{
    const MTB = 'option';
    const COUNTYTB = 'county';
    const ZIPCODETB = 'zipcode';

    const ST_ON = 'Enabled';
    const ST_OFF = 'Disabled';

    /**
     * @param $group
     * @param $mode
     * @return mixed
     */
    public static function load($group = '', $mode = 'Demand')
    {
        $filter = [
            'status' => self::ST_ON,
            'loader' => $mode
        ];

        if ($group != '') {
            $filter['group'] = $group;
        }

        $rows = mh()->select(self::fmTbl(), [
            'group', 'name', 'content'
        ], $filter);

        $options = [];

        foreach ($rows as $row) {
            if ($group != '') {
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
     * @param  int     $name - option name
     * @return array
     */
    public static function get($name)
    {
        $rows = db()->query('SELECT * FROM `' . self::fmTbl() . "` WHERE `name`=? AND `status`='" . self::ST_ON . "' LIMIT 1 ", $name);

        if (count($rows) != 1) {
            return null;
        } else {
            return $rows[0]['content'];
        }
    }

    /**
     * @return mixed
     */
    public static function load_counties()
    {
        $rows = db()->query('SELECT * FROM `' . tpf() . self::COUNTYTB . '` ORDER BY `id`');

        return $rows;
    }

    /**
     * @param $county
     * @return mixed
     */
    public static function load_zipcodes($county)
    {
        $rows = db()->query('SELECT `zipcode`, `town`, CONCAT(`county`, `town`) AS `full_name` FROM `' . tpf() . self::ZIPCODETB . '` WHERE `county`= ? ORDER BY `zipcode`', $county);

        return $rows;
    }

    /**
     * @param $queryStr
     * @return mixed
     */
    public static function genQuery($queryStr = '')
    {
        $query = parent::genQuery($queryStr);

        if (array_key_exists('all', $query)) {
            $query['OR']['name[~]'] = $query['all'];
            $query['OR']['group[~]'] = $query['all'];
            unset($query['all']);
        }

        if (array_key_exists('all[!]', $query)) {
            $query['AND']['name[!]'] = $query['all[!]'];
            $query['AND']['group[!]'] = $query['all[!]'];
            unset($query['all[!]']);
        }

        if (array_key_exists('all[!~]', $query)) {
            $query['AND']['name[!~]'] = $query['all[!~]'];
            $query['AND']['group[!~]'] = $query['all[!~]'];
            unset($query['all[!~]']);
        }

        return $query;
    }

    /**
     * @param $query
     * @param $page
     * @param $limit
     * @param $cols
     */
    public static function limitRows($query = '', $page = 0, $limit = 10, $cols = '')
    {
        $lang = Module::_lang();

        $filter = self::genQuery($query);

        return parent::paginate(self::fmTbl(), $filter, $page, $limit, ['id', 'group', 'loader', 'status', 'name', 'content']);
    }

    /**
     * @param $str
     * @param array $cols
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
