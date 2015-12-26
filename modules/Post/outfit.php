<?php
namespace F3CMS;

/**
* for render page
*/
class oPost extends Outfit
{

    function do_home ($f3, $args)
    {
        $subset = fWork::get_works($args['page']);
        f3()->set('works', $subset);

        $subset = fProduct::get_products(0, 1);
        f3()->set('prods', $subset);

        $pcate = fCategory::get_categories(0);
        foreach ($pcate as &$row) {
            $row['subrows'] = fCategory::get_categories($row['id']);
        }
        f3()->set('pcate', $pcate);

        f3()->set('advs', fAdv::getAdvs(1, 5));

        parent::wrapper('home.html', '首頁', '/');
    }

    function do_post_show ($f3, $args)
    {
        $cu = fPost::get_row('/'. $args['slug'], 'slug', " AND `status`='". fPost::ST_ON ."' ");

        if (empty($cu)) {
            f3()->error(404);
        }

        f3()->set('cu', $cu);

        parent::wrapper('post.html', $cu['title'], '/post'. $cu['slug']);
    }

    function do_about ($f3, $args)
    {
        $cu = fPost::get_row('/about', 'slug', " AND `status`='". fPost::ST_ON ."' ");

        if (empty($cu)) {
            f3()->error(404);
        }

        f3()->set('cu', $cu);

        parent::wrapper('about.html', '關於酷崎獅', '/about');
    }

    function do_privacy ($f3, $args)
    {
        $cu = fPost::get_row('/privacy', 'slug', " AND `status`='". fPost::ST_ON ."' ");

        if (empty($cu)) {
            f3()->error(404);
        }

        f3()->set('cu', $cu);

        parent::wrapper('post.html', $cu['title'], $cu['slug']);
    }

    function do_comingsoon ($f3, $args)
    {
        parent::wrapper('comingsoon.html', 'Coming Soon', '/');
    }
}
