<?php

namespace F3CMS;

/**
 * data feed
 */
class fPortal extends Feed
{
    const MTB       = 'portal';
    const MULTILANG = 0;

    const ST_ON  = 'Enabled';
    const ST_OFF = 'Disabled';

    const PV_R = 'use.cms';
    const PV_U = 'use.cms';
    const PV_D = 'use.cms';

    const BE_COLS = 'm.id,m.type,m.status,m.cmd,m.last_ts';

    /**
     * @param $query
     * @param $page
     * @param $limit
     * @param $cols
     */
    public static function limitRows($query = '', $page = 0, $limit = 10, $cols = '')
    {
        $filter = self::genQuery($query);

        $filter['ORDER'] = ['m.insert_ts' => 'DESC'];

        $join = [];

        return parent::paginate(self::fmTbl() . '(m)', $filter, $page, $limit, explode(',', self::BE_COLS), $join);
    }

    public static function postback($req)
    {
        if (isset($req['target_ts'])) {
            return fDatetime::add($req);
        }

        // TODO:: Handle issue, mode change

        return null;
    }
}
