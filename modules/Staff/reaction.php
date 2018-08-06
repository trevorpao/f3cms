<?php
namespace F3CMS;

class rStaff extends Reaction
{
    function do_get_one($f3, $args)
    {
        rStaff::_chkLogin();

        $req = parent::_getReq();

        if (!isset($req['pid'])) {
            return parent::_return(8004);
        }

        if ($req['pid'] == 0) {
            return parent::_return(1, array(
                'id' => 0
            ));
        }

        $cu = fStaff::one($req['pid']);

        if ($cu == null) {
            return parent::_return(8106);
        }

        unset($cu['pwd']);

        return parent::_return(1, $cu);
    }

    function do_login($f3, $args)
    {
        $req = parent::_getReq();

        if (!isset($req['account'])) {
            return parent::_return(8102);
        }

        if (!isset($req['pwd'])) {
            return parent::_return(8103);
        }

        $cu = fStaff::one($req['account'], 'account');

        if ($cu == null) {
            return parent::_return(8106);
        }

        if ($cu['pwd'] != fStaff::_setPsw($req['pwd'])) {
            return parent::_return(8104, ['get' => fStaff::_setPsw($req['pwd'])]);
        }

        if ($cu['status'] != fStaff::ST_VERIFIED) {
            return parent::_return(8105);
        }

        f3()->set('SESSION.cs', array(
            'name' => $cu['account'],
            "id" => $cu['id'],
            'has_login' => 1
        ));

        return parent::_return(self::_isLogin() , array(
            'name' => self::_CStaff('name')
        ));
    }

    function do_logout($f3, $args)
    {
        if (self::_isLogin()) {
            f3()->clear('SESSION.cs');
        }

        return parent::_return(!self::_isLogin() , array());
    }

    function do_status($f3, $args)
    {
        $rtn = [
            'isLogin' => 0
        ];
        if (self::_isLogin()) {
            $rtn['isLogin'] = 1;
            $rtn['user'] = f3()->get('SESSION.cs');
        }
        return parent::_return(1, $rtn);
    }

    static function _isLogin()
    {
        $cu = f3()->get('SESSION.cs');

        if (isset($cu)) {
            if (isset($cu['has_login']) && $cu['has_login']) {
                return 1;
            }
        }

        return 0;
    }

    static function _chkLogin()
    {
        if (!self::_isLogin()) {
            return parent::_return(8001);
        }
    }

    static function _CStaff($column = 'id')
    {
        $cu = f3()->get('SESSION.cs');
        $str = "";

        if (isset($cu)) {
            if (isset($cu[$column])) {
                $str = $cu[$column];
            }
        }

        return $str;
    }

    static function handleRow($row = array())
    {
        unset($row['pwd']);
        return $row;
    }
}
