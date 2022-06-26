<?php

namespace F3CMS;

/**
 * data feed
 */
class fDatetime extends Feed
{
    const MTB       = 'datetime';
    const MULTILANG = 0;

    const ST_ON  = 'Enabled';
    const ST_OFF = 'Disabled';

    const BE_COLS = 'm.id,m.target_ts,m.status,m.last_ts';

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
