<?php

namespace F3CMS;

/**
 * kit lib
 */
class kPost extends Kit
{
    public static function checkHomepageLang($args)
    {
        if (isset($args['lang']) && !in_array($args['lang'], f3()->get('acceptLang'))) {
            f3()->error(404);
        }
    }
}
