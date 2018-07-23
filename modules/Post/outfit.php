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

    function do_sitemap ($f3, $args)
    {

        $subset = fPress::load_list(0, '', 'author', 500);

        foreach ($subset['subset'] as &$row) {
            if (!empty($row['rel_tag'])) {
                $row['rel_tag'] = json_decode($row['rel_tag'], true);
                $ary = array();

                foreach ($row['rel_tag'] as $tmp) {
                    $ary[] = $tmp['title'];
                }

                $row['keyword'] = implode(',', $ary) .','. $row['keyword'];
            }
            if (!empty($row['rel_dict'])) {
                $row['rel_dict'] = json_decode($row['rel_dict'], true);
                $ary = array();

                foreach ($row['rel_dict'] as $tmp) {
                    $ary[] = $tmp['title'];
                }

                $row['keyword'] = implode(',', $ary) .','. $row['keyword'];
            }
        }

        f3()->set('rows', $subset);

        f3()->set('page', fOption::load('page'));

        echo \Template::instance()->render('sitemap.xml','application/xml');
    }

    function do_rss ($f3, $args)
    {

        $subset = fPress::load_list(0, '', 'author', 50);

        f3()->set('rows', $subset);

        f3()->set('page', fOption::load('page'));

        f3()->set('contact_mail', fOption::get('contact_mail'));

        $tp = \Template::instance();
        $tp->filter('date','\F3CMS\Outfit::date');

        echo $tp->render('rss.xml','application/xml');
    }

    function do_show ($f3, $args)
    {

        $row = fPost::get_row('/'. $args['slug'], 'slug', " AND `status`='". fPost::ST_ON ."' ");

        if (empty($cu)) {
            f3()->error(404);
        }

        f3()->set('cu', $cu);

        f3()->set('bc_ary', array(
            array('link'=>'javascript:;', 'title'=>$cu['title'])
        ));

        f3()->set('nav', rMenu::sort_menus(1, 0 , '', 0));

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
        $ts = strtotime($f3->get('siteBeginDate'));
        $now = time();
        if ($now < $ts) {
            parent::wrapper('comingsoon.html', 'Coming Soon', '/');
        }
        else {
            $f3->reroute('/home');
        }
    }
}
