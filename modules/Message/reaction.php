<?php

namespace F3CMS;

/**
 * React any request
 */
class rMessage extends Reaction
{
    /**
     * @param $txt
     */
    public static function hello($txt = '')
    {
        return '你好呀，該吃飯啦';
    }

    /**
     * @param $txt
     */
    public static function google($txt = '')
    {
        return 'https://www.google.com/search?ie=UTF-8&q=' . $txt;
    }

    /**
     * @param $txt
     */
    public static function wiki($txt = '')
    {
        return 'https://zh.wikipedia.org/wiki/' . $txt;
    }

    /**
     * @param $txt
     *
     * @return mixed
     */
    public static function saveUri($txt = '')
    {
        return ''; // $txt; // '已儲存網址';
    }

    public static function closeQuickMenu($params)
    {
        return '選單關閉';
    }

    /**
     * @param $txt
     */
    public static function start($txt = '')
    {
        return LineMsgBuilder::quickMenu();
    }

    /**
     * @param $txt
     */
    public static function find($txt = '')
    {
        return LineMsgBuilder::findMenu();
    }

    /**
     * @param $txt
     */
    public static function humanResource($txt = '')
    {
        return LineMsgBuilder::hrMenu();
    }
}
