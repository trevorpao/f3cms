<?php

namespace F3CMS;

class rSubscription extends Reaction
{
    /**
     * @param $f3
     * @param $args
     */
    public function do_add_new($f3, $args)
    {
        $req = parent::_getReq();

        if (empty($req['email'])) {
            return self::_return(8002, ['msg' => 'Email  is required!!']);
        }

        if (empty($req['lang'])) {
            return self::_return(8002, ['msg' => 'Lang is required!!']);
        }

        fSubscription::insert($req);

        // f3()->set('lang', $req['lang']);
        // f3()->set('email', $req['email']);

        // $tp = \Template::instance();
        // $content = $tp->render(f3()->get('theme') .'/mail/confirm.html');

        // $sent = Sender::send('Confirmation Email', $content, $req['email']);

        return self::_return(1, ['msg' => 'thanks']); // 'Please click the link in the confirmation email'));
    }
}
