<?php

namespace F3CMS;

class rPost extends Backend
{

    const MTB = "posts";
    const ST_ON = "Enabled";
    const ST_OFF = "Disabled";

    public function __construct()
    {
        parent::__construct();
    }

    function do_list_all($f3, $args)
    {
        rUser::_chkLogin();

        $rows = $this->_db->exec("SELECT a.id, a.title, a.last_ts, a.pic, a.slug FROM `". $f3->get('tpf') . self::MTB ."` a ");

        return parent::_return(1, $rows);
    }

    /**
     * get a post by slug
     *
     * @param string $slug - slug
     *
     * @return array
     */
    static function get_post_by_slug($slug)
    {
        $f3 = \Base::instance();

        $rows = $f3->get('DB')->exec(
            "SELECT a.id, a.title, a.content, a.pic, a.last_ts, a.slug FROM `". $f3->get('tpf') . self::MTB ."` a WHERE a.`slug`=? LIMIT 1 ", '/' . $slug
        );

        if (count($rows) != 1) {
            return null;
        }
        else {
            return $rows[0];
        }
    }

    /**
     * get a next post
     *
     * @param int $post_id     - current id
     * @param int $category_id - current id
     *
     * @return string
     */
    static function get_next_post($post_id, $category_id = 0)
    {
        $f3 = \Base::instance();

        $condition = " WHERE `id` > '". $post_id ."' ";

        if ($category_id != 0) {
            $condition .= " AND `category_id`='". $category_id ."' ";
        }

        $rows = $f3->get('DB')->exec("SELECT `slug` FROM `". $f3->get('tpf') . self::MTB ."` ". $condition ." ORDER BY id ASC  LIMIT 1 ");

        if (count($rows) != 1) {
            return null;
        }
        else {
            return $rows[0]['slug'];
        }
    }

    /**
     * get a prev post
     *
     * @param int $post_id     - current id
     * @param int $category_id - current id
     *
     * @return string
     */
    static function get_prev_post($post_id, $category_id = 0)
    {
        $f3 = \Base::instance();

        $condition = " WHERE `id` < '". $post_id ."' ";

        if ($category_id != 0) {
            $condition .= " AND `category_id`='". $category_id ."' ";
        }

        $rows = $f3->get('DB')->exec("SELECT `slug` FROM `". $f3->get('tpf') . self::MTB ."` ". $condition ." ORDER BY id DESC  LIMIT 1 ");

        if (count($rows) != 1) {
            return null;
        }
        else {
            return $rows[0]['slug'];
        }
    }
}
