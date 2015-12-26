<?php
namespace F3CMS;

class Outfit extends BaseHelper
{
    public function __construct()
    {
        $f3 = \Base::instance();
        parent::__construct();
        // $f3->set('options', Option::get_options());
    }

    function do_comingsoon ($f3, $args)
    {
        self::wrapper('comingsoon.html', 'Coming Soon', '/');
    }

    /**
     * render thumbnail file name
     * @param  string $path - old path
     * @param  string $type - thumb type
     * @return string       - new path
     */
    static function thumbnail($path, $type)
    {
        $f3 = \Base::instance();

        list($w, $h) = $f3->get($type . '_thn');

        $tmp = explode('.', $path);

        $newpath = $tmp[0] .'_' . $w . 'x' . $h . '.'. $tmp[1];

        return $newpath;
    }

    static function paginate ($total, $limit = 10, $link = "", $range = 5) {
        $pages = new Pagination($total, $limit);
        $pages->setTemplate('parter/pagination.html');
        if (!empty($link)) {
            $pages->setLinkPath($link);
        }
        $pages->setRange($range);
        return $pages->serve();
    }

    static function nl2br ($val){
        return nl2br($val);
    }

    static function crop($val,$len) {
        return mb_substr($val, 0, $len, "utf-8");
    }

    static function minify($buffer) {

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

    static function wrapper ($html, $title = "", $slug = "") {
        $f3 = \Base::instance();
        $f3->set('canonical', $slug);
        $f3->set('pageTitle', $title);
        // $pcate = Category::get_categories(0);

        // foreach ($pcate as &$row) {
        //     $row['subrows'] = Category::get_categories($row['id']);
        // }

        // $f3->set('pcate', $pcate);

        $tp = \Template::instance();
        $tp->filter('nl2br','Outfit::nl2br');
        $tp->filter('crop','Outfit::crop');

        echo self::minify($tp->render($html));
    }
}
