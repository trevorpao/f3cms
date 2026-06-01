<?php

namespace F3CMS;

/**
 * Architecture Purpose
 * - Owner-side Reaction boundary for menu tree orchestration, covering backend save,
 *   nested menu retrieval, deep clone, sorter updates, and response shaping around the
 *   Menu module's hierarchical navigation model.
 *
 * Design Intent
 * - Keep hierarchical navigation orchestration close to the Menu owner module while
 *   preserving the FORKS split: this Reaction owns request validation, auth gates,
 *   tree-flow coordination, and transport shaping; fMenu keeps menu persistence,
 *   tree update semantics, and tag relation truth.
 *
 * Strict Constraints
 * - This Reaction must not absorb generic tree-helper responsibility or move menu
 *   ownership rules into shared libs.
 * - Structural mutation semantics, persistence truth, and frozen-node policy remain
 *   owned by the Menu feed and collaborators.
 *
 * Porting Guidelines
 * - Port as the request/orchestration layer for hierarchical menu management.
 * - Preserve the split between request/auth/response behavior here and menu/tree
 *   persistence rules in the Menu owner module.
 */
class rMenu extends Reaction
{
    /**
     * Architecture Role
     * - Save a backend menu form through the owner-side request boundary.
     *
     * Strict Constraints
     * - This method validates request shape and access, but menu persistence and parent
     *   ownership rules remain with the Menu feed.
     *
     * Porting Note
     * - Port as the backend menu save entrypoint with owner-side guardrails.
     */
    public function do_save($f3, $args)
    {
        chkAuth(fMenu::PV_U);

        $req = parent::_getReq();

        if (!isset($req['id'])) {
            return self::_return(8004);
        }

        if (0 != $req['id'] && $req['id'] == $req['parent_id']) {
            return self::_return(8004);
        }

        if (0 != $req['id'] && 0 == $req['parent_id']) {
            return self::_return(8004);
        }

        $id = fMenu::save($req);

        return self::_return(1, ['id' => $id]);
    }

    /**
     * Architecture Role
     * - Project the menu tree into flattened or nested option structures for owner-side
     *   menu selection and tree rendering flows.
     *
     * Strict Constraints
     * - This method shapes traversal output, but authoritative menu/tree data remains in
     *   fMenu and cached runtime state.
     *
     * Porting Note
     * - Port as a menu-tree projection helper owned by the Menu reaction layer.
     */
    public static function sort_menus($parent_id = 0, $level = 0, $level_mod = '', $flatten = 1, $force = 0)
    {
        $menus = f3()->get('menus');

        if (empty($menus)) {
            $menus = fMenu::get_menus(-1, $force);
            f3()->set('menus', $menus);
        }

        $cates = [];

        foreach ($menus as $row) {
            if ($row['parent_id'] == $parent_id) {
                if ('' == $level_mod) {
                    $row['prefix'] = '';
                } else {
                    $row['prefix'] = str_repeat($level_mod, $level + 1);
                }
                $row['level'] = $level;
                $row['title'] = $row['prefix'] . $row['title'];
                $subCates     = self::sort_menus($row['id'], $level + 1, $level_mod, $flatten);
                if (1 == $flatten) {
                    $cates[] = $row;
                    if (!empty($subCates)) {
                        $cates = array_merge($cates, $subCates);
                    }
                } else {
                    $row['rows'] = $subCates;
                    $cates[]     = $row;
                }
            }
        }

        return $cates;
    }

