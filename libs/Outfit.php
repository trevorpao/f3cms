<?php

namespace F3CMS;

// The Outfit class extends the Module class and provides additional utility methods.
// It includes methods for handling breadcrumbs, pagination, and formatting data.

class Outfit extends Module
{
    const JUST_RETURN_STR = true; // Constant to indicate whether to return a string directly.

    /**
     * Displays content based on the provided arguments.
     *
     * @param array $args The arguments for displaying content.
     */
    public static function show($args)
    {
        $that = get_called_class();
        $that::_staticFile($args);
    }

    /**
     * Magic method to handle dynamic method calls.
     *
     * @param string $method The name of the method being called.
     * @param array  $args   The arguments passed to the method.
     */
    public function __call($method, $args)
    {
        $time_start = microtime(true);
        $that       = get_called_class();

        $that::_beforeRoute($args[1]);

        $response = $that::_middleware($args[1], $method);

        $that::_afterRoute($args[1]);

        $time_end = microtime(true);

        $spent = $time_end - $time_start;

        echo '<!-- spent: ' . $spent . ' -->';

        return $response;
    }

    /**
     * Executes logic before routing.
     *
     * @param array $args The arguments for the pre-route logic.
     */
    public static function _beforeRoute($args)
    {
        if (f3()->exists('siteBeginDate')) {
            $ts  = strtotime(f3()->get('siteBeginDate'));
            $now = time();
            if ($now < $ts) {
                if (empty($args) || '/comingsoon' != $args[0]) {
                    f3()->reroute(f3()->get('uri') . '/comingsoon');
                }
            }
        }

        parent::_mobile_user_agent();
        Module::_lang($args);
        f3()->set('lang', Module::_lang());
        f3()->set('SESSION.csrf', getCSRF());
    }

    /**
     * Executes logic after routing.
     *
     * @param array $args The arguments for the post-route logic.
     */
    public static function _afterRoute($args)
    {
    }

    /**
     * Middleware logic to process requests and responses.
     *
     * @param array  $args The arguments for the middleware.
     * @param string $next The next middleware or handler to execute.
     */
    public static function _middleware($args, string $next)
    {
        $class  = get_called_class();
        $method = str_replace('do_', '', $next);

        $class = str_replace('F3CMS', 'PCMS', $class);

        if (!method_exists($class, $method)) {
            $class = str_replace('PCMS', 'F3CMS', $class);
            if (!method_exists($class, $method)) {
                throw new \Exception('(1004) ' . $class . '::' . $next . ' not found');
            }
        }

        $args = parent::_escape($args, false);

        return call_user_func_array([$class, $method], [$args]);
    }

    /**
     * Handles static file requests.
     *
     * @param array $args  The arguments for the static file request.
     * @param bool  $force Whether to force the handling of the static file.
     */
    public static function _staticFile($args, $force = false)
    {
        $that = get_called_class();

        // echo '<pre>';
        // FSHelper::mkdir(['/var/www/html/static/s/nQ/nQaKWfP9KsJ7/tw/press/2/test-news']);
        // FSHelper::mkdir(['/var/www/html/theme/default/assets/tw/press/2/test-news']);
        // FSHelper::mirror('/var/www/html/theme/default/assets', '/var/www/html/static/s/nQ/nQaKWfP9KsJ7');
        // print_r(FSHelper::ls('/var/www/html/static/s/nQ/nQaKWfP9KsJ7', true));
        // print_r(FSHelper::ls('/var/www/html/theme/default/assets', true));
        // die;

        // new cache file path
        // new file name rule

        if (f3()->get('DEBUG') > 2) {
            $html = $that::_render($args);
        } else {
            $path = dirname($args[0]);
            if (0 !== strpos($path, '/' . parent::_lang() . '/')) {
                $path = '/' . parent::_lang() . $path;
            }
            $html = SRHelper::get($path);

            if (empty($html)) {
                if (1 || f3()->get('DEBUG') > 0) { // only for test // || rStaff::_isLogin()
                    $html = $that::_render($args);
                    SRHelper::save($path, $html);
                } else {
                    f3()->error(403);
                }
            }
        }

        echo $html;
        $that::_showVariables();
    }

