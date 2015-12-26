<?php

namespace F3CMS;

class rContact extends Backend
{

    const MTB = "contact";

    function do_list_all($f3, $args) {
        $f3->set('result', $this->_db->exec("SELECT `id`, `status`, `name`, `phone`, `email`, `last_ts` FROM `". $f3->get('tpf') . self::MTB ."` ORDER BY insert_ts DESC "));
        return parent::_return(1, $f3->get('result'));
    }

    function do_add_new($f3, $args) {

        $req = $f3->get('POST'); //parent::getReq();

        if (empty($req['cname'])) {
            return parent::_return(8002, array('msg'=>'姓名未填寫!!'));
        }

        if (empty($req['cemail'])) {
            return parent::_return(8002, array('msg'=>'Email 未填寫!!'));
        }

        if (empty($req['cmessage'])) {
            return parent::_return(8002, array('msg'=>'訊息未填寫!!'));
        }

        $now = date('Y-m-d H:i:s');
        $obj = new \DB\SQL\Mapper($this->_db, $f3->get('tpf') . self::MTB);

        $obj->name = $req['cname'];
        $obj->email = $req['cemail'];
        $obj->message = $req['cmessage'];
        $obj->last_ts = $now;
        $obj->insert_ts = $now;
        $obj->save();

        $f3->set('name', $req['cname']);
        $f3->set('email', $req['cemail']);
        $f3->set('message', nl2br($req['cmessage']));

        $tp = \Template::instance();
        $content = $tp->render('mail/contact.html');

        $sent = Sender::sendmail('詢問單通知', $content, $f3->get('inquiry_receiver'));

        return parent::_return(1, array('pid' => $obj->id, 'msg' => '感謝您，我們會儘快與您聯絡'));
    }

    function do_dl_csv($f3, $args) {
        if (!User::_isLogin()) {
            return parent::_return(8001);
        }

        $rows = $this->_db->exec(
            "SELECT * FROM `". $f3->get('tpf') . self::MTB ."` ORDER BY insert_ts DESC "
        );

        if (!$rows) {
            header("Content-Type:text/html; charset=utf-8");
            echo '無結果';
        }
        else {
            $template = new Template;
            $f3->set('rows', $rows);

            parent::_setXls("contact_".date("YmdHis"));
            echo $template->render('contact.dl.html', "application/vnd.ms-excel");
        }
    }
}
