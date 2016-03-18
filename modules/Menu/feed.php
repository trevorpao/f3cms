<?php
namespace F3CMS;
/**
 * data feed
 */
class fMenu extends Feed
{
    const MTB = "menu";

    const ST_NEW = "New";
    const ST_PAID = "Paid";
    const ST_DONE = "Done";
    const ST_INVALID = "Invalid";

    static function getAll()
    {
        return rMenu::sort_menus(0, 0 , '', 0);
    }

    /**
     * get menus by parent id
     *
     * @param int $parent_id - parent type id
     *
     * @return array
     */
    static function get_menus($parent_id = - 1)
    {

        $condition = "";

        if ($parent_id != - 1) {
            $condition = " where c.parent_id='" . $parent_id . "' ";
        }

        $rows = db()->exec("SELECT c.id, c.title, c.slug, c.parent_id, c.summary, p.title AS parent FROM `" . self::fmTbl() . "` c LEFT JOIN `" . self::fmTbl() . "` p ON p.id=c.parent_id " . $condition . " ORDER BY c.sorter, c.id ");

        return $rows;
    }

    static function update_sorter($pid, $value)
    {
        db()->exec(
            "UPDATE `" . self::fmTbl() . "` SET `sorter`=? WHERE `id`=?",
            parent::_fixAry(array($value, $pid))
        );
    }
}
