<?php

namespace F3CMS;

/**
 * Architecture Purpose
 * - WorkflowEngine is a shared runtime evaluator inside the FORKS libs layer. It
 *   validates workflow definition payloads, projects current state, and resolves
 *   transition legality without becoming the owner of module persistence or business
 *   coordination.
 *
 * Design Intent
 * - OntoCMS prioritizes FORKS first: workflow rules can be reused across modules,
 *   but owner-side context loading, audit writeback, and business transaction control
 *   must remain with the calling module. WorkflowEngine exists to keep transition
 *   logic stable and portable without collapsing that boundary.
 *
 * Strict Constraints
 * - WorkflowEngine is not a workflow persistence owner. Do not turn it into the
 *   place that owns module runtime tables, entity writeback, or cross-entity duty
 *   judgment just because that looks convenient.
 * - If reuse convenience conflicts with FORKS owner boundary, keep the boundary:
 *   module-side loading, module-owned log tables, and owner transaction control stay
 *   outside shared libs.
 * - Treat definition loading as compatibility input, not as permission to recentralize
 *   workflow runtime ownership back into a shared engine table model.
 *
 * Porting Guidelines
 * - In .NET or Go, map WorkflowEngine to a stateless workflow runtime service or
 *   evaluator. Let modules provide definition payloads and runtime context, and let
 *   modules persist audit trails or business state changes in their own owner-side
 *   boundaries.
 * - Preserve the rule stabilized in this repo's ArchitecturalIntent spec: FORKS
 *   owner boundary comes before local abstraction convenience.
 */

class WorkflowEngine extends Helper
{
    const TB_DEFINITION = 'tbl_workflow_definition';
    const TB_DEFINITION_STAGE = 'tbl_workflow_definition_stage';
    const TB_DEFINITION_TRANSITION = 'tbl_workflow_definition_transition';
    const TB_DEFINITION_ROLE_MAP = 'tbl_workflow_definition_role_map';

    private $definition = [];

    /**
     * Architecture Role
     * - Initializes the shared workflow runtime evaluator with a normalized definition
     *   payload supplied by an owner-side caller.
     *
     * Strict Constraints
     * - Construction only prepares runtime evaluation state; it does not make
     *   WorkflowEngine the owner of module persistence or context loading.
     *
     * Porting Note
     * - Preserve this as the thin constructor that accepts definition input and delegates
     *   normalization to internal helpers.
     */
    public function __construct($workflowJson, $options = [])
    {
        $this->definition = self::normalizeDefinitionPayload($workflowJson, $options);
    }

    /**
     * Architecture Role
     * - Validates the in-memory workflow definition currently held by this evaluator
     *   instance.
     *
     * Strict Constraints
     * - Definition validation stays inside the shared runtime layer; callers should not
     *   fork competing validation rules around the engine.
     *
     * Porting Note
     * - Keep this as the instance-level validation entry point over the canonical
     *   payload-validation helper.
     */
    public function validateDefinition()
    {
        return self::validateDefinitionPayload($this->definition);
    }

    /**
     * Architecture Role
     * - Exposes the normalized workflow definition payload that this evaluator instance
     *   will project and transit against.
     *
     * Strict Constraints
     * - This is a read-only runtime payload surface, not a signal that WorkflowEngine now
     *   owns durable definition storage.
     *
     * Porting Note
     * - Preserve this as the accessor for normalized definition data when host runtimes
     *   need to inspect the current evaluator payload.
     */
    public function getDefinitionPayload()
    {
        return $this->definition;
    }

    /**
     * Architecture Role
     * - Projects the current workflow state from owner-supplied runtime context into a
     *   shared runtime view of stages, state, and available actions.
     *
     * Strict Constraints
     * - Projection uses caller-provided runtime context; it must not pull owner-side
     *   entity state or persistence directly into WorkflowEngine.
     *
     * Porting Note
     * - Keep this as the stateless projection entry point for workflow UI or decision
     *   surfaces in target runtimes.
     */
    public function project($runtimeContext = [])
    {
        $currentStageCodes = self::resolveRuntimeStageCodes($this->definition, $runtimeContext);
        $currentStateCode = self::resolveRuntimeStateCode($this->definition, $currentStageCodes, $runtimeContext);

        return self::buildProjection($this->definition, $currentStageCodes, $currentStateCode);
    }

