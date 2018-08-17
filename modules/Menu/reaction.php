<?php

namespace F3CMS;

/**
 * Menu
 */
class rMenu extends Reaction
{

    /**
     * save whole form for backend
     * @param object $f3   - $f3
     * @param array  $args - uri params
     */
    public function do_save($f3, $args)
    {

        rStaff::_chkLogin(); // chkAuth($feed::PV_U);

        $req = parent::_getReq();

        if (!isset($req['id'])) {
            return self::_return(8004);
        }

        if ($req['id'] != 0  && $req['id'] == $req['parent_id']) {
            return self::_return(8004);
        }

        if ($req['id'] != 0  && $req['parent_id'] == 0) {
            return self::_return(8004);
        }

        $id = fMenu::save($req);

        return self::_return(1, array('id' => $id));
    }

    /**
     * get menus in option mode
     *
     * @param int $parent_id - parent type id
     * @param int $level     - level number
     * @param int $level_mod - level string mode
     *
     * @return array
     */
    static function sort_menus($parent_id = 0, $level = 0, $level_mod = '', $flatten = 1, $force = 0)
    {

        $menus = f3()->get('menus');

        if (empty($menus)) {
            $menus = fMenu::get_menus(-1, $force);
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

    public function do_lotsMenu($f3, $args)
    {
        $req = parent::_getReq();

        $fc = new FCHelper('menu');

        $rtn = $fc->get('menu_'. $req['menuID'], 1); // 1 mins

        if (empty($rtn)) {
            $rtn = rMenu::sort_menus($req['menuID'], 0 , '', 0);
            $fc->save('menu_'. $req['menuID'], json_encode($rtn));
        }
        else {
            $rtn = json_decode(preg_replace('/<!--(?!\s*(?:\[if [^\]]+]|<!|>))(?:(?!-->).)*-->/s', '', $rtn), true);
        }

        return parent::_return(1, $rtn);
    }

    /**
     * save sorter
     * @param  object $f3   - $f3
     * @param  array  $args - uri params
     * @return array        - std json
     */
    function do_update_sorter($f3, $args)
    {

        rStaff::_chkLogin(); // chkAuth($feed::PV_U);

        $req = parent::_getReq();

        if (is_array($req['data']) && !empty($req['data'])) {
            self::recursion($req['data'], 0);
        }

        return parent::_return(1, $req);
    }

    static function handleRow($row = array())
    {
        $row['tags'] = fMenu::lotsTag($row['id']);
        return $row;
    }

    public static function recursion($rows, $parent = 0)
    {
        foreach ($rows as $row) {
            if (!in_array($row['id'], fMenu::freezeNode()) && $row['id'] != $parent) {
                fMenu::update_sorter($row['id'], $row['sorter'], $parent);
            }

            if (!empty($row['children'])) {
                self::recursion($row['children'], $row['id']);
            }
        }
    }
}
