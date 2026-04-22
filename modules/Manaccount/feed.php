<?php

namespace F3CMS;

class fManaccount extends Feed
{
    public const MTB = 'manaccount';
    public const MULTILANG = 0;

    public const ST_ENABLED = 'Enabled';
    public const ST_DISABLED = 'Disabled';

    public static function ensureByMemberId($memberId, $insertUser = 0)
    {
        $memberId = (int) $memberId;
        $insertUser = (int) $insertUser;

        if ($memberId <= 0) {
            return null;
        }

        $existing = self::oneByMemberId($memberId);
        if (!empty($existing)) {
            return $existing;
        }

        mh()->insert(self::fmTbl(), [
            'member_id' => $memberId,
            'balance' => 0,
            'status' => self::ST_ENABLED,
            'insert_ts' => date('Y-m-d H:i:s'),
            'insert_user' => $insertUser,
            'last_ts' => date('Y-m-d H:i:s'),
            'last_user' => $insertUser,
        ]);

        self::chkErr(mh()->id());

        return self::oneByMemberId($memberId);
    }

    public static function oneByMemberId($memberId)
    {
        $memberId = (int) $memberId;
        if ($memberId <= 0) {
            return null;
        }

        return mh()->get(self::fmTbl(), '*', [
            'member_id' => $memberId,
        ]);
    }

    public static function addPointsForMember($memberId, $deltaPoint, $actionCode, $insertUser = 0, $remark = '')
    {
        $memberId = (int) $memberId;
        $deltaPoint = (int) $deltaPoint;
        $insertUser = (int) $insertUser;

        if ($memberId <= 0 || 0 === $deltaPoint || '' === trim((string) $actionCode)) {
            return null;
        }

        $account = self::ensureByMemberId($memberId, $insertUser);
        if (empty($account) || !is_array($account)) {
            return null;
        }

        $oldBalance = isset($account['balance']) ? (int) $account['balance'] : 0;
        $newBalance = $oldBalance + $deltaPoint;

        mh()->update(self::fmTbl(), [
            'balance' => $newBalance,
            'last_ts' => date('Y-m-d H:i:s'),
            'last_user' => $insertUser,
        ], [
            'id' => (int) $account['id'],
        ]);

        self::chkErr(true);
        self::appendLog((int) $account['id'], $actionCode, $deltaPoint, $oldBalance, $newBalance, $remark, $insertUser);

        return self::oneByMemberId($memberId);
    }

    public static function appendLog($accountId, $actionCode, $deltaPoint, $oldBalance, $newBalance, $remark = '', $insertUser = null)
    {
        $accountId = (int) $accountId;
        if ($accountId <= 0 || '' === trim((string) $actionCode)) {
            return null;
        }

        if (null === $insertUser) {
            $insertUser = fStaff::_current('id');
        }

        mh()->insert(self::fmTbl('log'), [
            'parent_id' => $accountId,
            'action_code' => trim((string) $actionCode),
            'delta_point' => (int) $deltaPoint,
            'old_balance' => (int) $oldBalance,
            'new_balance' => (int) $newBalance,
            'remark' => (string) $remark,
            'insert_ts' => date('Y-m-d H:i:s'),
            'insert_user' => (int) $insertUser,
        ]);

        return self::chkErr(mh()->id());
    }
}