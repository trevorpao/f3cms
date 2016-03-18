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

    static function getAll()
    {

        $result = db()->exec("SELECT a.id, a.title, a.last_ts, a.pic, a.status, a.slug FROM `" . self::fmTbl() . "` a ORDER BY `online_date` DESC ");

        return $result;
    }

    static function load_list($page, $slug)
    {
        $rows = self::map();

        $limit = 10;

        $filter = array(
            'status = :status',
            ':status' => self::ST_ON
        );

        if (!empty($slug)) {
            $filter[0] .= fCategory::get_condition($slug);
        }

        $option = array('order' => '`online_date` DESC');

        return $rows->paginate($page-1, $limit, $filter, $option);
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

        $condition = " WHERE `". $col ."` >= '" . $cu[$col] . "' AND `id` != '". $cu['id'] ."' ";

        if ($category_id != 0) {
            $condition.= " AND `category_id`='" . $category_id . "' ";
        }

        $rows = db()->exec("SELECT `slug` FROM `" . self::fmTbl() . "` " . $condition . " ORDER BY `". $col ."` DESC, `id` DESC  LIMIT 1 ");

        if (count($rows) != 1) {
            return null;
        }
        else {
            return $rows[0]['slug'];
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

        $condition = " WHERE `". $col ."` <= '" . $cu[$col] . "' AND `id` != '". $cu['id'] ."' ";

        if ($category_id != 0) {
            $condition.= " AND `category_id`='" . $category_id . "' ";
        }

        $rows = db()->exec("SELECT `slug` FROM `" . self::fmTbl() . "` " . $condition . " ORDER BY `". $col ."` ASC, `id` ASC LIMIT 1 ");

        if (count($rows) != 1) {
            return null;
        }
        else {
            return $rows[0]['slug'];
        }
    }
}
