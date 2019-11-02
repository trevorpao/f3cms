<?php

namespace F3CMS;

/**
 * data feed
 */
class fContact extends Feed
{
    const MTB = 'contact';
    const MULTILANG = 0;

    const ST_NEW = 'New';
    const ST_Process = 'Process';
    const ST_DONE = 'Done';

    const BE_COLS = 'id,status,name,email,insert_ts,last_ts';

    /**
     * @param $req
     */
    public static function insert($req)
    {
        $now = date('Y-m-d H:i:s');

        $data = [
            'name'      => $req['name'],
            'email'     => $req['email'],
            'message'   => $req['message'],
            'status'    => self::ST_NEW,
            'last_ts'   => $now,
            'insert_ts' => $now
        ];

        mh()->insert(self::fmTbl(), $data);

        return mh()->id();
    }
}
