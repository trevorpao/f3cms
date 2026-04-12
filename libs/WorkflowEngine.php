<?php

namespace F3CMS;

class WorkflowEngine extends Helper
{
    const TB_DEFINITION = 'tbl_workflow_definition';
    const TB_DEFINITION_STAGE = 'tbl_workflow_definition_stage';
    const TB_DEFINITION_TRANSITION = 'tbl_workflow_definition_transition';
    const TB_DEFINITION_ROLE_MAP = 'tbl_workflow_definition_role_map';

    private $definition = [];

    public function __construct($workflowJson, $options = [])
    {
        $this->definition = self::normalizeDefinitionPayload($workflowJson, $options);
    }

    public function validateDefinition()
    {
        return self::validateDefinitionPayload($this->definition);
    }

    public function getDefinitionPayload()
    {
        return $this->definition;
    }

    public function project($runtimeContext = [])
    {
        $currentStageCodes = self::resolveRuntimeStageCodes($this->definition, $runtimeContext);
        $currentStateCode = self::resolveRuntimeStateCode($this->definition, $currentStageCodes, $runtimeContext);

        return self::buildProjection($this->definition, $currentStageCodes, $currentStateCode);
    }

    public function canTransit($actionCode, $runtimeContext = [])
    {
        $currentStageCodes = self::resolveRuntimeStageCodes($this->definition, $runtimeContext);
        $currentStateCode = self::resolveRuntimeStateCode($this->definition, $currentStageCodes, $runtimeContext);
        $instance = [
            'current_stage_codes_json' => $currentStageCodes,
            'current_state_code' => $currentStateCode,
            'available_action_codes_json' => self::collectAvailableActionCodesForStages($this->definition, $currentStageCodes),
        ];

        $transition = self::resolveTransition($this->definition, $instance, $actionCode);
        if (empty($transition)) {
            return false;
        }

        try {
            self::assertRoleAllowed(
                $this->definition,
                $transition['from_stage_code'],
                isset($runtimeContext['operator_role_constant']) ? $runtimeContext['operator_role_constant'] : null
            );
        } catch (\Throwable $e) {
            return false;
        }

        return true;
    }

    public function transit($actionCode, $runtimeContext = [])
    {
        $operatorId = isset($runtimeContext['operator_id']) ? (int) $runtimeContext['operator_id'] : 0;

        if ($operatorId <= 0) {
            throw new \RuntimeException('Workflow runtime context missing operator_id.');
        }

        $currentStageCodes = self::resolveRuntimeStageCodes($this->definition, $runtimeContext);
        $currentStateCode = self::resolveRuntimeStateCode($this->definition, $currentStageCodes, $runtimeContext);
        $instance = [
            'current_stage_codes_json' => $currentStageCodes,
            'current_state_code' => $currentStateCode,
            'available_action_codes_json' => self::collectAvailableActionCodesForStages($this->definition, $currentStageCodes),
        ];

        $transition = self::resolveTransition($this->definition, $instance, $actionCode);
        if (empty($transition)) {
            throw new \RuntimeException('Workflow transition not allowed.');
        }

        self::assertRoleAllowed(
            $this->definition,
            $transition['from_stage_code'],
            isset($runtimeContext['operator_role_constant']) ? $runtimeContext['operator_role_constant'] : null
        );

        return self::transitRuntimeWithDefinition($this->definition, $actionCode, $operatorId, $runtimeContext);
    }

    public static function loadDefinition($workflowCode, $version = null)
    {
        $workflowCode = trim((string) $workflowCode);
        if ('' === $workflowCode) {
            return null;
        }

        $fileDefinition = self::loadDefinitionFromFile($workflowCode, $version);
        if (!empty($fileDefinition)) {
            return $fileDefinition;
        }

        $filter = [
            'workflow_code' => $workflowCode,
            'ORDER' => ['version' => 'DESC'],
        ];

        if (null !== $version) {
            $filter['version'] = $version;
        } else {
            $filter['status'] = 'Active';
        }

        try {
            $definition = mh()->get(self::TB_DEFINITION, '*', $filter);
        } catch (\Throwable $e) {
            return null;
        }

        if (empty($definition)) {
            return null;
        }

        $definition['definition_json'] = self::decodeJsonField($definition, 'definition_json');
        $definition['terminal_state_codes_json'] = self::decodeJsonField($definition, 'terminal_state_codes_json');
        $definition['meta_json'] = self::decodeJsonField($definition, 'meta_json');
        $definition['stages'] = self::loadStages($definition['id']);
        $definition['transitions'] = self::loadTransitions($definition['id']);
        $definition['role_map'] = self::loadRoleMap($definition['id']);

        self::validateDefinitionPayload($definition);

        return $definition;
    }

