<?php

namespace F3CMS;

/**
 * data feed
 */
class fMedia extends Feed
{
    public const MTB = 'media';

    public const ST_ON  = 'Enabled';
    public const ST_OFF = 'Disabled';

    public const PV_R = 'use.cms';
    public const PV_U = 'use.cms';
    public const PV_D = 'use.cms';

    public const PV_SOP = 'see.other.press';

    public const BE_COLS = 'm.id,m.title,m.slug,m.status,m.pic,info,m.last_ts';

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

        // if (!canDo(self::PV_SOP)) {
        // $filter['m.insert_user'] = rStaff::_CStaff();
        // }

        $filter['ORDER'] = ['m.insert_ts' => 'DESC'];

        $join = ['[>]' . fStaff::fmTbl() . '(s)' => ['m.insert_user' => 'id']];

        return self::paginate(self::fmTbl() . '(m)', $filter, $page, $limit, explode(',', self::BE_COLS), $join);
    }
}
