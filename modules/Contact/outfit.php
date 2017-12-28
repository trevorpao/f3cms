<?php
namespace F3CMS;

/**
* for render page
*/
class oContact extends Outfit
{

    function do_contact ($f3, $args)
    {

        $cu = fPost::get_row('/contact', 'slug', " AND `status`='". fPost::ST_ON ."' ");

        if (empty($cu)) {
            f3()->error(404);
        }

        f3()->set('cu', $cu);
        f3()->set('act_link', 'contact');

        // $tp = \Template::instance();
        // $content = $tp->render('mail/edm.html');

        // $sent = Sender::sendmail('edm test', $content, '<shuaib25@gmail.com>,<rosalee322@gmail.com>'); //

        parent::wrapper('contact.html', $cu['title'], '/contact');
    }
}