    public static function validateDefinitionPayload($definition)
    {
        if (empty($definition) || !is_array($definition)) {
            throw new \RuntimeException('Workflow definition payload is invalid.');
        }

        foreach (['workflow_code', 'title', 'version', 'source_type', 'entry_stage_code'] as $field) {
            if (!isset($definition[$field]) || '' === trim((string) $definition[$field])) {
                throw new \RuntimeException('Workflow definition missing required field: ' . $field);
            }
        }

        if ('json' !== strtolower((string) $definition['source_type'])) {
            throw new \RuntimeException('Workflow definition source_type must be json.');
        }

        if (empty($definition['definition_json']) || empty($definition['definition_json']['workflow'])) {
            throw new \RuntimeException('Workflow definition_json.workflow is required.');
        }

        $workflowPayload = $definition['definition_json']['workflow'];
        foreach (['code', 'title', 'version', 'entryStageCode'] as $field) {
            if (!isset($workflowPayload[$field]) || '' === trim((string) $workflowPayload[$field])) {
                throw new \RuntimeException('Workflow definition_json.workflow missing required field: ' . $field);
            }
        }

        if ((string) $workflowPayload['code'] !== (string) $definition['workflow_code']) {
            throw new \RuntimeException('Workflow definition code does not match definition_json.workflow.code.');
        }

        if ((string) $workflowPayload['entryStageCode'] !== (string) $definition['entry_stage_code']) {
            throw new \RuntimeException('Workflow entry_stage_code does not match definition_json.workflow.entryStageCode.');
        }

        if ((int) $workflowPayload['version'] !== (int) $definition['version']) {
            throw new \RuntimeException('Workflow version does not match definition_json.workflow.version.');
        }

        if (empty($definition['stages']) || !is_array($definition['stages'])) {
            throw new \RuntimeException('Workflow definition requires at least one stage.');
        }

        $stageCodes = [];
        foreach ($definition['stages'] as $stage) {
            foreach (['stage_code', 'stage_title', 'stage_type'] as $field) {
                if (!isset($stage[$field]) || '' === trim((string) $stage[$field])) {
                    throw new \RuntimeException('Workflow stage missing required field: ' . $field);
                }
            }

            if (isset($stageCodes[$stage['stage_code']])) {
                throw new \RuntimeException('Workflow stage_code duplicated: ' . $stage['stage_code']);
            }

            $stageCodes[$stage['stage_code']] = true;
        }

        if (!isset($stageCodes[$definition['entry_stage_code']])) {
            throw new \RuntimeException('Workflow entry_stage_code not found in stages.');
        }

        if (!empty($definition['transitions'])) {
            foreach ($definition['transitions'] as $transition) {
                foreach (['transition_code', 'from_stage_code', 'action_code', 'transition_kind'] as $field) {
                    if (!isset($transition[$field]) || '' === trim((string) $transition[$field])) {
                        throw new \RuntimeException('Workflow transition missing required field: ' . $field);
                    }
                }

                if (!isset($stageCodes[$transition['from_stage_code']])) {
                    throw new \RuntimeException('Workflow transition from_stage_code not found: ' . $transition['from_stage_code']);
                }

                if (!empty($transition['to_stage_code']) && !isset($stageCodes[$transition['to_stage_code']])) {
                    throw new \RuntimeException('Workflow transition to_stage_code not found: ' . $transition['to_stage_code']);
                }
            }
        }

        return true;
    }

    public static function getOrCreateInstance($workflowCode, $version, $bizModule, $bizEntityType, $bizEntityId, $operatorId)
    {
        throw new \RuntimeException('Workflow instance persistence API has been retired. Modules must own runtime state and call WorkflowEngine with workflow JSON plus runtime context.');
    }

    public static function getOrCreateInstanceFromDefinition($definition, $bizModule, $bizEntityType, $bizEntityId, $operatorId)
    {
        throw new \RuntimeException('Workflow instance persistence API has been retired. Modules must own runtime state and call WorkflowEngine with workflow JSON plus runtime context.');
    }

    public static function transitByInstanceId($instanceId, $actionCode, $operatorId, $context = [])
    {
        throw new \RuntimeException('Workflow instance persistence API has been retired. Use WorkflowEngine::transit() with runtime context.');
    }

