<?php
namespace F3CMS;

/**
 * data feed
 */
class fMenu extends Feed
{
    const MTB = 'menu';

    const ST_ON = "Enabled";
    const ST_OFF = "Disabled";

    const PV_R = 'use.menu.config';
    const PV_U = 'use.menu.config';
    const PV_D = 'use.menu.config';

    /**
     * @param $query
     * @param $page
     * @param $limit
     */
    public static function limitRows($query = '', $page = 0, $limit = 1000, $cols = '')
    {
        return array(
            'subset' => rMenu::sort_menus(0, 0, '', 0, 1),
            'limit'  => $limit,
            'pos'    => 0,
            'sql'    => ((f3()->get('DEBUG') === 0) ? '': mh()->last())
        );
    }

    /**
     * get menus by parent id
     *
     * @param  int     $parent_id - parent type id
     * @return array
     */
    public static function get_menus($parent_id = -1, $force = 0)
    {
        $lang = Module::_lang();

        $condition = ' where 1 ';
        $join = ' LEFT JOIN `' . self::fmTbl('lang') . '` l2 ON l2.parent_id=c.parent_id AND l2.lang = \''. $lang .'\' ';

        if ($parent_id != - 1) {
            $condition .= ' AND c.parent_id=\'' . $parent_id . '\' ';
        }

        if (!$force) {
            $condition .= ' AND c.`status` = \'' . self::ST_ON . '\' ';
        }

        $join .= ' LEFT JOIN `' . self::fmTbl('lang') . '` l1 ON l1.parent_id=c.id AND l1.lang = \''. $lang .'\' ';

        $rows = mh()->query('SELECT c.id, l1.title, c.uri, c.theme, c.blank, l1.badge, c.color, c.parent_id, l2.title AS parent FROM `' . self::fmTbl() . '` c ' . $join . $condition . ' ORDER BY c.sorter, c.id ')->fetchAll(\PDO::FETCH_ASSOC);

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
                ':id' => $pid
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
                ':id' => $pid
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
        $menus = rMenu::sort_menus(0, 0, '--', 0);
        $rtn = [];

        if (is_array($menus) && !empty($menus)) {
            self::recursion($menus, $rtn);
        }

        return $rtn;
    }

    public static function recursion($rows, &$rtn)
    {
        foreach ($rows as $row) {
            $rtn[] = ['id' => $row['id'], 'title' => $row['title']];
            if (!empty($row['rows'])) {
                self::recursion($row['rows'], $rtn);
            }
        }
    }

    public static function freezeNode()
    {
        return [1, 2, 15, 16, 17, 24];
    }
}
