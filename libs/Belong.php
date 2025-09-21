<?php

namespace F3CMS;

// The Belong trait provides methods for managing relationships between tables.
// It includes functionalities like binding, counting, and saving related data.

trait Belong
{
    /**
     * Binds a sub-table to a target ID and optionally a member ID.
     *
     * @param string $subTbl   The name of the sub-table.
     * @param int    $target_id The ID of the target to bind.
     * @param int    $member_id The ID of the member (optional).
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
     * Updates the clap count for a specific target ID.
     *
     * @param int $pid The ID of the target to update.
     * @return bool True if the update was successful, false otherwise.
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
     * Sets a count for a specific sub-table and target ID.
     *
     * @param int    $pid     The ID of the target to update.
     * @param string $subTbl  The name of the sub-table.
     * @param array  $filter  Additional filters for the count.
     * @param int    $type    The type of count operation.
     * @return bool True if the update was successful, false otherwise.
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
     * Retrieves a list of sub-table entries related to a target ID.
     *
     * @param string $subTbl  The name of the sub-table.
     * @param int    $pid     The ID of the target.
     * @param array  $columns The columns to retrieve.
     * @return array The list of related entries.
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
     * Retrieves a list of target IDs associated with a specific tag.
     *
     * @param int|array $id The ID or list of IDs of the tag(s).
     * @return array The list of associated target IDs.
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
     * Saves multiple relationships for a sub-table and target ID.
     *
     * @param string $subTbl  The name of the sub-table.
     * @param int    $pid     The ID of the target.
     * @param array  $rels    The relationships to save.
     * @param bool   $reverse Whether to reverse the relationships.
     * @param bool   $sortable Whether the relationships are sortable.
     * @return int The number of rows affected.
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
     * Initializes the current class and sub-table details.
     *
     * @param string $that    The name of the current class.
     * @param string $subTbl  The name of the sub-table.
     * @return array An array containing the class, sub-table, primary key, and foreign key.
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