    private static function transitRuntimeWithDefinition($definition, $actionCode, $operatorId, $runtimeContext = [])
    {
        self::validateDefinitionPayload($definition);

        $currentStageCodes = self::resolveRuntimeStageCodes($definition, $runtimeContext);
        $currentStateCode = self::resolveRuntimeStateCode($definition, $currentStageCodes, $runtimeContext);
        $traceRows = self::normalizeRuntimeTraceRows(isset($runtimeContext['trace_rows']) ? $runtimeContext['trace_rows'] : []);

        $instance = [
            'id' => isset($runtimeContext['instance_id']) ? (int) $runtimeContext['instance_id'] : 0,
            'current_stage_codes_json' => $currentStageCodes,
            'current_state_code' => $currentStateCode,
            'available_action_codes_json' => self::collectAvailableActionCodesForStages($definition, $currentStageCodes),
            'trace_rows' => $traceRows,
        ];

        $transition = self::resolveTransition($definition, $instance, $actionCode);
        if (empty($transition)) {
            throw new \RuntimeException('Workflow transition not allowed.');
        }

        self::assertRoleAllowed($definition, $transition['from_stage_code'], isset($runtimeContext['operator_role_constant']) ? $runtimeContext['operator_role_constant'] : null);

        $transitionOutcome = self::resolveTransitionOutcome($definition, $instance, $transition, $operatorId);

        $traceData = [
            'id' => count($traceRows) + 1,
            'from_stage_codes_json' => json_encode($instance['current_stage_codes_json']),
            'to_stage_codes_json' => json_encode($transitionOutcome['target_stage_codes']),
            'from_state_code' => $instance['current_state_code'],
            'to_state_code' => $transitionOutcome['target_state_code'],
            'action_code' => $transition['action_code'],
            'action_label' => $transition['action_label'],
            'transition_kind' => $transition['transition_kind'],
            'operator_role_constant' => isset($runtimeContext['operator_role_constant']) ? $runtimeContext['operator_role_constant'] : null,
            'operator_id' => $operatorId,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $updatedTraceRows = $traceRows;
        $updatedTraceRows[] = $traceData;

        $updatedInstance = [
            'id' => $instance['id'],
            'current_stage_codes_json' => json_encode($transitionOutcome['target_stage_codes']),
            'current_state_code' => $transitionOutcome['target_state_code'],
            'available_action_codes_json' => json_encode($transitionOutcome['available_action_codes']),
            'last_ts' => date('Y-m-d H:i:s'),
            'last_user' => $operatorId,
        ];

        $updatedInstance = self::hydrateInstance($updatedInstance);

        return [
            'instance' => $updatedInstance,
            'transition' => $transition,
            'runtime' => [
                'join_pending' => $transitionOutcome['join_pending'],
                'join_required_count' => $transitionOutcome['join_required_count'],
                'join_completed_count' => $transitionOutcome['join_completed_count'],
            ],
            'trace' => $traceData,
            'trace_rows' => $updatedTraceRows,
        ];
    }

    public static function syncInstanceState($instanceId, $stateCode, $operatorId = null)
    {
        throw new \RuntimeException('Workflow instance persistence API has been retired. Use runtime context with current_state_code/current_stage_codes instead.');
    }

    public static function projectInstance($instanceId)
    {
        throw new \RuntimeException('Workflow instance persistence API has been retired. Use WorkflowEngine::project() with workflow JSON plus runtime context.');
    }

    private static function loadStages($definitionId)
    {
        $rows = mh()->select(self::TB_DEFINITION_STAGE, '*', [
            'parent_id' => $definitionId,
            'ORDER' => ['sorter' => 'ASC', 'id' => 'ASC'],
        ]);

        if (empty($rows)) {
            return [];
        }

        foreach ($rows as &$row) {
            $row['join_policy_json'] = self::decodeJsonField($row, 'join_policy_json');
            $row['allowed_role_constants_json'] = self::decodeJsonField($row, 'allowed_role_constants_json');
            $row['rollbackable_to_json'] = self::decodeJsonField($row, 'rollbackable_to_json');
            $row['meta_json'] = self::decodeJsonField($row, 'meta_json');
        }

        return $rows;
    }

    private static function loadTransitions($definitionId)
    {
        $rows = mh()->select(self::TB_DEFINITION_TRANSITION, '*', [
            'parent_id' => $definitionId,
            'ORDER' => ['priority' => 'ASC', 'id' => 'ASC'],
        ]);

        if (empty($rows)) {
            return [];
        }

        foreach ($rows as &$row) {
            $row['guard_rule_json'] = self::decodeJsonField($row, 'guard_rule_json');
            $row['effect_rule_json'] = self::decodeJsonField($row, 'effect_rule_json');
            $row['meta_json'] = self::decodeJsonField($row, 'meta_json');
        }

        return $rows;
    }

    private static function loadRoleMap($definitionId)
    {
        $rows = mh()->select(self::TB_DEFINITION_ROLE_MAP, '*', [
            'parent_id' => $definitionId,
            'ORDER' => ['id' => 'ASC'],
        ]);

        return empty($rows) ? [] : $rows;
    }

    private static function hydrateInstance($instance)
    {
        if (empty($instance)) {
            return null;
        }

        $instance['current_stage_codes_json'] = self::decodeJsonField($instance, 'current_stage_codes_json');
        $instance['available_action_codes_json'] = self::decodeJsonField($instance, 'available_action_codes_json');

        return $instance;
    }

    private static function resolveTransition($definition, $instance, $actionCode)
    {
        foreach ($definition['transitions'] as $transition) {
            if ($transition['action_code'] !== $actionCode) {
                continue;
            }

            if (in_array($transition['from_stage_code'], $instance['current_stage_codes_json'], true)) {
                return $transition;
            }
        }

        return null;
    }

    private static function collectAvailableActionCodes($definition, $stageCode)
    {
        if (empty($stageCode)) {
            return [];
        }

        $actionCodes = [];
        foreach ($definition['transitions'] as $transition) {
            if ($transition['from_stage_code'] !== $stageCode) {
                continue;
            }

            $actionCodes[] = $transition['action_code'];
        }

        return array_values(array_unique($actionCodes));
    }

    private static function collectAvailableActionCodesForStages($definition, $stageCodes)
    {
        if (empty($stageCodes) || !is_array($stageCodes)) {
            return [];
        }

        $actionCodes = [];
        foreach ($stageCodes as $stageCode) {
            $actionCodes = array_merge($actionCodes, self::collectAvailableActionCodes($definition, $stageCode));
        }

        return array_values(array_unique($actionCodes));
    }

    private static function resolveTransitionOutcome($definition, $instance, $transition, $operatorId)
    {
        $targetStateCode = !empty($transition['to_state_code']) ? $transition['to_state_code'] : $transition['to_stage_code'];
        $targetStageCodes = [];
        if (!empty($transition['to_stage_code'])) {
            $targetStageCodes[] = $transition['to_stage_code'];
        }

        $availableActionCodes = self::collectAvailableActionCodes($definition, $transition['to_stage_code']);
        $joinPending = false;
        $joinRequiredCount = null;
        $joinCompletedCount = null;

        $sourceStage = self::findStageByCode($definition, $transition['from_stage_code']);
        if (self::shouldUseParallelJoinRuntime($sourceStage, $transition)) {
            $joinRequiredCount = self::resolveParallelJoinRequiredCount($sourceStage);
            $joinCompletedCount = self::countParallelJoinParticipants($instance, $transition);

            if (self::hasParallelJoinParticipant($instance, $transition, $operatorId)) {
                throw new \RuntimeException('Workflow operator already participated in current parallel stage action.');
            }

            if (($joinCompletedCount + 1) < $joinRequiredCount) {
                $joinPending = true;
                $targetStageCodes = $instance['current_stage_codes_json'];
                $targetStateCode = $instance['current_state_code'];
                $availableActionCodes = $instance['available_action_codes_json'];
            }

            $joinCompletedCount += 1;
        }

        return [
            'target_stage_codes' => $targetStageCodes,
            'target_state_code' => $targetStateCode,
            'available_action_codes' => $availableActionCodes,
            'join_pending' => $joinPending,
            'join_required_count' => $joinRequiredCount,
            'join_completed_count' => $joinCompletedCount,
        ];
    }

    private static function shouldUseParallelJoinRuntime($stage, $transition)
    {
        if (empty($stage)) {
            return false;
        }

        if ('parallel' !== $stage['stage_type']) {
            return false;
        }

        if ('forward' !== $transition['transition_kind']) {
            return false;
        }

        return in_array($stage['join_policy_type'], ['all_of', 'quorum'], true);
    }

    private static function resolveParallelJoinRequiredCount($stage)
    {
        if (!empty($stage['join_policy_required_count'])) {
            return (int) $stage['join_policy_required_count'];
        }

        if (!empty($stage['join_policy_json']['requiredCount'])) {
            return (int) $stage['join_policy_json']['requiredCount'];
        }

        if (!empty($stage['join_policy_json']['requiredBranches']) && is_array($stage['join_policy_json']['requiredBranches'])) {
            return count($stage['join_policy_json']['requiredBranches']);
        }

        return 1;
    }

    private static function countParallelJoinParticipants($instance, $transition)
    {
        $rows = self::loadParallelJoinTraceRows($instance, $transition);
        $operatorIds = array_unique(array_map('intval', array_column($rows, 'operator_id')));

        return count(array_filter($operatorIds, function ($operatorId) {
            return $operatorId > 0;
        }));
    }

    private static function hasParallelJoinParticipant($instance, $transition, $operatorId)
    {
        $operatorId = (int) $operatorId;
        if ($operatorId <= 0) {
            return false;
        }

        foreach (self::loadParallelJoinTraceRows($instance, $transition) as $row) {
            if ((int) $row['operator_id'] === $operatorId) {
                return true;
            }
        }

        return false;
    }

    private static function loadParallelJoinTraceRows($instance, $transition)
    {
        if (empty($instance['trace_rows']) || !is_array($instance['trace_rows'])) {
            return [];
        }

        $currentStageCodesJson = json_encode(array_values($instance['current_stage_codes_json']));
        $stageEntryTraceId = self::findCurrentStageEntryTraceIdFromRows($instance['trace_rows'], $currentStageCodesJson);

        $rows = [];
        foreach ($instance['trace_rows'] as $row) {
            if (!isset($row['action_code']) || $row['action_code'] !== $transition['action_code']) {
                continue;
            }

            if (!isset($row['from_stage_codes_json']) || $row['from_stage_codes_json'] !== $currentStageCodesJson) {
                continue;
            }

            if ($stageEntryTraceId > 0 && (int) $row['id'] <= $stageEntryTraceId) {
                continue;
            }

            $rows[] = [
                'id' => (int) $row['id'],
                'operator_id' => isset($row['operator_id']) ? (int) $row['operator_id'] : 0,
            ];
        }

        return $rows;
    }

    private static function findCurrentStageEntryTraceIdFromRows($rows, $currentStageCodesJson)
    {
        if (empty($rows)) {
            return 0;
        }

        $stageEntryTraceId = 0;
        foreach ($rows as $row) {
            if ($row['to_stage_codes_json'] !== $currentStageCodesJson) {
                continue;
            }

            if ($row['from_stage_codes_json'] === $currentStageCodesJson) {
                continue;
            }

            $stageEntryTraceId = (int) $row['id'];
        }

        return $stageEntryTraceId;
    }

    private static function normalizeRuntimeTraceRows($rows)
    {
        if (empty($rows) || !is_array($rows)) {
            return [];
        }

        $normalizedRows = [];
        $nextId = 1;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $normalizedRows[] = [
                'id' => isset($row['id']) ? (int) $row['id'] : $nextId,
                'from_stage_codes_json' => isset($row['from_stage_codes_json'])
                    ? (string) $row['from_stage_codes_json']
                    : json_encode(isset($row['from_stage_codes']) ? (array) $row['from_stage_codes'] : []),
                'to_stage_codes_json' => isset($row['to_stage_codes_json'])
                    ? (string) $row['to_stage_codes_json']
                    : json_encode(isset($row['to_stage_codes']) ? (array) $row['to_stage_codes'] : []),
                'from_state_code' => isset($row['from_state_code']) ? (string) $row['from_state_code'] : '',
                'to_state_code' => isset($row['to_state_code']) ? (string) $row['to_state_code'] : '',
                'action_code' => isset($row['action_code']) ? (string) $row['action_code'] : '',
                'action_label' => isset($row['action_label']) ? (string) $row['action_label'] : '',
                'transition_kind' => isset($row['transition_kind']) ? (string) $row['transition_kind'] : '',
                'operator_role_constant' => isset($row['operator_role_constant']) ? (string) $row['operator_role_constant'] : null,
                'operator_id' => isset($row['operator_id']) ? (int) $row['operator_id'] : 0,
                'created_at' => isset($row['created_at']) ? (string) $row['created_at'] : '',
            ];

            $nextId += 1;
        }

        return $normalizedRows;
    }

