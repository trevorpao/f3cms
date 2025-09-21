<?php

namespace F3CMS;

/**
 * for render page
 */
class oPost extends Outfit
{
    /**
     * @param $args
     */
    public static function home($args)
    {
        parent::render('index.twig', '首頁', '/');
    }

    /**
     * @param $args
     */
    public function do_robots($f3, $args)
    {
        echo \Template::instance()->render('robots.html');
    }

    /**
     * @param $args
     */
    public static function sitemap($args)
    {
        $subset = fPress::limitRows([
            'm.status' => [fPress::ST_PUBLISHED, fPress::ST_CHANGED],
        ], 0, 1000);

        f3()->set('rows', $subset);
        f3()->set('page', fOption::load('page'));

        $categories = fCategory::limitRows([
            'm.status' => fCategory::ST_ON,
        ], 0, 20, ',m.insert_ts');

        f3()->set('categories', $categories);

        self::_echoXML('sitemap');
    }

    /**
     * @param $args
     */
    public static function rss($args)
    {
        $subset = fPress::limitRows([
            'm.status' => [fPress::ST_PUBLISHED, fPress::ST_CHANGED],
        ], 0, 1000);

        $subset['subset'] = \__::map($subset['subset'], function ($cu) {
            $cu['authors'] = fPress::lotsAuthor($cu['id']);

            if (!empty($cu['authors'])) {
                $cu['authors'] = implode(', ', \__::pluck($cu['authors'], 'title'));
            } else {
                $cu['authors'] = '';
            }

            return $cu;
        });

        f3()->set('rows', $subset);

        f3()->set('page', fOption::load('page'));
        f3()->set('contact_mail', fOption::get('contact_mail'));

        self::_echoXML('rss');
    }

    /**
     * @param $args
     */
    public static function lineXml($args)
    {
        if (!f3()->get('connectLineXml')) {
            exit('403 Forbidden');
        }

        // TODO: is LINE connention?

        $subset = fPress::limitRows([
            'm.status' => [fPress::ST_PUBLISHED, fPress::ST_CHANGED],
        ], 0, 100, ',l.content');

        $subset['subset'] = \__::map($subset['subset'], function ($cu) {
            $cu['authors'] = fPress::lotsAuthor($cu['id']);

            if (!empty($cu['authors'])) {
                $cu['authors'] = implode(', ', \__::pluck($cu['authors'], 'title'));
            } else {
                $cu['authors'] = '';
            }

            $cu['keyword'] = fPress::lotsTag($cu['id'], true);

            if (!empty($cu['keyword'])) {
                $cu['keyword'] = implode(', ', \__::pluck($cu['keyword'], 'title'));
            } else {
                $cu['keyword'] = '';
            }

            $cu['online_ts'] = strtotime($cu['online_date']) . '000';
            $cu['online_ts'] = strtotime($cu['last_ts']) . '000';

            return $cu;
        });

        f3()->set('rows', $subset);

        f3()->set('page', fOption::load('page'));
        f3()->set('time', time() . '000');
        f3()->set('contact_mail', fOption::get('contact_mail'));

        self::_echoXML('lineXml');
    }

    /**
     * @param $args
     */
    public static function _render($args)
    {
        $row = fPost::one(parent::_slugify($args['slug']), 'slug', ['status' => fPost::ST_ON], 0);

        if (empty($row)) {
            f3()->error(404);
        }

        _dzv('cu', $row);

        f3()->set('breadcrumb_sire', ['title' => '首頁', 'slug' => '/home']);

        if ($row['layout'] == 'na') {
            die('此頁面非一般單頁，無法開啟');
        }

        $args['layout'] = (isset($args['layout'])) ? $args['layout'] : $row['layout'];
        // $args['slug']   = (isset($args['slug'])) ? $args['slug'] : 's/' . $row['slug'];

        parent::render('post/' . $args['layout'] . '.twig', $row['title'], '/s/' . $args['slug']);
    }

    /**
     * @param $args
     */
    public static function about($args)
    {
        $args['slug'] = 'about';
        self::_render($args);
    }

    /**
     * @param $args
     */
    public static function privacy($args)
    {
        $args['slug'] = 'privacy';
        self::_render($args);
    }

    /**
     * @param $args
     */
    public static function terms($args)
    {
        $args['slug'] = 'terms';
        self::_render($args);
    }

    /**
     * @param $args
     */
    public static function maintenance($args)
    {
        parent::wrapper('../maintenance.html', 'Maintenance, Coming Soon', '/maintenance');
    }

    /**
     * @param $args
     */
    public static function comingsoon($args)
    {
        $ts  = strtotime(f3()->get('siteBeginDate'));
        $now = time();
        if ($now < $ts) {
            parent::wrapper('comingsoon.html', 'Coming Soon', '/comingsoon');
        } else {
            f3()->reroute('/home');
        }
    }

    /**
     * @param $args
     */
    public static function notfound($args)
    {
        _dzv('ERROR', [
            'code' => '404',
            'text' => 'Not Found',
        ]);

        parent::render('error.twig', 'Not Found', '/404');
    }
}
