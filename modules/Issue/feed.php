<?php

namespace F3CMS;

/**
 * data feed
 */
class fIssue extends Feed
{
    const MTB       = 'issue';
    const MULTILANG = 0;

    const ST_NEW      = 'New';
    const ST_ACCEPTED = 'Accepted';
    const ST_GOING    = 'Going';
    const ST_DONE     = 'Done';
    const ST_INVALID  = 'Invalid';

    const BE_COLS = 'm.id,m.status,m.title,m.insert_ts,m.last_ts';

    /**
     * @param $req
     */
    public static function insert($req)
    {
        $now = date('Y-m-d H:i:s');

        $req['slug'] = (empty($req['slug'])) ? self::renderUniqueNo(16) : parent::_slugify($req['slug']);

        mh()->insert(self::fmTbl(), array_merge($req, [
            'status'      => self::ST_NEW,
            'last_ts'     => $now,
            'insert_ts'   => $now,
            'insert_user' => $req['user_id'],
            'last_user'   => $req['user_id'],
        ]));

        return self::chkErr(mh()->id());
    }
}
