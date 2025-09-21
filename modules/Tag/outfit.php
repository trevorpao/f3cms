<?php

namespace F3CMS;

/**
 * for render page
 */
class oTag extends Outfit
{
    /**
     * @param $args
     *
     * @return mixed
     */
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

        $tag['title'] = (empty($tag['title'])) ? '' : $tag['title'];

        _dzv('cu', $tag);
        _dzv('srcType', 'tag');

        self::render('press/list.twig', $tag['title'], '/tag/' . $tag['slug']);
    }
}
