<?php

namespace F3CMS;

/**
 * Architecture Purpose
 * - Owner-side Feed for backend staff identity records, role projection, current-session
 *   snapshot wiring, and staff-specific security/sudo support surfaces.
 *
 * Design Intent
 * - Keep backend operator identity truth and privilege/session projection inside the
 *   Staff module even when it reuses the shared Feed base, so staff login context,
 *   sudo audit trail, and role-derived access semantics are not flattened into generic
 *   session helpers or auth utilities.
 *
 * Strict Constraints
 * - Staff-specific session snapshot ownership, role privilege shaping, and sudo trail
 *   writeback must remain in the Staff boundary rather than move into shared session
 *   runtime layers.
 * - This Feed may reuse shared persistence mechanics, but it must not turn into a
 *   generic authentication service for every backend identity concern.
 *
 * Porting Guidelines
 * - Port as the backend staff/account Feed for operator identity and session-adjacent
 *   persistence helpers.
 * - Preserve the split between shared Feed mechanics and Staff-owned identity, role,
 *   sudo, and diagnostic query semantics.
 */
class fStaff extends Feed
{
    const MTB       = 'staff';
    const MULTILANG = 0;

    const ST_NEW      = 'New';
    const ST_VERIFIED = 'Verified';
    const ST_FREEZE   = 'Freeze';

    const PV_R = 'mgr.site';
    const PV_U = 'mgr.site';
    const PV_D = 'mgr.site';

    const BE_COLS = 'm.id,m.account,m.role_id,m.email,m.status,r.title(role)';

    /**
     * Architecture Role
     * - Declare the default role join used when reading backend staff rows.
     *
     * Strict Constraints
     * - Role projection for staff records remains module-owned retrieval shape; do not
     *   duplicate it across callers as ad hoc joins.
     *
     * Porting Note
     * - Port as the canonical Staff-to-Role projection join.
     */
    public static function genJoin()
    {
        return [
            '[>]' . fRole::fmTbl() . '(r)' => ['m.role_id' => 'id'],
        ];
    }

    /**
     * Architecture Role
     * - Persist a Staff-owned sudo audit row when an operator impersonation action is
     *   initiated.
     *
     * Strict Constraints
     * - Sudo-trail ownership stays with Staff identity persistence; do not move this
     *   audit write into generic session or member helpers.
     *
     * Porting Note
     * - Port as a Staff sudo/audit insert owned by the backend operator module.
     */
    public static function insertSudo($member_id)
    {
        $now = date('Y-m-d H:i:s');

        mh()->insert(self::fmTbl('sudo'), [
            'member_id'   => $member_id,
            'last_ts'     => $now,
            'insert_ts'   => $now,
            'insert_user' => self::_current('id'),
        ]);

        return mh()->id();
    }

    /**
     * Architecture Role
     * - Materialize the current backend staff session snapshot, including role-derived
     *   privilege projection and menu context.
     *
     * Strict Constraints
     * - Current-staff session shape belongs to the Staff module; do not spread this
     *   identity contract into shared session runtime or generic auth helpers.
     *
     * Porting Note
     * - Port as the staff-session snapshot writer invoked after successful backend auth.
     */
    public static function _setCurrent($account, $id, $email, $avatar, $priv, $menu_id)
    {
        f3()->set('SESSION.cu_staff', [
            'name'      => $account,
            'id'        => $id,
            'email'     => $email,
            'avatar'    => $avatar,
            'menu'      => $menu_id,
            'priv'      => fRole::parseAuth(fRole::parseAuthIdx(fRole::reverseAuth($priv))),
            'has_login' => 1,
        ]);
    }

    /**
     * Architecture Role
     * - Clear the current backend staff session snapshot.
     *
     * Strict Constraints
     * - This only manages Staff-owned session state; broader logout orchestration or
     *   cross-module cleanup should stay with the caller.
     *
     * Porting Note
     * - Port as the Staff session-clearing primitive.
     */
    public static function _clearCurrent()
    {
        f3()->clear('SESSION.cu_staff');
    }