    private static function assertRoleAllowed($definition, $stageCode, $operatorRoleConstant)
    {
        $stage = self::findStageByCode($definition, $stageCode);
        if (empty($stage)) {
            throw new \RuntimeException('Workflow stage not found for role guard.');
        }

        $allowedRoleConstants = isset($stage['allowed_role_constants_json']) ? $stage['allowed_role_constants_json'] : [];
        if (empty($allowedRoleConstants)) {
            return;
        }

        $operatorRoleConstant = trim((string) $operatorRoleConstant);
        if ('' === $operatorRoleConstant) {
            throw new \RuntimeException('Workflow operator role is required.');
        }

        if (!in_array($operatorRoleConstant, $allowedRoleConstants, true)) {
            throw new \RuntimeException('Workflow operator role not allowed for current stage.');
        }
    }

    private static function findStageByCode($definition, $stageCode)
    {
        foreach ($definition['stages'] as $stage) {
            if ($stage['stage_code'] === $stageCode) {
                return $stage;
            }
        }

        return null;
    }

    private static function isTerminalStage($definition, $stageCode)
    {
        if (empty($stageCode)) {
            return false;
        }

        $stage = self::findStageByCode($definition, $stageCode);
        if (empty($stage)) {
            return false;
        }

        return 'terminal' === $stage['stage_type'];
    }

