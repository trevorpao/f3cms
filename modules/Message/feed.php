<?php

namespace F3CMS;

/**
 * data feed
 */
class fMessage extends Feed
{
    const MTB       = 'message';
    const MULTILANG = 0;

    const BE_COLS = 'm.id,m.content,m.insert_ts';

    // 'Command','Location','Datetime','Link','Audio','Video','Image','Postback','Join','Leave','Follow','Unfollow'

    /**
     * @param $req
     *
     * @return mixed
     */
    public static function add($req)
    {
        mh()->insert(self::fmTbl(), array_merge($req, [
            'insert_ts' => date('Y-m-d H:i:s'),
        ]));

        return self::chkErr(1);
    }
}
