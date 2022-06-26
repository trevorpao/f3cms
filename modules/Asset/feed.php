<?php

namespace F3CMS;

/**
 * data feed
 */
class fAsset extends Feed
{
    public const MTB       = 'asset';
    public const MULTILANG = 0;

    public const ST_ON  = 'Enabled';
    public const ST_OFF = 'Disabled';

    public const BE_COLS = 'm.id,m.uri,m.status,m.last_ts';

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
            'token'     => self::renderUniqueNo(32),
        ]));

        return self::chkErr(1);
    }

    public static function byUser($type, $userID, $boardID)
    {
        $filter = [
            'user_id' => $userID,
            'type'    => $type,
            'status'  => self::ST_ON,
            'LIMIT'   => 12,
            'ORDER'   => ['insert_ts' => 'DESC'],
        ];

        if (!empty($boardID)) {
            $filter['board_id'] = $boardID;
        }

        return mh()->select(self::fmTbl(), [
            'uri', 'title', 'insert_ts', 'token', 'type',
        ], $filter);
    }
}
