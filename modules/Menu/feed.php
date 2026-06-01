<?php

namespace F3CMS;

/**
 * Architecture Purpose
 * - Owner-side Feed for menu tree persistence, hierarchical ordering, parent-child
 *   rewiring, and menu option projection in the backend site-navigation domain.
 *
 * Design Intent
 * - Keep structural menu ownership inside the Menu module even when it reuses the
 *   shared Feed base, so tree sorting, parent reassignment, cloned subtree creation,
 *   and orphan cleanup do not drift into generic utilities or UI-side helpers.
 *
 * Strict Constraints
 * - Hierarchical menu semantics belong to this module boundary and must not be widened
 *   into a generic tree engine that starts owning unrelated content structures.
 * - Parent/child maintenance, deletion cleanup, and option projection must remain tied
 *   to Menu entity truth rather than being reconstructed ad hoc by callers.
 *
 * Porting Guidelines
 * - Port as the owner-side Feed for backend menu-tree persistence and navigation
 *   structure maintenance.
 * - Preserve the split between shared Feed mechanics and Menu-owned tree shaping,
 *   deletion cleanup, and option projection semantics.
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
     * Architecture Role
     * - Provide the canonical backend menu listing result using the module-owned sorted
     *   tree projection instead of generic paginated SQL rows.
     *
     * Strict Constraints
     * - Menu hierarchy presentation belongs to this Feed boundary; do not replace it
     *   with flat caller-side pagination when the tree structure is still required.
     *
     * Porting Note
     * - Port as the menu-tree listing entry that returns sorted hierarchical subsets.
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
     * Architecture Role
     * - Read menu rows by parent boundary with localized titles and optional disabled
     *   nodes for backend maintenance flows.
     *
     * Strict Constraints
     * - Parent-scoped menu retrieval remains a Menu module concern; do not rebuild the
     *   join and status rules across callers.
     *
     * Porting Note
     * - Port as a localized menu read query scoped by parent_id and force flag.
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
     * Architecture Role
     * - Update the stored sort order of one menu node.
     *
     * Strict Constraints
     * - Sort-order mutation is part of Menu tree ownership; do not scatter this write
     *   across controllers or generic reorder helpers.
     *
     * Porting Note
     * - Port as a focused menu-node sorter update primitive.
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
     * Architecture Role
     * - Reassign the parent node of one menu item within the menu tree.
     *
     * Strict Constraints
     * - Parent rewiring remains a Menu-owned structural write; do not delegate this to
     *   generic tree utilities that ignore menu-specific persistence context.
     *
     * Porting Note
     * - Port as a focused menu-node parent update primitive.
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
     * Architecture Role
     * - Project the menu tree into a flattened option list for selection UIs.
     *
     * Strict Constraints
     * - Option projection must preserve menu hierarchy ownership and should not be
     *   regenerated inconsistently in views or request handlers.
     *
     * Porting Note
     * - Port as a flattened option-list projection over the sorted menu tree.
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
     * Architecture Role
     * - Flatten a hierarchical menu row set into the option-list accumulator.
     *
     * Strict Constraints
     * - This recursion helper exists only to preserve Menu projection semantics; do not
     *   widen it into a generic tree traversal helper.
     *
     * Porting Note
     * - Port as a local recursive flattener for menu option projection.
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
     * Architecture Role
     * - Clone one menu node into a new parent location while reusing the Menu save path.
     *
     * Strict Constraints
     * - Clone semantics remain Menu-owned structural behavior; do not move them into a
     *   generic duplication helper that loses parent-boundary meaning.
     *
     * Porting Note
     * - Port as a menu-node clone primitive that resets identity and rewires parent_id.
     */
    public static function cloneMenu($menu, $parent_id)
    {
        unset($menu['id']);
        $menu['parent_id'] = $parent_id;

        return self::save($menu);
    }

    /**
     * Architecture Role
     * - Declare the fixed menu nodes that backend tree editing must not mutate freely.
     *
     * Strict Constraints
     * - Frozen-node policy belongs to Menu structural ownership and should not be hidden
     *   in UI-only code.
     *
     * Porting Note
     * - Port as static protected-node metadata for menu maintenance flows.
     */
    public static function freezeNode()
    {
        return [1, 2, 16];
    }

    /**
     * Architecture Role
     * - Delete one menu node and trigger the module-owned orphan cleanup pass.
     *
     * Strict Constraints
     * - Hard delete and follow-up tree cleanup are Menu persistence responsibilities;
     *   do not separate them into unrelated deletion and repair flows.
     *
     * Porting Note
     * - Port as a menu delete command that immediately normalizes orphaned descendants.
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

    /**
     * Architecture Role
     * - Remove orphaned menu and menu_lang rows until the menu tree reaches a stable
     *   parent-child state.
     *
     * Strict Constraints
     * - Orphan cleanup belongs to Menu structural ownership and should not be pushed to
     *   generic maintenance jobs that no longer understand menu hierarchy truth.
     *
     * Porting Note
     * - Port as a bounded recursive cleanup pass over orphaned menu nodes and localized
     *   rows.
     */
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
