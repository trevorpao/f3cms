<?php

namespace F3CMS;

/**
 * Architecture Purpose
 * - Owner-side Outfit boundary for site-level Post/frontdoor rendering, covering home,
 *   robots, sitemap, RSS, LINE XML, static single-page rendering, maintenance/coming
 *   soon gates, and not-found presentation.
 *
 * Design Intent
 * - Keep site-wide frontend entry rendering close to the Post owner module while
 *   preserving the FORKS split: this Outfit owns route-to-view flow, page-format
 *   selection, and response projection for public frontdoor endpoints; Feed/Option/
 *   Category collaborators keep persisted content truth and list data ownership.
 *
 * Strict Constraints
 * - This Outfit must not absorb persistence ownership, generic site bootstrap logic, or
 *   long-lived content query rules that belong to owner-side collaborators.
 * - Rendering of XML/static/frontdoor pages may be coordinated here, but canonical data
 *   sourcing and module-owned semantics remain outside the Outfit layer.
 *
 * Porting Guidelines
 * - Port as the site-level public/frontdoor rendering boundary.
 * - Preserve the split between route/render/output-format behavior here and content /
 *   option retrieval ownership in Post/Press/Category collaborators.
 */
class oPost extends Outfit
{
    /**
     * Architecture Role
     * - Render the public site homepage through the frontdoor outfit boundary.
     *
     * Strict Constraints
     * - This method is a route-to-view adapter only; homepage content sourcing remains
     *   outside the Outfit itself.
     *
     * Porting Note
     * - Port as the root homepage rendering entrypoint.
     */
    public static function home($args)
    {
        parent::render('index.twig', '首頁', '/');
    }

    /**
     * Architecture Role
     * - Render the site robots.txt payload from the outfit boundary.
     *
     * Strict Constraints
     * - This method outputs a static robots view only; crawler policy truth remains in
     *   the served template/configuration.
     *
     * Porting Note
     * - Port as the robots.txt rendering endpoint.
     */
    public function do_robots($f3, $args)
    {
        echo \Template::instance()->render('robots.html');
    }

    /**
     * Architecture Role
     * - Render the sitemap XML payload from published Press and Category inputs.
     *
     * Strict Constraints
     * - XML response shaping may happen here, but canonical published rows and page
     *   option truth remain owned by the corresponding feeds.
     *
     * Porting Note
     * - Port as the sitemap-generation endpoint for public crawlers.
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
     * Architecture Role
     * - Render the RSS feed output from published Press content.
     *
     * Strict Constraints
     * - Feed formatting may happen here, but article truth and author relations remain
     *   owned by Press collaborators.
     *
     * Porting Note
     * - Port as the public RSS output endpoint.
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
     * Architecture Role
     * - Render the LINE XML export from published Press content when the integration gate
     *   is enabled.
     *
     * Strict Constraints
     * - Integration gating and XML shaping may happen here, but article/tag/author truth
     *   remains with owner-side collaborators.
     *
     * Porting Note
     * - Port as the conditional LINE XML output endpoint.
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
     * Architecture Role
     * - Resolve and render a static Post single page by slug/layout.
     *
     * Strict Constraints
     * - This helper coordinates static-page routing and template selection, but persisted
     *   page truth remains in the Post feed.
     *
     * Porting Note
     * - Port as the internal static-page rendering helper behind named page routes.
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
     * Architecture Role
     * - Render the canonical About static page through the shared static-page helper.
     *
     * Strict Constraints
     * - This method selects a fixed owner-side slug only; page content truth remains in
     *   the Post collaborator.
     *
     * Porting Note
     * - Port as the named About-page route entrypoint.
     */
    public static function about($args)
    {
        $args['slug'] = 'about';
        self::_render($args);
    }

    /**
     * Architecture Role
     * - Render the canonical Privacy static page through the shared static-page helper.
     *
     * Strict Constraints
     * - This method selects a fixed owner-side slug only; page content truth remains in
     *   the Post collaborator.
     *
     * Porting Note
     * - Port as the named Privacy-page route entrypoint.
     */
    public static function privacy($args)
    {
        $args['slug'] = 'privacy';
        self::_render($args);
    }

    /**
     * Architecture Role
     * - Render the canonical Terms static page through the shared static-page helper.
     *
     * Strict Constraints
     * - This method selects a fixed owner-side slug only; page content truth remains in
     *   the Post collaborator.
     *
     * Porting Note
     * - Port as the named Terms-page route entrypoint.
     */
    public static function terms($args)
    {
        $args['slug'] = 'terms';
        self::_render($args);
    }

    /**
     * Architecture Role
     * - Render the maintenance holding page from the site frontdoor boundary.
     *
     * Strict Constraints
     * - This method controls maintenance presentation only; deployment/site-state policy
     *   remains outside the Outfit itself.
     *
     * Porting Note
     * - Port as the maintenance-page rendering endpoint.
     */
    public static function maintenance($args)
    {
        parent::wrapper('../maintenance.html', 'Maintenance, Coming Soon', '/maintenance');
    }

    /**
     * Architecture Role
     * - Gate and render the coming-soon page before the site begin date.
     *
     * Strict Constraints
     * - Time-window gating may happen here, but site launch policy truth remains in the
     *   configured begin-date setting.
     *
     * Porting Note
     * - Port as the launch-window holding-page entrypoint.
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
     * Architecture Role
     * - Render the public 404 error page from the frontdoor outfit boundary.
     *
     * Strict Constraints
     * - Error-page shaping may happen here, but not-found detection semantics remain with
     *   the routing layer.
     *
     * Porting Note
     * - Port as the standard not-found presentation endpoint.
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
