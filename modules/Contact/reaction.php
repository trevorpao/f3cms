<?php

namespace F3CMS;

class rContact extends Reaction
{

    function do_add_new($f3, $args) {

        $req = f3()->get('POST'); //parent::getReq();

        if (empty($req['name'])) {
            return parent::_return(8002, array('msg'=>'姓名未填寫!!'));
        }

        if (empty($req['email'])) {
            return parent::_return(8002, array('msg'=>'Email 未填寫!!'));
        }

        if (empty($req['message'])) {
            return parent::_return(8002, array('msg'=>'訊息未填寫!!'));
        }

        fContact::insert($req);

        f3()->set('name', $req['name']);
        f3()->set('email', $req['email']);
        f3()->set('message', nl2br($req['message']));

        $tp = \Template::instance();
        $content = $tp->render('mail/contact.html');

        $sent = Sender::sendmail('聯絡我們-通知', $content, f3()->get('webmaster'));

        return parent::_return(1, array('pid' => $obj->id, 'msg' => '感謝您~~'));
    }

    function do_dl_csv($f3, $args) {
        if (!rStaff::_isLogin()) {
            return parent::_return(8001);
        }

        $rows = $this->_db->exec(
            "SELECT * FROM `". self::fmTbl() ."` ORDER BY insert_ts DESC "
        );

        if (!$rows) {
            header("Content-Type:text/html; charset=utf-8");
            echo '無結果';
        }
        else {
            $template = new Template;
            f3()->set('rows', $rows);

            Outfit::_setXls("contact_".date("YmdHis"));
            echo $template->render('contact.dl.html', "application/vnd.ms-excel");
        }
    }
}