    private static function normalizeDefinitionPayload($workflowJson, $options = [])
    {
        if (
            is_array($workflowJson)
            && isset($workflowJson['workflow_code'])
            && isset($workflowJson['definition_json'])
            && is_array($workflowJson['definition_json'])
            && !empty($workflowJson['definition_json']['workflow'])
        ) {
            self::validateDefinitionPayload($workflowJson);

            return $workflowJson;
        }

        if (is_string($workflowJson)) {
            $workflowJson = json_decode($workflowJson, true);
            if (JSON_ERROR_NONE !== json_last_error()) {
                throw new \RuntimeException('Workflow JSON payload is invalid.');
            }
        }

        if (!is_array($workflowJson) || empty($workflowJson)) {
            throw new \RuntimeException('Workflow JSON payload is invalid.');
        }

        $workflowPayload = isset($workflowJson['workflow']) ? $workflowJson['workflow'] : $workflowJson;
        if (!is_array($workflowPayload) || empty($workflowPayload)) {
            throw new \RuntimeException('Workflow JSON payload missing workflow root.');
        }

        $definition = [
            'id' => isset($options['id']) ? (int) $options['id'] : null,
            'workflow_code' => isset($options['workflow_code']) ? (string) $options['workflow_code'] : (isset($workflowPayload['code']) ? (string) $workflowPayload['code'] : ''),
            'title' => isset($options['title']) ? (string) $options['title'] : (isset($workflowPayload['title']) ? (string) $workflowPayload['title'] : ''),
            'version' => isset($options['version']) ? (int) $options['version'] : (isset($workflowPayload['version']) ? (int) $workflowPayload['version'] : 0),
            'status' => isset($options['status']) ? (string) $options['status'] : (isset($workflowPayload['status']) ? (string) $workflowPayload['status'] : 'active'),
            'source_type' => isset($options['source_type']) ? (string) $options['source_type'] : (isset($workflowPayload['sourceType']) ? (string) $workflowPayload['sourceType'] : 'json'),
            'entry_stage_code' => isset($options['entry_stage_code']) ? (string) $options['entry_stage_code'] : (isset($workflowPayload['entryStageCode']) ? (string) $workflowPayload['entryStageCode'] : ''),
            'definition_json' => ['workflow' => $workflowPayload],
            'terminal_state_codes_json' => isset($options['terminal_state_codes_json']) ? (array) $options['terminal_state_codes_json'] : (isset($workflowPayload['terminalStateCodes']) ? (array) $workflowPayload['terminalStateCodes'] : []),
            'meta_json' => isset($options['meta_json']) ? (array) $options['meta_json'] : [],
            'stages' => self::normalizeWorkflowStages(isset($workflowPayload['stages']) ? $workflowPayload['stages'] : []),
            'transitions' => self::normalizeWorkflowTransitions(isset($workflowPayload['transitions']) ? $workflowPayload['transitions'] : []),
            'role_map' => self::normalizeWorkflowRoleMap(isset($workflowPayload['roleConstants']) ? $workflowPayload['roleConstants'] : []),
        ];

        self::validateDefinitionPayload($definition);

        return $definition;
    }

