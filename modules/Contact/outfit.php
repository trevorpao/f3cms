<?php
namespace F3CMS;

/**
* for render page
*/
class oContact extends Outfit
{

    function do_contact ($f3, $args)
    {
        parent::wrapper('contact.html', '聯絡我們', '/contact');
    }
}
