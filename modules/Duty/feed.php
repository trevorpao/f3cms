<?php

namespace F3CMS;

class fDuty extends Feed
{
    public const MTB = 'duty';
    public const MULTILANG = 0;

    public const ST_ENABLED = 'Enabled';
    public const ST_DISABLED = 'Disabled';
    public const SK_PREREQUISITE_UNMET = 'prerequisite_unmet';
    public const SK_PREREQUISITE_UNRESOLVABLE = 'prerequisite_unresolvable';

    protected static array $taskTemplateDutyCache = [];

    public static function oneBySlug($slug)
    {
        if ('' === trim((string) $slug)) {
            return null;
        }

        return mh()->get(self::fmTbl(), '*', [
            'slug' => trim((string) $slug),
        ]);
    }

    public static function enabledClaimRows()
    {
        $rows = self::claimRows([
            'status' => self::ST_ENABLED,
        ]);

        return is_array($rows) ? $rows : [];
    }

    public static function oneByTaskTemplateSlug($taskTemplateSlug)
    {
        $taskTemplateSlug = trim((string) $taskTemplateSlug);
        if ('' === $taskTemplateSlug) {
            return null;
        }

        if (array_key_exists($taskTemplateSlug, self::$taskTemplateDutyCache)) {
            return self::$taskTemplateDutyCache[$taskTemplateSlug];
        }

        foreach (self::claimRows() as $row) {
            $dutyId = (int) ($row['id'] ?? 0);
            if ($dutyId <= 0) {
                continue;
            }

            $payload = self::normalizeRulePayload($row['claim'] ?? '', $dutyId, 'claim');
            $taskTemplate = $payload['task_template'] ?? null;
            if (!is_array($taskTemplate)) {
                continue;
            }

            $candidateSlug = trim((string) ($taskTemplate['slug'] ?? ''));
            if ('' === $candidateSlug) {
                continue;
            }

            if (!array_key_exists($candidateSlug, self::$taskTemplateDutyCache)) {
                self::$taskTemplateDutyCache[$candidateSlug] = $row;
            }

            if ($candidateSlug === $taskTemplateSlug) {
                return $row;
            }
        }

        self::$taskTemplateDutyCache[$taskTemplateSlug] = null;

        return null;
    }

    private static function claimRows($where = [])
    {
        $rows = mh()->select(self::fmTbl(), [
            'id',
            'slug',
            'claim',
        ], is_array($where) ? $where : []);

        return is_array($rows) ? $rows : [];
    }

    public static function loadRulePayload($dutyId, $column = 'claim')
    {
        if (!in_array($column, ['claim', 'factor', 'next'], true)) {
            throw new \InvalidArgumentException('Unsupported duty payload column: ' . $column);
        }

        $dutyId = (int) $dutyId;
        if ($dutyId <= 0) {
            return null;
        }

        return mh()->get(self::fmTbl(), [$column], [
            'id' => $dutyId,
        ]);
    }

    public static function loadTaskTemplate($dutyId)
    {
        $row = self::loadRulePayload($dutyId, 'claim');
        if (empty($row) || !is_array($row) || !array_key_exists('claim', $row)) {
            return null;
        }

        $payload = self::normalizeRulePayload($row['claim'], $dutyId, 'claim');
        if (!isset($payload['task_template']) || !is_array($payload['task_template'])) {
            return null;
        }

        return $payload['task_template'];
    }

    public static function isTaskTemplateExpired($taskTemplate, $now = null)
    {
        if (!is_array($taskTemplate) || !array_key_exists('expire_at', $taskTemplate)) {
            return false;
        }

        $expireAt = trim((string) $taskTemplate['expire_at']);
        if ('' === $expireAt) {
            return false;
        }

        $expireAtTs = strtotime($expireAt);
        if (false === $expireAtTs) {
            throw new \RuntimeException('Duty task_template.expire_at is not a valid datetime: ' . $expireAt);
        }

        if (null === $now) {
            $now = time();
        }

        return $expireAtTs <= (int) $now;
    }

    public static function hasUnavailableSeenTarget($taskTemplate)
    {
        foreach (self::listSeenTargets($taskTemplate) as $targetRef) {
            if (!self::isSeenTargetAvailable($targetRef['target'], $targetRef['row_id'])) {
                return true;
            }
        }

        return false;
    }

    public static function hasUnmetTaskPrerequisite($taskTemplate, $memberId)
    {
        return null !== self::taskPrerequisiteFailureReason($taskTemplate, $memberId);
    }

