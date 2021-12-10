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

        f3()->set('cu', $cu);

        f3()->set('bc_ary', [
            ['link' => 'javascript:;', 'title' => $cu['title']],
        ]);

        f3()->set('nav', rMenu::sort_menus(1, 0, '', 0));

        parent::wrapper('dictionary.html', $cu['title'] . ' - 小知識', '/d/' . $cu['id'] . '/' . $cu['slug']);
    }
}
