<?php

namespace F3CMS;

/**
 * for render page
 */
class oPress extends Outfit
{
    public function list($args)
    {
        $req = parent::_getReq();

        $req['page'] = ($req['page']) ? ($req['page'] - 1) : 0;

        $subset = fPress::limitRows('status:' . fPress::ST_PUBLISHED, $req['page']);

        $subset['subset'] = \__::map($subset['subset'], function ($cu) {
            $cu['tags'] = fPress::lotsTag($cu['id']);
            $cu['authors'] = fPress::lotsAuthor($cu['id']);
            $cu['metas'] = fPress::lotsMeta($cu['id']);

            return $cu;
        });

        f3()->set('rows', $subset);

        f3()->set('breadcrumb_sire', ['title' => '首頁', 'slug' => '/home']);

        parent::wrapper(f3()->get('theme') . '/presses.html', '消息', '/presses');
    }

    /**
     * @param $args
     */
    public static function show($args)
    {
        $fc = new FCHelper('press');

        if (0 === f3()->get('cache.press')) {
            $html = $fc->get('press_' . parent::_lang() . '_' . $args['slug']);

            if (empty($html)) {
                if (!rStaff::_isLogin()) {
                    f3()->error(404);
                } else {
                    $html = self::_render($args['slug']);
                }
            }
        } else {
            $html = $fc->get('press_' . parent::_lang() . '_' . $args['slug'], f3()->get('cache.press'));

            if (empty($html)) {
                $html = self::_render($args['slug']);
                $fc->save('press_' . parent::_lang() . '_' . $args['slug'], $html, f3()->get('cache.press'));
            }
        }

        echo $html;
    }

    /**
     * @param $args
     */
    public static function force($args)
    {
        $fc            = new FCHelper('press');
        $fc->ifHistory = 1;

        $html = self::_render($args['slug']);

        $fc->save('press_' . parent::_lang() . '_' . $args['slug'], $html);
    }

    /**
     * @param $args
     */
    public static function preview($args)
    {
        if (!rStaff::_isLogin()) {
            f3()->error(404);
        }

        echo self::_render($args['slug'], false);
    }

    /**
     * @param $id
     * @param $published
     *
     * @return mixed
     */
    private static function _render($id = 0, $published = true)
    {
        $filter = [];
        if ($published) {
            $filter['status'] = [fPress::ST_PUBLISHED, fPress::ST_CHANGED];
        }

        $cu = fPress::one($id, 'id', $filter, 0);

        if (empty($cu)) {
            f3()->error(404);
        }

        if (3 == $cu['site_id']) {
            f3()->set('uri', f3()->get('us_uri'));
        }

        $tags     = fPress::lotsTag($cu['id']);
        $authors  = fPress::lotsAuthor($cu['id']);
        $relateds = fPress::lotsRelated($cu['id']);
        $metas    = fPress::lotsMeta($cu['id']);

        $seo = [
            'desc'    => $cu['info'],
            'img'     => $cu['pic'],
            'keyword' => $cu['keyword'],
            'header'  => '消息',
        ];

        if (!empty($metas['seo_desc'])) {
            $seo['desc'] = $metas['seo_desc'];
        }

        if (!empty($metas['seo_keyword'])) {
            $seo['keyword']       = $metas['seo_keyword'];
            $metas['seo_keyword'] = explode(',', $metas['seo_keyword']);
        }

        f3()->set('page', $seo);

        f3()->set('cu', $cu);
        // f3()->set('cate', $cate);
        f3()->set('metas', $metas);
        f3()->set('tags', $tags);
        f3()->set('authors', $authors);
        f3()->set('relateds', $relateds);

        f3()->set('next', fPress::load_next($cu, 0, 'online_date'));
        f3()->set('prev', fPress::load_prev($cu, 0, 'online_date'));

        f3()->set('act_link', str_replace('/', '', $cate['slug']));

        f3()->set('breadcrumb_sire', ['title' => '消息', 'slug' => '/presses', 'sire' => ['title' => '首頁', 'slug' => '/home']]);

        $html = self::wrapper(f3()->get('theme') . '/press.html', $cu['title'], '/p/' . $cu['id'] . '/' . $cu['slug'], true);

        return $html;
    }
}
