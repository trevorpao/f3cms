<?php

namespace F3CMS;

/**
 * data feed
 */
class fContact extends Feed
{
    const MTB       = 'contact';
    const MULTILANG = 0;

    const PV_R = 'base.member';
    const PV_U = 'base.member';
    const PV_D = 'mgr.member';

    const ST_NEW     = 'New';
    const ST_Process = 'Process';
    const ST_DONE    = 'Done';

    const BE_COLS = 'm.id,m.status,m.name,m.type,m.email,m.insert_ts,m.last_ts';

    /**
     * @param $req
     */
    public static function insert($req)
    {
        $now = date('Y-m-d H:i:s');

        $data = [
            'type'      => $req['type'],
            'name'      => $req['name'],
            'email'     => $req['email'],
            'message'   => $req['message'],
            'status'    => self::ST_NEW,
            'last_ts'   => $now,
            'insert_ts' => $now,
        ];

        mh()->insert(self::fmTbl(), $data);

        return self::chkErr(mh()->id());
    }
}
