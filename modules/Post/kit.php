<?php

namespace F3CMS;

/**
 * kit lib
 */
class kPost extends Kit
{
    public static function isAvailable($row = [])
    {
        $row = is_array($row) ? $row : ['id' => (int) $row];
        $postId = (int) ($row['id'] ?? 0);

        if ($postId <= 0) {
            return false;
        }

        return !empty(fPost::one($postId, 'id', ['status' => fPost::ST_ON], 0));
    }

    public static function checkHomepageLang($args)
    {
        if (isset($args['lang']) && !in_array($args['lang'], f3()->get('acceptLang'))) {
            f3()->error(404);
        }
    }
}
