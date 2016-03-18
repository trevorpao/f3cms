<?php

namespace F3CMS;

/**
 * Menu
 */
class rMenu extends Reaction
{

    /**
     * get menus in option mode
     *
     * @param int $parent_id - parent type id
     * @param int $level     - level number
     * @param int $level_mod - level string mode
     *
     * @return array
     */
    static function sort_menus($parent_id = 0, $level = 0, $level_mod = '', $flatten = 1)
    {

        $menus = f3()->get('menus');

        if (empty($menus)) {
            $menus = fMenu::get_menus();
            f3()->set('menus', $menus);
        }

        $cates = array();

        foreach ($menus AS $row) {
            if ($row['parent_id'] == $parent_id) {
                if ($level_mod == '') {
                    $row['prefix'] = '';
                }
                else {
                    $row['prefix'] = str_repeat($level_mod, $level+1);
                }
                $row['level'] = $level;
                $row['title'] = $row['prefix'] . $row['title'];
                $subCates = self::sort_menus($row['id'], $level + 1, $level_mod, $flatten);
                if ($flatten == 1) {
                    $cates[] = $row;
                    if (!empty($subCates)) {
                        $cates = array_merge($cates, $subCates);
                    }
                }
                else {
                    $row['rows'] = $subCates;
                    $cates[] = $row;
                }
            }
        }

        return $cates;
    }

    /**
     * save sorter
     * @param  object $f3   - $f3
     * @param  array  $args - uri params
     * @return array        - std json
     */
    function do_update_sorter($f3, $args)
    {
        rStaff::_chkLogin();

        $req = parent::_getReq();

        foreach ($req['data'] as $row) {
            fMenu::update_sorter($row['id'], $row['sorter']);
        }

        return parent::_return(1, $req);
    }

    static function handleRow($row = array())
    {
        $row['menus'] = self::sort_menus(0, 0 , '~');
        return $row;
    }
}
