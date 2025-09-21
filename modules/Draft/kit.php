<?php

namespace F3CMS;

/**
 * kit lib
 */
class kDraft extends Kit
{
    public static function writing($intent)
    {
        return self::ask(__FUNCTION__, ['intent' => $intent]);
    }

    public static function seohelper($guideline, $pid = 0)
    {
        return self::ask(__FUNCTION__, ['intent' => '產生文章 SEO 相關資料', 'guideline' => $guideline, 'pid' => $pid]);
    }

    public static function guideline($intent)
    {
        return self::ask(__FUNCTION__, ['intent' => $intent]);
    }

    public static function translate($lang, $guideline, $pid = 0)
    {
        return self::ask(__FUNCTION__, ['intent' => '產生文章 ' . $lang . ' 翻譯', 'guideline' => $guideline, 'lang' => $lang, 'pid' => $pid]);
    }

    public static function quicktrans($lang, $guideline)
    {
        return self::ask(__FUNCTION__, ['intent' => '產生文案 ' . $lang . ' 翻譯', 'guideline' => $guideline, 'lang' => $lang]);
    }

    public static function answer($request_id)
    {
        $merchant = f3()->get('mp.merchant');
        $secret   = f3()->get('mp.secret');

        $api = new MPThelper($merchant, $secret);

        $action       = 'answer';
        $request_data = [
            'request_id' => $request_id,
        ];

        $result = $api->call($action, $request_data);

        return $result;
    }

    public static function ask($method, $params)
    {
        $merchant = f3()->get('mp.merchant');
        $secret   = f3()->get('mp.secret');

        $api = new MPThelper($merchant, $secret);

        $action       = 'ask';
        $request_data = [
            'method' => $method,
            'channel_id' => f3()->get('mp.channel_id'),
            'intent' => $params['intent'],
        ];

        if (isset($params['lang'])) {
            $request_data['lang'] = $params['lang'];
        }

        if (isset($params['guideline'])) {
            $request_data['guideline'] = $params['guideline'];
        }

        if (isset($params['pid'])) {
            $request_data['pid'] = $params['pid'];
        }

        $result = $api->call($action, $request_data);

        return $result;
    }

    public static function toMarkdown($text)
    {
        return $text; // use js markdown convert

        $text = trim($text);
        $text = \Markdown::instance()->convert($text);

        return preg_replace('/<br \/>+/', '</p><p>', $text);
    }
}
