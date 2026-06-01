<?php

namespace F3CMS;

/**
 * Architecture Purpose
 * - Owner-side Outfit boundary for Press frontend rendering, covering article list/search,
 *   published article delivery, preview/build-cache flows, and article-page shaping
 *   around the Press module.
 *
 * Design Intent
 * - Keep article page rendering and public presentation orchestration close to the Press
 *   owner module while preserving the FORKS split: this Outfit owns route-to-view flow,
 *   cache coordination, template payload shaping, and SEO/ld+json projection; Feed/Kit
 *   collaborators keep content truth, relations, and farther business data assembly.
 *
 * Strict Constraints
 * - This Outfit must not absorb Press persistence ownership, workflow mutation, or
 *   generic page-helper responsibilities.
 * - Preview/build paths may be coordinated here, but content truth, relation reads, and
 *   owner-side data policies remain in Press/Category/Media/History collaborators.
 *
 * Porting Guidelines
 * - Port as the frontend article rendering boundary for Press pages.
 * - Preserve the split between route/render/cache/SEO shaping here and content retrieval
 *   or workflow ownership in the Press owner-side collaborators.
 */
class oPress extends Outfit
{
    /**
     * Architecture Role
     * - Render the public Press listing page with language-aware page-title projection.
     *
     * Strict Constraints
     * - This method shapes listing-page presentation only; list data ownership remains in
     *   Press collaborators and downstream page loaders.
     *
     * Porting Note
     * - Port as the public article-list route entrypoint.
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
     * Architecture Role
     * - Render the public Press search result page from request query input.
     *
     * Strict Constraints
     * - Search-string projection may happen here, but actual search result ownership
     *   remains outside this Outfit boundary.
     *
     * Porting Note
     * - Port as the frontend search-results rendering entrypoint for article search.
     */
    public static function search($args)
    {
        $req = self::_getReq();
        _dzv('searchStr', $req['q']);

        parent::render('press/search_results.twig', '搜尋結果：' . $req['q'], '/search?q=' . $req['q']);
    }

    /**
     * Architecture Role
     * - Deliver a published Press page through the cached frontend article route.
     *
     * Strict Constraints
     * - Cache lookup and preview fallback may be coordinated here, but article truth and
     *   permission semantics remain in Press/Staff collaborators.
     *
     * Porting Note
     * - Port as the canonical public article-display endpoint with cache integration.
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
     * Architecture Role
     * - Rebuild per-language cached Press pages from the backend/operator side.
     *
     * Strict Constraints
     * - This method coordinates cache rebuild only; article content truth and cache
     *   storage semantics remain outside the Outfit itself.
     *
     * Porting Note
     * - Port as an operator-only article cache refresh entrypoint.
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
     * Architecture Role
     * - Render a non-published Press preview for authenticated staff.
     *
     * Strict Constraints
     * - Preview gating may happen here, but unpublished content truth and staff policy
     *   remain owned by Press/Staff collaborators.
     *
     * Porting Note
     * - Port as the authenticated article-preview entrypoint.
     */
    public static function preview($args)
    {
        if (!kStaff::_isLogin()) {
            f3()->error(404);
        }

        echo self::_render($args['slug'], false);
    }

    /**
     * Architecture Role
     * - Assemble the full article-page view model and renderable HTML for a Press page.
     *
     * Strict Constraints
     * - This helper performs page-shaping and render assembly, but it must not become the
     *   owner of article persistence, relation truth, or workflow state changes.
     *
     * Porting Note
     * - Port as the internal article-page rendering assembler behind public/preview/cache
     *   routes.
     */
    private static function _render($id = 0, $published = true, $history = false)
    {
        if (is_numeric($id)) {
            $cu = $published ? fPress::onePublished($id, 'id', 0) : fPress::one($id, 'id', [], 0);
        } else {
            $cu = $published ? fPress::onePublished(parent::_slugify($id), 'slug', 0) : fPress::one(parent::_slugify($id), 'slug', [], 0);
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

    /**
     * Architecture Role
     * - Project article metadata into schema.org NewsArticle ld+json for frontend pages.
     *
     * Strict Constraints
     * - Structured-data shaping may happen here, but article metadata truth remains owned
     *   by the Press render inputs.
     *
     * Porting Note
     * - Port as a frontend structured-data projection helper for Press pages.
     */
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
