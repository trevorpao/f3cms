<?php

namespace F3CMS;

/**
 * data feed
 */
class fMenu extends Feed
{
    const MTB = 'menu';

    const ST_ON  = 'Enabled';
    const ST_OFF = 'Disabled';

    const PV_R = 'mgr.site';
    const PV_U = 'mgr.site';
    const PV_D = 'mgr.site';

    const PAGELIMIT = 1000;

    const HARD_DEL = 1;

    /**
     * @param $query
     * @param $page
     * @param $limit
     */
    public static function limitRows($query = '', $page = 0, $limit = 0, $cols = '')
    {
        if (0 == $limit) {
            $limit = self::PAGELIMIT;
        }

        return [
            'subset' => rMenu::sort_menus(0, 0, '', 0, 1),
            'limit'  => $limit,
            'pos'    => 0,
            'sql'    => ((0 === f3()->get('DEBUG')) ? '' : mh()->last()),
        ];
    }

    /**
     * get menus by parent id
     *
     * @param int $parent_id - parent type id
     *
     * @return array
     */
    public static function get_menus($parent_id = -1, $force = 0)
    {
        $lang = Module::_lang();

        $cols      = 'c.id, l1.title, c.uri, c.theme, c.blank, l1.badge, c.color, c.icon, c.parent_id, l2.title AS parent';
        $condition = ' where 1 ';
        $join      = ' LEFT JOIN `' . self::fmTbl('lang') . '` l2 ON l2.parent_id=c.parent_id AND l2.lang = \'' . $lang . '\' ';

        if (-1 != $parent_id) {
            $condition .= ' AND c.parent_id=\'' . $parent_id . '\' ';
        }

        if (!$force) {
            $condition .= ' AND c.`status` = \'' . self::ST_ON . '\' ';
        } else {
            $cols .= ', c.status';
        }

        $join .= ' LEFT JOIN `' . self::fmTbl('lang') . '` l1 ON l1.parent_id=c.id AND l1.lang = \'' . $lang . '\' ';

        $rows = mh()->query('SELECT ' . $cols . ' FROM `' . self::fmTbl() . '` c ' . $join . $condition . ' ORDER BY c.sorter, c.id ')->fetchAll(\PDO::FETCH_ASSOC);

        return $rows;
    }

    /**
     * @param $pid
     * @param $value
     */
    public static function update_sorter($pid, $sorter)
    {
        mh()->query(
            'UPDATE `' . self::fmTbl() . '` SET `sorter`=:sorter WHERE `id`=:id',
            [
                ':sorter' => $sorter,
                ':id'     => $pid,
            ]
        );
    }

    /**
     * @param $pid
     * @param $value
     */
    public static function update_parent($pid, $parent = 0)
    {
        mh()->query(
            'UPDATE `' . self::fmTbl() . '` SET `parent_id`=:parent_id WHERE `id`=:id',
            [
                ':parent_id' => $parent,
                ':id'        => $pid,
            ]
        );
    }

    /**
     * @param $query
     * @param $page
     * @param $limit
     */
    public static function getOpts($query = '', $column = 'title')
    {
        $menus = rMenu::sort_menus(0, 0, ' 　 　', 0);
        $rtn   = [];

        if (is_array($menus) && !empty($menus)) {
            self::recursion($menus, $rtn);
        }

        return $rtn;
    }

    /**
     * @param $rows
     * @param $rtn
     */
    public static function recursion($rows, &$rtn)
    {
        foreach ($rows as $row) {
            $rtn[] = ['id' => $row['id'], 'title' => $row['title']];
            if (!empty($row['rows'])) {
                self::recursion($row['rows'], $rtn);
            }
        }
    }

    /**
     * @param object $menu
     * @param int    $parent_id
     *
     * @return int new menu id
     */
    public static function cloneMenu($menu, $parent_id)
    {
        unset($menu['id']);
        $menu['parent_id'] = $parent_id;

        return self::save($menu);
    }

    public static function freezeNode()
    {
        return [1, 2, 16];
    }

    /**
     * delete one row
     *
     * @param int $pid
     */
    public static function delRow($pid, $sub_table = '')
    {
        $data = mh()->delete(self::fmTbl(), [
            'id' => $pid,
        ]);

        if (self::chkErr($data->rowCount()) > 0) {
            self::removeOrphanNode();

            return 1;
        } else {
            return 0;
        }
    }

    private static function removeOrphanNode()
    {
        $rowCount = self::exec('DELETE FROM `' . self::fmTbl() . '` WHERE `parent_id` IN (SELECT l.`parent_id` FROM `' . self::fmTbl() . '` l LEFT JOIN `' .
            self::fmTbl() . '` a ON a.`id`=l.`parent_id` WHERE a.`id` is NULL AND l.`parent_id` != 0);');

        if (self::chkErr($rowCount) > 0) {
            $rowCount = self::exec('DELETE FROM `' . self::fmTbl('lang') . '` WHERE `parent_id` IN (SELECT l.`parent_id` FROM `' . self::fmTbl('lang') . '` l LEFT JOIN `' .
                self::fmTbl() . '` a ON a.`id`=l.`parent_id` WHERE a.`id` is NULL);');

            if (self::chkErr($rowCount) > 0) {
                self::removeOrphanNode();
            }
        }
    }
}
