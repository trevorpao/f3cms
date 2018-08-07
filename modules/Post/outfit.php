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
        $row = fPost::one($args['slug'], 'slug', ['status' => fPost::ST_ON]);

        if (empty($row)) {
            f3()->error(404);
        }

        f3()->set('cu', $row);

        parent::wrapper('post.html', $row['title'], '/post/'. $row['slug']);
    }

    function do_about ($f3, $args)
    {
        $args['slug'] = 'about';
        $this->do_show($f3, $args);
    }

    function do_privacy ($f3, $args)
    {
        $args['slug'] = 'privacy';
        $this->do_show($f3, $args);
    }

    function do_comingsoon ($f3, $args)
    {
        $ts = strtotime($f3->get('siteBeginDate'));
        $now = time();
        if ($now < $ts) {
            parent::wrapper('comingsoon.html', 'Coming Soon', '/comingsoon');
        }
        else {
            $f3->reroute('/home');
        }
    }

    function do_404 ($f3, $args)
    {

        f3()->set('ERROR', [
            'code' => '404',
            'text' => 'Not Found'
        ]);

        parent::wrapper('error.html', 'Not Found', '/404');
    }
}