    /**
     * Architecture Role
     * - Checks whether a requested action can transition under the supplied runtime
     *   context without performing owner-side state persistence.
     *
     * Strict Constraints
     * - Transition gating belongs in the shared runtime evaluator, but module-owned role
     *   sourcing and state persistence still remain outside WorkflowEngine.
     *
     * Porting Note
     * - Preserve this as the non-mutating transition eligibility check for callers that
     *   need to test actions before invoking a write path.
     */
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

    /**
     * Architecture Role
     * - Executes a workflow transition against the current definition and runtime
     *   context, returning the runtime transition outcome without taking over module
     *   persistence ownership.
     *
     * Strict Constraints
     * - WorkflowEngine may resolve the transition outcome, but modules still own the
     *   durable runtime state, audit writeback, and business transaction boundary.
     *
     * Porting Note
     * - Keep this as the mutating runtime-evaluation entry point, with owner-side callers
     *   responsible for persisting the resulting business effects.
     */
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

    /**
     * Architecture Role
     * - Loads a workflow definition payload from compatibility sources so the shared
     *   evaluator can run against file-backed or legacy table-backed definitions.
     *
     * Strict Constraints
     * - Definition loading is a compatibility adapter only; it must not be treated as a
     *   green light to move runtime ownership back into a shared engine store.
     *
     * Porting Note
     * - Preserve file-first loading semantics if legacy compatibility still matters, but
     *   keep owner modules responsible for choosing when and why a definition is loaded.
     */
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

    /**
     * Architecture Role
     * - Enforces the canonical structural contract for workflow definition payloads
     *   before the runtime evaluator accepts them.
     *
     * Strict Constraints
     * - Definition-shape validation belongs in WorkflowEngine so callers cannot drift
     *   into incompatible workflow schemas.
     *
     * Porting Note
     * - Keep this as the central schema guard for workflow definitions, even if target
     *   runtimes later map the checks into stricter typed models.
     */
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

    /**
     * Architecture Role
     * - Marks the old shared-instance persistence entry point as retired so callers do
     *   not route owner-side runtime storage back through WorkflowEngine.
     *
     * Strict Constraints
     * - Workflow instance creation now belongs to owner modules; this method must stay a
     *   boundary guard, not quietly reintroduce shared persistence ownership.
     *
     * Porting Note
     * - Preserve this retirement signal when migrating legacy integrations away from the
     *   old shared instance API.
     */
    public static function getOrCreateInstance($workflowCode, $version, $bizModule, $bizEntityType, $bizEntityId, $operatorId)
    {
        throw new \RuntimeException('Workflow instance persistence API has been retired. Modules must own runtime state and call WorkflowEngine with workflow JSON plus runtime context.');
    }

    /**
     * Architecture Role
     * - Blocks the older definition-to-instance shared persistence path for the same
     *   owner-boundary reason as `getOrCreateInstance()`.
     *
     * Strict Constraints
     * - Do not revive instance creation inside WorkflowEngine just because definition data
     *   is already available here; owner modules still own runtime storage.
     *
     * Porting Note
     * - Keep this explicit retirement guard so future ports do not accidentally regress to
     *   a shared engine-owned instance model.
     */
    public static function getOrCreateInstanceFromDefinition($definition, $bizModule, $bizEntityType, $bizEntityId, $operatorId)
    {
        throw new \RuntimeException('Workflow instance persistence API has been retired. Modules must own runtime state and call WorkflowEngine with workflow JSON plus runtime context.');
    }