    private static function loadDefinitionFromFile($workflowCode, $version = null)
    {
        $definitionPath = self::resolveDefinitionFilePath($workflowCode);
        if (null === $definitionPath || !is_file($definitionPath)) {
            return null;
        }

        $definitionJson = @file_get_contents($definitionPath);
        if (false === $definitionJson || '' === $definitionJson) {
            return null;
        }

        $definition = self::normalizeDefinitionPayload($definitionJson);
        if (null !== $version && (int) $definition['version'] !== (int) $version) {
            return null;
        }

        return $definition;
    }

    private static function resolveDefinitionFilePath($workflowCode)
    {
        $definitionMap = [
            'PRESS_BASIC' => dirname(__DIR__) . '/modules/Press/flow.json',
        ];

        if (isset($definitionMap[$workflowCode])) {
            return $definitionMap[$workflowCode];
        }

        $fixturePath = dirname(__DIR__) . '/scripts/workflow_fixtures/' . strtolower($workflowCode) . '.json';

        return is_file($fixturePath) ? $fixturePath : null;
    }

    private static function normalizeWorkflowStages($stages)
    {
        if (!is_array($stages)) {
            return [];
        }

        $normalizedStages = [];
        foreach (array_values($stages) as $index => $stage) {
            if (!is_array($stage)) {
                continue;
            }

            $joinPolicy = isset($stage['joinPolicy']) && is_array($stage['joinPolicy']) ? $stage['joinPolicy'] : [];
            $requiredCount = null;
            if (isset($joinPolicy['requiredCount'])) {
                $requiredCount = (int) $joinPolicy['requiredCount'];
            } elseif (!empty($joinPolicy['requiredBranches']) && is_array($joinPolicy['requiredBranches'])) {
                $requiredCount = count($joinPolicy['requiredBranches']);
            }

            $normalizedStages[] = [
                'stage_code' => isset($stage['stageCode']) ? (string) $stage['stageCode'] : '',
                'stage_title' => isset($stage['stageTitle']) ? (string) $stage['stageTitle'] : '',
                'stage_type' => isset($stage['stageType']) ? (string) $stage['stageType'] : '',
                'action_mode' => isset($stage['actionMode']) ? $stage['actionMode'] : null,
                'join_policy_type' => isset($joinPolicy['type']) ? (string) $joinPolicy['type'] : null,
                'join_policy_required_count' => $requiredCount,
                'join_policy_json' => $joinPolicy,
                'allowed_role_constants_json' => isset($stage['allowedRoleConstants']) ? (array) $stage['allowedRoleConstants'] : [],
                'rollbackable_to_json' => isset($stage['rollbackableTo']) ? (array) $stage['rollbackableTo'] : [],
                'meta_json' => isset($stage['meta']) && is_array($stage['meta']) ? $stage['meta'] : [],
                'sorter' => ($index + 1) * 10,
            ];
        }

        return $normalizedStages;
    }

