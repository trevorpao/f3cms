<?php

namespace F3CMS;

/**
 * Architecture Purpose
 * - Shared relation-binding trait for common subordinate relation writes, reads, and counter updates used by Feed-style owners.
 *
 * Design Intent
 * - Keep repeated relation-table mechanics in one shared trait while leaving entity-specific relation policy and duty judgment in the owning module.
 *
 * Strict Constraints
 * - This trait must not decide business ownership, relation semantics, or module-specific side effects beyond the shared bind/count patterns it already encapsulates.
 * - If relation behavior depends on one entity's policy, keep that policy in the owner instead of widening this shared trait.
 *
 * Porting Guidelines
 * - Port as shared relation persistence support attached to the owning Feed or repository layer.
 * - Preserve the split between generic relation-table mechanics here and entity-owned relation policy in the caller.
 */
trait Belong
{
    /**
     * Architecture Purpose
     * - Apply the shared bind/toggle pattern for a subordinate relation table row.
     *
     * Design Intent
     * - Centralize the common relation-row enable/disable or counter-increment flow so owner Feeds reuse one persistence-side pattern.
     *
     * Strict Constraints
     * - This method executes shared relation mechanics only; it must not infer higher-level entity business meaning beyond the passed relation name.
     *
     * Porting Guidelines
     * - Port as a relation-table update helper inside the owning persistence layer, not as a controller/service endpoint.
     */
    public static function bind($subTbl, $target_id, $member_id = 0)
    {
        // Initialize the current class and sub-table details.
        [$that, $sub, $pk, $fk] = self::_init(get_called_class(), $subTbl);

        // If no member ID is provided, use a default value.
        if (0 == $member_id) {
            $member_id = fMember::_CMember();
        }

        // Fetch related data from the database.
        $related = mh()->get($that::fmTbl($subTbl) . '(r)', [
            '[>]' . $that::fmTbl() . '(t)'       => ['r.' . $pk => 'id'],
        ], ['t.id', 'r.status'], [
            'r.member_id' => $member_id,
            'r.' . $pk    => $target_id,
        ]);

        // Handle the case where related data exists or does not exist.
        if ($related) {
            if ('claps' == $subTbl) {
                mh()->update($that::fmTbl($subTbl), [
                    'cnt[+]' => 1,
                ], [
                    'member_id' => $member_id,
                    $pk         => $target_id,
                    'cnt[<]'    => 100, // TODO: use fOption
                ]);
            } elseif ('hits' == $subTbl) {
                mh()->update($that::fmTbl($subTbl), [
                    'cnt[+]' => 1,
                ], [
                    'member_id' => $member_id,
                    $pk         => $target_id,
                ]);
            } else {
                if ('Enabled' == $related['status'] && 'seen' != $subTbl) {
                    mh()->update($that::fmTbl($subTbl), [
                        'status' => 'Disabled',
                    ], [
                        'member_id' => $member_id,
                        $pk         => $target_id,
                    ]);
                }
            }
        } else {
            mh()->insert($that::fmTbl($subTbl), [
                'member_id' => $member_id,
                $pk         => $target_id,
                'status'    => 'Enabled',
                'insert_ts' => date('Y-m-d H:i:s'),
            ]);
        }

        // Additional logic for specific sub-tables like 'seen' or 'favo'.
        if (in_array($subTbl, ['seen', 'favo'])) {
            $that::setCnt($subTbl, $target_id);
        }

        // Special handling for the 'claps' sub-table.
        if ('claps' == $subTbl) {
            $that::setClapCnt($target_id);
        }
    }

