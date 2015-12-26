<?php

namespace F3CMS;

/**
 * data feed
 */
class fCategory extends Feed
{
    const MTB           = "category";

    const ST_NEW        = "New";
    const ST_PAID       = "Paid";
    const ST_DONE       = "Done";
    const ST_INVALID    = "Invalid";

    static function getAll()
    {
        $f3 = \Base::instance();

        $result = $f3->get('DB')->exec(
            "SELECT id, parent_id, title, slug, last_ts FROM `". $f3->get('tpf') .
            self::MTB ."` "
        );

        foreach ($result as &$row) {
            $row['category'] = rCategory::breadcrumb(rCategory::breadcrumb_categories($row['parent_id']), false);
            $row['category'] .= (($row['category'] != ' / ')?' / ':'') . $row['title'];
        }

        return $result;
    }

    /**
     * get a category by slug
     *
     * @param string $slug - slug
     *
     * @return array
     */
    static function get_category_by_slug($slug)
    {
        $f3 = \Base::instance();

        $rows = $f3->get('DB')->exec(
            "SELECT c.*, p.title AS parent FROM `". $f3->get('tpf') . self::MTB ."` c LEFT JOIN `". $f3->get('tpf') . self::MTB ."` p ON p.id=c.parent_id WHERE c.`slug`=? LIMIT 1 ", '/'.$slug
        );

        if (count($rows) != 1) {
            return null;
        }
        else {
            $cu = $rows[0];
            $cu['subrows'] = self::get_categories($cu['id']);
            return $cu;
        }
    }

    /**
     * get a category by category id
     *
     * @param int $cid - type id
     *
     * @return array
     */
    static function get_category($cid)
    {
        $f3 = \Base::instance();

        $rows = $f3->get('DB')->exec(
            "SELECT * FROM `". $f3->get('tpf') . self::MTB ."` WHERE `id`=? LIMIT 1 ", $cid
        );

        if (count($rows) != 1) {
            return null;
        }
        else {
            $cu = $rows[0];
            $cu['subrows'] = self::get_categories($cu['id']);
            return $cu;
        }
    }

    /**
     * get categories by parent id
     *
     * @param int $parent_id - parent type id
     *
     * @return array
     */
    static function get_categories($parent_id = -1)
    {
        $f3 = \Base::instance();
        $condition = "";

        if ($parent_id!=-1) {
            $condition = " where c.parent_id='". $parent_id ."' ";
        }

        $rows = $f3->get('DB')->exec("SELECT c.id, c.title, c.slug, c.parent_id, p.title AS parent FROM `".
            $f3->get('tpf') . self::MTB ."` c LEFT JOIN `". $f3->get('tpf') . self::MTB .
            "` p ON p.id=c.parent_id ". $condition ." ORDER BY c.id ");

        return $rows;
    }
}
