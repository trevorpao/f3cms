<?php
namespace F3CMS;

/**
 * for render page
 */
class oPost extends Outfit
{
    /**
     * @param $args
     */
    public static function home($args)
    {
        $contact = fPost::one('contact', 'slug', ['status' => fPost::ST_ON], 0);

        f3()->set('contact', $contact);

        parent::wrapper(f3()->get('theme') . '/home.html', '首頁', '/');
    }

    /**
     * @param $args
     */
    public static function sitemap($args)
    {
        $subset = fPress::limitRows('status:' . fPress::ST_PUBLISHED, 0, 1000);

        f3()->set('rows', $subset);

        f3()->set('page', fOption::load('page'));

        $tp = \Template::instance();
        $tp->filter('date', '\F3CMS\Outfit::date');

        echo $tp->render('sitemap.xml', 'application/xml');
    }

    /**
     * @param $args
     */
    public static function rss($args)
    {
        // TODO: mutil lang

        $subset = fPress::limitRows('status:' . fPress::ST_PUBLISHED, 0, 100);

        f3()->set('rows', $subset);

        f3()->set('page', fOption::load('page'));

        f3()->set('contact_mail', fOption::one('contact_mail', 'name'));

        $tp = \Template::instance();
        $tp->filter('date', '\F3CMS\Outfit::date');

        echo $tp->render('rss.xml', 'application/xml');
    }

    /**
     * @param $f3
     * @param $args
     */
    public function do_lineXml($f3, $args)
    {
        $subset = fPress::limitRows('status:' . fPress::ST_PUBLISHED, 0, 100);

        foreach ($subset['subset'] as &$row) {
            $row['rel_tag'] = json_decode($row['rel_tag'], true);

            if (!empty($row['rel_tag'])) {
                $ary = [];

                foreach ($row['rel_tag'] as $n) {
                    $ary[] = $n['title'];
                }

                $row['keyword'] = implode(',', $ary) . ',' . $row['keyword'];
            }

            $row['online_ts'] = strtotime($row['online_date']) . '000';
            $row['online_ts'] = strtotime($row['last_ts']) . '000';
        }

        f3()->set('rows', $subset);

        f3()->set('page', fOption::load('page'));

        f3()->set('time', time() . '000');

        f3()->set('contact_mail', fOption::get('contact_mail'));

        $tp = \Template::instance();
        $tp->filter('date', '\F3CMS\Outfit::date');

        echo self::utf8Xml($tp->render('lineXml.xml', 'application/xml'));
    }

    /**
     * @param $args
     */
    public static function show($args)
    {
        $row = fPost::one($args['slug'], 'slug', ['status' => fPost::ST_ON], 0);

        if (empty($row)) {
            f3()->error(404);
        }

        f3()->set('cu', $row);

        f3()->set('breadcrumb_sire', ['title' => '首頁', 'slug' => '/home']);

        parent::wrapper(f3()->get('theme') . '/post.html', $row['title'], '/post/' . $row['slug']);
    }

    /**
     * @param $args
     */
    public static function about($args)
    {
        $args['slug'] = 'about';
        self::show($args);
    }

    /**
     * @param $args
     */
    public static function privacy($args)
    {
        $args['slug'] = 'privacy';
        self::show($args);
    }

    /**
     * @param $args
     */
    public static function comingsoon($args)
    {
        $ts = strtotime(f3()->get('siteBeginDate'));
        $now = time();
        if ($now < $ts) {
            parent::wrapper('comingsoon.html', 'Coming Soon', '/comingsoon');
        } else {
            f3()->reroute('/home');
        }
    }

    /**
     * @param $args
     */
    public static function notfound($args)
    {
        f3()->set('ERROR', [
            'code' => '404',
            'text' => 'Not Found'
        ]);

        parent::wrapper(f3()->get('theme') . '/error.html', 'Not Found', '/404');
    }

    /**
     * @param $args
     */
    public static function word($args)
    {
        $phpWord = WHelper::init();
        $page = $phpWord->newPage();

        $cert = $phpWord->newCert('三思資訊');
        $page->addImage($cert, [
            'wrappingStyle' => 'behind',
            'width'         => 637,
            'height'        => 923,
            'marginTop'     => -1,
            'marginLeft'    => -1
        ]);

        // \PhpOffice\PhpWord\Shared\Html::addHtml($section, '<table style="width:100%"><tr><td><img src="https://www.gettyimages.ca/gi-resources/images/Homepage/Hero/UK/CMS_Creative_164657191_Kingfisher.jpg" width="200"/></td><td>text</td></tr></table>');

        // $header = $page->addHeader();
        // $header->addWatermark(f3()->get('ROOT') . f3()->get('BASE') . '/upload/img/bg.png', array('marginTop' => 200, 'marginLeft' => 55));

        // $fontStyleName = 'oneUserDefinedStyle';
        // $phpWord->addFontStyle(
        //     $fontStyleName,
        //     array('name' => 'Tahoma', 'size' => 10, 'color' => '1B2232', 'bold' => true)
        // );

        // $textbox = $page->addTextBox(
        //     array(
        //         'alignment'   => \PhpOffice\PhpWord\SimpleType\Jc::CENTER,
        //         'width'       => 400,
        //         'height'      => 150,
        //         'borderSize'  => 1,
        //         // 'borderColor' => '#FF0000',
        //         'background-color' => '#FF0000'
        //     )
        // );
        // $textbox->addText('Text box content in section.');
        // $textbox->addText('Another line.');

        // $page->addText(
        //     '"Learn from yesterday, live for today, hope for tomorrow. '
        //         . 'The important thing is not to stop questioning." '
        //         . '(Albert Einstein)',
        //     $fontStyleName
        // );

        // $page->addText(
        //     '"Great achievement is usually born of great sacrifice, '
        //         . 'and is never the result of selfishness." '
        //         . '(Napoleon Hill)',
        //     $fontStyleName
        // );

        // $page->addTextBreak(2);

        // $page->addText(
        //     '"The greatest accomplishment is not in never falling, '
        //         . 'but in rising again after you fall." '
        //         . '(Vince Lombardi)',
        //     $fontStyleName
        // );

        $phpWord->done('certification_' . date('YmdHis') . '.odt');
        // $phpWord->done('certification_' . date('YmdHis').'.docx', 'Word2007');
    }
}
