<?php

namespace F3CMS;

/**
 * for render page
 */
class oProject extends Outfit
{
    public function list($args)
    {
        $req = parent::_getReq();

        $req['page'] = ($req['page']) ? ($req['page'] - 1) : 0;

        $subset = fProject::limitRows('status:' . fProject::ST_ON, $req['page']);

        f3()->set('rows', $subset);

        f3()->set('breadcrumb_sire', ['title' => '首頁', 'slug' => '/home']);

        parent::wrapper(f3()->get('theme') . '/projects.html', '專案', '/projects');
    }

    /**
     * @param $args
     */
    public static function show($args)
    {
        $fc = new FCHelper('project');

        if (0 === f3()->get('cache.project')) {
            $html = $fc->get('project_' . parent::_lang() . '_' . $args['slug']);

            if (empty($html)) {
                if (!rStaff::_isLogin()) {
                    f3()->error(404);
                } else {
                    $html = self::_render($args['slug']);
                }
            }
        } else {
            $html = $fc->get('project_' . parent::_lang() . '_' . $args['slug'], f3()->get('cache.project'));

            if (empty($html)) {
                $html = self::_render($args['slug']);
                $fc->save('project_' . parent::_lang() . '_' . $args['slug'], $html, f3()->get('cache.project'));
            }
        }

        echo $html;
    }

    /**
     * @param $args
     */
    public static function force($args)
    {
        $fc            = new FCHelper('project');
        $fc->ifHistory = 1;

        $html = self::_render($args['slug']);

        $fc->save('project_' . parent::_lang() . '_' . $args['slug'], $html);
    }

    /**
     * @param $id
     *
     * @return mixed
     */
    private static function _render($id = 0)
    {
        $cu = fProject::one($id, 'id', [
            'status' => fProject::ST_ON,
        ], false);

        if (empty($cu)) {
            f3()->error(404);
        }

        if (3 == $cu['site_id']) {
            f3()->set('uri', f3()->get('us_uri'));
        }

        // $tags = fProject::lotsTag($cu['id']);
        // $authors = fProject::lotsAuthor($cu['id']);
        // $metas = fProject::lotsMeta($cu['id']);
        $relateds = fProject::lotsRelated($cu['id']);

        $pics = fMedia::limitRows('m.target:Project,m.status:' . fMedia::ST_ON . ',m.parent_id:' . $cu['id']);

        $seo = [
            'desc'    => $cu['info'],
            'img'     => $cu['pic'],
            'keyword' => $cu['keyword'],
            'header'  => '專案',
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
        // f3()->set('metas', $metas);
        // f3()->set('tags', $tags);
        // f3()->set('authors', $authors);
        f3()->set('relateds', $relateds);
        f3()->set('pics', $pics);

        f3()->set('next', fProject::load_next($cu));
        f3()->set('prev', fProject::load_prev($cu));

        f3()->set('act_link', str_replace('/', '', $cate['slug']));

        f3()->set('breadcrumb_sire', ['title' => '專案', 'slug' => '/projects', 'sire' => ['title' => '首頁', 'slug' => '/home']]);

        $html = self::wrapper(f3()->get('theme') . '/project.html', $cu['title'], '/c/' . $cu['id'] . '/' . $cu['slug'], true);

        return $html;
    }
}
