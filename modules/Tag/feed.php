<?php

namespace F3CMS;

/**
 * Architecture Purpose
 * - Owner-side Feed for tag entities, covering tag option projection, related-tag reads,
 *   detail payload lookup, and published-press counter synchronization.
 *
 * Design Intent
 * - Keep tag semantics close to the Tag module even when it reuses the shared Feed base,
 *   so content-classification truth, relation reads, and published usage counts are not
 *   flattened into generic lookup helpers.
 *
 * Strict Constraints
 * - Tag relation and counter semantics belong to this module boundary and must not be
 *   widened into a generic taxonomy service that starts owning other modules' business
 *   decisions.
 * - Cross-module reads may reference Press publication state, but the Tag module must
 *   remain responsible for its own counter and lookup projections.
 *
 * Porting Guidelines
 * - Port as the owner-side Tag Feed for classification lookup and usage aggregation.
 * - Preserve the split between shared Feed mechanics and Tag-owned detail, relation,
 *   option, and counter-maintenance semantics.
 */
class fTag extends Feed
{
    const MTB    = 'tag';
    const ST_ON  = 'Enabled';
    const ST_OFF = 'Disabled';

    const PAGELIMIT = 1000;

    const BE_COLS = 'm.id,l.title,m.slug,m.cate_id,m.counter';

    /**
     * Architecture Role
     * - Provide the Tag module's post-save hook for future relation-driven refresh work.
     *
     * Strict Constraints
     * - Even when currently thin, this hook remains Tag-owned lifecycle surface; do not
     *   relocate later tag-side renewal semantics into generic save helpers.
     *
     * Porting Note
     * - Port as the Tag post-save extension point over shared Feed save flow.
     */
    public static function handleSave($req)
    {
        if (0 != $req['id']) {
            // fPress::batchRenew('tag', $req['id']);
        }

        return 1;
    }

    /**
     * Architecture Role
     * - Read tag-to-tag related entities through the module-owned related relation table.
     *
     * Strict Constraints
     * - Related-tag semantics belong to the Tag module and should not be hidden behind
     *   generic relation traversal that loses tag status rules.
     *
     * Porting Note
     * - Port as a related-tag lookup over the tag relation table.
     */
    public static function lotsRelated($pid)
    {
        $pk = 'tag_id';
        $fk = 'related_id';

        $filter = [
            'r.' . $pk => $pid,
            't.status' => self::ST_ON,
        ];

        return mh()->select(self::fmTbl('related') . '(r)', ['[>]' . self::fmTbl() . '(t)' => ['r.related_id' => 'id']], ['t.id', 't.slug', 't.title'], $filter);
    }

    /**
     * Architecture Role
     * - Project tags into a lightweight option list keyed by title or slug search.
     *
     * Strict Constraints
     * - Option projection remains Tag-owned lookup behavior; do not duplicate these tag
     *   search semantics in callers.
     *
     * Porting Note
     * - Port as a bounded option query over tag title/slug fields.
     */
    public static function get_opts($query = '', $column = 'title')
    {
        $filter = ['LIMIT' => 100];

        if ('' != $query) {
            $filter['OR']['title[~]'] = $query;
            $filter['OR']['slug[~]']  = $query;
        }

        return mh()->select(self::fmTbl(), ['id', $column], $filter);
    }

    /**
     * Architecture Role
     * - Read the auxiliary detail payload attached to one tag entity.
     *
     * Strict Constraints
     * - Tag detail retrieval stays with the Tag module because it reflects tag-owned
     *   extended content, not a generic metadata bag.
     *
     * Porting Note
     * - Port as a tag-detail lookup with selectable projection scope.
     */
    public static function detail($pid, $type = 'all')
    {
        if ('all' == $type) {
            $cols = '*';
        } else {
            $cols = '`cover`, `banner`, `info`';
        }

        $rows = self::exec('SELECT ' . $cols . ' FROM `' . self::fmTbl('detail') . '` WHERE `parent_id`=:pid LIMIT 1 ', [':pid' => $pid]);

        if (1 != count($rows)) {
            return null;
        } else {
            return $rows[0];
        }
    }

    /**
     * Architecture Role
     * - Recompute and persist the published Press usage count for one tag.
     *
     * Strict Constraints
     * - The Tag module owns its counter column even though the count depends on Press
     *   publication state; do not move this writeback into generic aggregation helpers.
     *
     * Porting Note
     * - Port as a tag-counter refresh command that counts only published/changed Press
     *   relations before updating the tag row.
     */
    public static function setPressCnt($pid)
    {
        $cnt = self::exec('SELECT COUNT(r.`press_id`) AS `cnt` FROM `' . fPress::fmTbl('tag') . '` AS `r` ' .
            ' INNER JOIN `' . fPress::fmTbl() . '` AS `m` ON `r`.`press_id` = `m`.`id` AND `m`.`status` IN (\'' . fPress::ST_PUBLISHED . '\', \'' . fPress::ST_CHANGED . '\') ' .
            'WHERE `r`.`tag_id` = :pid LIMIT 1', [':pid' => $pid], true);
        $cnt = ($cnt) ? $cnt['cnt'] * 1 : 0;

        $rtn = mh()->update(self::fmTbl(), [
            'counter' => $cnt,
        ], [
            'id' => $pid,
        ]);

        return self::chkErr($rtn->rowCount());
    }
}