    /**
        * Architecture Purpose
        * - Recalculate and persist the clap aggregate for an owner row.
        *
        * Design Intent
        * - Keep shared aggregate-counter refresh close to relation persistence so repeated counter math is not duplicated across modules.
        *
        * Strict Constraints
        * - The aggregate write stays persistence-side only; business decisions about when clap semantics apply remain module-owned.
        *
        * Porting Guidelines
        * - Port as a persistence aggregate refresh helper tied to relation writes.
     */
    public static function setClapCnt($pid)
    {
        // Initialize the current class and sub-table details.
        [$that, $sub, $pk, $fk] = self::_init(get_called_class(), 'member');

        // Calculate the total clap count from the database.
        $cnt = mh()->get($that::fmTbl('claps'), ['cnt' => MHelper::raw('SUM(<cnt>)')], [$pk => $pid]);
        $cnt = ($cnt) ? $cnt['cnt'] * 1 : 0;

        // Update the clap count in the main table.
        $rtn = mh()->update($that::fmTbl(), [
            'claps' => $cnt,
        ], [
            'id' => $pid,
        ]);

        // Check for errors and return the result.
        return $that::chkErr($rtn->rowCount());
    }

    /**
        * Architecture Purpose
        * - Refresh a shared subordinate-count field on the owner row.
        *
        * Design Intent
        * - Keep count synchronization near relation persistence so owner rows expose stable cached counts without duplicating query logic in callers.
        *
        * Strict Constraints
        * - This helper updates stored counts only; it must not become a policy engine for relation visibility or feature-specific counting semantics.
        *
        * Porting Guidelines
        * - Port as a persistence-side aggregate update helper, keeping caller-specific filters and business triggers outside the shared trait.
     */
    public static function setCnt($pid, $subTbl = 'video', $filter = [], $type = 0)
    {
        // Initialize the current class and sub-table details.
        [$that, $sub, $pk, $fk] = self::_init(get_called_class(), $subTbl);

        // Perform different operations based on the type.
        switch ($type) {
            case 1:
                $cnt = $sub::total(
                    array_merge([$pk => $pid], $filter),
                    null, '', 'COUNT(<' . $pk . '>)');
                break;
            default:
                $cnt = $that::total(
                    array_merge([$pk => $pid], $filter),
                    null, $subTbl, 'COUNT(<' . $pk . '>)');
                break;
        }

        // Update the count in the main table.
        $rtn = mh()->update($that::fmTbl(), [
            $subTbl . '_cnt' => $cnt,
        ], [
            'id' => $pid,
        ]);

        // Check for errors and return the result.
        return $that::chkErr($rtn->rowCount());
    }

    /**
        * Architecture Purpose
        * - Read subordinate relation rows joined to their target records.
        *
        * Design Intent
        * - Keep the shared join topology for relation-table reads in one trait instead of scattering relation read SQL across callers.
        *
        * Strict Constraints
        * - This is a persistence helper, not a page/query orchestration surface.
        *
        * Porting Guidelines
        * - Port as a repository-side relation read helper that returns raw relation-linked rows for owner-level consumption.
     */
    public static function lotsSub($subTbl, $pid, $columns = ['t.id', 'title'])
    {
        // Initialize the current class and sub-table details.
        [$that, $sub, $pk, $fk] = self::_init(get_called_class(), $subTbl);

        // Define the filter for the query.
        $filter = [$pk => $pid];

        // Generate the join conditions for the query.
        $join = $sub::genJoin();

        if (!$join) {
            $join = [];
        }
        $join['[>]' . $sub::fmTbl() . '(t)'] = ['r.' . $fk => 'id'];

        // Execute the query and return the results.
        return mh()->select(
            $that::fmTbl($subTbl) . '(r)',
            $join,
            $columns,
            $filter
        );
    }

    /**
        * Architecture Purpose
        * - Read owner rows that match one or more tag relations.
        *
        * Design Intent
        * - Compose tag-based ID lookup with the owner's canonical collection reader instead of duplicating pagination/read flow.
        *
        * Strict Constraints
        * - Tag matching here stays a persistence composition helper; broader search or page semantics remain outside the trait.
        *
        * Porting Guidelines
        * - Port as a convenience wrapper that pipes relation-derived IDs into the owner repository's standard list reader.
     */
    public static function lotsByTag($id, $page = 0, $limit = 6, $cols = '')
    {
        $that = get_called_class();

        return $that::lotsByID($that::byTag($id), $page, $limit, $cols);
    }

