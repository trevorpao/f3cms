<?php

namespace F3CMS;

class fTask extends Feed
{
    public const MTB = 'task';
    public const MULTILANG = 0;

    public const ST_NEW = 'New';
    public const ST_CLAIMED = 'Claimed';
    public const ST_DONE = 'Done';
    public const ST_INVALID = 'Invalid';

    public const AC_MEMBER_REGISTER_TRIGGER = 'MEMBER_REGISTER_TRIGGER';
    public const AC_TASK_DONE_REWARD = 'TASK_DONE_REWARD';

    public static function createForDutyAndMember($dutyId, $memberId, $insertUser = 0)
    {
        $dutyId = (int) $dutyId;
        $memberId = (int) $memberId;
        $insertUser = (int) $insertUser;

        if ($dutyId <= 0 || $memberId <= 0) {
            return null;
        }

        $existing = self::oneByDutyAndMember($dutyId, $memberId);
        if (!empty($existing)) {
            $existing['_created'] = false;

            return $existing;
        }

        $timestamp = date('Y-m-d H:i:s');

        mh()->insert(self::fmTbl(), [
            'duty_id' => $dutyId,
            'member_id' => $memberId,
            'status' => self::ST_NEW,
            'insert_ts' => $timestamp,
            'insert_user' => $insertUser,
            'last_ts' => $timestamp,
            'last_user' => $insertUser,
        ]);

        $taskId = (int) self::chkErr(mh()->id());
        if ($taskId <= 0) {
            return null;
        }

        self::appendLog($taskId, self::AC_MEMBER_REGISTER_TRIGGER, null, self::ST_NEW, 'Task created from Member::Register duty trigger.', $insertUser);

        $created = mh()->get(self::fmTbl(), '*', [
            'id' => $taskId,
        ]);

        if (empty($created) || !is_array($created)) {
            return null;
        }

        $created['_created'] = true;

        return $created;
    }

    public static function oneByDutyAndMember($dutyId, $memberId)
    {
        $dutyId = (int) $dutyId;
        $memberId = (int) $memberId;

        if ($dutyId <= 0 || $memberId <= 0) {
            return null;
        }

        return mh()->get(self::fmTbl(), '*', [
            'duty_id' => $dutyId,
            'member_id' => $memberId,
        ]);
    }

    public static function pendingByMemberId($memberId)
    {
        $memberId = (int) $memberId;
        if ($memberId <= 0) {
            return [];
        }

        $rows = mh()->select(self::fmTbl(), '*', [
            'member_id' => $memberId,
            'status' => [self::ST_NEW, self::ST_CLAIMED],
        ]);

        return is_array($rows) ? $rows : [];
    }

    public static function markDone($taskId, $actionCode = self::AC_TASK_DONE_REWARD, $remark = '', $insertUser = 0)
    {
        $taskId = (int) $taskId;
        $insertUser = (int) $insertUser;

        if ($taskId <= 0) {
            return null;
        }

        $task = mh()->get(self::fmTbl(), '*', [
            'id' => $taskId,
        ]);

        if (empty($task) || !is_array($task)) {
            return null;
        }

        if (self::ST_DONE === ($task['status'] ?? null)) {
            $task['_updated'] = false;

            return $task;
        }

        mh()->update(self::fmTbl(), [
            'status' => self::ST_DONE,
            'last_ts' => date('Y-m-d H:i:s'),
            'last_user' => $insertUser,
        ], [
            'id' => $taskId,
        ]);

        self::chkErr(true);
        self::appendLog($taskId, $actionCode, $task['status'] ?? null, self::ST_DONE, $remark, $insertUser);

        $updated = mh()->get(self::fmTbl(), '*', [
            'id' => $taskId,
        ]);

        if (empty($updated) || !is_array($updated)) {
            return null;
        }

        $updated['_updated'] = true;

        return $updated;
    }

    public static function appendLog($taskId, $actionCode, $oldStateCode, $newStateCode, $remark = '', $insertUser = null)
    {
        $taskId = (int) $taskId;
        if ($taskId <= 0 || '' === trim((string) $actionCode)) {
            return null;
        }

        if (null === $insertUser) {
            $insertUser = fStaff::_current('id');
        }

        mh()->insert(self::fmTbl('log'), [
            'parent_id' => $taskId,
            'action_code' => trim((string) $actionCode),
            'old_state_code' => null === $oldStateCode ? null : (string) $oldStateCode,
            'new_state_code' => null === $newStateCode ? null : (string) $newStateCode,
            'remark' => (string) $remark,
            'insert_ts' => date('Y-m-d H:i:s'),
            'insert_user' => (int) $insertUser,
        ]);

        return self::chkErr(mh()->id());
    }
}