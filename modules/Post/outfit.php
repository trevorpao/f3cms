<?php
namespace F3CMS;

/**
* for render page
*/
class oPost extends Outfit
{

    function do_home ($f3, $args)
    {
        parent::wrapper('home.html', '首頁', '/');
    }

    function do_show ($f3, $args)
    {
        $cu = fPost::get_row('/'. $args['slug'], 'slug', " AND `status`='". fPost::ST_ON ."' ");

        if (empty($cu)) {
            f3()->error(404);
        }

        f3()->set('cu', $cu);

        f3()->set('bc_ary', array(
            array('link'=>'javascript:;', 'title'=>$cu['title'])
        ));

        parent::wrapper('post.html', $cu['title'], '/post'. $cu['slug']);
    }

    function do_preview ($f3, $args)
    {
        rStaff::_chkLogin();

        $cu = fPost::get_row('/'. $args['slug'], 'slug', '', true);

        if (empty($cu)) {
            f3()->error(404);
        }

        f3()->set('cu', $cu);

        switch ($cu['slug']) {
            case '/about':
                $tmpl = 'about.html';
                break;
            case '/ourservice':
                $tmpl = 'ourservice.html';
                break;
            default:
                $tmpl = 'post.html';
                break;
        }

        f3()->set('bc_ary', array(
            array('link'=>'javascript:;', 'title'=>$cu['title'])
        ));

        parent::wrapper($tmpl, $cu['title'], '/post'. $cu['slug']);
    }

    function do_about ($f3, $args)
    {
        $cu = fPost::get_row('/about', 'slug', " AND `status`='". fPost::ST_ON ."' ");

        if (empty($cu)) {
            f3()->error(404);
        }

        f3()->set('cu', $cu);

        f3()->set('bc_ary', array(
            array('link'=>'javascript:;', 'title'=>$cu['title'])
        ));
        $f3->set('act_link', 'about');

        parent::wrapper('about.html', $cu['title'], '/about');
    }

    function do_privacy ($f3, $args)
    {
        $cu = fPost::get_row('/privacy', 'slug', " AND `status`='". fPost::ST_ON ."' ");

        if (empty($cu)) {
            f3()->error(404);
        }

        f3()->set('cu', $cu);

        f3()->set('bc_ary', array(
            array('link'=>'javascript:;', 'title'=>$cu['title'])
        ));

        parent::wrapper('post.html', $cu['title'], $cu['slug']);
    }

    function do_comingsoon ($f3, $args)
    {
        parent::wrapper('comingsoon.html', 'Coming Soon', '/');
    }
}
