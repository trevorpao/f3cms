<?php

namespace F3CMS;

/**
 * for render page
 */
class oCategory extends Outfit
{
    /**
     * @param $args
     *
     * @return mixed
     */
    public static function show($args)
    {
        if (is_numeric($args['slug']) && 'chunyichang' != f3()->get('theme')) {
            $cate = fCategory::one($args['slug'], 'id', ['status' => fCategory::ST_ON], false);
        } else {
            $cate = fCategory::one(parent::_slugify($args['slug']), 'slug', ['status' => fCategory::ST_ON], false);
        }

        if (empty($cate)) {
            f3()->error(404);
        }

        $cate['tags'] = fCategory::lotsTag($cate['id'], true);

        _dzv('cu', $cate);
        _dzv('srcType', 'category');

        f3()->set('page', [
            'desc'    => $cate['info'],
            'img'     => $cate['cover'],
            'keyword' => (!empty($cate['tags'])) ? \__::plurk($cate['tags'], 'title') : '',
        ]);

        self::render('press/category.twig', $cate['title'], '/category/' . $cate['slug']);
    }
}
