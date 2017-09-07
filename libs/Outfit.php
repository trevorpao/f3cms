<?php
namespace F3CMS;

class Outfit extends Module
{
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
        $pages->setTemplate('parter/pagination.html');
        if (!empty($link)) {
            $pages->setLinkPath($link);
        }
        if ($current!=-1) {
            $pages->setCurrent($current);
        }
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

        //return $buffer;

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

    static function wrapper ($html, $title = "", $slug = "")
    {
        f3()->set('SESSION.csrf', 'wdfghn'); // f3()->get('sess')->csrf());

        f3()->set('canonical', $slug);

        $page = fOption::load('page');

        if (f3()->exists('page')) {
            $new = f3()->get('page', $page);
            $page = array_merge($page, $new);
        }

        f3()->set('page', $page);

        f3()->set('site.title', $page['title']);
        f3()->set('page.title', $title .(($title!='') ? ' | ' : ''). $page['title']);

        f3()->set('social', fOption::load('social'));

        f3()->set('nav', rMenu::sort_menus(1, 0 , '', 0));
        f3()->set('footernav', rMenu::sort_menus(85, 0 , '', 0));

        parent::_mobile_user_agent();

        $tp = \Template::instance();
        $tp->filter('nl2br','\F3CMS\Outfit::nl2br');
        $tp->filter('crop','\F3CMS\Outfit::crop');
        $tp->filter('date','\F3CMS\Outfit::date');
        $tp->filter('str2tbl','\F3CMS\Outfit::str2tbl');
        $tp->filter('thumbnail','\F3CMS\Outfit::thumbnail');

        echo self::minify($tp->render($html));
    }
}
