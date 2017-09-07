<?php
namespace F3CMS;

/**
* for render page
*/
class oEvent extends Outfit
{

    function do_show ($f3, $args)
    {

        $cu = fEvent::get_row('/'. $args['slug'], 'slug', " AND `status`='". fEvent::ST_ON ."' ");

        if (empty($cu)) {
            f3()->error(404);
        }

        f3()->set('cu', $cu);

        f3()->set('reports', fReport::get_type_list($cu['id']));

        f3()->set('bc_ary', array(
            array('link'=>'javascript:;', 'title'=>$cu['title'])
        ));

        parent::wrapper('event.html', $cu['title'], '/event'. $cu['slug']);
    }

    function do_preview ($f3, $args)
    {
        rStaff::_chkLogin();

        $cu = fEvent::get_row('/'. $args['slug'], 'slug', '', true);

        if (empty($cu)) {
            f3()->error(404);
        }

        f3()->set('cu', $cu);

        $tmpl = 'event.html';

        f3()->set('bc_ary', array(
            array('link'=>'javascript:;', 'title'=>$cu['title'])
        ));

        parent::wrapper($tmpl, $cu['title'], '/event'. $cu['slug']);
    }
}
