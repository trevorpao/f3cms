<?php
namespace F3CMS;

/**
* for render page
*/
class oDictionary extends Outfit
{

    function do_show ($f3, $args)
    {

        $cu = fDictionary::get_row($args['slug'], 'id', " AND `status`='". fDictionary::ST_ON ."' ");

        if (empty($cu)) {
            f3()->error(404);
        }

        f3()->set('cu', $cu);

        f3()->set('bc_ary', array(
            array('link'=>'javascript:;', 'title'=>$cu['title'])
        ));

        f3()->set('nav', rMenu::sort_menus(1, 0 , '', 0));

        parent::wrapper('dictionary.html', $cu['title'] . ' - 藝知識', '/d/'. $cu['id']);
    }

    function do_preview ($f3, $args)
    {
        rStaff::_chkLogin();

        $cu = fDictionary::get_row('/'. $args['slug'], 'slug', '', true);

        if (empty($cu)) {
            f3()->error(404);
        }

        f3()->set('cu', $cu);

        f3()->set('nav', rMenu::sort_menus(1, 0 , '', 0));

        parent::wrapper('dictionary.html', $cu['title'], '/dictionary'. $cu['slug']);
    }
}
