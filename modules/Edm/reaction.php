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
        rStaff::_chkLogin();

        $req = parent::_getReq();

        if (empty($req['edm_id'])) {
            return parent::_return(8002, array('msg' => 'Edm is required!!'));
        }

        if (empty($req['emails'])) {
            return parent::_return(8002, array('msg' => 'Emails is required!!'));
        }

        $data = array(
            'tw' => self::getHtml($req['edm_id'], 'tw')
        );

        // find some email by Module::_lang()
        $sentEmails = array();
        foreach ($req['emails'] as $row) {
            list($e, $l) = explode(':', $row);
            $sent = Sender::sendmail($data[$l]['title'], $data[$l]['html'], $e);
            $sentEmails[] = $e;
        }

        return parent::_return(1, array('emails' => $sentEmails));
    }

    /**
     * @param $slug
     * @param $lang
     */
    public static function getHtml($slug, $lang)
    {
        $tp = \Template::instance();
        Module::_lang(array('lang' => $lang));

        $cu = fEdm::one($slug, 'id', array('status' => fEdm::ST_ON), 0);

        if (empty($cu)) {
            f3()->error(404);
        }
        f3()->set('cu', $cu);

        return array(
            'title' => $cu['title'],
            'html'  => $tp->render(f3()->get('theme') .'/edm.html')
        );

    }
}
