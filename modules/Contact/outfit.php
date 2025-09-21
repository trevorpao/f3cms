<?php

namespace F3CMS;

use Gregwar\Captcha\CaptchaBuilder;
use Gregwar\Captcha\PhraseBuilder;

/**
 * for render page
 */
class oContact extends Outfit
{
    /**
     * @param $args
     */
    public static function contact($args)
    {
        $row = fPost::one('contact', 'slug', ['status' => fPost::ST_ON], false);

        if (empty($row)) {
            f3()->error(404);
        }

        _dzv('cu', $row);
        f3()->set('breadcrumb_sire', ['title' => '首頁', 'slug' => '/home']);

        parent::render(parent::_lang() . '/contact.twig', $row['title'], '/contact');
    }

    public function do_captcha($f3, $args)
    {
        header('Content-type: image/png');
        if ($f3->exists('SESSION.captchaExpired') && $f3->get('SESSION.captchaExpired') > time()) {
            $captcha = new CaptchaBuilder('Wait 10s');
            $captcha->setImageType('png')->setBackgroundAlpha(127)->setIgnoreAllEffects(true)->setBackgroundColor(255, 255, 255);
        } else {
            $f3->set('SESSION.captchaExpired', time() + 10);
            $phraseBuilder = new PhraseBuilder(6, '3456789ACDFGHJKLMNPQRSTWXY');
            $captcha       = new CaptchaBuilder(null, $phraseBuilder);
            $captcha->setImageType('png');
            $f3->set('SESSION.captcha', strtolower($captcha->getPhrase()));
        }

        $captcha->build();
        $captcha->output();
    }
}
