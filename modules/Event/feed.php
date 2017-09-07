<?php
namespace F3CMS;
/**
 * data feed
 */
class fEvent extends Feed
{
    const MTB = "event";
    const ST_ON = "Enabled";
    const ST_OFF = "Disabled";

    static function getAll()
    {

        $result = db()->exec("SELECT a.id, a.title, a.last_ts, a.pic, a.status, a.slug FROM `" . self::fmTbl() . "` a ");

        return $result;
    }

    static function get_opts($query)
    {
        $condition = " WHERE `status` = '". self::ST_ON ."' ";

        return db()->exec("SELECT `id`, `title` FROM `". self::fmTbl() ."` " . $condition . " LIMIT 30 ");
    }


    static function load_homepage_list($limit = 4)
    {

        $condition = " WHERE `status` = '" . self::ST_ON . "' ";
        // AND `on_homepage` = 'Yes'

        $result = db()->exec("SELECT `id`, `title`, `pic`, `slug`, `info`, `end_date` FROM `" . self::fmTbl() . "` " . $condition . "  ORDER BY `end_date` DESC, `insert_ts` DESC LIMIT " . $limit);

        return $result;
    }

    /**
     * get a next event
     *
     * @param int $event_id     - current id
     * @param int $category_id - current id
     *
     * @return string
     */
    static function load_next($event_id, $category_id = 0)
    {

        $condition = " WHERE `id` > '" . $event_id . "' ";

        if ($category_id != 0) {
            $condition.= " AND `category_id`='" . $category_id . "' ";
        }

        $rows = db()->exec("SELECT `slug` FROM `" . self::fmTbl() . "` " . $condition . " ORDER BY id ASC  LIMIT 1 ");

        if (count($rows) != 1) {
            return null;
        }
        else {
            return $rows[0]['slug'];
        }
    }

    /**
     * get a prev event
     *
     * @param int $event_id     - current id
     * @param int $category_id - current id
     *
     * @return string
     */
    static function load_prev($event_id, $category_id = 0)
    {

        $condition = " WHERE `id` < '" . $event_id . "' ";

        if ($category_id != 0) {
            $condition.= " AND `category_id`='" . $category_id . "' ";
        }

        $rows = db()->exec("SELECT `slug` FROM `" . self::fmTbl() . "` " . $condition . " ORDER BY id DESC  LIMIT 1 ");

        if (count($rows) != 1) {
            return null;
        }
        else {
            return $rows[0]['slug'];
        }
    }
}
