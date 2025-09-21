<?php

namespace F3CMS;

/**
 * for render page
 */
class oAuthor extends Outfit
{
    public static function show($args)
    {
        $author = fAuthor::one(parent::_slugify($args['slug']), 'slug', ['status' => fAuthor::ST_ON], false);

        if (empty($author)) {
            f3()->error(404);
        }

        $author['title'] = $author['title'] . '的所有文章';

        $seo = [
            'desc' => $author['summary'],
            'img'  => $author['cover'],
        ];

        _dzv('page', $seo);
        _dzv('cu', $author);
        _dzv('srcType', 'author');

        self::render('press/list.twig', $author['title'], '/author/' . $author['slug']);
    }
}
