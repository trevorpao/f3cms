<?php

namespace F3CMS;

class rEdm extends Reaction
{
    /**
     * @param $f3
     * @param $args
     */
    public function do_send($f3, $args)
    {
        kStaff::_chkLogin();

        $req = parent::_getReq();

        if (empty($req['edm_id'])) {
            return self::_return(8002, ['msg' => 'Edm is required!!']);
        }

        if (empty($req['emails'])) {
            return self::_return(8002, ['msg' => 'Emails is required!!']);
        }

        $data = [
            'tw' => self::getHtml($req['edm_id'], 'tw'),
        ];

        // find some email by Module::_lang()
        $sentEmails = [];
        foreach ($req['emails'] as $row) {
            [$e, $l]      = explode(':', $row);
            $sent         = Sender::sendmail($data[$l]['title'], $data[$l]['html'], $e);
            $sentEmails[] = $e;
        }

        return self::_return(1, ['emails' => $sentEmails]);
    }

    /**
     * @param $slug
     * @param $lang
     */
    public static function getHtml($slug, $lang)
    {
        $tp = \Template::instance();
        Module::_lang(['lang' => $lang]);

        $cu = fEdm::one($slug, 'id', ['status' => fEdm::ST_ON], 0);

        if (empty($cu)) {
            f3()->error(404);
        }
        f3()->set('cu', $cu);

        return [
            'title' => $cu['title'],
            'html'  => $tp->render('/edm.html'),
        ];
    }
}