    /**
     * set excel header
     *
     * @param string $filename - file name to user
     */
    public static function _setXls($filename)
    {
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
        header('Cache-Control: no-cache, must-revalidate'); // HTTP/1.1
        header('Pragma: no-cache'); // HTTP/1.0
        header('Content-Disposition:filename=' . $filename . '.xls');
        header('Content-type:application/vnd.ms-excel; charset=UTF-8');
        header('Content-Language:content=zh-tw');
        echo '<META HTTP-EQUIV="Content-Type" CONTENT="text/html; CHARSET=UTF-8">';

        $tp = \Template::instance();
        $tp->filter('date', '\F3CMS\Outfit::date');

        return $tp;
    }

    /**
     * Outputs XML content.
     *
     * @param string $filename The name of the XML file.
     */
    public static function _echoXML($filename)
    {
        $tp = \Template::instance();
        $tp->filter('date', '\F3CMS\Outfit::date');

        echo '<';
        echo '?xml version="1.0" encoding="UTF-8"?';
        echo '>';

        echo self::utf8Xml($tp->render($filename . '.xml', 'application/xml'));
    }

    /**
     * Displays variables for debugging purposes.
     */
    public static function _showVariables()
    {
        if (f3()->get('DEBUG') > 1) {
            echo '<div class="pretty-code">' . hJsonEncode(f3()->get('_dzv')) . '</div>';
        }
    }

    /**
     * Generates a breadcrumb navigation structure.
     *
     * @param array  $ary   The breadcrumb items.
     * @param bool   $isLi  Whether to format the breadcrumb as a list.
     * @param string $home  The label for the home link.
     * @return string The generated breadcrumb HTML.
     */
    public static function breadcrumb($ary, $isLi = true, $home = '')
    {
        $rtn  = [];
        $tmpl = ' > <a href="/' . parent::_lang() . '%slug$s">%str$s</a>';
        if ($isLi) {
            $tmpl = '<li class="breadcrumb-item"><a href="/' . parent::_lang() . '%slug$s">%str$s</a></li>';
        }

        if (empty($ary['sire'])) {
            if ('' != $home) {
                $rtn[] = ['slug' => '/', 'str' => $home];
            }
        } else {
            $rtn = self::breadcrumb($ary['sire'], $isLi);
        }

        if (!empty($ary['title'])) {
            $rtn[] = ['slug' => $ary['slug'], 'str' => $ary['title']];
        }

        return $rtn;
    }

    /**
     * Generates a breadcrumb navigation structure as a string.
     *
     * @param array $ary  The breadcrumb items.
     * @param bool  $isLi Whether to format the breadcrumb as a list.
     * @return string The generated breadcrumb string.
     */
    public static function breadcrumb_str($ary, $isLi = true)
    {
        $str  = '';
        $tmpl = ' > <a href="/' . parent::_lang() . '%slug$s">%str$s</a>';
        if ($isLi) {
            $tmpl = '<li class="breadcrumb-item"><a href="/' . parent::_lang() . '%slug$s">%str$s</a></li>';
        }

        foreach ($ary as $val) {
            $str .= self::_sprintf($tmpl, ['slug' => $val['slug'], 'str' => $val['str']]);
        }

        return $str;
    }

    /**
     * Converts a string to UTF-8 XML format.
     *
     * @param string $string The input string.
     * @return string The UTF-8 XML formatted string.
     */
    public static function utf8Xml($string)
    {
        return preg_replace('/[^\x{0009}\x{000a}\x{000d}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', ' ', $string);
    }

    /**
     * Generates a thumbnail for a given path and type.
     *
     * @param string $path The path to the image.
     * @param string $type The type of thumbnail to generate.
     * @return string The generated thumbnail path.
     */
    public static function thumbnail($path, $type)
    {
        $tmp = explode('.', $path);

        if ('sm' == $type) {
            $newpath = $tmp[0] . '_sm.' . $tmp[1];
        } else {
            [$w, $h] = f3()->get($type . '_thn');
            $newpath = $tmp[0] . '_' . $w . 'x' . $h . '.' . $tmp[1];
        }

        return $newpath;
    }

    /**
     * Converts URLs in a text to clickable links.
     *
     * @param string $text The input text containing URLs.
     * @return string The text with URLs converted to links.
     */
    public static function convertUrlsToLinks($text)
    {
        // 正則表達式匹配模式，用於識別 https 開頭的 URL
        $regex = '@(?<!["\'])((https)://[^\s/$.].([\w./])*\??[\w&=-]*)@';

        // 使用 preg_replace_callback 來替換匹配的 URL 為 HTML <a> 標籤
        $textWithLinks = preg_replace_callback($regex, function ($matches) {
            $url = $matches[1];

            // 返回替換後的 <a> 標籤
            return '<a href="' . htmlspecialchars($url) . '">' . htmlspecialchars($url) . '</a>';
        }, $text);

        return $textWithLinks;
    }