    /**
        * Architecture Purpose
        * - Resolve owner-row IDs linked to one or more tags.
        *
        * Design Intent
        * - Keep the shared relation-table lookup and multi-tag intersection pattern centralized at the persistence boundary.
        *
        * Strict Constraints
        * - This helper should return relation-derived IDs only; entity-specific ranking or presentation logic belongs elsewhere.
        *
        * Porting Guidelines
        * - Port as a relation-ID selector that can back higher-level repository reads without collapsing tag policy into generic helpers.
     */
    public static function byTag($id)
    {
        $that = get_called_class();

        if (is_array($id)) {
            // Build a query to count occurrences of each target ID across multiple tags.
            $condi = [];
            foreach ($id as $row) {
                $condi[] = ' SELECT `' . $that::MTB . '_id` FROM `' . $that::fmTbl('tag') . '` WHERE `tag_id`=' . intval($row) . ' ';
            }

            $rows = mh()->query('SELECT `' . $that::MTB . '_id`, COUNT(`' . $that::MTB . '_id`) AS `cnt` FROM (' . implode(' UNION ALL ', $condi) . ') u GROUP by `' . $that::MTB . '_id` HAVING `cnt` > ' . (count($condi) - 1) . ' ')->fetchAll(\PDO::FETCH_ASSOC);
        } else {
            $rows = mh()->select($that::fmTbl('tag') . '(r)', ['r.' . $that::MTB . '_id'], ['r.tag_id' => $id]);
        }

        // Extract and return the list of target IDs.
        return \__::pluck($rows, '' . $that::MTB . '_id');
    }

    /**
        * Architecture Purpose
        * - Replace the owner's many-to-many relation rows with a new shared relation set.
        *
        * Design Intent
        * - Keep bulk relation replacement and optional sorter persistence in one shared primitive for Feed owners.
        *
        * Strict Constraints
        * - This helper performs relation-table replacement only; authorization, semantic validation, and cross-entity coordination stay outside the trait.
        *
        * Porting Guidelines
        * - Port as a persistence batch-upsert or replace helper that remains called from the owner boundary.
     */
    public static function saveMany($subTbl, $pid, $rels = [], $reverse = false, $sortable = false)
    {
        if (empty($rels)) {
            return false;
        }

        // Initialize the current class and sub-table details.
        [$that, $sub, $pk, $fk] = self::_init(get_called_class(), $subTbl);

        // Prepare the data for insertion.
        $data = [];

        if ($reverse) {
            [$fk, $pk] = [$pk, $fk];
        }

        mh()->delete($that::fmTbl($subTbl), [$pk => $pid]);

        foreach ($rels as $idx => $value) {
            if (!empty($value)) {
                $data[$idx] = [
                    $pk => $pid,
                    $fk => $value,
                ];

                if ($sortable) {
                    $data[$idx]['sorter'] = $idx;
                }
            }
        }

        if (!empty($data)) {
            mh()->insert($that::fmTbl($subTbl), $data);
        }

        return 1;
    }

    /**
        * Architecture Purpose
        * - Normalize shared relation metadata needed by this trait's persistence helpers.
        *
        * Design Intent
        * - Keep class, relation Feed, and foreign-key naming derivation in one internal helper so relation helpers stay convention-driven.
        *
        * Strict Constraints
        * - This helper resolves naming only; it must not load owner-side state or make business decisions.
        *
        * Porting Guidelines
        * - Port as an internal naming resolver that preserves F3CMS relation key conventions.
     */
    private static function _init($that, $subTbl)
    {
        // $that = get_called_class();
        $sub  = '\F3CMS\f' . ucfirst($subTbl);

        $pk = $that::MTB . '_id';
        $fk = $subTbl . '_id';

        return [$that, $sub, $pk, $fk];
    }
}
