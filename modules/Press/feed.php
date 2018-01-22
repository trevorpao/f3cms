<?php
namespace F3CMS;
/**
 * data feed
 */
class fPress extends Feed
{
    const MTB = "press";
    const ST_ON = "Enabled";
    const ST_OFF = "Disabled";

    const BE_COLS = 'id,title,last_ts,pic,status,slug,online_date';

    static function getAll()
    {

        $result = db()->exec("SELECT a.id, a.title, a.last_ts, a.pic, a.status, a.slug, m.title AS author
            FROM `" . self::fmTbl() . "` a
            LEFT JOIN `". fAuthor::fmTbl() ."` m ON m.id = a.author_id
            ORDER BY a.`online_date` DESC, a.`insert_ts` DESC ");

        return $result;
    }

    // static function load_list($page, $slug, $type = 'cate')
    // {
    //     $rows = self::map();

    //     $limit = 9;

    //     $filter = array(
    //         'status = :status',
    //         ':status' => self::ST_ON
    //     );

    //     if (!empty($slug)) {
    //         if ($type == 'cate') {
    //             $filter[0] .= fCategory::get_condition($slug);
    //         }
    //         else {
    //             $tag = fTag::get_tag_by_slug($slug);
    //             if ($tag) {
    //                 $filter[0] .= " AND `rel_tag` LIKE '%\"id\":\"". $tag['id'] ."\"%' ";
    //             }
    //         }
    //     }

    //     $option = array('order' => '`online_date` DESC');

    //     return $rows->paginate($page-1, $limit, $filter, $option);
    // }

    static function load_list($page, $slug, $type = 'author', $limit = 9)
    {

        $filter = array(
            ':status' => self::ST_ON,
            ':date' => date('Y-m-d')
        );

        $condition = ' WHERE m.`status` = :status  AND m.online_date <= :date ';

        if (!empty($slug)) {
            if ($type == 'author') {
                $filter[':author'] = $slug;
                $condition .= " AND m.`author_id` = :author ";
            }
            else {
                $tag = fTag::get_tag_by_slug($slug);
                if ($tag) {
                    $condition .= " AND m.`rel_tag` LIKE '%\"". $tag['id'] ."\"%' ";
                }
            }
        }

        $sql = 'SELECT m.`id`, m.`author_id`, m.`status`, m.`slug`, m.`rel_tag`, m.`rel_dict`, m.`online_date`, m.`title`, m.`keyword`, m.`pic`, m.`info`, m.`last_ts`, a.`title` AS `author`, a.`slug` AS `author_slug` FROM `'. self::fmTbl() .'` m '
        . ' LEFT JOIN `'. fAuthor::fmTbl() .'` a ON a.`id` = m.`author_id` '
        . $condition .' ORDER BY m.`online_date` DESC ';

        // die($sql);

        return parent::paginate($sql, $filter, $page - 1, $limit);
    }

    static function load_homepage_list($limit = 4, $tag = 0, $except = array())
    {

        $condition = " WHERE m.`status` = '" . self::ST_ON . "' AND m.`on_homepage` = 'Yes' AND m.online_date <= '". date('Y-m-d') ."' AND m.id NOT IN ('". implode("', '", $except) ."') ";

        if ($tag !== 0) {
            $condition .= " AND m.`rel_tag` LIKE '%\"". $tag ."\"%' ";
        }

        $result = db()->exec(
            "SELECT m.`id`, m.`title`, m.`pic`, m.`slug`, m.`info`, m.`online_date`, a.`slug` AS `author_slug`, a.`title` AS `author` FROM `" . self::fmTbl() . "` m "

            . ' LEFT JOIN `'. fAuthor::fmTbl() .'` a ON a.`id` = m.`author_id` '
            . $condition . "  ORDER BY m.`online_date` DESC, m.`insert_ts` DESC LIMIT " . $limit
        );

        return $result;
    }

    /**
     * get a next press
     *
     * @param int $press_id     - current
     * @param int $category_id - category
     *
     * @return string
     */
    static function load_next($cu, $category_id = 0, $col = 'id')
    {

        $condition = " WHERE `status` = '" . self::ST_ON . "' AND online_date <= '". date('Y-m-d') ."' AND `". $col ."` >= '" . $cu[$col] . "' AND `id` != '". $cu['id'] ."' ";

        if ($category_id != 0) {
            $condition.= " AND `category_id`='" . $category_id . "' ";
        }

        $rows = db()->exec("SELECT `id`, `slug`, `title` FROM `" . self::fmTbl() . "` " . $condition . " ORDER BY `". $col ."` ASC, `id` DESC  LIMIT 1 ");

        if (count($rows) != 1) {
            return null;
        }
        else {
            return array('id' => $rows[0]['id'], 'title' => $rows[0]['title']);
        }
    }
    /**
     * get a prev press
     *
     * @param int $cu     - current
     * @param int $category_id - category
     *
     * @return string
     */
    static function load_prev($cu, $category_id = 0, $col = 'id')
    {

        $condition = " WHERE `status` = '" . self::ST_ON . "' AND online_date <= '". date('Y-m-d') ."' AND `". $col ."` <= '" . $cu[$col] . "' AND `id` != '". $cu['id'] ."' ";

        if ($category_id != 0) {
            $condition.= " AND `category_id`='" . $category_id . "' ";
        }

        $rows = db()->exec("SELECT `id`, `slug`, `title` FROM `" . self::fmTbl() . "` " . $condition . " ORDER BY `". $col ."` DESC, `id` DESC LIMIT 1 ");

        if (count($rows) != 1) {
            return null;
        }
        else {
            return array('id' => $rows[0]['id'], 'title' => $rows[0]['title']);
        }
    }
}