    /**
     * Adjusts a file path based on the device type.
     *
     * @param string $path The original file path.
     * @param string $type The device type (e.g., mobile, desktop).
     * @return string The adjusted file path.
     */
    public static function pathByDevice($path, $type)
    {
        $device = parent::_mobile_user_agent();

        if ('unknown' != $device) {
            [$w, $h] = f3()->get($type . '_thn');

            $tmp = explode('.', $path);

            $path = $tmp[0] . '_' . $w . 'x' . $h . '.' . $tmp[1];
        }

        return $path;
    }

    /**
     * Handles tags and processes them as needed.
     *
     * @param array $tags The tags to process.
     * @return array The processed tags.
     */
    public static function handleTag($tags)
    {
        $ary = [];
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

    /**
     * Converts a value to a formatted date string.
     *
     * @param mixed  $val    The value to format.
     * @param string $format The date format.
     * @return string The formatted date string.
     */
    public static function date($val, $format)
    {
        return date($format, strtotime($val));
    }

    /**
     * Calculates the duration between two timestamps.
     *
     * @param int $start The start timestamp.
     * @param int $end   The end timestamp.
     * @return string The calculated duration.
     */
    public static function during($start, $end)
    {
        if (date('Y-m-d', strtotime($start)) == date('Y-m-d', strtotime($end))) {
            return date('Y-m-d(D) H:i', strtotime($start)) . ' ~ ' . date('H:i', strtotime($end));
        } else {
            return date('Y-m-d(D) H:i', strtotime($start)) . ' ~ ' . date('Y-m-d(D) H:i', strtotime($end));
        }
    }

    /**
     * Fixes and formats a slug string.
     *
     * @param string $val The input slug string.
     * @return string The fixed slug string.
     */
    public static function fixSlug($val)
    {
        return ucwords(preg_replace('/[-_]/', ' ', $val));
    }

    /**
     * @param $val
     */
    public static function nl2brSecurity($val)
    {
        $val = str_replace(['&#13;&#10;', '&#13;', '&#10;'], [PHP_EOL, PHP_EOL, PHP_EOL], $val);

        return nl2br($val);
    }

    /**
     * @param $val
     */
    public static function upper($val)
    {
        return strtoupper($val);
    }

    /**
     * @param $val
     */
    public static function join($val, $glue = ',')
    {
        return implode($glue, $val);
    }

    /**
     * @param $val
     */
    public static function avatar($email, $size = 80, $type = 'avatar')
    {
        return (('cat' !== $type) ? '//www.gravatar.com/avatar/' . md5($email) . '.jpg?s=' : '//robohash.org/' . md5($email) . '?set=set4&s=') . $size;
    }

    public static function numFormat($num, $decimals = 0)
    {
        return number_format($num, $decimals);
    }

    /**
     * @param $num
     */
    public static function s2m($num)
    {
        $s = $num % 60;
        $m = floor($num / 60);

        return ($s > 0) ? $m . ' 分 ' . $s . ' 秒' : $m . ' 分';
    }

    /**
     * @param $num
     */
    public static function s2ms($num)
    {
        $m = ceil($num / 60);

        return $m;
    }

    /**
     * @param $num
     */
    public static function s2h($num)
    {
        $m = ceil(($num % 3600) / 60);
        $h = floor($num / 3600);

        return (($h > 0) ? $h . ' 小時' : '') . (($h > 0 && $m > 0) ? ' ' : '') . (($m > 0) ? $m . ' 分' : '');
    }

    public static function repathImg($str)
    {
        return $str; // str_replace('/upload/', f3()->get('picUri'), empty($str) ? '' : $str);
    }

    public static function repathUri($str)
    {
        return $str;
    }

    /**
     * @param $val
     */
    public static function str2tbl($val)
    {
        $val = str_replace(['&#13;', '&#10;'], [PHP_EOL, ''], $val);
        $ary = explode(PHP_EOL, $val);
        $str = '';
        foreach ($ary as $row) {
            $row = explode('：', $row);
            $str .= '<tr><td class="title">' . $row[0] . '：</td><td>' . $row[1] . '</td></tr>';
        }

        return '<table class="normal-tbl">' . $str . '</table>';
    }

    /**
     * @param $val
     */
    public static function str2li($val)
    {
        $val = str_replace(['&#13;', '&#10;'], [PHP_EOL, ''], $val);
        $ary = explode(PHP_EOL, $val);
        $str = '';
        foreach ($ary as $idx => $row) {
            $str .= '<li>' . $row . '</li>';
        }

        return $str;
    }

    public static function str2hashtag($val)
    {
        $val = str_replace(['&#13;', '&#10;'], [PHP_EOL, ''], $val);
        $ary = explode(PHP_EOL, $val);
        $str = '';
        foreach ($ary as $row) {
            $row = trim(str_replace(['＃', '#'], ['', ''], $row));
            if (!empty($row)) {
                $str .= ' <a href="/search?q=' . $row . '" target="_blank" aria-label="另開新視窗" class="hero-tag">#' . $row . '</a> ';
            }
        }

        return $str;
    }

    public static function str2vtt($val)
    {
        $val = str_replace(['&#13;', '&#10;'], [PHP_EOL, ''], $val);
        $ary = explode(PHP_EOL, $val);
        $str = '';
        foreach ($ary as $row) {
            $str .= '<p class="hero-podcastList">' . $row . '</p>';
        }

        return $str;
    }

    public static function htmlDecode($val)
    {
        return html_entity_decode($val, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401, 'UTF-8');
    }

    public static function urlencode($val)
    {
        return rawurlencode($val);
    }

    public static function safeRaw($val)
    {
        $val = self::htmlDecode($val);

        return str_replace(['<', '>', '@'], ['&lt;', '&gt;', '©'], $val);

        // return html_entity_decode($val, ENT_QUOTES);
        // return str_replace(['&rsquo;', '&quot;'], ['\'', '"'], $val);
    }

    /**
     * @param $val
     * @param $len
     */
    public static function crop($val, $len)
    {
        $val = empty($val) ? '' : $val;

        return mb_substr($val, 0, $len, 'utf-8');
    }

    /**
     * @param $buffer
     *
     * @return mixed
     */
    public static function minify($buffer)
    {
        if (f3()->get('DEBUG') > 0) {
            return $buffer;
        }

        $search = [
            '/\>[^\S ]+/s', // strip whitespaces after tags, except space
            '/[^\S ]+\</s', // strip whitespaces before tags, except space
            '/(\s)+/s', // shorten multiple whitespace sequences
            '/<!--(?!\s*(?:\[if [^\]]+]|<!|>))(?:(?!-->).)*-->/s',
        ];

        $replace = [
            '>',
            '<',
            '\\1',
            '',
        ];

        $buffer = preg_replace($search, $replace, $buffer);

        return $buffer;
    }

    public static function assetsSite($val)
    {
        return f3()->get('_dzv.assetsUri') . $val;
    }

    /**
     * @param $html
     * @param $title
     * @param $slug
     * @param $rtn
     */
    public static function wrapper($html, $title = '', $slug = '', $rtn = false)
    {
        $that = get_called_class();

        f3()->set('UI', f3()->get('UI') . f3()->get('theme') . '/');

        f3()->set('nav', rMenu::sort_menus(1, 0, '', 0));
        f3()->set('footernav', rMenu::sort_menus(85, 0, '', 0));

        $that::_seoMeta($title);

        f3()->set('page.canonical', '/' . Module::_lang() . $slug);
        f3()->set('page.share_link', f3()->get('uri') . $slug);

        f3()->set('year', date('Y'));

        if (!f3()->exists('act_link')) {
            f3()->set('act_link', '');
        }

        $tp = self::_origin();

        if (!$rtn) {
            echo self::minify($tp->render($html));
        } else {
            return self::minify($tp->render($html));
        }
    }

    /**
     * get twig instance
     *
     * @return instance
     */
    public static function _origin()
    {
        $tp = \Template::instance();
        $tp->filter('nl2brSecurity', '\F3CMS\Outfit::nl2brSecurity');
        $tp->filter('crop', '\F3CMS\Outfit::crop');
        $tp->filter('date', '\F3CMS\Outfit::date');
        $tp->filter('str2tbl', '\F3CMS\Outfit::str2tbl');
        $tp->filter('thumbnail', '\F3CMS\Outfit::thumbnail');
        $tp->filter('avatar', '\F3CMS\Outfit::avatar');
        $tp->filter('s2m', '\F3CMS\Outfit::s2m');
        $tp->filter('s2ms', '\F3CMS\Outfit::s2ms');
        $tp->filter('htmlDecode', '\F3CMS\Outfit::htmlDecode');
        $tp->filter('urlencode', '\F3CMS\Outfit::urlencode');
        $tp->filter('safeRaw', '\F3CMS\Outfit::safeRaw');

        return $tp;
    }

    /**
     * @param $html
     * @param $title
     * @param $slug
     * @param $rtn
     *
     * @return mixed
     */
    public static function render($html, $title = '', $slug = '', $rtn = false)
    {
        $that = get_called_class();

        _dzv('layout', str_replace(['/', '.html', '.twig'], ['-', '', ''], $html));

        $that::_seoMeta($title);

        f3()->set('page.canonical', $slug);
        f3()->set('page.share_link', f3()->get('uri') . $slug);

        _dzv('page', f3()->get('page'));

        _dzv('page.breadcrumb', self::breadcrumb(['title' => $title, 'slug' => $slug, 'sire' => f3()->get('breadcrumb_sire')]));

        $opts    = fOption::load('', 'Preload');

        if (!empty($opts['default']['color_name'])) {
            $setting['colorName'] = $opts['default']['color_name'];
        }

        f3()->set('opts', $opts);
        _dzv('opts', $opts);

        _dzv('feVersion', f3()->get('feVersion'));
        _dzv('theme', f3()->get('theme'));
        _dzv('uri', f3()->get('uri'));
        _dzv('main_domain', f3()->get('main_domain'));
        _dzv('lang', f3()->get('lang'));
        _dzv('csrf', f3()->get('SESSION.csrf'));
        _dzv('assetsUri', '/assets/');
        // _dzv('liffID', f3()->get('line_liff'));
        _dzv('year', date('Y'));

        if (!$rtn) {
            echo self::_twig()->render($html, f3()->get('_dzv'));
            self::_showVariables();
        } else {
            return self::_twig()->render($html, f3()->get('_dzv'));
        }
    }

    /**
     * get twig instance
     *
     * @return instance
     */
    public static function _twig()
    {
        $loader = new \Twig\Loader\FilesystemLoader(f3()->get('UI') . '/' . f3()->get('theme'));

        $loader->addPath(f3()->get('UI') . '/' . f3()->get('theme') . '/partial', 'partial');

        $twig = new Twig($loader, [
            'debug'       => true,
            'cache'       => f3()->get('TEMP') . (('cli' == php_sapi_name()) ? 'twig_cli/' : 'twig/'),
            'charset'     => 'utf-8',
            'auto_reload' => true,
        ]);

        // if (f3()->get('DEBUG') > 0) {
        //     $twig->addExtension(new \Twig_Extension_Debug());
        // }

        $filter = new \Twig\TwigFilter('nl2brSecurity', '\F3CMS\Outfit::nl2brSecurity');
        $twig->addFilter($filter);

        $filter = new \Twig\TwigFilter('staticSite', '\F3CMS\Outfit::staticSite');
        $twig->addFilter($filter);

        $filter = new \Twig\TwigFilter('assetsSite', '\F3CMS\Outfit::assetsSite');
        $twig->addFilter($filter);

        $filter = new \Twig\TwigFilter('downloadSite', '\F3CMS\Outfit::downloadSite');
        $twig->addFilter($filter);

        $filter = new \Twig\TwigFilter('assets', '\F3CMS\Outfit::assetsSite');
        $twig->addFilter($filter);

        $filter = new \Twig\TwigFilter('thumbnail', '\F3CMS\Outfit::thumbnail');
        $twig->addFilter($filter);

        $filter = new \Twig\TwigFilter('router', '\F3CMS\Outfit::router');
        $twig->addFilter($filter);

        $filter = new \Twig\TwigFilter('breadcrumb', '\F3CMS\Outfit::breadcrumb_str');
        $twig->addFilter($filter);

        $filter = new \Twig\TwigFilter('t', '\F3CMS\Outfit::trans');
        $twig->addFilter($filter);

        $filter = new \Twig\TwigFilter('safeRaw', '\F3CMS\Outfit::safeRaw');
        $twig->addFilter($filter);

        $filter = new \Twig\TwigFilter('avatar', '\F3CMS\Outfit::avatar');
        $twig->addFilter($filter);

        $filter = new \Twig\TwigFilter('s2m', '\F3CMS\Outfit::s2m');
        $twig->addFilter($filter);

        $filter = new \Twig\TwigFilter('s2ms', '\F3CMS\Outfit::s2ms');
        $twig->addFilter($filter);

        $filter = new \Twig\TwigFilter('s2h', '\F3CMS\Outfit::s2h');
        $twig->addFilter($filter);

        $filter = new \Twig\TwigFilter('numFormat', '\F3CMS\Outfit::numFormat');
        $twig->addFilter($filter);

        $filter = new \Twig\TwigFilter('crop', '\F3CMS\Outfit::crop');
        $twig->addFilter($filter);

        $filter = new \Twig\TwigFilter('repathImg', '\F3CMS\Outfit::repathImg');
        $twig->addFilter($filter);

        $filter = new \Twig\TwigFilter('repathUri', '\F3CMS\Outfit::repathUri');
        $twig->addFilter($filter);

        $filter = new \Twig\TwigFilter('gmapI18n', '\F3CMS\Outfit::gmapI18n');
        $twig->addFilter($filter);

        $filter = new \Twig\TwigFilter('str2hashtag', '\F3CMS\Outfit::str2hashtag');
        $twig->addFilter($filter);

        $filter = new \Twig\TwigFilter('urlencode', '\F3CMS\Outfit::urlencode');
        $twig->addFilter($filter);

        $filter = new \Twig\TwigFilter('str2vtt', '\F3CMS\Outfit::str2vtt');
        $twig->addFilter($filter);

        $filter = new \Twig\TwigFilter('str2li', '\F3CMS\Outfit::str2li');
        $twig->addFilter($filter);

        $filter = new \Twig\TwigFilter('htmlDecode', '\F3CMS\Outfit::htmlDecode');
        $twig->addFilter($filter);

        $filter = new \Twig\TwigFilter('fixSlug', '\F3CMS\Outfit::fixSlug');
        $twig->addFilter($filter);

        return $twig;
    }

    /**
     * @param $title
     */
    public static function _seoMeta($title = '')
    {
        $page = fOption::load('page');

        f3()->set('page.site_name', $page['title']);
        if (empty($page['subtitle'])) {
            $page['subtitle'] = '';
        }
        f3()->set('page.site_subtitle', $page['subtitle']);

        if (f3()->exists('page')) {
            $new  = f3()->get('page', $page);
            $page = array_merge($page, $new);
        }

        if (f3()->exists('_dzv.page')) {
            $new  = f3()->get('_dzv.page', $page);
            $page = array_merge($page, $new);
        }

        f3()->set('page', $page);
        f3()->set('page.title', $title);

        f3()->set('social', fOption::load('social'));
    }

    /**
     * @param $slug
     */
    public static function _setAlternate($slug = '')
    {
        // TODO:
        f3()->set('page.alternate', '<1-- <link rel="alternate" href="' . $slug . '" hreflang="zh-tw" />' .
            '<link rel="alternate" href="' . $slug . '" hreflang="en" /> -->');
    }

    /**
     * Like vsprintf, but accepts $args keys instead of order index.
     * Both numeric and strings matching /[a-zA-Z0-9_-]+/ are allowed.
     *
     * Example: vskprintf('y = %y$d, x = %x$1.1f', array('x' => 1, 'y' => 2))
     * Result:  'y = 2, x = 1.0'
     *
     * $args also can be object, then it's properties are retrieved
     * using get_object_vars().
     *
     * '%s' without argument name works fine too. Everything vsprintf() can do
     * is supported.
     *
     * @author Josef Kufner <jkufner(at)gmail.com>
     */
    public static function _sprintf($str, $args)
    {
        if (is_object($args)) {
            $args = get_object_vars($args);
        }

        $map     = array_flip(array_keys($args));
        $new_str = preg_replace_callback('/(^|[^%])%([a-zA-Z0-9_-]+)\$/',
            function ($m) use ($map) {return $m[1] . '%' . ($map[$m[2]] + 1) . '$'; },
            $str);

        return vsprintf($new_str, $args);
    }
}
