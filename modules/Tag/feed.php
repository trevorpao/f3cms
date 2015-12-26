<?php

namespace F3CMS;

/**
 * data feed
 */
class fTag extends Feed
{
    const MTB = "tag";

    static function getAll()
    {


        $result = f3()->get('DB')->exec(
            "SELECT c.*, p.`title` AS parent, c.`title` AS `title`, c.`slug` AS `slug`, c.`counter` AS `counter` FROM `".
            f3()->get('tpf') . self::MTB ."` c LEFT JOIN `". f3()->get('tpf') . self::MTB ."` p ON p.id=c.parent_id "
        );

        return $result;
    }

    /**
     * get a tag by tag id
     *
     * @param int $cid - type id
     *
     * @return array
     */
    static function get_tag($cid)
    {


        $rows = f3()->get('DB')->exec(
            "SELECT * FROM `". f3()->get('tpf') . self::MTB ."` WHERE `id`=? LIMIT 1 ", $cid
        );

        if (count($rows) != 1) {
            return null;
        }
        else {
            $cu = $rows[0];
            $cu['subrows'] = self::get_tags($cu['id']);
            return $cu;
        }
    }

    /**
     * get a tag by slug
     *
     * @param string $slug - slug
     *
     * @return array
     */
    static function get_tag_by_slug($slug)
    {


        $rows = f3()->get('DB')->exec(
            "SELECT * FROM `". f3()->get('tpf') . self::MTB ."` WHERE `slug`=? LIMIT 1 ", '/'.$slug
        );

        if (count($rows) != 1) {
            return null;
        }
        else {
            $cu = $rows[0];
            $cu['subrows'] = self::get_tags($cu['id']);
            return $cu;
        }
    }

    /**
     * get tags by parent id
     *
     * @param int $parent_id - parent type id
     *
     * @return array
     */
    static function get_tags($parent_id = -1)
    {

        $condition = "";

        if ($parent_id!=-1) {
            $condition = " where c.parent_id='". $parent_id ."' ";
        }

        return f3()->get('DB')->exec("SELECT c.`id`, c.`title`, c.`slug` FROM `". f3()->get('tpf') . self::MTB ."` c ". $condition);
    }
}
