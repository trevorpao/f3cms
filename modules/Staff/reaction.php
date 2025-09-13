<?php

namespace F3CMS;

class rStaff extends Reaction
{
    const RTN_MISSCOLS   = 'MissCols';
    const RTN_WRONGDATA  = 'WrongData';
    const RTN_UNVERIFIED = 'UnVerified';
    const RTN_NOTSAME    = 'NotSame';
    const RTN_LOGINED    = 'Logined';
    const RTN_DONE       = 'Done';
    const RTN_UPDATED    = 'Updated';
    const RTN_RESET      = 'Reset';
    const RTN_NOTSTRONG  = 'Weak';
    const RTN_PAUSE      = 'Pause';
    const RTN_TOOOLD     = 'TooOld';
    const RTN_REPEAT     = 'Repeated';
    const RTN_MUTLI      = 'Mutli';
    const RTN_WRONGIP    = 'WrongIP';
    const RTN_WRONGCSRF  = 'WrongCSRF';

    /**
     * @param $f3
     * @param $args
     */
    public function do_login($f3, $args)
    {
        $rtn = self::RTN_LOGINED;

        $req     = parent::_getReq();
        $counter = 0;

        Validation::return($req, kStaff::rule('login'));

        if ($_SERVER['HTTP_X_REQUESTED_TOKEN'] != f3()->get('SESSION.csrf')) {
            $rtn = self::RTN_WRONGCSRF;
        }

        if (self::RTN_LOGINED == $rtn) {
            $cu = fStaff::one($req['account'], 'account');

            if (null == $cu) {
                $rtn = self::RTN_WRONGDATA;
            } else {
                // check error counter
                $counter = fDoorman::count(fDoorman::T_STAFF, $cu['id']);
                if ($counter > 2) {
                    $rtn = self::RTN_PAUSE;
                    fStream::insert('StaffLogin', $cu['id'], '登入管理帳號：' . $cu['account'] . ' 失敗過多');
                } else {
                    if (!fStaff::_chkPsw($req['pwd'], $cu['pwd'], $cu['id'])) {
                        $rtn = self::RTN_WRONGDATA;
                        fDoorman::insert(fDoorman::T_STAFF, $cu['id'], fStaff::_setPsw($req['pwd']));
                        ++$counter;
                        fStream::insert('StaffLogin', $cu['id'], '登入管理帳號：' . $cu['account'] . ' 失敗: ' . $counter . ' 次');
                    }

                    if (fStaff::ST_VERIFIED != $cu['status']) {
                        $rtn = self::RTN_UNVERIFIED;
                    }

                    $whitelist = fOption::get('ip_whitelist');

                    if (!empty($whitelist)) {
                        $whitelist = explode(PHP_EOL, $whitelist);
                    } else {
                        $whitelist = ['*'];
                    }

                    if (1 != $cu['role_id'] && f3()->get('DEBUG') < 1 && !isAllowedIP(f3()->IP, $whitelist)) {
                        $rtn = self::RTN_WRONGIP;
                    }
                }
            }
        }

        $result = [
            'msg'     => self::formatMsgs()[$rtn]['msg'],
            'counter' => $counter,
            'seed'    => ((f3()->get('DEBUG') > 1) ? fStaff::_setPsw($req['pwd']) : ''),
        ];

        if (self::RTN_LOGINED == $rtn) {
            $counter = fDoorman::zero(fDoorman::T_STAFF, $cu['id']);

            $lastFootmark = fDoorman::lastFootmark(fDoorman::T_STAFF, $cu['id']);
            if ($lastFootmark > f3()->get('passwd_expired')) {
                $rtn           = self::RTN_TOOOLD;
                $result['msg'] = self::formatMsgs()[$rtn]['msg'];

                fStaff::saveCol([
                    'col' => 'needReset',
                    'val' => 1,
                    'pid' => $cu['id'],
                ]);
            }

            $role = fRole::one($cu['role_id'], 'id');

            fStaff::_setCurrent($cu['account'], $cu['id'], $cu['email'], '', $role['priv'], $role['menu_id']);
            $result['lastFootmark'] = $lastFootmark;
        }

        return self::_return(self::formatMsgs()[$rtn]['code'], $result);
    }

    /**
     * @param $f3
     * @param $args
     */
    public function do_saveMine($f3, $args)
    {
        chkAuth(); // only check login

        $req = parent::_getReq();
        $rtn = self::RTN_UPDATED;

        if (empty($req['email'])) {
            $rtn = self::RTN_MISSCOLS;
        }

        if (!empty($req['pwd'])) {
            if ($req['pwd_confirm'] != $req['pwd']) {
                $rtn = self::RTN_NOTSAME;
            }
        }

        if (self::RTN_UPDATED == $rtn) {
            $cu = fStaff::_current('*');

            if (null == $cu) {
                $rtn = self::RTN_WRONGDATA;
            } else {
                // if (fStaff::ST_FREEZE == $cu['status']) {
                //     $rtn = self::RTN_UNVERIFIED;
                // }
            }
        }

        $result = [
            'msg' => self::formatMsgs()[$rtn]['msg'],
        ];

        if (self::RTN_UPDATED == $rtn) {
            $pid = fStaff::_current('id');
            unset($req['pwd_confirm']);

            if (!empty($req['pwd'])) {
                fDoorman::insertFootmark(fDoorman::T_STAFF, $pid, fStaff::_setPsw($req['pwd']));
            }

            [$data, $other] = fStaff::_handleColumn($req);

            mh()->update(fStaff::fmTbl(), $data, ['id' => $pid]);

            $cu = fStaff::one($pid, 'id');

            $role = fRole::one($cu['role_id'], 'id');

            fStaff::_setCurrent($cu['account'], $cu['id'], $cu['email'], '', $role['priv'], $role['menu_id']);
        }

        return self::_return(self::formatMsgs()[$rtn]['code'], $result);
    }

