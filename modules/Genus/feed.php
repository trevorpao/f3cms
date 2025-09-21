<?php

namespace F3CMS;

/**
 * data feed
 */
class fGenus extends Feed
{
    const MTB = 'genus';

    const PV_R = 'mgr.cms';
    const PV_U = 'mgr.cms';
    const PV_D = 'mgr.site';

    const ST_ON  = 'Enabled';
    const ST_OFF = 'Disabled';

    const MULTILANG = 0;

    const BE_COLS = 'm.id,m.group,m.status,m.name,m.content';

    /**
     * @param $group
     *
     * @return mixed
     */
    public static function load($group = '')
    {
        $filter = [
            'LIMIT'  => 100,
            'status' => self::ST_ON,
            'ORDER'  => self::genOrder(),
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
     * @param $query
     * @param $page
     * @param $limit
     */
    public static function getOpts($query = '', $column = 'm.name')
    {
        $filter = [
            'LIMIT'  => 100,
            'status' => self::ST_ON,
            'ORDER'  => self::genOrder(),
        ];

        if ('' != $query) {
            $filter[$column . '[~]'] = $query;
        }

        return mh()->select(self::fmTbl() . '(m)',
            ['m.id', 'title' => MHelper::raw('CONCAT(m.<id>, \') \', COALESCE(m.<name>, \'\'))')],
            $filter
        );
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
        $rows = self::exec('SELECT * FROM `' . self::fmTbl() . "` WHERE `name`=? AND `status`='" . self::ST_ON . "' LIMIT 1 ", $name);

        if (1 != count($rows)) {
            return null;
        } else {
            return $rows[0]['content'];
        }
    }

    public static function genOrder()
    {
        return ['m.sorter' => 'ASC', 'm.group' => 'ASC', 'm.insert_ts' => 'ASC'];
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
            $query['OR']['m.name[~]']  = $query['all'];
            $query['OR']['m.group[~]'] = $query['all'];
            unset($query['all']);
        }

        if (array_key_exists('all[!]', $query)) {
            $query['AND']['m.name[!]']  = $query['all[!]'];
            $query['AND']['m.group[!]'] = $query['all[!]'];
            unset($query['all[!]']);
        }

        if (array_key_exists('all[!~]', $query)) {
            $query['AND']['m.name[!~]']  = $query['all[!~]'];
            $query['AND']['m.group[!~]'] = $query['all[!~]'];
            unset($query['all[!~]']);
        }

        return $query;
    }
}
