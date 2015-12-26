<?php

namespace F3CMS;

/**
 * data feed
 */
class fPost extends Feed
{
    const MTB    = "post";
    const ST_ON  = "Enabled";
    const ST_OFF = "Disabled";

    static function getAll()
    {


        $result = f3()->get('DB')->exec(
            "SELECT a.id, a.title, a.last_ts, a.pic, a.slug FROM `". f3()->get('tpf') . self::MTB ."` a "
        );

        return $result;
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


        $condition = " WHERE `id` > '". $post_id ."' ";

        if ($category_id != 0) {
            $condition .= " AND `category_id`='". $category_id ."' ";
        }

        $rows = f3()->get('DB')->exec("SELECT `slug` FROM `". f3()->get('tpf') . self::MTB ."` ". $condition ." ORDER BY id ASC  LIMIT 1 ");

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


        $condition = " WHERE `id` < '". $post_id ."' ";

        if ($category_id != 0) {
            $condition .= " AND `category_id`='". $category_id ."' ";
        }

        $rows = f3()->get('DB')->exec("SELECT `slug` FROM `". f3()->get('tpf') . self::MTB ."` ". $condition ." ORDER BY id DESC  LIMIT 1 ");

        if (count($rows) != 1) {
            return null;
        }
        else {
            return $rows[0]['slug'];
        }
    }
}
