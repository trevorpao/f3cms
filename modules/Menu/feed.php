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
        $lang = Module::_lang();

        $filter = [
            'ORDER' => ['c.sorter' => 'ASC', 'c.id' => 'ASC'],
            'cl.lang' => $lang,
            'pl.lang' => $lang
        ];

        if ($parent_id != - 1) {
            $filter['c.parent_id'] = $parent_id;
        }

        $rows = mh()->select(self::fmTbl().'(c)', [
            '[><]'. self::fmTbl() .'(pl)' => ['c.parent_id' => 'parent_id'],
            '[><]'. self::fmTbl() .'(cl)' => ['c.id' => 'parent_id'],
        ], [
            'c.id', 'cl.title', 'c.uri', 'c.parent_id', 'c.summary', 'pl.title(parent)'
        ], $filter);

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
