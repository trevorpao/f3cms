<?php

namespace F3CMS;

/**
 * data feed
 */
class fStaff extends Feed
{
    public const MTB       = 'staff';
    public const MULTILANG = 0;

    public const ST_NEW      = 'New';
    public const ST_VERIFIED = 'Verified';
    public const ST_FREEZE   = 'Freeze';

    public const PV_R = 'use.web.config';
    public const PV_U = 'use.web.config';
    public const PV_D = 'use.web.config';

    public const BE_COLS = 'id,account,status';

    /**
     * @param $query
     * @param $page
     * @param $limit
     * @param $cols
     *
     * @return mixed
     */
    public static function limitRows($query = '', $page = 0, $limit = 12, $cols = '')
    {
        $filter = self::genQuery($query);

        $filter['ORDER'] = ['m.insert_ts' => 'DESC'];

        $join = [];

        return self::paginate(self::fmTbl() . '(m)', $filter, $page, $limit, explode(',', self::BE_COLS), $join);
    }

    /**
     * @param $str
     */
    public static function _setPsw($str)
    {
        return md5($str);
    }
}
