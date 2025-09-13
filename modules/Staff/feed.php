<?php

namespace F3CMS;

/**
 * data feed
 */
class fStaff extends Feed
{
    const MTB       = 'staff';
    const MULTILANG = 0;

    const ST_NEW      = 'New';
    const ST_VERIFIED = 'Verified';
    const ST_FREEZE   = 'Freeze';

    const PV_R = 'mgr.site';
    const PV_U = 'mgr.site';
    const PV_D = 'mgr.site';

    const BE_COLS = 'm.id,m.account,m.role_id,m.email,m.status,r.title(role)';

    public static function genJoin()
    {
        return [
            '[>]' . fRole::fmTbl() . '(r)' => ['m.role_id' => 'id'],
        ];
    }

    /**
     * @param $member_id
     */
    public static function insertSudo($member_id)
    {
        $now = date('Y-m-d H:i:s');

        mh()->insert(self::fmTbl('sudo'), [
            'member_id'   => $member_id,
            'last_ts'     => $now,
            'insert_ts'   => $now,
            'insert_user' => self::_current('id'),
        ]);

        return mh()->id();
    }

    /**
     * @param $account
     * @param $id
     * @param $email
     * @param $avatar
     * @param $priv
     */
    public static function _setCurrent($account, $id, $email, $avatar, $priv, $menu_id)
    {
        f3()->set('SESSION.cu_staff', [
            'name'      => $account,
            'id'        => $id,
            'email'     => $email,
            'avatar'    => $avatar,
            'menu'      => $menu_id,
            'priv'      => fRole::parseAuth(fRole::parseAuthIdx(fRole::reverseAuth($priv))),
            'has_login' => 1,
        ]);
    }

    public static function _clearCurrent()
    {
        f3()->clear('SESSION.cu_staff');
    }

    /**
     * @param $column
     *
     * @return mixed
     */
    public static function _current($column = 'id')
    {
        $cu = f3()->get('SESSION.cu_staff');
        // $cu = [
        //     'account' => 'shuaib25@gmail.com',
        //     'id' => 1,
        //     'nickname' => '測試帳號',
        //     'avatar' => 'https://robohash.org/c4e919002494d5e124c544e99e073308?set=set4&s=64',
        //     'has_login' => 1
        // ];

        $str = '';

        if (isset($cu) && '*' != $column && isset($cu[$column])) {
            $str = $cu[$column];
        }

        return ('*' == $column) ? $cu : $str;
    }
}