    /**
     * Architecture Role
     * - Read the current backend staff session snapshot or one projected field from it.
     *
     * Strict Constraints
     * - Current-operator lookup remains a Staff identity concern; do not replace it
     *   with generic global session access when the caller still depends on staff
     *   session semantics.
     *
     * Porting Note
     * - Port as a small accessor over the Staff session snapshot.
     */
    public static function _current($column = 'id')
    {
        $cu = f3()->get('SESSION.cu_staff');
        // $cu = [
        //     'account' => 'shuaib25@gmail.com',
        //     'id' => 1,
        //     'nickname' => '測試帳號',
        //     'avatar' => 'https://robohash.org/c4e919002494d5e124c544e99e073308?set=set4&s=64',
        //     'has_login' => 1
        // ];

        $str = '';

        if (isset($cu) && '*' != $column && isset($cu[$column])) {
            $str = $cu[$column];
        }

        return ('*' == $column) ? $cu : $str;
    }

    /**
     * Architecture Role
     * - Expose a Staff-owned diagnostic query for suspicious account identifiers.
     *
     * Strict Constraints
     * - This is a bounded security read over staff identity records, not a generic site
     *   moderation or threat-analysis abstraction.
     *
     * Porting Note
     * - Port as a staff-account regexp scan helper.
     */
    public static function suspiciousAccounts($pattern, $limit = 50)
    {
        if (!self::hasColumn(self::fmTbl(), 'account')) {
            return [];
        }

        return self::runSecurityQuery(
            'SELECT `id`, `account` FROM `' . self::fmTbl() . '` WHERE `account` REGEXP :pattern ORDER BY `id` DESC LIMIT ' . (int) $limit,
            [':pattern' => (string) $pattern]
        );
    }

    /**
     * Architecture Role
     * - Expose a Staff-owned diagnostic query for suspicious email patterns.
     *
     * Strict Constraints
     * - Keep this diagnostic scoped to staff identity data and do not widen it into a
     *   generic email-audit subsystem.
     *
     * Porting Note
     * - Port as a lowercased regexp scan over staff email values.
     */
    public static function suspiciousEmails($pattern, $limit = 50)
    {
        if (!self::hasColumn(self::fmTbl(), 'email')) {
            return [];
        }

        return self::runSecurityQuery(
            'SELECT `id`, `email` FROM `' . self::fmTbl() . '` WHERE LOWER(`email`) REGEXP :pattern ORDER BY `id` DESC LIMIT ' . (int) $limit,
            [':pattern' => strtolower((string) $pattern)]
        );
    }

    /**
     * Architecture Role
     * - Guard Staff diagnostic queries by checking whether the expected identity column
     *   exists in the current schema.
     *
     * Strict Constraints
     * - This is a defensive schema probe for Staff diagnostics only; do not promote it
     *   into a generic schema-inspection service.
     *
     * Porting Note
     * - Port as a bounded column-existence probe local to Staff security helpers.
     */
    private static function hasColumn($tableName, $columnName)
    {
        $tableName = trim((string) $tableName);
        $columnName = trim((string) $columnName);

        if ('' === $tableName || '' === $columnName) {
            return false;
        }

        try {
            $columns = mh()->query('SHOW COLUMNS FROM `' . $tableName . '` LIKE :column_name', [
                ':column_name' => $columnName,
            ])->fetchAll();
        } catch (\Throwable $e) {
            return false;
        }

        return is_array($columns) && !empty($columns);
    }

    /**
     * Architecture Role
     * - Execute Staff-owned security scan queries with safe failure fallback.
     *
     * Strict Constraints
     * - Keep this helper local to Staff diagnostic reads; do not widen it into a shared
     *   query runner that hides module ownership.
     *
     * Porting Note
     * - Port as a small Staff security-query wrapper returning normalized row arrays.
     */
    private static function runSecurityQuery($sql, $params = [])
    {
        try {
            $rows = mh()->query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
            return is_array($rows) ? $rows : [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}
