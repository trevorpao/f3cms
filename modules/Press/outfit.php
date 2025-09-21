<?php

namespace F3CMS;

/**
 * for render page
 */
class oPress extends Outfit
{
    /**
     * @param $args
     *
     * @return mixed
     */
    public static function list($args)
    {
        $langTxts = [
            'tw' => '文章清單',
            'en' => 'Articles',
            'ja' => '記事一覧',
            'ko' => '기사 목록',
        ];

        $title = $langTxts[Module::_lang()]; // '文章清單';

        _dzv('cu', ['title' => $title, 'id' => 0]);
        _dzv('srcType', 'tag');

        self::render('press/list.twig', $title, '/presses');
    }

    /**
     * @param $args
     */
    public static function search($args)
    {
        $req = self::_getReq();
        _dzv('searchStr', $req['q']);

        parent::render('press/search_results.twig', '搜尋結果：' . $req['q'], '/search?q=' . $req['q']);
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
                if (!kStaff::_isLogin()) {
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
        self::_showVariables();
    }

    /**
     * @param $args
     */
    public static function buildPage($args)
    {
        if (!kStaff::_isLogin()) {
            f3()->error(404);
        }

        $fc            = new FCHelper('press');
        $fc->ifHistory = 0;

        foreach (f3()->get('acceptLang') as $n) {
            parent::_lang(['lang' => $n]);

            $html = self::_render($args['slug'], false, true);
            $fc->save('press_' . $n . '_' . $args['slug'], $html); // renew cache
        }
    }

    /**
     * @param $args
     */
    public static function preview($args)
    {
        if (!kStaff::_isLogin()) {
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
    private static function _render($id = 0, $published = true, $history = false)
    {
        $filter = [];
        if ($published) {
            $filter['status'] = [fPress::ST_PUBLISHED, fPress::ST_CHANGED];
        }

        if (is_numeric($id)) {
            $cu = fPress::one($id, 'id', $filter, 0);
        } else {
            $cu = fPress::one(parent::_slugify($id), 'slug', $filter, 0);
        }

        if (empty($cu)) {
            f3()->error(404);
        }

        $cate     = fCategory::one($cu['cate_id'], 'id', ['status' => fCategory::ST_ON], 0);
        $tags     = fPress::lotsTag($cu['id'], true);
        $authors  = fPress::lotsAuthor($cu['id']);
        $relateds = fPress::lotsRelated($cu['id']);
        $terms    = fPress::lotsTerm($cu['id']);
        $metas    = fPress::lotsMeta($cu['id']);

        $seo = [
            'desc'    => $cu['info'],
            'img'     => f3()->get('uri') . ((empty($cu['banner'])) ? $cu['cover'] : $cu['banner']),
            'keyword' => '',
            'header'  => '文章',
        ];

        if (!empty($tags)) {
            $seo['keyword'] .= implode(',', \__::pluck($tags, 'title'));
        }

        $recommends = [];
        if (safeCount($tags) > 0) {
            $recommends = fPress::relatedTag($cu['id'], \__::pluck($tags, 'id'), 5);
        }

        if (safeCount($relateds) > 0) {
            foreach ($relateds as $k => $row) {
                $relateds[$k]['tags']    = fPress::lotsTag($row['id']);
                $relateds[$k]['authors'] = fPress::lotsAuthor($row['id']);
            }
        }

        $cu['content'] = parent::convertUrlsToLinks($cu['content']);

        $subset = fMedia::limitRows([
            'm.status'    => fMedia::ST_ON,
            'm.target'    => 'Press',
            'm.parent_id' => $cu['id'],
        ], 0, 30);

        _dzv('medias', $subset['subset']);

        _dzv('cu', $cu);
        _dzv('cate', $cate);
        _dzv('metas', $metas);
        _dzv('tags', $tags);
        _dzv('authors', $authors);
        _dzv('relateds', $relateds);
        _dzv('recommends', $recommends);
        _dzv('terms', $terms);

        _dzv('next', fPress::neighbor($cu, 'next'));
        _dzv('prev', fPress::neighbor($cu, 'prev'));

        f3()->set('page', $seo);

        $link = '/p/' . $cu['id'] . '/' . $cu['slug'];

        _dzv('ldjson', self::ldjson(
            $cu['title'],
            $seo['desc'],
            f3()->get('uri') . $link,
            $seo['img'],
            $cu['last_ts'],
            $cu['online_date']
        ));

        kPress::fartherData($cu['id']);

        f3()->set('breadcrumb_sire', ['title' => '文章', 'slug' => '/presses', 'sire' => ['title' => '首頁', 'slug' => '/home']]);

        $html = self::render('press/show.twig', $cu['title'], $link, true);

        if ($history) {
            kHistory::save('Press', $cu['id'], $cu['title'], $html);
        }

        return $html;
    }

    public static function ldjson($title, $desc, $link, $img, $last_ts, $online_date)
    {
        switch (parent::_lang()) {
            case 'en':
                $lang = 'en-US';
                break;
            case 'ja':
                $lang = 'ja-JP';
                break;
            case 'ko':
                $lang = 'ko-KR';
                break;
            default:
                $lang = 'zh-TW';
                break;
        }

        // TODO: use @graph, add ImageObject, BreadcrumbList, Person
        return '<script type="application/ld+json">' . jsonEncode([
            '@context'         => 'https://schema.org',
            '@type'            => 'NewsArticle',
            'headline'         => parent::safeRaw($title),
            'description'      => parent::safeRaw($desc),
            'contentUrl'       => $link,
            'image'            => [$img],
            'dateModified'     => date('c', strtotime($last_ts)),
            'datePublished'    => date('c', strtotime($online_date)),
            'inLanguage'       => $lang,
        ]) . '</script>';
    }
}