    private static function normalizeWorkflowTransitions($transitions)
    {
        if (!is_array($transitions)) {
            return [];
        }

        $normalizedTransitions = [];
        foreach (array_values($transitions) as $index => $transition) {
            if (!is_array($transition)) {
                continue;
            }

            $normalizedTransitions[] = [
                'transition_code' => isset($transition['transitionCode']) ? (string) $transition['transitionCode'] : '',
                'from_stage_code' => isset($transition['fromStageCode']) ? (string) $transition['fromStageCode'] : '',
                'action_code' => isset($transition['actionCode']) ? (string) $transition['actionCode'] : '',
                'action_label' => isset($transition['actionLabel']) ? (string) $transition['actionLabel'] : '',
                'transition_kind' => isset($transition['transitionKind']) ? (string) $transition['transitionKind'] : '',
                'to_stage_code' => isset($transition['toStageCode']) ? (string) $transition['toStageCode'] : '',
                'to_state_code' => isset($transition['toStateCode']) ? (string) $transition['toStateCode'] : '',
                'guard_rule_json' => isset($transition['guardRule']) && is_array($transition['guardRule']) ? $transition['guardRule'] : [],
                'effect_rule_json' => isset($transition['effectRule']) && is_array($transition['effectRule']) ? $transition['effectRule'] : [],
                'priority' => ($index + 1) * 10,
                'meta_json' => isset($transition['meta']) && is_array($transition['meta']) ? $transition['meta'] : [],
            ];
        }

        return $normalizedTransitions;
    }

    private static function normalizeWorkflowRoleMap($roleConstants)
    {
        if (!is_array($roleConstants)) {
            return [];
        }

        $normalizedRoleMap = [];
        foreach ($roleConstants as $roleConstant => $roleLabel) {
            $normalizedRoleMap[] = [
                'role_constant' => (string) $roleConstant,
                'role_label' => (string) $roleLabel,
            ];
        }

        return $normalizedRoleMap;
    }

