<?php

namespace F3CMS;

/**
 * for render page
 */
class oPortal extends Outfit
{
    /**
     * @param $args
     */
    public static function default($args)
    {
        $portal = f3()->get('bot.portal');

        if (!empty(f3()->get('GET.path'))) {
            $layout = f3()->get('GET.path') . '.twig';
            $title  = (isset($portal[f3()->get('GET.path')])) ? $portal[f3()->get('GET.path')] : ucfirst(str_replace('/', ' ', f3()->get('GET.path')));
        } else {
            $layout = 'portal/home.twig';
            $title  = '載入中';
        }

        if ('asset/view.twig' == $layout) {
            echo '<h1>Coming Soon';
        } elseif ('asset/refresh.twig' == $layout) {
            echo '<h1>Coming Soon';
        } else {
            parent::render($layout, $title, '/portal/default');
        }
    }
}
