<?php
namespace F3CMS;

/**
* for render page
*/
class oContact extends Outfit
{

    function do_contact ($f3, $args)
    {
        $f3->set('act_link', 'contact');

        parent::wrapper('contact.html', 'Contact me', '/contact');
    }
}
