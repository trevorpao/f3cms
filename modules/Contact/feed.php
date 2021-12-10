<?php

namespace F3CMS;

/**
 * data feed
 */
class fContact extends Feed
{
    public const MTB       = 'contact';
    public const MULTILANG = 0;

    public const ST_NEW     = 'New';
    public const ST_Process = 'Process';
    public const ST_DONE    = 'Done';

    public const BE_COLS = 'id,status,name,email,insert_ts,last_ts';

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
            'insert_ts' => $now,
        ];

        mh()->insert(self::fmTbl(), $data);

        return mh()->id();
    }
}
