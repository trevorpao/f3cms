<?php
namespace F3CMS;

/**
* for render page
*/
class oTag extends Outfit
{

    public static function show($args)
    {
        if (is_numeric($args['slug'])) {
            $tag = fTag::one($args['slug'], 'id', ['status' => fTag::ST_ON], false);
        } else {
            $tag = fTag::one(parent::_slugify($args['slug']), 'slug', ['status' => fTag::ST_ON], false);
        }

        if (empty($tag)) {
            f3()->error(404);
        }

        $subset = fPress::lotsByTag($tag['id']);

        $subset['subset'] = \__::map($subset['subset'], function ($cu) {
            $cu['tags'] = fPress::lotsTag($cu['id']);
            $cu['authors'] = fPress::lotsAuthor($cu['id']);
            $cu['metas'] = fPress::lotsMeta($cu['id']);

            return $cu;
        });

        $f3->set('rows', $subset);
        $f3->set('cate', $tag);

        parent::wrapper('presses.html', $tag['title'], '/tag/' . $tag['slug']);
    }
}
