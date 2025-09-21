<?php

namespace F3CMS;

/**
 * data feed
 */
class fYell extends Feed
{
    const MTB       = 'yell';
    const MULTILANG = 0;

    const PV_R = 'mgr.site';
    const PV_U = 'mgr.site';
    const PV_D = 'mgr.site';

    const BE_COLS = 'm.id,m.insert_ts,m.source,m.action,m.req,m.res';

    /**
     * @param $req
     */
    public static function insert($source, $action, $req, $res)
    {
        $data = [
            'source'      => $source,
            'action'      => $action,
            'req'         => $req,
            'res'         => $res,
            'insert_ts'   => date('Y-m-d H:i:s'),
        ];

        mh()->insert(self::fmTbl(), $data);

        return self::chkErr(mh()->id());
    }
}
