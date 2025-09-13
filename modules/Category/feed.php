<?php

namespace F3CMS;

/**
 * data feed
 */
class fCategory extends Feed
{
    const MTB = 'category';

    const PV_R = 'mgr.site';
    const PV_U = 'mgr.site';
    const PV_D = 'mgr.site';

    const ST_ON  = 'Enabled';
    const ST_OFF = 'Disabled';

    public const BE_COLS = 'm.id,m.slug,m.sorter,m.group,m.status,l.title,l.info';

    public static function genOrder()
    {
        return ['m.sorter' => 'ASC', 'm.group' => 'ASC', 'm.insert_ts' => 'ASC'];
    }

    /**
     * @param $queryStr
     *
     * @return mixed
     */
    public static function adjustFilter($oldFilter = [])
    {
        $oldFilter = parent::adjustFilter($oldFilter);

        if (array_key_exists('all', $oldFilter)) {
            $oldFilter['OR']['l.title[~]'] = $oldFilter['all'];
            $oldFilter['OR']['m.group[~]'] = $oldFilter['all'];
            unset($oldFilter['all']);
        }

        if (array_key_exists('all[!]', $oldFilter)) {
            $oldFilter['AND']['l.title[!]'] = $oldFilter['all[!]'];
            $oldFilter['AND']['m.group[!]'] = $oldFilter['all[!]'];
            unset($oldFilter['all[!]']);
        }

        if (array_key_exists('all[!~]', $oldFilter)) {
            $oldFilter['AND']['l.title[!~]'] = $oldFilter['all[!~]'];
            $oldFilter['AND']['m.group[!~]'] = $oldFilter['all[!~]'];
            unset($oldFilter['all[!~]']);
        }

        return $oldFilter;
    }
}
