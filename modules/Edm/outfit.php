<?php

namespace F3CMS;

/**
 * for render page
 */
class oEdm extends Outfit
{
    /**
     * @param $args
     */
    public static function show($args)
    {
        $cu = fEdm::one($args['slug'], 'slug', ['status' => fEdm::ST_ON]);

        if (empty($cu)) {
            f3()->error(404);
        }

        $cu['lang'] = fEdm::load_lang($cu['id'], Module::_lang());

        f3()->set('cu', $cu);

        $subset = fSubject::load_list(0, $cu['id'], 20, true);

        $cates = [];

        foreach ($subset['subset'] as $row) {
            $cates[$row['category_id']][] = $row;
        }

        f3()->set('cates', $cates);

        parent::wrapper(f3()->get('theme') . '/edm.html', $cu['lang']['title'], '/edm/' . $cu['slug']);
    }

    /**
     * @param $args
     */
    public static function test($args)
    {
        $cu = fEdm::one($args['slug'], 'slug', ['status' => fEdm::ST_ON], 0);

        if (empty($cu)) {
            f3()->error(404);
        }

        f3()->set('cu', $cu);
        f3()->set('email', f3()->get('webmaster'));

        $tp   = \Template::instance();
        $html = $tp->render(f3()->get('theme') . '/edm.html');

        $sent = Sender::sendmail($cu['title'] . ' - 測試信', $html, f3()->get('webmaster'));

        echo '<pre>';
        print_r($sent);
    }

    /**
     * @param $args
     */
    public static function sent($args)
    {
        $cu = fEdm::one($args['slug'], 'slug', ['status' => fEdm::ST_ON], 0);

        if (empty($cu)) {
            f3()->error(404);
        }

        parent::wrapper('/sent.html', $cu['title'], '/edm/' . $cu['slug'] . '/sent');
    }

    /**
     * @param $args
     */
    public static function preview($args)
    {
        rStaff::_chkLogin();

        $cu = fEdm::one($args['slug'], 'slug', [], 0);
        if (empty($cu)) {
            f3()->error(404);
        }
        f3()->set('cu', $cu);
        f3()->set('email', f3()->get('webmaster'));

        parent::wrapper(f3()->get('theme') . '/edm.html', $cu['title'], '/edm/' . $cu['slug']);
    }
}
