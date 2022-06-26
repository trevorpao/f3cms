<?php

namespace F3CMS;

use LINE\LINEBot;
use LINE\LINEBot\HTTPClient\CurlHTTPClient;

/**
 * data feed
 */
class fBoard extends Feed
{
    const MTB = 'board';

    const ST_ON  = 'Join';
    const ST_OFF = 'Leave';

    const MULTILANG = 0;

    const PV_R = 'use.cms';
    const PV_U = 'use.cms';
    const PV_D = 'use.cms';

    const BE_COLS = 'm.id,m.display_name,m.cover,m.counter,m.insert_ts';

    /**
     * @param $req
     *
     * @return mixed
     */
    public static function join($req)
    {
        $old = self::chk($req['id']);

        if (!$old) {
            mh()->insert(self::fmTbl(), array_merge($req, [
                'status'    => self::ST_ON,
                'insert_ts' => date('Y-m-d H:i:s'),
                'renew_ts'  => date('Y-m-d H:i:s', strtotime('+1 month')),
            ]));

            return ['id' => $req['id'], 'status' => self::ST_ON];
        } else {
            if (self::ST_OFF == $old['status']) {
                $data = mh()->update(self::fmTbl(), [
                    'status'       => self::ST_ON,
                    'display_name' => $req['display_name'],
                    'counter'      => $req['counter'],
                    'cover'        => $req['cover'],
                    'renew_ts'     => date('Y-m-d H:i:s', strtotime('+1 month')),
                ], [
                    'id' => $req['id'],
                ]);

                parent::chkErr($data->rowCount());

                $old['status'] = self::ST_ON;
            }

            return $old;
        }
    }

    /**
     * @param $board_id
     */
    public static function chk($board_id)
    {
        return mh()->get(self::fmTbl(), [
            'id', 'status',
        ], [
            'id' => $board_id,
        ]);
    }

    /**
     * @param $board_id
     */
    public static function leave($board_id)
    {
        $data = mh()->update(self::fmTbl(), [
            'status' => self::ST_OFF,
        ], [
            'id' => $board_id,
        ]);

        return parent::chkErr($data->rowCount());
    }

    public static function getMembers($board_id)
    {
        $bot = new LINEBot(new CurlHTTPClient(f3()->get('line_token')), [
            'channelSecret' => f3()->get('line_secret'),
            // 'endpointBase' => $apiEndpointBase, // <= Normally, you can omit this
        ]);

        $members = $bot->getAllGroupMemberIds($board_id);

        print_r($members);
    }
}