    /**
     * Architecture Role
     * - Rejects the legacy transition-by-instance-id entry point that assumed shared
     *   engine-owned runtime persistence.
     *
     * Strict Constraints
     * - Transition execution must now use owner-supplied runtime context, not a shared
     *   instance lookup hidden inside WorkflowEngine.
     *
     * Porting Note
     * - Preserve this guardrail during migration so integrations move to context-based
     *   transition APIs instead of rebuilding shared lookup flows.
     */
    public static function transitByInstanceId($instanceId, $actionCode, $operatorId, $context = [])
    {
        throw new \RuntimeException('Workflow instance persistence API has been retired. Use WorkflowEngine::transit() with runtime context.');
    }

    /**
     * Architecture Role
     * - Resolves a workflow transition outcome purely from definition payload plus
     *   caller-supplied runtime context, returning the projected next runtime snapshot.
     *
     * Strict Constraints
     * - This method may compute the next runtime state, trace row, and available actions,
     *   but it still does not persist owner-side workflow state or business effects.
     *
     * Porting Note
     * - Keep this as the core runtime transition evaluator, with owner modules persisting
     *   the returned instance/trace payloads in their own boundaries.
     */
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

    /**
     * Architecture Role
     * - Explicitly retires the old shared instance-state synchronization API.
     *
     * Strict Constraints
     * - WorkflowEngine must not retake ownership of persisted runtime state updates via a
     *   convenience sync method.
     *
     * Porting Note
     * - Preserve this retirement boundary so callers continue passing current runtime
     *   state through context instead of reviving shared instance writes.
     */
    public static function syncInstanceState($instanceId, $stateCode, $operatorId = null)
    {
        throw new \RuntimeException('Workflow instance persistence API has been retired. Use runtime context with current_state_code/current_stage_codes instead.');
    }

    /**
     * Architecture Role
     * - Explicitly retires the old projection-by-instance-id API that depended on shared
     *   engine-owned runtime storage.
     *
     * Strict Constraints
     * - Runtime projection must come from definition payload plus caller context, not
     *   hidden shared instance lookup inside libs.
     *
     * Porting Note
     * - Keep this guard in place during migration away from legacy shared-instance APIs.
     */
    public static function projectInstance($instanceId)
    {
        throw new \RuntimeException('Workflow instance persistence API has been retired. Use WorkflowEngine::project() with workflow JSON plus runtime context.');
    }

    /**
     * Architecture Role
     * - Loads and decodes workflow stage rows for legacy definition-backed compatibility
     *   input.
     *
     * Strict Constraints
     * - Stage loading is a definition compatibility helper only; it must not be expanded
     *   into owner-side runtime coordination or business loading.
     *
     * Porting Note
     * - Preserve this only as long as legacy table-backed definition sources remain part
     *   of the compatibility surface.
     */
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

    /**
     * Architecture Role
     * - Loads and decodes workflow transition rows for compatibility-oriented definition
     *   assembly.
     *
     * Strict Constraints
     * - Transition row loading remains a narrow definition-source helper, not a reason to
     *   move transition persistence ownership into WorkflowEngine.
     *
     * Porting Note
     * - Keep this as a compatibility adapter for legacy transition storage if still
     *   required by the target runtime.
     */
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

    /**
     * Architecture Role
     * - Loads legacy role-map rows that augment a definition payload for runtime role
     *   checks.
     *
     * Strict Constraints
     * - Role-map retrieval here is definition support only; actual operator-role sourcing
     *   still comes from owner modules through runtime context.
     *
     * Porting Note
     * - Preserve this as a compatibility loader, not as a substitute for owner-side role
     *   resolution.
     */
    private static function loadRoleMap($definitionId)
    {
        $rows = mh()->select(self::TB_DEFINITION_ROLE_MAP, '*', [
            'parent_id' => $definitionId,
            'ORDER' => ['id' => 'ASC'],
        ]);

        return empty($rows) ? [] : $rows;
    }

    /**
     * Architecture Role
     * - Normalizes a runtime instance snapshot into the decoded shape expected by the
     *   shared evaluator and its callers.
     *
     * Strict Constraints
     * - Hydration only reshapes runtime payload data; it does not imply WorkflowEngine now
     *   owns durable instance records.
     *
     * Porting Note
     * - Keep this as a runtime-payload hydration helper for context-based workflow state.
     */
    private static function hydrateInstance($instance)
    {
        if (empty($instance)) {
            return null;
        }

        $instance['current_stage_codes_json'] = self::decodeJsonField($instance, 'current_stage_codes_json');
        $instance['available_action_codes_json'] = self::decodeJsonField($instance, 'available_action_codes_json');

        return $instance;
    }

