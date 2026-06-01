<?php

namespace F3CMS;

/**
 * Architecture Purpose
 * - Owner-side Feed for backend role records, privilege mapping, and role-specific query
 *   semantics used by the staff/operator administration boundary.
 *
 * Design Intent
 * - Keep backend authority truth inside the Role module even when it reuses the shared
 *   Feed base, so permission-bit mapping, role option projection, and role search
 *   behavior do not drift into generic auth helpers or caller-specific code.
 *
 * Strict Constraints
 * - Role-to-privilege semantics belong to this module boundary and must not be widened
 *   into a generic authorization engine that starts owning session or request policy.
 * - This Feed may reuse shared persistence/query mechanics, but it must remain the
 *   source of truth for backend role bitmask interpretation and projection helpers.
 *
 * Porting Guidelines
 * - Port as the backend role/privilege Feed owned by the Role module.
 * - Preserve the split between shared Feed mechanics and Role-owned authority mapping,
 *   option projection, and search/filter semantics.
 */
class fRole extends Feed
{
    const MTB       = 'role';
    const MULTILANG = 0;

    const ST_ON  = 'Enabled';
    const ST_OFF = 'Disabled';

    const PV_R = 'mgr.site';
    const PV_U = 'mgr.site';
    const PV_D = 'mgr.site';

    const BE_COLS = 'id,status,priv,title,info';

    /**
     * Architecture Role
     * - Extend the shared Feed query parser with Role-specific full-text style title
     *   search semantics.
     *
     * Strict Constraints
     * - Role search shaping stays with this module because it reflects role-admin read
     *   semantics; do not scatter these clauses across callers.
     *
     * Porting Note
     * - Port as a Role-specific query-normalization extension over the shared Feed parser.
     */
    public static function genQuery($queryStr = '')
    {
        $query = parent::genQuery($queryStr);

        if (array_key_exists('all', $query)) {
            $query['OR']['title[~]'] = $query['all'];
            unset($query['all']);
        }

        if (array_key_exists('all[!]', $query)) {
            $query['AND']['title[!]'] = $query['all[!]'];
            unset($query['all[!]']);
        }

        if (array_key_exists('all[!~]', $query)) {
            $query['AND']['title[!~]'] = $query['all[!~]'];
            unset($query['all[!~]']);
        }

        return $query;
    }

    /**
     * Architecture Role
     * - Materialize the canonical backend authority catalog and cache it for role/staff
     *   privilege projection.
     *
     * Strict Constraints
     * - This method defines role authority truth for the backend administration domain;
     *   do not relocate that mapping into unrelated session or controller helpers.
     *
     * Porting Note
     * - Port as the authoritative role-permission registry with bounded caching.
     */
    public static function getAuth()
    {
        $load = [];

        if (!f3()->exists('AUTH_LIST')) {
            $auth_list = [
                ['idx' => 1, 'name' => 'base.cms', 'title' => '基本管理'],                // 1
                ['idx' => 2, 'name' => 'mgr.cms', 'title' => '進階內容管理'],              // 2
                ['idx' => 3, 'name' => 'base.member', 'title' => '基本客戶管理'],          // 4
                ['idx' => 4, 'name' => 'mgr.member', 'title' => '進階客戶管理'],           // 8
                ['idx' => 5, 'name' => 'mgr.site', 'title' => '完整網站管理'],             // 16
            ];

            foreach ($auth_list as $auth) {
                $load[$auth['name']] = [
                    'idx'   => $auth['idx'],
                    'val'   => (1 << ($auth['idx'] - 1)),
                    'title' => $auth['title'],
                ];
            }

            f3()->set('AUTH_LIST', $load, 36000);
        } else {
            $load = f3()->get('AUTH_LIST');
        }

        return $load;
    }