    /**
     * @param $f3
     * @param $args
     */
    public function do_logout($f3, $args)
    {
        if (kStaff::_isLogin()) {
            fStaff::_clearCurrent();
        }

        return self::_return(!kStaff::_isLogin(), []);
    }

    /**
     * @param $f3
     * @param $args
     */
    public function do_status($f3, $args)
    {
        $rtn = [
            'isLogin' => 0,
        ];
        if (kStaff::_isLogin()) {
            $rtn['isLogin'] = 1;
            $rtn['user']    = fStaff::_current('*');
        }

        return self::_return(1, $rtn);
    }

    /**
     * @param $f3
     * @param $args
     */
    public function do_resend($f3, $args)
    {
        chkAuth(fStaff::PV_U);
        $req = parent::_getReq();

        if (!isset($req['pid'])) {
            return self::_return(8004);
        }

        $cu = fStaff::one((int) $req['pid']);

        if (empty($cu)) {
            return self::_return(8004);
        }

        $verify_code = fStaff::renderUniqueNo(32);

        kStaff::sendInvite($cu['email'], $verify_code);

        fStaff::saveCol([
            'col' => 'verify_code',
            'val' => $verify_code,
            'pid' => $cu['id'],
        ]);

        return parent::_return(1, ['msg' => '已寄出驗證信']);
    }

    /**
     * @param $f3
     * @param $args
     */
    public function do_sudo($f3, $args)
    {
        chkAuth(fStaff::PV_U);

        $member = fMember::one($args['UID']);

        if (!empty($member)) {
            $super  = canDo(fMember::PV_SDP);
            $normal = canDo(fMember::PV_R);

            if ($super || ($normal && fStaff::_current('email') == $member['email'])) {
                fMember::_setCurrent($member['account'], $member['id'], $member['avatar'], $member['nickname'], $member['agency_id'], $member['role']);
                fStaff::insertSudo($member['id']);
                fStream::insert('Sudo', $member['id'], '登入會員帳號：' . $member['account'] . ' 進行操作');
            } else {
                fStream::insert('Sudo', $member['id'], '登入會員帳號：' . $member['account'] . ' 失敗(權限不足)');
            }
        } else {
            fStream::insert('Sudo', $args['UID'], '登入會員帳號：' . $args['UID'] . ' 失敗(會員資料遺失)');
        }

        f3()->reroute(f3()->get('uri') . '/member');
    }

    public function do_get_opts($f3, $args)
    {
        chkAuth(fRole::PV_R);

        return self::_return(1, fRole::getAuthOpts());
    }

    public static function formatMsgs()
    {
        return array_merge([
            'Done'     => [
                'code' => 1,
                'msg'  => '歡迎您加入!',
            ],
            'Logined'  => [
                'code' => 1,
                'msg'  => '歡迎您回來!',
            ],
            'Updated'  => [
                'code' => 1,
                'msg'  => '變更已完成!',
            ],
            'Reset'    => [
                'code' => 1,
                'msg'  => '已重設密碼，請收信取得新密碼!',
            ],
            'NotSame'  => [
                'code' => 8206,
                'msg'  => '二次密碼不符，請重新確認!',
            ],
            'Weak'     => [
                'code' => 8207,
                'msg'  => '密碼強度不足，請重新確認!',
            ],
            'Pause'    => [
                'code' => 8208,
                'msg'  => '錯誤次數過多，請一小時後再試!',
            ],
            'TooOld'   => [
                'code' => 8209,
                'msg'  => '密碼過舊，請儘快變更密碼!',
            ],
            'Repeated' => [
                'code' => 8210,
                'msg'  => '密碼重覆，請重新設定!',
            ],
            'Mutli'    => [
                'code' => 8211,
                'msg'  => '重覆登入，請重新登入!',
            ],
            'WrongIP'    => [
                'code' => 8212,
                'msg'  => '來源 IP 異常(' . f3()->IP . ')!',
            ],
            'WrongCSRF'    => [
                'code' => 8213,
                'msg'  => 'CSRF 異常，請重整畫面後重試',
            ],
        ], parent::formatMsgs());
    }

    /**
     * @param array $row
     *
     * @return mixed
     */
    public static function handleRow($row = [])
    {
        unset($row['pwd']);

        return $row;
    }
}
