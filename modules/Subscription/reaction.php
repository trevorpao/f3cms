<?php

namespace F3CMS;

class rSubscription extends Reaction
{
    /**
     * @param $f3
     * @param $args
     */
    public function do_load_all($f3, $args)
    {
        rStaff::_chkLogin();

        $rows = fSubscription::getEnabled();

        return parent::_return(1, $rows);
    }

    /**
     * @param $f3
     * @param $args
     */
    public function do_add_new($f3, $args)
    {
        $req = parent::_getReq();

        if (empty($req['email'])) {
            return parent::_return(8002, ['msg' => 'Email  is required!!']);
        }

        if (empty($req['lang'])) {
            return parent::_return(8002, ['msg' => 'Lang is required!!']);
        }

        fSubscription::insert($req);

        // f3()->set('lang', $req['lang']);
        // f3()->set('email', $req['email']);

        // $tp = \Template::instance();
        // $content = $tp->render(f3()->get('theme') .'/mail/confirm.html');

        // $sent = Sender::send('Confirmation Email', $content, $req['email']);

        return parent::_return(1, ['msg' => 'thanks']); //'Please click the link in the confirmation email'));
    }

    /**
     * @param $f3
     * @param $args
     */
    public function do_dl_csv($f3, $args)
    {
        rStaff::_chkLogin();

        $req = parent::_getReq();

        $subset = fSubscription::limitRows($req['query'], 0, 5000);

        if (!$rows) {
            header('Content-Type:text/html; charset=utf-8');
            echo '無結果';
        } else {
            $template = new Template;
            f3()->set('rows', $subset['subset']);

            Outfit::_setXls('subscrition_' . date('YmdHis'));
            echo $template->render('excel/subscrition.html', 'application/vnd.ms-excel');
        }
    }
}
