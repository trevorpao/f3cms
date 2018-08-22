<?php
namespace F3CMS;

/**
* for render page
*/
class oPost extends Outfit
{
    public static function _middleware($args, string $next)
    {
        Module::_lang($args[1]);
        return parent::_middleware($args, $next);
    }

    public static function home ($args)
    {
        parent::wrapper('home.html', '首頁', '/');
    }

    public static function sitemap ($args)
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

    public static function rss ($args)
    {

        $subset = fPress::load_list(0, '', 'author', 50);

        f3()->set('rows', $subset);

        f3()->set('page', fOption::load('page'));

        f3()->set('contact_mail', fOption::get('contact_mail'));

        $tp = \Template::instance();
        $tp->filter('date','\F3CMS\Outfit::date');

        echo $tp->render('rss.xml','application/xml');
    }

    public static function show ($args)
    {
        $row = fPost::one($args['slug'], 'slug', ['status' => fPost::ST_ON], false);

        if (empty($row)) {
            f3()->error(404);
        }

        f3()->set('cu', $row);

        parent::wrapper('post.html', $row['title'], '/post/'. $row['slug']);
    }

    public static function about ($args)
    {
        $args['slug'] = 'about';
        self::show($args);
    }

    public static function privacy ($args)
    {
        $args['slug'] = 'privacy';
        self::show($args);
    }

    public static function comingsoon ($args)
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

    public static function notfound ($args)
    {
        f3()->set('ERROR', [
            'code' => '404',
            'text' => 'Not Found'
        ]);

        parent::wrapper('error.html', 'Not Found', '/404');
    }
}
