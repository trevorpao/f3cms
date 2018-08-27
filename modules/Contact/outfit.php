<?php
namespace F3CMS;

/**
* for render page
*/
class oContact extends Outfit
{

    public static function contact ($args)
    {

        $row = fPost::one('contact', 'slug', ['status' => fPost::ST_ON], false);

        if (empty($row)) {
            f3()->error(404);
        }

        f3()->set('cu', $row);
        // f3()->set('social', fOption::load('social'));

        parent::wrapper(f3()->get('theme') .'/contact.html', $row['title'], '/contact');
    }
}
