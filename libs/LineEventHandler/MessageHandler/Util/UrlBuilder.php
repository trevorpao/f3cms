<?php

namespace F3CMS\LineEventHandler\MessageHandler\Util;

class UrlBuilder
{
    public static function buildUrl($req, array $paths)
    {
        // NOTE: You should configure $baseUri according to your environment
        // Perhaps, it is prefer to use $_SERVER['HTTP_HOST'], $_SERVER['HTTP_X_FORWARDED_HOST'] or etc
        $baseUri = f3()->get('uri');
        foreach ($paths as $path) {
            $baseUri .= '/' . urlencode($path);
        }

        return $baseUri;
    }
}