    /**
     * Architecture Role
     * - Resolves the concrete transition definition that matches the requested action
     *   from the runtime instance's current stage set.
     *
     * Strict Constraints
     * - Transition selection is shared workflow runtime logic; callers should not fork
     *   their own stage/action matching outside WorkflowEngine.
     *
     * Porting Note
     * - Preserve this as the canonical transition lookup helper for action gating and
     *   transition execution paths.
     */
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

    /**
     * Architecture Role
     * - Collects the action codes that are available from a single workflow stage in the
     *   current definition.
     *
     * Strict Constraints
     * - Available-action computation belongs in the runtime evaluator so projections and
     *   transitions stay aligned to one shared rule source.
     *
     * Porting Note
     * - Keep this as the stage-scoped action collector used by projection and transition
     *   helpers.
     */
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

    /**
     * Architecture Role
     * - Aggregates deduplicated action codes across the current set of active stages.
     *
     * Strict Constraints
     * - Multi-stage available-action aggregation remains a shared runtime concern, not a
     *   per-caller convenience rule.
     *
     * Porting Note
     * - Preserve this as the canonical multi-stage action collector for runtime context
     *   projection and transition checks.
     */
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

    /**
     * Architecture Role
     * - Computes the next runtime snapshot for a resolved transition, including
     *   parallel-join pending semantics and next available actions.
     *
     * Strict Constraints
     * - Outcome calculation stays inside WorkflowEngine as pure runtime evaluation; it
     *   must not be conflated with owner-side persistence or business side effects.
     *
     * Porting Note
     * - Keep this as the central transition-outcome evaluator for context-based workflow
     *   runtimes.
     */
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

    /**
     * Architecture Role
     * - Determines whether the current transition should follow the parallel-join runtime
     *   evaluation path.
     *
     * Strict Constraints
     * - Parallel-join gating rules belong in the shared evaluator so callers do not drift
     *   on when join semantics should apply.
     *
     * Porting Note
     * - Preserve this as the predicate that activates parallel-join runtime behavior.
     */
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

    /**
     * Architecture Role
     * - Resolves how many participants are required before a parallel stage may complete.
     *
     * Strict Constraints
     * - Required-count resolution is part of shared workflow runtime semantics; do not
     *   duplicate this fallback chain in owner modules.
     *
     * Porting Note
     * - Keep this as the canonical required-count resolver for parallel join stages.
     */
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

    /**
     * Architecture Role
     * - Counts distinct operator participation for the current parallel-join action using
     *   the runtime trace rows supplied by the caller.
     *
     * Strict Constraints
     * - Join participation counting remains a runtime evaluation helper; it should not
     *   imply shared ownership of the trace store itself.
     *
     * Porting Note
     * - Preserve this as the participation counter over caller-owned trace payloads.
     */
    private static function countParallelJoinParticipants($instance, $transition)
    {
        $rows = self::loadParallelJoinTraceRows($instance, $transition);
        $operatorIds = array_unique(array_map('intval', array_column($rows, 'operator_id')));

        return count(array_filter($operatorIds, function ($operatorId) {
            return $operatorId > 0;
        }));
    }

    /**
     * Architecture Role
     * - Checks whether the current operator already participated in the active
     *   parallel-join action window.
     *
     * Strict Constraints
     * - Duplicate-participation prevention belongs to shared runtime semantics, while the
     *   underlying trace data still remains caller-owned context.
     *
     * Porting Note
     * - Keep this as the duplicate-operator guard for parallel join evaluation.
     */
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

