<?php

namespace F3CMS;

class kDuty extends Kit
{
    public static function evaluateTaskTemplateForMember($dutyId, $memberId, $contextOverrides = [], $engineOptions = [])
    {
        $claim = self::loadRulePayload($dutyId, 'claim');
        $taskFactor = isset($claim['task_template']['factor']) ? $claim['task_template']['factor'] : null;

        if (!is_array($taskFactor) || empty($taskFactor)) {
            throw new \RuntimeException('Duty task_template.factor not found for duty_id: ' . (int) $dutyId);
        }

        $engine = new EventRuleEngine($taskFactor, $engineOptions);
        $context = kMember::createEventRuleContext($memberId, $contextOverrides);

        return $engine->evaluate($context);
    }

    public static function completeTasksForSeenTarget($memberId, $target, $rowId, $source, $insertUser = 0)
    {
        $memberId = (int) $memberId;
        $rowId = (int) $rowId;
        $target = trim((string) $target);
        $source = trim((string) $source);
        $insertUser = (int) $insertUser;

        if ($memberId <= 0 || $rowId <= 0 || '' === $target || '' === $source) {
            throw new \RuntimeException('Seen target completion requires member_id, target, row_id, and source.');
        }

        $member = fMember::oneEnabledById($memberId);
        if (empty($member)) {
            throw new \RuntimeException('Enabled member not found for seen target completion: ' . $memberId);
        }

        $transactionStarted = false;

        try {
            mh()->begin();
            $transactionStarted = true;

            $seen = fMember::createSeenTarget($memberId, $target, $rowId, $source, $insertUser);
            if (empty($seen)) {
                throw new \RuntimeException('Failed to create or load member_seen truth.');
            }

            $contextOverrides = [
                'member_seen_targets' => fMember::seenTargetMapByMemberId($memberId),
            ];

            $completedTasks = [];

            foreach (fTask::pendingByMemberId($memberId) as $task) {
                $dutyId = (int) ($task['duty_id'] ?? 0);
                if ($dutyId <= 0) {
                    continue;
                }

                $claim = self::loadRulePayload($dutyId, 'claim');
                if (!isset($claim['task_template']) || !is_array($claim['task_template'])) {
                    continue;
                }

                $evaluation = self::evaluateTaskTemplateForMember($dutyId, $memberId, $contextOverrides)->toArray();
                if (true !== ($evaluation['matched'] ?? false)) {
                    continue;
                }

                $reward = self::normalizeTaskReward($claim['task_template']);
                $actionCode = '' !== $reward['action_code'] ? $reward['action_code'] : fTask::AC_TASK_DONE_REWARD;
                $updatedTask = fTask::markDone((int) $task['id'], $actionCode, 'Task completed by member_seen target.', $insertUser);

                if (empty($updatedTask)) {
                    throw new \RuntimeException('Failed to mark task done for task_id: ' . (int) $task['id']);
                }

                $account = null;
                if ('POINT' === $reward['type'] && 0 !== $reward['amount']) {
                    $account = fManaccount::addPointsForMember($memberId, $reward['amount'], $actionCode, $insertUser, 'Reward granted after task completion.');
                    if (empty($account)) {
                        throw new \RuntimeException('Failed to grant reward for member_id: ' . $memberId);
                    }
                }

                $completedTasks[] = [
                    'task_id' => (int) ($updatedTask['id'] ?? 0),
                    'duty_id' => $dutyId,
                    'status' => $updatedTask['status'] ?? null,
                    'reward_action_code' => $actionCode,
                    'reward_amount' => $reward['amount'],
                    'account_balance' => isset($account['balance']) ? (int) $account['balance'] : null,
                    'evaluation' => $evaluation,
                ];
            }

            mh()->commit();

            return [
                'seen' => $seen,
                'completed_tasks' => $completedTasks,
            ];
        } catch (\Throwable $e) {
            if ($transactionStarted) {
                mh()->rollback();
            }

            throw $e;
        }
    }

    public static function createTasksForTrigger($trigger, $memberId, $insertUser = 0)
    {
        $trigger = trim((string) $trigger);
        $memberId = (int) $memberId;
        $insertUser = (int) $insertUser;

        if ('' === $trigger || $memberId <= 0) {
            return [];
        }

        $member = fMember::oneEnabledById($memberId);
        if (empty($member)) {
            throw new \RuntimeException('Enabled member not found for trigger task creation: ' . $memberId);
        }

        $matchedTasks = [];

        foreach (fDuty::enabledClaimRows() as $row) {
            $dutyId = (int) ($row['id'] ?? 0);
            if ($dutyId <= 0) {
                continue;
            }

            $payload = self::normalizeRulePayload($row['claim'] ?? '', $dutyId, 'claim');
            if ($trigger !== trim((string) ($payload['trigger'] ?? ''))) {
                continue;
            }

            if (!isset($payload['task_template']) || !is_array($payload['task_template'])) {
                continue;
            }

            $task = fTask::createForDutyAndMember($dutyId, $memberId, $insertUser);
            if (empty($task)) {
                throw new \RuntimeException('Failed to create task for duty_id: ' . $dutyId . ' member_id: ' . $memberId);
            }

            $matchedTasks[] = [
                'duty_id' => $dutyId,
                'duty_slug' => isset($row['slug']) ? (string) $row['slug'] : '',
                'task_id' => (int) ($task['id'] ?? 0),
                'created' => !empty($task['_created']),
                'status' => isset($task['status']) ? (string) $task['status'] : '',
            ];
        }

        return $matchedTasks;
    }

    public static function loadRulePayload($dutyId, $column = 'claim')
    {
        $row = fDuty::loadRulePayload($dutyId, $column);
        if (empty($row) || !is_array($row) || !array_key_exists($column, $row)) {
            throw new \RuntimeException('Duty rule payload not found for duty_id: ' . (int) $dutyId . ' column: ' . $column);
        }

        return self::normalizeRulePayload($row[$column], $dutyId, $column);
    }

    public static function createRuleEngine($dutyId, $column = 'claim', $options = [])
    {
        return new EventRuleEngine(self::loadRulePayload($dutyId, $column), $options);
    }

    public static function evaluateForMember($dutyId, $memberId, $column = 'claim', $contextOverrides = [], $engineOptions = [])
    {
        $engine = self::createRuleEngine($dutyId, $column, $engineOptions);
        $context = kMember::createEventRuleContext($memberId, $contextOverrides);

        return $engine->evaluate($context);
    }

    private static function normalizeRulePayload($rawPayload, $dutyId, $column)
    {
        if (is_array($rawPayload)) {
            return $rawPayload;
        }

        $rawPayload = trim((string) $rawPayload);
        if ('' === $rawPayload) {
            throw new \RuntimeException('Duty rule payload is empty for duty_id: ' . (int) $dutyId . ' column: ' . $column);
        }

        $decoded = json_decode($rawPayload, true);
        if (JSON_ERROR_NONE !== json_last_error() || !is_array($decoded)) {
            throw new \RuntimeException('Duty rule payload is not valid JSON for duty_id: ' . (int) $dutyId . ' column: ' . $column);
        }

        return $decoded;
    }

    private static function normalizeTaskReward($taskTemplate)
    {
        $reward = isset($taskTemplate['reward']) && is_array($taskTemplate['reward']) ? $taskTemplate['reward'] : [];

        return [
            'type' => isset($reward['type']) ? strtoupper(trim((string) $reward['type'])) : '',
            'amount' => isset($reward['amount']) ? (int) $reward['amount'] : 0,
            'action_code' => isset($reward['action_code']) ? trim((string) $reward['action_code']) : '',
        ];
    }
}