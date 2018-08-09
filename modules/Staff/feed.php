<?php
namespace F3CMS;
/**
 * data feed
 */
class fStaff extends Feed
{
    const MTB = 'staff';
    const ST_NEW = 'New';
    const ST_VERIFIED = 'Verified';
    const ST_FREEZE = 'Freeze';

    const PV_R = 'use.web.config';
    const PV_U = 'use.web.config';
    const PV_D = 'use.web.config';

    const BE_COLS = 'id,account,status';

    /**
     * @param $query
     * @param $page
     * @param $limit
     * @return mixed
     */
    public static function limitRows($query = '', $page = 0, $limit = 12)
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