    /**
     * Architecture Role
     * - Return a menu subtree for frontend/consumer lookup with language-aware cache
     *   orchestration at the request boundary.
     *
     * Strict Constraints
     * - Cache orchestration may happen here, but menu structure truth and query semantics
     *   remain owned by Menu collaborators.
     *
     * Porting Note
     * - Port as the cached menu-subtree retrieval endpoint.
     */
    public function do_lotsMenu($f3, $args)
    {
        parent::_lang($args);
        f3()->set('lang', parent::_lang());

        $req = parent::_getReq();

        if (empty($req['menuID'])) {
            return parent::_return(8002);
        }

        $fc = new FCHelper('menu');

        $rtn = $fc->get('menu_' . parent::_lang() . '_' . $req['menuID'], 1); // 1 mins

        if (empty($rtn)) {
            $rtn = self::sort_menus($req['menuID'], 0, '', 0);
            $fc->save('menu_' . parent::_lang() . '_' . $req['menuID'], json_encode($rtn));
        } else {
            $rtn = json_decode(preg_replace('/<!--(?!\s*(?:\[if [^\]]+]|<!|>))(?:(?!-->).)*-->/s', '', $rtn), true);
        }

        return parent::_return(1, $rtn);
    }

    /**
     * Architecture Role
     * - Trigger deep cloning of a menu subtree from the owner-side request boundary.
     *
     * Strict Constraints
     * - This method validates access and dispatch only; clone semantics remain in the
     *   Menu owner collaborators.
     *
     * Porting Note
     * - Port as the backend menu subtree clone entrypoint.
     */
    public static function do_deepClone()
    {
        chkAuth(fMenu::PV_U);

        $req = parent::_getReq();

        if (!isset($req['id'])) {
            return self::_return(8004);
        }

        self::deepCloneMenu($req['id']);

        return parent::_return(1);
    }

    /**
     * Architecture Role
     * - Recursively duplicate a menu node and its descendants while preserving tree
     *   ownership through Menu collaborators.
     *
     * Strict Constraints
     * - This helper coordinates recursion only; source lookup and clone persistence stay
     *   with fMenu.
     *
     * Porting Note
     * - Port as a reaction-owned recursive clone coordinator for menu trees.
     */
    public static function deepCloneMenu($menuID, $parent_id = 0)
    {
        $menu = fMenu::one($menuID);
        if (!$menu) {
            return false;
        }

        $newMenuId = fMenu::cloneMenu($menu, $parent_id);

        // 複製子節點
        $childMenus = self::sort_menus($menuID, 0, '', 0);
        foreach ($childMenus as $childMenu) {
            self::deepCloneMenu($childMenu['id'], $newMenuId);
        }
    }

    /**
     * Architecture Role
     * - Persist drag-sorter and parent reassignment changes for the menu tree from the
     *   backend request boundary.
     *
     * Strict Constraints
     * - This method coordinates sorter payload application, but authoritative update rules
     *   remain with the Menu feed.
     *
     * Porting Note
     * - Port as the backend menu tree reorder entrypoint.
     */
    public function do_update_sorter($f3, $args)
    {
        chkAuth(fMenu::PV_U);

        $req = parent::_getReq();

        if (is_array($req['sort']) && !empty($req['sort'])) {
            foreach ($req['sort'] as $row) {
                fMenu::update_sorter($row['id'], $row['sorter']);
            }
        }

        fMenu::update_parent($req['itemID'], $req['parentID']);

        return parent::_return(1, $req);
    }

    /**
     * Architecture Role
     * - Enrich a menu row with owner-side related tag data before transport.
     *
     * Strict Constraints
     * - Transport shaping may happen here, but tag relation truth remains with the Menu
     *   feed.
     *
     * Porting Note
     * - Port as a row-shaping helper for menu payloads.
     */
    public static function handleRow($row = [])
    {
        $row['tags'] = fMenu::lotsTag($row['id']);

        return $row;
    }

    /**
     * Architecture Role
     * - Walk a submitted menu tree and apply recursive sorter/parent updates while
     *   respecting frozen-node constraints.
     *
     * Strict Constraints
     * - This helper coordinates recursion and guardrails, but frozen-node policy and
     *   persistence updates remain Menu-owned.
     *
     * Porting Note
     * - Port as a recursive tree-update coordinator for menu nesting changes.
     */
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