    private static function resolveRuntimeStageCodes($definition, $runtimeContext)
    {
        if (!empty($runtimeContext['current_stage_codes']) && is_array($runtimeContext['current_stage_codes'])) {
            return array_values(array_filter(array_map('strval', $runtimeContext['current_stage_codes'])));
        }

        if (isset($runtimeContext['current_state_code']) && '' !== trim((string) $runtimeContext['current_state_code'])) {
            $stageCode = self::resolveStageCodeForState($definition, (string) $runtimeContext['current_state_code']);
            if ('' !== $stageCode) {
                return [$stageCode];
            }
        }

        return !empty($definition['entry_stage_code']) ? [$definition['entry_stage_code']] : [];
    }

    private static function resolveRuntimeStateCode($definition, $currentStageCodes, $runtimeContext)
    {
        if (isset($runtimeContext['current_state_code']) && '' !== trim((string) $runtimeContext['current_state_code'])) {
            return (string) $runtimeContext['current_state_code'];
        }

        if (empty($currentStageCodes)) {
            return '';
        }

        return self::resolveStateCodeForStage($definition, $currentStageCodes[0]);
    }

    private static function buildProjection($definition, $currentStageCodes, $currentStateCode)
    {
        $currentStages = [];
        foreach ($currentStageCodes as $stageCode) {
            $stage = self::findStageByCode($definition, $stageCode);
            if (empty($stage)) {
                continue;
            }

            $currentStages[] = [
                'stage_code' => $stage['stage_code'],
                'stage_title' => $stage['stage_title'],
                'stage_type' => $stage['stage_type'],
                'allowed_role_constants' => isset($stage['allowed_role_constants_json']) ? $stage['allowed_role_constants_json'] : [],
            ];
        }

        $availableTransitions = [];
        foreach ($definition['transitions'] as $transition) {
            if (!in_array($transition['from_stage_code'], $currentStageCodes, true)) {
                continue;
            }

            $availableTransitions[] = [
                'action_code' => $transition['action_code'],
                'action_label' => $transition['action_label'],
                'transition_kind' => $transition['transition_kind'],
                'from_stage_code' => $transition['from_stage_code'],
                'to_stage_code' => $transition['to_stage_code'],
                'to_state_code' => $transition['to_state_code'],
                'next_step_judgment' => [
                    'next_stage_code' => $transition['to_stage_code'],
                    'next_state_code' => !empty($transition['to_state_code']) ? $transition['to_state_code'] : $transition['to_stage_code'],
                    'is_terminal' => ('terminate' === $transition['transition_kind']) || self::isTerminalStage($definition, $transition['to_stage_code']),
                ],
            ];
        }

        return [
            'workflow_code' => $definition['workflow_code'],
            'workflow_version' => $definition['version'],
            'current_state_code' => $currentStateCode,
            'current_stage_codes' => $currentStageCodes,
            'current_stages' => $currentStages,
            'available_action_codes' => self::collectAvailableActionCodesForStages($definition, $currentStageCodes),
            'available_transitions' => $availableTransitions,
        ];
    }

    private static function resolveStateCodeForStage($definition, $stageCode)
    {
        if (empty($stageCode)) {
            return '';
        }

        foreach ($definition['stages'] as $stage) {
            if ($stage['stage_code'] !== $stageCode) {
                continue;
            }

            if (!empty($stage['meta_json']['stateCode'])) {
                return (string) $stage['meta_json']['stateCode'];
            }

            if (!empty($stage['meta_json']['pressStatus'])) {
                return (string) $stage['meta_json']['pressStatus'];
            }

            return (string) $stageCode;
        }

        return (string) $stageCode;
    }

    private static function resolveStageCodeForState($definition, $stateCode)
    {
        if ('' === (string) $stateCode) {
            return '';
        }

        foreach ($definition['stages'] as $stage) {
            if (!empty($stage['meta_json']['stateCode']) && $stage['meta_json']['stateCode'] === $stateCode) {
                return (string) $stage['stage_code'];
            }

            if (!empty($stage['meta_json']['pressStatus']) && $stage['meta_json']['pressStatus'] === $stateCode) {
                return (string) $stage['stage_code'];
            }
        }

        foreach ($definition['transitions'] as $transition) {
            if (!empty($transition['to_state_code']) && $transition['to_state_code'] === $stateCode && !empty($transition['to_stage_code'])) {
                return (string) $transition['to_stage_code'];
            }
        }

        return '';
    }

    private static function decodeJsonField($row, $field)
    {
        if (!isset($row[$field]) || '' === $row[$field] || null === $row[$field]) {
            return [];
        }

        $decoded = json_decode($row[$field], true);
        if (JSON_ERROR_NONE !== json_last_error()) {
            return [];
        }

        return $decoded;
    }
}