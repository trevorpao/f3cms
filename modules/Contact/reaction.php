<?php

namespace F3CMS;

class rContact extends Reaction
{
    /**
     * @param $f3
     * @param $args
     */
    public function do_add_new($f3, $args)
    {
        $req = parent::_getReq();

        // if (!chkCSRF()) {
        //     return parent::_return(8002, ['msg' => '欄位未填寫，請重新確認! (miss_token)']);
        // }

        Validation::return($req, kContact::rule('add_new'));

        fContact::insert($req);

        f3()->set('name', $req['name']);
        f3()->set('email', $req['email']);

        f3()->set('phone', !empty($req['phone']) ? $req['phone'] : '');
        f3()->set('type', !empty($req['type']) ? $req['type'] : '');
        f3()->set('company', !empty($req['company']) ? $req['company'] : '');

        f3()->set('message', nl2br($req['message']));

        $sent = Sender::sendmail(
            f3()->get('site_title') . ' 網站詢問通知',
            Sender::renderBody('contact'),
            f3()->get('opts.default.contact_mail')
        );

        return parent::_return(1, ['msg' => '感謝您~~']);
    }
}
