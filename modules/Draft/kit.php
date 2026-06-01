<?php

namespace F3CMS;

/**
 * Architecture Purpose
 * - Owner-side Kit boundary for Draft-to-MPT request shaping, covering AI writing, SEO,
 *   guideline, translation, and answer retrieval flows.
 *
 * Design Intent
 * - Keep external AI helper intent mapping and payload shaping close to the Draft owner
 *   module while preserving the FORKS split: this Kit owns reusable request/response
 *   bridge helpers, while Draft feeds/reactions keep content truth and workflow
 *   orchestration.
 *
 * Strict Constraints
 * - This Kit must not expand into a generic AI service layer for unrelated modules.
 * - Intent mapping and MPT transport coordination may happen here, but draft lifecycle
 *   ownership remains with Draft collaborators.
 *
 * Porting Guidelines
 * - Port as the Draft module's owner-side AI bridge/helper layer.
 * - Preserve the split between intent/payload shaping here and content lifecycle or
 *   request orchestration in Draft owner modules.
 */
class kDraft extends Kit
{
    /**
     * Architecture Role
     * - Issue an AI writing request for draft content generation.
     *
     * Strict Constraints
     * - Intent shaping may happen here, but draft content truth remains outside this Kit.
     *
     * Porting Note
     * - Port as the Draft writing-intent helper over the shared ask bridge.
     */
    public static function writing($intent)
    {
        return self::ask(__FUNCTION__, ['intent' => $intent]);
    }

    /**
     * Architecture Role
     * - Issue an AI SEO-helper request for a draft or press target.
     *
     * Strict Constraints
     * - SEO request shaping may happen here, but content and SEO persistence truth remain
     *   with Draft/Press collaborators.
     *
     * Porting Note
     * - Port as the Draft SEO-assist intent helper.
     */
    public static function seohelper($guideline, $pid = 0)
    {
        return self::ask(__FUNCTION__, ['intent' => '產生文章 SEO 相關資料', 'guideline' => $guideline, 'pid' => $pid]);
    }

    /**
     * Architecture Role
     * - Issue an AI guideline request for draft content planning.
     *
     * Strict Constraints
     * - Guideline intent shaping may happen here, but draft planning truth remains outside
     *   this Kit.
     *
     * Porting Note
     * - Port as the Draft guideline-intent helper.
     */
    public static function guideline($intent)
    {
        return self::ask(__FUNCTION__, ['intent' => $intent]);
    }

    /**
     * Architecture Role
     * - Issue an AI translation request for article-level draft content.
     *
     * Strict Constraints
     * - Translation request shaping may happen here, but translated content truth remains
     *   with Draft/Press collaborators.
     *
     * Porting Note
     * - Port as the Draft article-translation intent helper.
     */
    public static function translate($lang, $guideline, $pid = 0)
    {
        return self::ask(__FUNCTION__, ['intent' => '產生文章 ' . $lang . ' 翻譯', 'guideline' => $guideline, 'lang' => $lang, 'pid' => $pid]);
    }

    /**
     * Architecture Role
     * - Issue an AI quick-translation request for shorter copy/content.
     *
     * Strict Constraints
     * - Quick-translation intent shaping may happen here, but content truth remains
     *   outside this Kit.
     *
     * Porting Note
     * - Port as the Draft quick-translation intent helper.
     */
    public static function quicktrans($lang, $guideline)
    {
        return self::ask(__FUNCTION__, ['intent' => '產生文案 ' . $lang . ' 翻譯', 'guideline' => $guideline, 'lang' => $lang]);
    }

    /**
     * Architecture Role
     * - Retrieve an answer/result payload from the external MPT request channel.
     *
     * Strict Constraints
     * - Transport retrieval may happen here, but request lifecycle ownership remains with
     *   Draft workflows and external MPT infrastructure.
     *
     * Porting Note
     * - Port as the Draft MPT answer-fetch helper.
     */
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

    /**
     * Architecture Role
     * - Send a shaped Draft intent payload to the external MPT ask endpoint.
     *
     * Strict Constraints
     * - Request shaping may happen here, but Draft content lifecycle ownership must not
     *   collapse into this transport helper.
     *
     * Porting Note
     * - Port as the shared Draft-to-MPT ask bridge.
     */
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

    /**
     * Architecture Role
     * - Normalize Draft text into markdown-ready output for presentation helpers.
     *
     * Strict Constraints
     * - This helper is limited to Draft-local output shaping and must not become a generic
     *   markdown conversion surface.
     *
     * Porting Note
     * - Port as the Draft-local markdown/output normalization helper.
     */
    public static function toMarkdown($text)
    {
        return $text; // use js markdown convert

        $text = trim($text);
        $text = \Markdown::instance()->convert($text);

        return preg_replace('/<br \/>+/', '</p><p>', $text);
    }
}
