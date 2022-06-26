<?php

namespace F3CMS;

/**
 * data feed
 */
class fAddress extends Feed
{
    const MTB = 'address';

    const ST_ON  = 'Enabled';
    const ST_OFF = 'Disabled';

    const MULTILANG = 0;

    const PV_R = 'use.cms';
    const PV_U = 'use.cms';
    const PV_D = 'use.cms';

    const BE_COLS = 'm.id,m.title,m.address,m.latitude,m.longitude,m.status,m.insert_ts';

    /**
     * @param $req
     *
     * @return mixed
     */
    public static function add($req)
    {
        mh()->insert(self::fmTbl(), array_merge($req, [
            'status'    => self::ST_ON,
            'insert_ts' => date('Y-m-d H:i:s'),
        ]));

        return self::chkErr(1);
    }
}
