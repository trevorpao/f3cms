<?php
namespace F3CMS;

/**
* for render page
*/
class oPress extends Outfit
{

    protected static function do_list($args)
    {
        $req = parent::_getReq();

        $req['page'] = ($req['page']) ? ($req['page'] -1) : 0;

        $subset = fPress::limitRows('status:'. fPress::ST_PUBLISHED, $req['page']);

        $subset['subset'] = \__::map($subset['subset'], function ($cu) {
            $cu['tags'] = fPress::lotsTag($cu['id']);
            $cu['authors'] = fPress::lotsAuthor($cu['id']);
            $cu['metas'] = fPress::lotsMeta($cu['id']);

            return $cu;
        });

        $f3->set('pagebrowser', parent::paginate($subset['total'], $subset['limit'], '/presses/' . $args['slug']));

        parent::wrapper('presses.html', '最新文章', '/presses');
    }

    protected static function do_show($args)
    {
        $fc = new FCHelper('press');

        if (f3()->get('cache.press') === 0) {
            $html = $fc->get('press_'. $args['slug']);

            if (empty($html)) {
                if (!rStaff::_isLogin()) {
                    f3()->error(404);
                }
                else {
                    $html = self::render($args['slug']);
                }
            }
        }
        else {
            $html = $fc->get('press_'. $args['slug'], f3()->get('cache.press'));

            if (empty($html)) {
                $html = self::render($args['slug']);
                $fc->save('press_'. $args['slug'], $html, f3()->get('cache.press'));
            }
        }

        echo $html;
    }

    protected static function do_force($args)
    {
        $fc = new FCHelper('press');
        $fc->ifHistory = 1;

        $html = self::render($args['slug']);

        $fc->save('press_'. $args['slug'], $html);
    }

    public static function render($id = 0)
    {

        $cu = fPress::one($id, 'id', [
            'status' => fPress::ST_PUBLISHED
        ], false);

        if (empty($cu)) {
            f3()->error(404);
        }

        if ($cu['site_id'] == 3) {
            f3()->set('uri', f3()->get('us_uri'));
        }

        $tags = fPress::lotsTag($cu['id']);
        $authors = fPress::lotsAuthor($cu['id']);
        $relateds = fPress::lotsRelated($cu['id']);
        $metas = fPress::lotsMeta($cu['id']);

        $seo = array(
            'desc' => $cu['info'],
            'img' => $cu['pic'],
            'keyword' => $cu['keyword'],
        );

        if (!empty($metas['seo_desc'])) {
            $seo['desc'] = $metas['seo_desc'];
        }

        if (!empty($metas['seo_keyword'])) {
            $seo['keyword'] = $metas['seo_keyword'];
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

        $html = self::wrapper('press.html', $cu['title'], '/p/'. $cu['id'] . '/' . $cu['slug'], true);

        return $html;
    }
}