    /**
     * Architecture Role
     * - Extracts the relevant trace rows for the current parallel-join stage/action from
     *   the caller-supplied runtime trace payload.
     *
     * Strict Constraints
     * - Trace row filtering is a runtime helper only; it does not move trace persistence
     *   or historical ownership into WorkflowEngine.
     *
     * Porting Note
     * - Preserve this as the scoped trace-row extractor for parallel join evaluation.
     */
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

    /**
     * Architecture Role
     * - Finds the trace row that marks entry into the current stage set so parallel-join
     *   participation can be evaluated only within the active stage window.
     *
     * Strict Constraints
     * - Stage-entry boundary detection is part of shared runtime trace semantics, not a
     *   caller-specific convention.
     *
     * Porting Note
     * - Keep this as the helper that anchors parallel join evaluation to the current
     *   stage-entry point.
     */
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

    /**
     * Architecture Role
     * - Normalizes caller-supplied runtime trace rows into the canonical shape consumed
     *   by transition and parallel-join helpers.
     *
     * Strict Constraints
     * - Trace normalization stays in WorkflowEngine so downstream runtime helpers all
     *   read one stable trace schema.
     *
     * Porting Note
     * - Preserve this as the canonical trace-payload normalizer for context-based runtime
     *   evaluation.
     */
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

    /**
     * Architecture Role
     * - Enforces stage-level role access for the current runtime transition or projection
     *   path.
     *
     * Strict Constraints
     * - Role allowance checks belong in the shared runtime evaluator, while actual
     *   operator role sourcing still remains owner-side context input.
     *
     * Porting Note
     * - Preserve this as the canonical stage-role guard used by runtime transition flows.
     */
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

    /**
     * Architecture Role
     * - Resolves a normalized stage definition by stage code from the current workflow
     *   definition payload.
     *
     * Strict Constraints
     * - Stage lookup is shared workflow metadata logic; callers should not rebuild their
     *   own lookup tables outside WorkflowEngine.
     *
     * Porting Note
     * - Keep this as the canonical stage resolver for projection and transition helpers.
     */
    private static function findStageByCode($definition, $stageCode)
    {
        foreach ($definition['stages'] as $stage) {
            if ($stage['stage_code'] === $stageCode) {
                return $stage;
            }
        }

        return null;
    }

    /**
     * Architecture Role
     * - Determines whether a given stage code maps to a terminal workflow stage in the
     *   normalized definition.
     *
     * Strict Constraints
     * - Terminal-stage judgment stays in the shared evaluator so transition projections
     *   do not drift across callers.
     *
     * Porting Note
     * - Preserve this as the helper for terminal-stage checks in runtime projection.
     */
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

    /**
     * Architecture Role
     * - Converts raw workflow JSON or pre-shaped arrays into the normalized definition
     *   payload consumed by the shared runtime evaluator.
     *
     * Strict Constraints
     * - Definition normalization belongs in WorkflowEngine so all callers share one
     *   canonical workflow schema before runtime evaluation begins.
     *
     * Porting Note
     * - Keep this as the single normalization gateway from transport/config payloads into
     *   the runtime definition model.
     */
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

    /**
     * Architecture Role
     * - Loads a workflow definition from file-backed compatibility sources and normalizes
     *   it for runtime use.
     *
     * Strict Constraints
     * - File loading here is a compatibility helper only; it does not make WorkflowEngine
     *   the owner of workflow configuration lifecycle.
     *
     * Porting Note
     * - Preserve this while file-backed definitions remain part of the compatibility
     *   surface, but keep selection of source under owner-side control.
     */
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

    /**
     * Architecture Role
     * - Resolves the compatibility file path for a workflow definition code.
     *
     * Strict Constraints
     * - File-path resolution is a narrow compatibility concern; it must not expand into a
     *   broader workflow ownership or discovery system inside shared libs.
     *
     * Porting Note
     * - Keep this as the lookup helper for legacy fixture/file-backed definitions only.
     */
    private static function resolveDefinitionFilePath($workflowCode)
    {
        $definitionMap = [
            'PRESS_BASIC' => dirname(__DIR__) . '/modules/Press/flow.json',
        ];

        if (isset($definitionMap[$workflowCode])) {
            return $definitionMap[$workflowCode];
        }

        $fixturePath = dirname(__DIR__, 2) . '/tests/fixtures/workflow_engine/' . strtolower($workflowCode) . '.json';

        return is_file($fixturePath) ? $fixturePath : null;
    }

