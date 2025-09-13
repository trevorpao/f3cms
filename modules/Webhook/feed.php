<?php

namespace F3CMS;

/**
 * data feed
 */
class fWebhook extends Feed
{
    const MTB       = 'webhook';
    const MULTILANG = 0;

    const BE_COLS = 'm.id,m.insert_ts,m.source,m.action,m.req,m.res,m.ip';

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
            'ip'          => f3()->IP,
            'insert_ts'   => date('Y-m-d H:i:s'),
        ];

        mh()->insert(self::fmTbl(), $data);

        return self::chkErr(mh()->id());
    }
}
