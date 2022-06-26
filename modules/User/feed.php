<?php

namespace F3CMS;

/**
 * data feed
 */
class fUser extends Feed
{
    const MTB       = 'user';
    const MULTILANG = 0;

    const ST_ON  = 'Follow';
    const ST_OFF = 'Unfollow';

    const BE_COLS = 'm.id,m.display_name,m.slogan,m.status,m.last_ts';

    /**
     * @param $req
     *
     * @return mixed
     */
    public static function follow($req)
    {
        $old = self::chk($req['id']);

        if (!$old) {
            mh()->insert(self::fmTbl(), array_merge($req, [
                'status'    => self::ST_ON,
                'insert_ts' => date('Y-m-d H:i:s'),
                'renew_ts'  => date('Y-m-d H:i:s', strtotime('+1 month')),
            ]));

            $old = ['id' => $req['id'], 'status' => self::ST_ON];
        } else {
            if (self::ST_OFF == $old['status']) {
                $data = mh()->update(self::fmTbl(), [
                    'status'       => self::ST_ON,
                    'display_name' => $req['display_name'],
                    'slogan'       => $req['slogan'],
                    'renew_ts'     => date('Y-m-d H:i:s', strtotime('+1 month')),
                ], [
                    'id' => $req['id'],
                ]);

                parent::chkErr($data->rowCount());

                $old['status'] = self::ST_ON;
            }
        }

        return $old;
    }

    /**
     * @param $user_id
     */
    public static function chk($user_id)
    {
        return mh()->get(self::fmTbl(), [
            'id', 'status',
        ], [
            'id' => $user_id,
        ]);
    }

    /**
     * @param $user_id
     */
    public static function unfollow($user_id)
    {
        $data = mh()->update(self::fmTbl(), [
            'status' => self::ST_OFF,
        ], [
            'id' => $user_id,
        ]);

        return parent::chkErr($data->rowCount());
    }
}