    public static function taskPrerequisiteFailureReason($taskTemplate, $memberId)
    {
        $memberId = (int) $memberId;
        if ($memberId <= 0) {
            return null;
        }

        $prerequisite = self::loadTaskPrerequisite($taskTemplate);
        if (empty($prerequisite)) {
            return null;
        }

        $operator = strtoupper(trim((string) ($prerequisite['operator'] ?? 'AND')));
        if (!in_array($operator, ['AND', 'OR'], true)) {
            return null;
        }

        $tasks = $prerequisite['tasks'] ?? null;
        if (!is_array($tasks) || empty($tasks)) {
            return null;
        }

        $matchedCount = 0;
        $checkedCount = 0;
        $hasUnresolvableDependency = false;
        $hasUnmetDependency = false;

        foreach ($tasks as $dependency) {
            $dependencyState = self::taskPrerequisiteDependencyState($dependency, $memberId);
            if (null === $dependencyState) {
                return null;
            }

            ++$checkedCount;

            if ('met' === $dependencyState) {
                ++$matchedCount;
                continue;
            }

            if ('unresolvable' === $dependencyState) {
                $hasUnresolvableDependency = true;
                continue;
            }

            if ('unmet' === $dependencyState) {
                $hasUnmetDependency = true;
            }
        }

        if ($checkedCount <= 0) {
            return null;
        }

        if ('OR' === $operator) {
            if ($matchedCount > 0) {
                return null;
            }

            return $hasUnresolvableDependency ? self::SK_PREREQUISITE_UNRESOLVABLE : self::SK_PREREQUISITE_UNMET;
        }

        if ($matchedCount === $checkedCount) {
            return null;
        }

        return $hasUnresolvableDependency ? self::SK_PREREQUISITE_UNRESOLVABLE : self::SK_PREREQUISITE_UNMET;
    }

    private static function taskPrerequisiteDependencyState($dependency, $memberId)
    {
        if (!is_array($dependency)) {
            return null;
        }

        $expectedStatus = trim((string) ($dependency['expected_status'] ?? fTask::ST_DONE));
        if ('' === $expectedStatus) {
            return null;
        }

        $duty = self::resolveTaskPrerequisiteDuty($dependency);
        if (null === $duty) {
            return null;
        }

        if (empty($duty) || !is_array($duty)) {
            return 'unresolvable';
        }

        $dependencyTask = fTask::oneByDutyAndMember((int) ($duty['id'] ?? 0), (int) $memberId);
        if (empty($dependencyTask) || !is_array($dependencyTask)) {
            return 'unresolvable';
        }

        return $expectedStatus === (string) ($dependencyTask['status'] ?? '') ? 'met' : 'unmet';
    }

    private static function resolveTaskPrerequisiteDuty($dependency)
    {
        $dutySlug = trim((string) ($dependency['duty_slug'] ?? ''));
        if ('' !== $dutySlug) {
            return self::oneBySlug($dutySlug) ?? [];
        }

        $taskTemplateSlug = trim((string) ($dependency['task_template_slug'] ?? ''));
        if ('' !== $taskTemplateSlug) {
            return self::oneByTaskTemplateSlug($taskTemplateSlug) ?? [];
        }

        return null;
    }

    public static function loadTaskPrerequisite($taskTemplate)
    {
        if (!is_array($taskTemplate) || !isset($taskTemplate['prerequisite']) || !is_array($taskTemplate['prerequisite'])) {
            return null;
        }

        return $taskTemplate['prerequisite'];
    }

    public static function isSeenTargetAvailable($target, $rowId)
    {
        $target = trim((string) $target);
        $rowId = (int) $rowId;

        if ('' === $target || $rowId <= 0) {
            return false;
        }

        $kitClass = __NAMESPACE__ . '\\k' . $target;
        if (!class_exists($kitClass) || !method_exists($kitClass, 'isAvailable')) {
            return false;
        }

        return true === $kitClass::isAvailable([
            'id' => $rowId,
        ]);
    }

    public static function listSeenTargets($node)
    {
        if (!is_array($node) || empty($node)) {
            return [];
        }

        if (isset($node['factor']) && is_array($node['factor'])) {
            return self::listSeenTargets($node['factor']);
        }

        $targets = [];
        $type = strtoupper(trim((string) ($node['type'] ?? '')));

        if ('MEMBER_SEEN_TARGET' === $type) {
            $target = trim((string) ($node['target'] ?? ''));
            $rowId = (int) ($node['row_id'] ?? 0);

            if ('' !== $target && $rowId > 0) {
                $targets[] = [
                    'target' => $target,
                    'row_id' => $rowId,
                ];
            }
        }

        foreach (['rules', 'children'] as $childrenKey) {
            if (!isset($node[$childrenKey]) || !is_array($node[$childrenKey])) {
                continue;
            }

            foreach ($node[$childrenKey] as $child) {
                $targets = array_merge($targets, self::listSeenTargets($child));
            }
        }

        return $targets;
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
}