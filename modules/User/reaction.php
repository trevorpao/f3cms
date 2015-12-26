<?php

namespace F3CMS;

class rUser extends Backend
{

    const MTB = "users";
    const ST_ON = "Enabled";
    const ST_OFF = "Disabled";

    const ROLE_ADMIN = "Admin";
    const ROLE_MEMBER = "Member";

    function do_list_all($f3, $args)
    {

        rUser::_chkLogin();

        $f3->set('result', $this->_db->exec("SELECT * FROM `". $f3->get('tpf') . self::MTB ."` "));
        return parent::_return(1, $f3->get('result'));
    }

    function do_get_one($f3, $args)
    {

        rUser::_chkLogin();

        $req = parent::_getReq();

        if (!isset($req['pid'])) {
            return parent::_return(8004);
        }

        if ($req['pid'] == 0) {
            return parent::_return(1, array('id'=>0));
        }

        $rows = $this->_db->exec(
            "SELECT * FROM `". $f3->get('tpf') . self::MTB ."` WHERE `id`=? ", $req['pid']
        );

        if (count($rows) != 1) {
            return parent::_return(8106);
        }

        $cu = $rows[0];

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

        $rows = $this->_db->exec(
            "SELECT * FROM `". $f3->get('tpf') . self::MTB ."` WHERE `account`=? ", $req['account']
        );

        if (count($rows) != 1) {
            return parent::_return(8106);
        }

        $cu = $rows[0];

        if ($cu['pwd'] != self::_setPsw($req['pwd'])) {
            return parent::_return(8104);
        }

        if ($cu['status'] != 'Verified') {
            return parent::_return(8105);
        }

        $f3->set('SESSION.cu', array('name'=>$cu['account'], "id"=>$cu['id'], 'has_login' => 1));

        return parent::_return(self::_isLogin(), array('name'=>self::_CUser('name')));
    }

    function do_logout($f3, $args)
    {
        if (self::_isLogin()) {
            $f3->clear('SESSION.cu');
        }

        return parent::_return(!self::_isLogin(), array());
    }

    function do_chk_login($f3, $args)
    {
        return parent::_return(self::_isLogin());
    }

    static function _setPsw($str)
    {
        return md5($str);
    }

    static function _isLogin()
    {
        if (!isset($f3)) {
            $f3 = \Base::instance();
        }

        $cu = $f3->get('SESSION.cu');

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
            return BaseModule::_return(8001);
        }
    }

    static function _CUser($column = 'id')
    {

        if (!isset($f3)) {
            $f3 = \Base::instance();
        }

        $cu = $f3->get('SESSION.cu');
        $str = "";

        if (isset($cu)) {
            if (isset($cu[$column])) {
                $str = $cu[$column];
            }
        }

        return $str;
    }
}
