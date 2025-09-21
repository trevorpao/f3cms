<?php

namespace F3CMS;

/**
 * for render page
 */
class oDictionary extends Outfit
{
    /**
     * @param $args
     */
    public static function show($args)
    {
        $cu = fDictionary::one($args['slug'], 'id', ['status' => fDictionary::ST_ON]);

        if (empty($cu)) {
            f3()->error(404);
        }

        _dzv('cu', $cu);

        f3()->set('breadcrumb_sire', ['title' => '首頁', 'slug' => '/home']);

        parent::render('post.twig', $cu['title'] . ' - 小知識', '/d/' . $cu['id'] . '/' . $cu['slug']);
    }
}