    /**
     * Architecture Role
     * - Project the authority catalog into option-list data for backend role editing
     *   surfaces.
     *
     * Strict Constraints
     * - Option projection is derived from Role authority truth and should stay close to
     *   the module instead of being rebuilt in views or request handlers.
     *
     * Porting Note
     * - Port as a UI-facing projection helper over the authority catalog.
     */
    public static function getAuthOpts()
    {
        $auth_list = fRole::getAuth();
        $load      = [];
        foreach ($auth_list as $auth) {
            $load[] = [
                'id'    => $auth['val'],
                'title' => $auth['title'],
            ];
        }

        return $load;
    }

    /**
     * Architecture Role
     * - Check whether a backend privilege bitmask contains a named authority owned by
     *   the Role module.
     *
     * Strict Constraints
     * - This helper interprets stored role privilege semantics only; broader access-flow
     *   orchestration must remain with callers such as Staff/Reaction boundaries.
     *
     * Porting Note
     * - Port as a bitmask-membership check keyed by authority name.
     */
    public static function hasAuth($user_priv = 0, $authority = '')
    {
        $auth_list = self::getAuth();

        return !empty($auth_list[$authority]) && (($user_priv & $auth_list[$authority]['val']) == $auth_list[$authority]['val']);
    }

    /**
     * Architecture Role
     * - Convert a list of authority indexes into the stored role privilege bitmask.
     *
     * Strict Constraints
     * - Bitmask encoding belongs to Role persistence semantics; do not duplicate this
     *   conversion logic in callers that merely consume role data.
     *
     * Porting Note
     * - Port as the index-to-bitmask encoder for role persistence payloads.
     */
    public static function parseAuth($ary = [])
    {
        $sum = 0;
        foreach ($ary as $val) {
            $sum += (1 << (intval($val) - 1));
        }

        return $sum;
    }

    /**
     * Architecture Role
     * - Extract the enabled privilege values from a boolean-like authority map.
     *
     * Strict Constraints
     * - Keep this value projection local to Role privilege semantics rather than turning
     *   it into a generic array utility.
     *
     * Porting Note
     * - Port as a helper that maps selected authority flags to stored bit values.
     */
    public static function parseAuthVal($ary = [])
    {
        $sum = [];
        foreach ($ary as $idx => $val) {
            if ($val) {
                $sum[] = pow(2, $idx);
            }
        }

        return $sum;
    }

    /**
     * Architecture Role
     * - Extract enabled authority indexes from a boolean-like authority map.
     *
     * Strict Constraints
     * - Index projection remains part of Role permission-shaping semantics and should
     *   not drift into generic caller-side preprocessing.
     *
     * Porting Note
     * - Port as a helper returning enabled authority indexes for UI/session projection.
     */
    public static function parseAuthIdx($ary = [])
    {
        $sum = [];
        foreach ($ary as $idx => $val) {
            if ($val) {
                $sum[] = $idx + 1;
            }
        }

        return $sum;
    }

    /**
     * Architecture Role
     * - Decode the stored privilege integer into a reversed binary authority array.
     *
     * Strict Constraints
     * - Stored-role bitmask decoding is Role-owned semantics; do not hide or re-define
     *   this encoding elsewhere.
     *
     * Porting Note
     * - Port as the privilege-bitmask decoder used before index/value projection.
     */
    public static function reverseAuth($val = 0)
    {
        return str_split(strrev(sprintf('%07s', base_convert($val, 10, 2) . '')));
    }

    /**
     * Architecture Role
     * - Normalize incoming Role save payload by collapsing selected authorities into the
     *   stored privilege value.
     *
     * Strict Constraints
     * - Role privilege encoding stays in this module extension point rather than being
     *   pushed up to request handlers or shared Feed logic.
     *
     * Porting Note
     * - Port as a Role-specific save-payload normalization hook over shared Feed logic.
     */
    public static function _handleColumn($req)
    {
        $req['priv'] = array_sum($req['auth']);
        unset($req['auth']);

        return parent::_handleColumn($req);
    }
}
