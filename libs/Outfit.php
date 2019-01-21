<?php
namespace F3CMS;

class Outfit extends Module
{
    public function __call($method, $args)
    {
        $time_start = microtime(true);
        $that = get_called_class();

        $that::_beforeRoute($args[1]);

        $response = $that::_middleware($args[1], $method);

        $that::_afterRoute($args[1]);

        $time_end = microtime(true);

        $spent = $time_end - $time_start;

        echo '<!-- spent: '. $spent .' -->';

        return $response;
    }

    public static function _beforeRoute($args)
    {
        if (f3()->exists('siteBeginDate')) {
            $ts = strtotime(f3()->get('siteBeginDate'));
            $now = time();
            if ($slug != '/comingsoon' && $now < $ts) {
                f3()->reroute(f3()->get('uri') . '/comingsoon');
            }
        }

        parent::_mobile_user_agent();
        Module::_lang($args);
        f3()->set('lang', Module::_lang());

        f3()->set('SESSION.csrf', f3()->get('sess')->csrf());
    }

    public static function _afterRoute($args)
    {

    }

    public static function _middleware($args, string $next)
    {
        $class = get_called_class();
        $method = str_replace('do_', '', $next);

        if (!method_exists($class, $method)) {
            throw new \Exception('(1004) '. $class .'::'. $next .' not found');
        }

        $args = parent::_escape($args, false);

        return call_user_func_array(array($class, $method), [$args]);
    }

    /**
     * set excel header
     * @param string $filename - file name to user
     */
    static function _setXls($filename)
    {
        header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // Date in the past
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT"); // always modified
        header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
        header("Pragma: no-cache"); // HTTP/1.0
        header("Content-Disposition:filename=". $filename .".xls");
        header("Content-type:application/vnd.ms-excel; charset=UTF-8");
        header("Content-Language:content=zh-tw");
        echo "<META HTTP-EQUIV=\"Content-Type\" CONTENT=\"text/html; CHARSET=UTF-8\">";
    }

    static function utf8Xml($string)
    {
        return preg_replace('/[^\x{0009}\x{000a}\x{000d}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', ' ', $string);
    }

    /**
     * render thumbnail file name
     * @param  string $path - old path
     * @param  string $type - thumb type
     * @return string       - new path
     */
    static function thumbnail($path, $type)
    {

        list($w, $h) = f3()->get($type . '_thn');

        $tmp = explode('.', $path);

        $newpath = $tmp[0] .'_' . $w . 'x' . $h . '.'. $tmp[1];

        return $newpath;
    }

    /**
     * render pathByDevice file name
     * @param  string $path - old path
     * @param  string $type - thumb type
     * @return string       - new path
     */
    static function pathByDevice($path, $type)
    {
        $device = parent::_mobile_user_agent();

        if ($device != 'unknown') {
            list($w, $h) = f3()->get($type . '_thn');

            $tmp = explode('.', $path);

            $path = $tmp[0] .'_' . $w . 'x' . $h . '.'. $tmp[1];
        }

        return $path;
    }

    static function paginate ($total, $limit = 10, $link = "", $current = -1, $range = 5)
    {
        $pages = new Pagination($total, $limit);
        $pages->setTemplate(f3()->get('theme') .'/parter/pagination.html');
        if (!empty($link)) {
            $pages->setLinkPath($link);
        }
        if ($current!=-1) {
            $pages->setCurrent($current);
        }
        $pages->setRouteKeyPrefix('?page=');
        $pages->setRange($range);
        return $pages->serve();
    }

    static function handleTag ($tags)
    {
        $ary = array();
        if (!empty($tags)) {

            $items = json_decode($tags);
            foreach ($items as $item) {
                $ary[] = $item->title;
            }
            f3()->set('rel_tag', $ary);
            f3()->set('pageKeyword', implode(',', $ary));
        }
        return $ary;
    }

    static function date ($val, $format)
    {
        return date($format, strtotime($val));
    }

    static function nl2br ($val)
    {
        return nl2br($val);
    }

    static function str2tbl($val)
    {
        $ary = explode("\n", $val);
        $str = '';
        foreach ($ary as $row) {
            $row = explode('：', $row);
            $str .= '<tr><td class="title">'. $row[0] .'：</td><td>'. $row[1] .'</td></tr>';
        }

        return '<table class="normal-tbl">'. $str .'</table>';
    }

    static function crop($val,$len)
    {
        return mb_substr($val, 0, $len, "utf-8");
    }

    static function minify($buffer)
    {
        if (f3()->get('DEBUG') > 0) {
            return $buffer;
        }

        $search = array(
            '/\>[^\S ]+/s',  // strip whitespaces after tags, except space
            '/[^\S ]+\</s',  // strip whitespaces before tags, except space
            '/(\s)+/s',      // shorten multiple whitespace sequences
            '/<!--(?!\s*(?:\[if [^\]]+]|<!|>))(?:(?!-->).)*-->/s'
        );

        $replace = array(
            '>',
            '<',
            '\\1',
            ''
        );

        $buffer = preg_replace($search, $replace, $buffer);

        return $buffer;
    }

    static function wrapper($html, $title = "", $slug = "", $rtn = false)
    {
        $that = get_called_class();

        f3()->set('canonical', $slug);
        f3()->set('nav', rMenu::sort_menus(1, 0 , '', 0));
        f3()->set('footernav', rMenu::sort_menus(85, 0 , '', 0));

        $that::_seoMeta($title);

        $tp = \Template::instance();
        $tp->filter('nl2br','\F3CMS\Outfit::nl2br');
        $tp->filter('crop','\F3CMS\Outfit::crop');
        $tp->filter('date','\F3CMS\Outfit::date');
        $tp->filter('str2tbl','\F3CMS\Outfit::str2tbl');
        $tp->filter('thumbnail','\F3CMS\Outfit::thumbnail');

        if (!$rtn) {
            echo self::minify($tp->render($html));
        } else {
            return self::minify($tp->render($html));
        }
    }

    public static function _seoMeta($title = '')
    {

        $page = fOption::load('page');

        if (f3()->exists('page')) {
            $new = f3()->get('page', $page);
            $page = array_merge($page, $new);
        }

        f3()->set('page', $page);

        f3()->set('site.title', $page['title']);
        f3()->set('page.title', $title .(($title!='') ? ' | ' : ''). $page['title']);

        f3()->set('social', fOption::load('social'));
    }

    public static function _setAlternate($slug = '')
    {
        // TODO:
        f3()->set('page.alternate', '<1-- <link rel="alternate" href="'. $slug .'" hreflang="zh-tw" />'.
            '<link rel="alternate" href="'. $slug .'" hreflang="en" /> -->');
    }


}
