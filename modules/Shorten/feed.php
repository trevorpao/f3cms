<?php

namespace F3CMS;

class fShorten extends Feed
{
    public const MTB       = 'shorten';
    public const MULTILANG = 0;

    public const ST_OFF = 'Disabled';
    public const ST_ON  = 'Enabled';

    public const BE_COLS = 'm.id,m.status,m.origin,m.token,m.note,m.hits,m.last_ts,m.finished,m.cap';

    /**
     * @param $id
     */
    public static function addCounter($id = 0)
    {
        mh()->update(self::fmTbl(), [
            'hits[+]' => 1,
        ], [
            'id' => $id,
        ]);
    }

    /**
     * @param $token
     */
    public static function addCounterByToken($token = '')
    {
        mh()->update(self::fmTbl(), [
            'hits[+]' => 1,
        ], [
            'token' => $token,
        ]);
    }

    /**
     * @param string $token
     */
    public static function addFinished($token = '')
    {
        mh()->update(self::fmTbl(), [
            'finished[+]' => 1,
        ], [
            'token' => $token,
        ]);
    }

    /**
     * @param $token
     * @param $origin
     * @param $cap
     *
     * @return mixed
     */
    public static function touch($token, $origin, $cap)
    {
        $old = self::chk($token);

        if (!$old) {
            mh()->insert(self::fmTbl(), [
                'status'    => self::ST_ON,
                'token'     => $token,
                'origin'    => $origin,
                'cap'       => $cap,
            ]);

            return [
                'id'       => mh()->id(),
                'origin'   => $origin,
                'token'    => $token,
                'cap'      => $cap,
                'finished' => 0,
            ];
        } else {
            // TODO: check status?
            return $old;
        }
    }

    public static function chk($token)
    {
        return mh()->get(self::fmTbl() . '(m)', [
            'm.id', 'm.origin', 'm.token', 'm.cap', 'm.finished',
        ], [
            'm.token' => $token,
        ]);
    }

    public static function _handleColumn($req)
    {
        if (empty($req['token'])) {
            $req['token'] = implode('-', str_split(self::renderUniqueNo(16), 4));
        }

        return parent::_handleColumn($req);
    }
}
