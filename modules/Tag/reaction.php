<?php

namespace F3CMS;

/**
 * Architecture Purpose
 * - Owner-side Reaction boundary for tag-driven content lookup and backend tag management,
 *   covering public tag listing flows, backend detail/list endpoints, and detail-form
 *   persistence around the Tag module.
 *
 * Design Intent
 * - Keep content classification and tag-facing request orchestration close to the Tag
 *   owner module while preserving the FORKS split: this Reaction owns request shaping,
 *   auth gates, query branching, and response presentation; fTag and related feeds keep
 *   tag truth, lookup semantics, and persisted classification data.
 *
 * Strict Constraints
 * - This Reaction must not reduce tag ownership into a thin transport wrapper nor absorb
 *   Press/Tag business rules into shared helpers.
 * - Tag/detail persistence, relation truth, and classification semantics remain owned by
 *   the Tag and Press collaborators.
 *
 * Porting Guidelines
 * - Port as the request/orchestration layer for tag-facing listing and backend tag
 *   management endpoints.
 * - Preserve the split between request/auth/response behavior here and persisted tag /
 *   content classification semantics in the owner modules.
 */
class rTag extends Reaction
{
    /**
     * Architecture Role
     * - Return more published content for a tag or column-driven content listing flow at
     *   the public request boundary.
     *
     * Strict Constraints
     * - This method coordinates query branching and presentation shaping, but published
     *   content lookup truth remains with Press and Tag collaborators.
     *
     * Porting Note
     * - Port as a public tag/content listing endpoint with owner-side branching.
     */
    public function do_more($f3, $args)
    {
        $query = [
            'm.status' => [fPress::ST_PUBLISHED, fPress::ST_CHANGED],
        ];

        $req = parent::_getReq();

        $req['page'] = intval($req['page']);
        $req['page'] = ($req['page']) ? ($req['page'] - 1) : 1;

        $req['limit'] = max(min($req['limit'] * 1, 24), 3);

        if (!empty($req['pid'])) {
            if (is_numeric($req['pid'])) {
                $tag = fTag::one($req['pid'], 'id', ['status' => fTag::ST_ON], false);
            } else {
                $tag = fTag::one(parent::_slugify($req['pid']), 'slug', ['status' => fTag::ST_ON], false);
            }
        } else {
            $tag = null;
        }

        if (!empty($tag)) {
            $rtn = fPress::lotsByTag($tag['id'], $req['page'], $req['limit']);
        } else {
            if (!empty($req['columnID'])) {
                if ('topic' == $req['columnType']) {
                    $query['m.topic_id'] = intval($req['columnID']);
                } else {
                    $query['m.column_id'] = intval($req['columnID']);
                }
            }

            $rtn = fPress::limitRows($query, $req['page'], $req['limit']);
        }

        $rtn['subset'] = \__::map($rtn['subset'], function ($row) {
            return kPress::presentListRow($row);
        });

        return self::_return(1, $rtn);
    }

    /**
     * Architecture Role
     * - Load one backend tag detail record, or synthesize an empty owner-side form state
     *   when the detail row does not exist yet.
     *
     * Strict Constraints
     * - This method controls backend access and form-state shaping, but detail truth
     *   remains owned by the Tag feed.
     *
     * Porting Note
     * - Port as the backend tag detail lookup entrypoint.
     */
    function do_getDetail($f3, $args)
    {
        chkAuth(fTag::PV_R);

        $req = parent::_getReq();

        if (!isset($req['parent_id'])) {
            return self::_return(8004);
        }

        $cu = fTag::detail($req['parent_id']);

        if (empty($cu)) {
            $cu = ['id' => 0, 'parent_id' => $req['parent_id']];
        } else {
        }

        return self::_return(1, $cu);
    }

    /**
     * Architecture Role
     * - Return backend tag rows grouped for management views at the owner-side request
     *   boundary.
     *
     * Strict Constraints
     * - Grouping/presentation may happen here, but list query semantics and genus/tag
     *   data truth remain in owner-side collaborators.
     *
     * Porting Note
     * - Port as the backend grouped tag list endpoint.
     */
    public function do_list($f3, $args)
    {
        chkAuth(fTag::PV_R);
        $req = parent::_getReq();

        if (empty($req['query'])) {
            $req['query'] = [];
        }
        $req['page'] = (isset($req['page'])) ? ($req['page'] - 1) : 0;
        $rtn         = fTag::limitRows($req['query'], $req['page']);

        $origAry = fGenus::getOpts('tag', 'm.group');
        $origAry = array_merge([
            [
                'id'    => '0',
                'title' => '未選擇',
            ],
        ], $origAry);

        $idArray   = array_column($origAry, 'id');
        $positions = array_combine($idArray, $origAry);

        $rtn['subset'] = array_reduce($rtn['subset'], function ($carry, $row) use ($positions) {
            if (!isset($positions[$row['cate_id']])) { // for the unknown genus
                $positions[$row['cate_id']] = [
                    'id'    => $row['cate_id'],
                    'title' => '未知分類 #' . $row['cate_id'],
                ];
            }

            // 初始化分组中的 'title'
            if (!isset($carry[$row['cate_id']]['title'])) {
                $carry[$row['cate_id']]['title'] = $positions[$row['cate_id']]['title'];
            }
            // 添加行到 'rows'
            $carry[$row['cate_id']]['rows'][] = $row;

            return $carry;
        }, []);

        return parent::_return(1, $rtn);
    }

    /**
     * Architecture Role
     * - Persist backend tag detail form updates through the owner-side request boundary.
     *
     * Strict Constraints
     * - This method validates access and request shape only; detail persistence and field
     *   semantics remain Tag-owned.
     *
     * Porting Note
     * - Port as the backend tag detail save entrypoint.
     */
    function do_saveDetail($f3, $args)
    {
        chkAuth(fTag::PV_D);

        $req = parent::_getReq();

        if (!isset($req['id'])) {
            return self::_return(8004);
        }

        $id = fTag::save($req, 'detail');

        return self::_return(1, ['id' => $id]);
    }
}
