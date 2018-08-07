<?php
namespace F3CMS;

/**
* for render page
*/
class oContact extends Outfit
{

    function do_contact ($f3, $args)
    {

        $row = fPost::one('contact', 'slug', ['status' => fPost::ST_ON]);

        if (empty($row)) {
            f3()->error(404);
        }

        f3()->set('cu', $row);
        // f3()->set('social', fOption::load('social'));

        parent::wrapper('contact.html', $row['title'], '/contact');
    }
}