    /**
     * Architecture Role
     * - Normalizes raw stage definitions into the canonical stage rows consumed by the
     *   runtime evaluator.
     *
     * Strict Constraints
     * - Stage-shape normalization belongs in WorkflowEngine so join policy, allowed roles,
     *   and stage metadata do not drift across callers.
     *
     * Porting Note
     * - Preserve this as the stage normalization helper for the runtime definition model.
     */
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

    /**
     * Architecture Role
     * - Normalizes raw transition definitions into the canonical transition rows used by
     *   action gating and runtime projection.
     *
     * Strict Constraints
     * - Transition-shape normalization stays in the shared evaluator to keep transition
     *   semantics consistent across all callers.
     *
     * Porting Note
     * - Keep this as the transition normalization helper for the runtime definition model.
     */
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

    /**
     * Architecture Role
     * - Normalizes workflow role constants into the canonical role-map rows expected by
     *   role-guard helpers.
     *
     * Strict Constraints
     * - Role-map normalization remains a definition-shaping concern; operator role truth
     *   itself still comes from owner-side runtime context.
     *
     * Porting Note
     * - Preserve this as the canonical role-map normalization helper for workflow
     *   definitions.
     */
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

    /**
     * Architecture Role
     * - Resolves the active runtime stage codes from caller-supplied context or falls
     *   back to entry-stage semantics in the normalized definition.
     *
     * Strict Constraints
     * - Runtime stage resolution belongs in the shared evaluator so callers do not drift
     *   on how current stage context is inferred.
     *
     * Porting Note
     * - Preserve this as the canonical stage-code resolver for context-based workflow
     *   projection and transition checks.
     */
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

    /**
     * Architecture Role
     * - Resolves the current runtime state code from caller context or derived stage
     *   mapping when the context omits an explicit state.
     *
     * Strict Constraints
     * - State resolution remains part of shared runtime semantics; callers should not
     *   invent alternative fallback rules outside WorkflowEngine.
     *
     * Porting Note
     * - Keep this as the state-code resolver paired with runtime stage resolution.
     */
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

    /**
     * Architecture Role
     * - Builds the normalized workflow projection payload returned to callers for the
     *   current runtime stage/state context.
     *
     * Strict Constraints
     * - Projection assembly belongs in the shared evaluator so stage metadata, available
     *   actions, and next-step judgment stay aligned to one runtime rule source.
     *
     * Porting Note
     * - Preserve this as the canonical projection builder for workflow UI and decision
     *   surfaces in target runtimes.
     */
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

    /**
     * Architecture Role
     * - Maps a normalized stage code to its effective state code using stage metadata and
     *   fallback stage identity.
     *
     * Strict Constraints
     * - Stage-to-state mapping is shared workflow model logic; callers should not fork
     *   alternate metadata precedence outside WorkflowEngine.
     *
     * Porting Note
     * - Keep this as the canonical stage-to-state resolver for projection and fallback
     *   runtime state inference.
     */
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

    /**
     * Architecture Role
     * - Maps a runtime state code back to the corresponding stage code using normalized
     *   stage metadata and transition fallbacks.
     *
     * Strict Constraints
     * - State-to-stage mapping remains part of the shared workflow evaluator so runtime
     *   context inference is consistent across callers.
     *
     * Porting Note
     * - Preserve this as the inverse state lookup helper for context-driven workflow
     *   evaluation.
     */
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

    /**
     * Architecture Role
     * - Decodes stored JSON field payloads into arrays for the normalized workflow model
     *   and compatibility loaders.
     *
     * Strict Constraints
     * - JSON decode fallback behavior belongs in one shared helper so legacy data parsing
     *   stays consistent across definition and runtime loaders.
     *
     * Porting Note
     * - Keep this as the canonical JSON-field decoder for compatibility-backed workflow
     *   payloads.
     */
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