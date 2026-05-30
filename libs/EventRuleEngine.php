<?php

namespace F3CMS;

use F3CMS\EventRule\RuleParser;

/**
 * Architecture Purpose
 * - Shared event-rule parsing and evaluation runtime for cross-module rule matching.
 *
 * Design Intent
 * - Keep payload validation, AST construction, evaluator dispatch, and fail-closed result shaping in libs while leaving payload loading, context preload, and writeback in owner modules.
 *
 * Strict Constraints
 * - This engine is not an owning module, persistence adapter, or business coordinator.
 * - Duty/task/member/manaccount truth, DB-backed payload loading, reaction integration, and writeback remain module-owned.
 *
 * Porting Guidelines
 * - Port as a shared pure runtime evaluator similar to WorkflowEngine.
 * - Preserve the split between engine/runtime here and owner-side context sourcing in module Kit/Feed boundaries.
 */
class EventRuleEngine extends Helper
{
    private $payload = [];
    private $options = [];
    private $registry;
    private $ast = null;

    /**
     * Architecture Purpose
     * - Initialize the shared event-rule runtime with payload, options, and evaluator registry.
     *
     * Design Intent
     * - Normalize runtime configuration once so evaluation can stay deterministic and detached from owner-side loading logic.
     *
     * Strict Constraints
     * - Construction must not pull payloads from tables or module state; callers own payload sourcing.
     *
     * Porting Guidelines
     * - Port as a pure constructor that accepts already-loaded payload and runtime options.
     */
    public function __construct($payload, $options = [])
    {
        $this->payload = is_array($payload) ? $payload : [];
        $this->options = array_merge([
            'max_depth' => 5,
            'allowed_group_operators' => ['AND', 'OR'],
            'allowed_comparison_operators' => ['>', '>=', '<', '<=', '==', '!='],
            'allowed_types' => ['WATCHED_VIDEO', 'EXAM_SCORE', 'HAS_BADGE', 'MEMBER_SEEN_TARGET'],
        ], is_array($options) ? $options : []);

        $this->registry = isset($this->options['registry']) && $this->options['registry'] instanceof EventRuleEvaluatorRegistry
            ? $this->options['registry']
            : self::createDefaultRegistry();
    }

    /**
     * Architecture Purpose
     * - Create the default evaluator registry for the shared rule types this runtime supports.
     *
     * Design Intent
     * - Keep default evaluator wiring centralized so modules reuse one stable baseline.
     *
     * Strict Constraints
     * - Registry creation must stay type-to-evaluator wiring only, not module-specific feature composition.
     *
     * Porting Guidelines
     * - Port as the default runtime registry factory.
     */
    public static function createDefaultRegistry()
    {
        return self::createRegistryForTypes(['WATCHED_VIDEO', 'EXAM_SCORE', 'HAS_BADGE', 'MEMBER_SEEN_TARGET']);
    }

    /**
     * Architecture Purpose
     * - Build an evaluator registry for a caller-selected set of supported rule types.
     *
     * Design Intent
     * - Allow owner modules to narrow exposed runtime types without changing core evaluation semantics.
     *
     * Strict Constraints
     * - This helper wires evaluators only; it must not preload owner data or permission state.
     *
     * Porting Guidelines
     * - Port as a configurable registry factory that preserves the same type tokens.
     */
    public static function createRegistryForTypes($types)
    {
        $registry = new EventRuleEvaluatorRegistry();

        foreach ((array) $types as $type) {
            $type = strtoupper(trim((string) $type));
            $registry->register($type, self::createEvaluator($type));
        }

        return $registry;
    }

    /**
     * Architecture Purpose
     * - Resolve the shared evaluator implementation for one normalized rule type.
     *
     * Design Intent
     * - Keep evaluator selection explicit so unsupported types fail closed instead of drifting into ad hoc logic.
     *
     * Strict Constraints
     * - This selector must not fall back to owner-side handlers or dynamic module lookups.
     *
     * Porting Guidelines
     * - Port as a strict runtime type-to-evaluator factory.
     */
    private static function createEvaluator($type)
    {
        switch ($type) {
            case 'WATCHED_VIDEO':
                return new EventRuleWatchedVideoEvaluator();
            case 'EXAM_SCORE':
                return new EventRuleExamScoreEvaluator();
            case 'HAS_BADGE':
                return new EventRuleHasBadgeEvaluator();
            case 'MEMBER_SEEN_TARGET':
                return new EventRuleMemberSeenTargetEvaluator();
            default:
                throw new \RuntimeException('Unsupported event rule evaluator type: ' . $type);
        }
    }

    /**
     * Architecture Purpose
     * - Validate the loaded payload against the shared event-rule contract.
     *
     * Design Intent
     * - Fail invalid rule payloads before parsing or evaluation starts.
     *
     * Strict Constraints
     * - Validation here checks shape only; owner modules still own where payloads come from.
     *
     * Porting Guidelines
     * - Port as an explicit pre-evaluation contract check.
     */
    public function validatePayload()
    {
        return EventRulePayloadValidator::assertValid($this->payload, $this->options);
    }

    /**
     * Architecture Purpose
     * - Expose the raw event-rule payload held by this runtime engine.
     *
     * Design Intent
     * - Allow callers to inspect the normalized input without reloading owner state.
     *
     * Strict Constraints
     * - This accessor returns already-provided payload only.
     *
     * Porting Guidelines
     * - Port as a simple payload getter.
     */
    public function getPayload()
    {
        return $this->payload;
    }

    /**
     * Architecture Purpose
     * - Lazily parse the validated payload into the shared runtime AST.
     *
     * Design Intent
     * - Avoid repeated parse work while preserving one canonical AST per engine instance.
     *
     * Strict Constraints
     * - AST creation stays pure runtime shaping; it must not touch persistence or module preload logic.
     *
     * Porting Guidelines
     * - Port as lazy AST memoization after validation.
     */
    public function getAst()
    {
        if (null === $this->ast) {
            $this->validatePayload();
            $this->ast = RuleParser::parse($this->payload);
        }

        return $this->ast;
    }

    /**
     * Architecture Purpose
     * - Evaluate the parsed event-rule tree against a player context.
     *
     * Design Intent
     * - Centralize fail-closed runtime evaluation so modules receive a stable result envelope instead of rolling their own rule execution.
     *
     * Strict Constraints
     * - Evaluation consumes an already-built context only; context loading and writeback stay module-owned.
     *
     * Porting Guidelines
     * - Port as the main rule-engine entry that accepts a preloaded context object or payload.
     */
    public function evaluate($context)
    {
        try {
            $playerContext = $context instanceof EventRulePlayerContext ? $context : new EventRulePlayerContext($context);
            $ast = $this->getAst();

            return $this->evaluateNode($ast, $playerContext);
        } catch (\RuntimeException $e) {
            $resultType = 0 === strpos($e->getMessage(), 'Context ') ? 'context_error' : 'invalid_payload';

            return EventRuleEvaluationResult::failClosed($resultType, '$', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Architecture Purpose
     * - Dispatch one AST node to the correct shared evaluator path.
     *
     * Design Intent
     * - Keep branch selection between group and leaf nodes inside the runtime engine.
     *
     * Strict Constraints
     * - Node dispatch stays structural only.
     *
     * Porting Guidelines
     * - Port as the internal AST dispatcher.
     */
    private function evaluateNode($node, EventRulePlayerContext $context)
    {
        if ('group' === $node['node_kind']) {
            return $this->evaluateGroupNode($node, $context);
        }

        return $this->evaluateLeafNode($node, $context);
    }

    /**
     * Architecture Purpose
     * - Evaluate a grouped rule node with AND/OR semantics.
     *
     * Design Intent
     * - Centralize recursive group evaluation, short-circuit behavior, and trace assembly in the shared runtime.
     *
     * Strict Constraints
     * - Group evaluation must remain pure runtime logic and must not trigger owner-side side effects.
     *
     * Porting Guidelines
     * - Port as recursive boolean group evaluation with the same fail-closed behavior.
     */
    private function evaluateGroupNode($node, EventRulePlayerContext $context)
    {
        $operator = $node['operator'];
        $children = [];

        foreach ($node['rules'] as $childNode) {
            $childResult = $this->evaluateNode($childNode, $context);
            $children[] = $childResult->toArray();

            if (!$childResult->isTerminalBusinessResult()) {
                return EventRuleEvaluationResult::failClosed(
                    $childResult->getResultType(),
                    $childResult->getFailedNodePath(),
                    [
                        'path' => $node['path'],
                        'operator' => $operator,
                        'children' => $children,
                    ]
                );
            }

            if ('AND' === $operator && false === $childResult->isMatched()) {
                return EventRuleEvaluationResult::notMatched($node['path'], [
                    'operator' => $operator,
                    'children' => $children,
                ]);
            }

            if ('OR' === $operator && true === $childResult->isMatched()) {
                return EventRuleEvaluationResult::matched($node['path'], [
                    'operator' => $operator,
                    'children' => $children,
                ]);
            }
        }

        if ('AND' === $operator) {
            return EventRuleEvaluationResult::matched($node['path'], [
                'operator' => $operator,
                'children' => $children,
            ]);
        }

        return EventRuleEvaluationResult::notMatched($node['path'], [
            'operator' => $operator,
            'children' => $children,
        ]);
    }

    /**
     * Architecture Purpose
     * - Evaluate one leaf rule through the registered shared evaluator.
     *
     * Design Intent
     * - Isolate evaluator lookup and leaf-level fail-closed shaping inside the runtime engine.
     *
     * Strict Constraints
     * - Leaf evaluation must not bypass the registry into module-specific hooks.
     *
     * Porting Guidelines
     * - Port as the leaf-dispatch bridge between AST nodes and concrete evaluators.
     */
    private function evaluateLeafNode($node, EventRulePlayerContext $context)
    {
        try {
            $evaluator = $this->registry->resolve($node['type']);
        } catch (\RuntimeException $e) {
            return EventRuleEvaluationResult::failClosed('missing_evaluator', $node['path'], [
                'type' => $node['type'],
                'message' => $e->getMessage(),
            ]);
        }

        try {
            $leafResult = $evaluator->evaluate($node, $context);
        } catch (\RuntimeException $e) {
            $resultType = 0 === strpos($e->getMessage(), 'Context ') ? 'context_error' : 'infrastructure_error';

            return EventRuleEvaluationResult::failClosed($resultType, $node['path'], [
                'type' => $node['type'],
                'message' => $e->getMessage(),
            ]);
        }

        return EventRuleEvaluationResult::fromLeafResult($leafResult, $node['path']);
    }
}

/**
 * Architecture Purpose
 * - Shared payload-shape validator for event-rule runtime inputs.
 *
 * Design Intent
 * - Keep structural contract checks independent from owner-side payload sourcing so invalid payloads fail before evaluation.
 *
 * Strict Constraints
 * - Validation here covers rule syntax and allowed runtime types only; business ownership and payload loading remain elsewhere.
 *
 * Porting Guidelines
 * - Port as a standalone validator that can run before parser/evaluator creation.
 */
class EventRulePayloadValidator
{
    /**
     * Architecture Purpose
     * - Validate an event-rule payload against shared runtime shape rules.
     *
     * Design Intent
     * - Provide one explicit contract gate before parsing or evaluation starts.
     *
     * Strict Constraints
     * - This method validates shape only; it must not resolve owner-side runtime context.
     *
     * Porting Guidelines
     * - Port as the public validation entry for payloads.
     */
    public static function assertValid($payload, $options = [])
    {
        if (!is_array($payload) || empty($payload)) {
            throw new \RuntimeException('Event rule payload must be a non-empty array.');
        }

        $options = array_merge([
            'max_depth' => 5,
            'allowed_group_operators' => ['AND', 'OR'],
            'allowed_comparison_operators' => ['>', '>=', '<', '<=', '==', '!='],
            'allowed_types' => ['WATCHED_VIDEO', 'EXAM_SCORE', 'HAS_BADGE', 'MEMBER_SEEN_TARGET'],
        ], is_array($options) ? $options : []);

        self::validateNode($payload, '$', 1, $options);

        return true;
    }

    /**
     * Architecture Purpose
     * - Validate one AST-like payload node recursively.
     *
     * Design Intent
     * - Keep node-kind routing and depth enforcement in one validator helper.
     *
     * Strict Constraints
     * - Node validation must stay structural only.
     *
     * Porting Guidelines
     * - Port as the internal recursive validator entry.
     */
    private static function validateNode($node, $path, $depth, $options)
    {
        if ($depth > (int) $options['max_depth']) {
            throw new \RuntimeException('Event rule payload exceeds max_depth at ' . $path . '.');
        }

        if (!is_array($node) || empty($node)) {
            throw new \RuntimeException('Event rule node must be a non-empty array at ' . $path . '.');
        }

        if (array_key_exists('rules', $node)) {
            self::validateGroupNode($node, $path, $depth, $options);

            return;
        }

        self::validateLeafNode($node, $path, $options);
    }

    /**
     * Architecture Purpose
     * - Validate one group node in the shared rule payload.
     *
     * Design Intent
     * - Enforce legal operator and child-list structure before evaluation.
     *
     * Strict Constraints
     * - Group validation does not inspect owner-side business meaning.
     *
     * Porting Guidelines
     * - Port as the structural validator for grouped boolean nodes.
     */
    private static function validateGroupNode($node, $path, $depth, $options)
    {
        if (isset($node['type'])) {
            throw new \RuntimeException('Event rule group node cannot contain type at ' . $path . '.');
        }

        if (!isset($node['operator']) || '' === trim((string) $node['operator'])) {
            throw new \RuntimeException('Event rule group node missing operator at ' . $path . '.');
        }

        $operator = strtoupper(trim((string) $node['operator']));
        if (!in_array($operator, $options['allowed_group_operators'], true)) {
            throw new \RuntimeException('Event rule group operator not allowed at ' . $path . '.');
        }

        if (!is_array($node['rules']) || empty($node['rules'])) {
            throw new \RuntimeException('Event rule group rules must be a non-empty array at ' . $path . '.');
        }

        foreach ($node['rules'] as $index => $childNode) {
            self::validateNode($childNode, $path . '.rules[' . $index . ']', $depth + 1, $options);
        }
    }

    /**
     * Architecture Purpose
     * - Validate one leaf node against the shared type contract.
     *
     * Design Intent
     * - Enforce per-type required fields and operator/value rules before evaluator dispatch.
     *
     * Strict Constraints
     * - Leaf validation must not preload data or infer owner-side state.
     *
     * Porting Guidelines
     * - Port as a per-type structural validator.
     */
    private static function validateLeafNode($node, $path, $options)
    {
        if (!isset($node['type']) || '' === trim((string) $node['type'])) {
            throw new \RuntimeException('Event rule leaf node missing type at ' . $path . '.');
        }

        $type = strtoupper(trim((string) $node['type']));
        if (!in_array($type, $options['allowed_types'], true)) {
            throw new \RuntimeException('Event rule type not allowed at ' . $path . '.');
        }

        if (isset($node['rules'])) {
            throw new \RuntimeException('Event rule leaf node cannot contain rules at ' . $path . '.');
        }

        switch ($type) {
            case 'WATCHED_VIDEO':
            case 'HAS_BADGE':
            case 'MEMBER_SEEN_TARGET':
                if (!isset($node['target']) || '' === trim((string) $node['target'])) {
                    throw new \RuntimeException('Event rule target is required for ' . $type . ' at ' . $path . '.');
                }
                if ('MEMBER_SEEN_TARGET' === $type && (!isset($node['row_id']) || !is_numeric($node['row_id']))) {
                    throw new \RuntimeException('Event rule row_id is required for MEMBER_SEEN_TARGET at ' . $path . '.');
                }
                if (isset($node['operator']) || isset($node['value'])) {
                    throw new \RuntimeException('Event rule target-only leaf cannot carry operator/value at ' . $path . '.');
                }
                break;
            case 'EXAM_SCORE':
                if (!isset($node['operator']) || '' === trim((string) $node['operator'])) {
                    throw new \RuntimeException('Event rule comparison leaf missing operator at ' . $path . '.');
                }
                if (!in_array(trim((string) $node['operator']), $options['allowed_comparison_operators'], true)) {
                    throw new \RuntimeException('Event rule comparison operator not allowed at ' . $path . '.');
                }
                if (!array_key_exists('value', $node) || !is_numeric($node['value'])) {
                    throw new \RuntimeException('Event rule comparison leaf requires numeric value at ' . $path . '.');
                }
                break;
        }
    }
}

/**
 * Architecture Purpose
 * - Shared registry mapping normalized event-rule types to evaluator implementations.
 *
 * Design Intent
 * - Keep evaluator wiring explicit and replaceable without widening the engine into module-owned coordination.
 *
 * Strict Constraints
 * - Registry state is runtime-only and must not become a service locator for owner modules.
 *
 * Porting Guidelines
 * - Port as a narrow evaluator registry or dictionary.
 */
class EventRuleEvaluatorRegistry
{
    private $evaluators = [];

    /**
     * Architecture Purpose
     * - Register one evaluator implementation for a normalized rule type.
     *
     * Design Intent
     * - Allow controlled evaluator extension without changing engine core logic.
     *
     * Strict Constraints
     * - Registration stays within shared runtime types only.
     *
     * Porting Guidelines
     * - Port as a registry add/replace helper.
     */
    public function register($type, EventRuleEvaluatorInterface $evaluator)
    {
        $type = strtoupper(trim((string) $type));
        $this->evaluators[$type] = $evaluator;

        return $this;
    }

    /**
     * Architecture Purpose
     * - Resolve the evaluator for one normalized rule type.
     *
     * Design Intent
     * - Fail closed when a rule type has no registered runtime evaluator.
     *
     * Strict Constraints
     * - Resolution must not fall through to owner-side dynamic hooks.
     *
     * Porting Guidelines
     * - Port as a strict lookup that raises on unsupported types.
     */
    public function resolve($type)
    {
        $type = strtoupper(trim((string) $type));
        if (!isset($this->evaluators[$type])) {
            throw new \RuntimeException('Event rule evaluator not found for type: ' . $type);
        }

        return $this->evaluators[$type];
    }
}

/**
 * Architecture Purpose
 * - Shared normalized context object consumed by the event-rule evaluator runtime.
 *
 * Design Intent
 * - Give the engine a stable, validated read-only projection of player facts without coupling it to DB tables or module loaders.
 *
 * Strict Constraints
 * - Context owns runtime fact access only; sourcing those facts remains module-owned.
 *
 * Porting Guidelines
 * - Port as a validated context DTO or value object passed into the engine.
 */
class EventRulePlayerContext
{
    private $payload = [];

    /**
     * Architecture Purpose
     * - Validate and normalize the runtime fact payload consumed by evaluators.
     *
     * Design Intent
     * - Fail early when required context facts are missing so evaluators can stay simple.
     *
     * Strict Constraints
     * - Construction must not fetch missing facts from storage.
     *
     * Porting Guidelines
     * - Port as a validated DTO constructor with required-field checks.
     */
    public function __construct($payload)
    {
        if (!is_array($payload)) {
            throw new \RuntimeException('Context payload must be an array.');
        }

        if (!array_key_exists('member_seen_targets', $payload)) {
            $payload['member_seen_targets'] = [];
        }

        foreach (['member_id', 'watched_video_codes', 'exam_scores', 'heraldry_codes', 'member_seen_targets', 'account_balance', 'account_status'] as $field) {
            if (!array_key_exists($field, $payload)) {
                throw new \RuntimeException('Context missing required field: ' . $field);
            }
        }

        if (!is_array($payload['watched_video_codes'])) {
            throw new \RuntimeException('Context watched_video_codes must be an array.');
        }

        if (!is_array($payload['exam_scores'])) {
            throw new \RuntimeException('Context exam_scores must be an array.');
        }

        if (!is_array($payload['heraldry_codes'])) {
            throw new \RuntimeException('Context heraldry_codes must be an array.');
        }

        if (!is_array($payload['member_seen_targets'])) {
            throw new \RuntimeException('Context member_seen_targets must be an array.');
        }

        $this->payload = $payload;
    }

    /**
     * Architecture Purpose
     * - Expose the normalized member identifier carried by this runtime context.
     *
     * Design Intent
     * - Provide a narrow accessor for evaluators and traces.
     *
     * Strict Constraints
     * - Getter only; no owner-side member lookup here.
     *
     * Porting Guidelines
     * - Port as a simple context getter.
     */
    public function getMemberId()
    {
        return $this->payload['member_id'];
    }

    /**
     * Architecture Purpose
     * - Answer whether the context shows a watched-video fact for the requested target.
     *
     * Design Intent
     * - Keep fact lookup semantics inside the normalized runtime context.
     *
     * Strict Constraints
     * - This is a read-only fact check, not a preload hook.
     *
     * Porting Guidelines
     * - Port as a simple membership test over the normalized context payload.
     */
    public function hasWatchedVideo($target)
    {
        return in_array((string) $target, $this->payload['watched_video_codes'], true);
    }

    /**
     * Architecture Purpose
     * - Answer whether the context shows a badge fact for the requested target.
     *
     * Design Intent
     * - Keep heraldry/badge fact lookup centralized in the runtime context object.
     *
     * Strict Constraints
     * - Read-only fact access only.
     *
     * Porting Guidelines
     * - Port as a simple membership test.
     */
    public function hasBadge($target)
    {
        return in_array((string) $target, $this->payload['heraldry_codes'], true);
    }

    /**
     * Architecture Purpose
     * - Answer whether the context shows a seen-target fact for the requested row.
     *
     * Design Intent
     * - Keep row-level seen-target lookup centralized in the normalized context payload.
     *
     * Strict Constraints
     * - This helper checks preloaded facts only.
     *
     * Porting Guidelines
     * - Port as a typed lookup over normalized seen-target collections.
     */
    public function hasSeenTarget($target, $rowId)
    {
        $target = (string) $target;
        $rowId = (int) $rowId;

        if (!isset($this->payload['member_seen_targets'][$target]) || !is_array($this->payload['member_seen_targets'][$target])) {
            return false;
        }

        return in_array($rowId, array_map('intval', $this->payload['member_seen_targets'][$target]), true);
    }

    /**
     * Architecture Purpose
     * - Resolve the applicable exam score fact for evaluation.
     *
     * Design Intent
     * - Allow target-specific or default score lookup from the same normalized context.
     *
     * Strict Constraints
     * - This helper reads existing context facts only.
     *
     * Porting Guidelines
     * - Port as a deterministic lookup with the same target/default fallback order.
     */
    public function getExamScore($target = null)
    {
        if (null !== $target && array_key_exists($target, $this->payload['exam_scores'])) {
            return $this->payload['exam_scores'][$target];
        }

        if (array_key_exists('default', $this->payload['exam_scores'])) {
            return $this->payload['exam_scores']['default'];
        }

        throw new \RuntimeException('Context exam score not found for target: ' . (null === $target ? 'default' : $target));
    }

    /**
     * Architecture Purpose
     * - Expose the account-balance fact carried by this runtime context.
     *
     * Design Intent
     * - Keep evaluator fact reads centralized on the context object.
     *
     * Strict Constraints
     * - Getter only; no storage fetch.
     *
     * Porting Guidelines
     * - Port as a simple context getter.
     */
    public function getAccountBalance()
    {
        return $this->payload['account_balance'];
    }

    /**
     * Architecture Purpose
     * - Expose the account-status fact carried by this runtime context.
     *
     * Design Intent
     * - Keep evaluator fact reads centralized on the context object.
     *
     * Strict Constraints
     * - Getter only; no storage fetch.
     *
     * Porting Guidelines
     * - Port as a simple context getter.
     */
    public function getAccountStatus()
    {
        return $this->payload['account_status'];
    }
}

/**
 * Architecture Purpose
 * - Shared result envelope for event-rule evaluation outcomes.
 *
 * Design Intent
 * - Give callers a stable matched/not-matched/fail-closed contract with trace payloads.
 *
 * Strict Constraints
 * - Result shaping stays runtime-only; it must not own writeback or remediation logic.
 *
 * Porting Guidelines
 * - Port as an immutable or value-style evaluation result object.
 */
class EventRuleEvaluationResult
{
    private $matched = false;
    private $resultType = 'not_matched';
    private $failedNodePath = '$';
    private $trace = [];

    /**
     * Architecture Purpose
     * - Construct a runtime result envelope with matched state, type, path, and trace.
     *
     * Design Intent
     * - Keep result normalization in one place so callers receive a stable contract.
     *
     * Strict Constraints
     * - Construction shapes runtime output only.
     *
     * Porting Guidelines
     * - Port as a value-object constructor.
     */
    public function __construct($matched, $resultType, $failedNodePath, $trace = [])
    {
        $this->matched = (bool) $matched;
        $this->resultType = (string) $resultType;
        $this->failedNodePath = (string) $failedNodePath;
        $this->trace = is_array($trace) ? $trace : [];
    }

    /**
     * Architecture Purpose
     * - Build a matched result envelope.
     *
     * Design Intent
     * - Provide a terse factory for successful rule matches.
     *
     * Strict Constraints
     * - Factory shapes output only.
     *
     * Porting Guidelines
     * - Port as a named success factory.
     */
    public static function matched($path, $trace = [])
    {
        return new self(true, 'matched', (string) $path, $trace);
    }

    /**
     * Architecture Purpose
     * - Build a non-matched result envelope.
     *
     * Design Intent
     * - Distinguish business non-match from invalid payload or infrastructure failure.
     *
     * Strict Constraints
     * - Factory shapes output only.
     *
     * Porting Guidelines
     * - Port as a named non-match factory.
     */
    public static function notMatched($path, $trace = [])
    {
        return new self(false, 'not_matched', (string) $path, $trace);
    }

    /**
     * Architecture Purpose
     * - Build a fail-closed result envelope for invalid or unsafe runtime conditions.
     *
     * Design Intent
     * - Make infrastructure or context problems explicit without pretending they are business non-matches.
     *
     * Strict Constraints
     * - Fail-closed shaping must remain runtime-only and side-effect free.
     *
     * Porting Guidelines
     * - Port as a named failure factory with explicit result type.
     */
    public static function failClosed($resultType, $path, $trace = [])
    {
        return new self(false, (string) $resultType, (string) $path, $trace);
    }

    /**
     * Architecture Purpose
     * - Convert a leaf evaluator result into the shared result envelope.
     *
     * Design Intent
     * - Keep leaf-result adaptation consistent with group-level result handling.
     *
     * Strict Constraints
     * - Adaptation must not inject owner-side semantics.
     *
     * Porting Guidelines
     * - Port as a bridge from leaf result objects into the main evaluation result contract.
     */
    public static function fromLeafResult(EventRuleEvaluationLeafResult $leafResult, $path)
    {
        return new self(
            $leafResult->isMatched(),
            $leafResult->isMatched() ? 'matched' : 'not_matched',
            (string) $path,
            $leafResult->toArray()
        );
    }

    /** Architecture Purpose - Expose whether the evaluated rule matched. Porting Guidelines - Port as a simple value getter. */
    public function isMatched()
    {
        return $this->matched;
    }

    /** Architecture Purpose - Expose the normalized result type. Porting Guidelines - Port as a simple value getter. */
    public function getResultType()
    {
        return $this->resultType;
    }

    /** Architecture Purpose - Expose the failing node path for diagnostics. Porting Guidelines - Port as a simple value getter. */
    public function getFailedNodePath()
    {
        return $this->failedNodePath;
    }

    /** Architecture Purpose - Distinguish business outcomes from fail-closed runtime errors. Porting Guidelines - Port as a predicate over result type. */
    public function isTerminalBusinessResult()
    {
        return in_array($this->resultType, ['matched', 'not_matched'], true);
    }

    /** Architecture Purpose - Serialize the result envelope into an array trace contract. Porting Guidelines - Port as a DTO/array serializer. */
    public function toArray()
    {
        return [
            'matched' => $this->matched,
            'result_type' => $this->resultType,
            'failed_node_path' => $this->failedNodePath,
            'trace' => $this->trace,
        ];
    }
}

/**
 * Architecture Purpose
 * - Shared leaf-level evaluation outcome used before wrapping into the full result envelope.
 *
 * Design Intent
 * - Let concrete evaluators report matched state and detail without duplicating the outer result contract.
 *
 * Strict Constraints
 * - Leaf results are runtime detail objects only.
 *
 * Porting Guidelines
 * - Port as a small evaluator return type or record.
 */
class EventRuleEvaluationLeafResult
{
    private $matched = false;
    private $type = '';
    private $target = null;
    private $detail = [];

    /** Architecture Purpose - Construct the shared leaf-evaluation payload. Porting Guidelines - Port as a small value-object constructor. */
    public function __construct($matched, $type, $target = null, $detail = [])
    {
        $this->matched = (bool) $matched;
        $this->type = (string) $type;
        $this->target = null === $target ? null : (string) $target;
        $this->detail = is_array($detail) ? $detail : [];
    }

    /** Architecture Purpose - Expose whether the concrete leaf evaluator matched. Porting Guidelines - Port as a simple value getter. */
    public function isMatched()
    {
        return $this->matched;
    }

    /** Architecture Purpose - Serialize leaf evaluation detail for tracing. Porting Guidelines - Port as a DTO/array serializer. */
    public function toArray()
    {
        return [
            'matched' => $this->matched,
            'type' => $this->type,
            'target' => $this->target,
            'detail' => $this->detail,
        ];
    }
}

interface EventRuleEvaluatorInterface
{
    /**
     * Architecture Purpose
     * - Define the shared evaluator contract used by the event-rule runtime registry.
     *
     * Design Intent
     * - Keep evaluator implementations interchangeable behind one runtime interface.
     *
     * Strict Constraints
     * - Implementations evaluate preloaded facts only; they must not fetch owner-side data.
     *
     * Porting Guidelines
     * - Port as the common evaluator interface used by the registry.
     */
    public function evaluate($leafNode, EventRulePlayerContext $context);
}

/**
 * Architecture Purpose
 * - Shared evaluator for WATCHED_VIDEO leaf rules.
 *
 * Design Intent
 * - Keep watched-video fact matching in a narrow runtime evaluator.
 *
 * Strict Constraints
 * - Evaluator reads context facts only.
 *
 * Porting Guidelines
 * - Port as a leaf evaluator over preloaded watched-video facts.
 */
class EventRuleWatchedVideoEvaluator implements EventRuleEvaluatorInterface
{
    /** Architecture Purpose - Evaluate one WATCHED_VIDEO leaf against the shared player context. Porting Guidelines - Port as a pure context fact matcher. */
    public function evaluate($leafNode, EventRulePlayerContext $context)
    {
        $target = $leafNode['target'];
        $matched = $context->hasWatchedVideo($target);

        return new EventRuleEvaluationLeafResult($matched, 'WATCHED_VIDEO', $target, [
            'member_id' => $context->getMemberId(),
        ]);
    }
}

/**
 * Architecture Purpose
 * - Shared evaluator for EXAM_SCORE comparison rules.
 *
 * Design Intent
 * - Keep numeric comparison semantics for exam-score rules centralized in one runtime evaluator.
 *
 * Strict Constraints
 * - Evaluator compares preloaded facts only and must not fetch exam results itself.
 *
 * Porting Guidelines
 * - Port as a pure comparison evaluator with the same operator set.
 */
class EventRuleExamScoreEvaluator implements EventRuleEvaluatorInterface
{
    /** Architecture Purpose - Evaluate one EXAM_SCORE leaf against the shared player context. Porting Guidelines - Port as a pure comparison evaluator. */
    public function evaluate($leafNode, EventRulePlayerContext $context)
    {
        $target = isset($leafNode['target']) ? $leafNode['target'] : null;
        $actual = $context->getExamScore($target);
        $expected = $leafNode['value'];
        $operator = $leafNode['operator'];

        switch ($operator) {
            case '>':
                $matched = $actual > $expected;
                break;
            case '>=':
                $matched = $actual >= $expected;
                break;
            case '<':
                $matched = $actual < $expected;
                break;
            case '<=':
                $matched = $actual <= $expected;
                break;
            case '==':
                $matched = $actual == $expected;
                break;
            case '!=':
                $matched = $actual != $expected;
                break;
            default:
                throw new \RuntimeException('Unsupported exam score operator: ' . $operator);
        }

        return new EventRuleEvaluationLeafResult($matched, 'EXAM_SCORE', $target, [
            'operator' => $operator,
            'actual' => $actual,
            'expected' => $expected,
        ]);
    }
}

/**
 * Architecture Purpose
 * - Shared evaluator for HAS_BADGE leaf rules.
 *
 * Design Intent
 * - Keep badge-fact matching in a narrow runtime evaluator.
 *
 * Strict Constraints
 * - Evaluator reads context facts only.
 *
 * Porting Guidelines
 * - Port as a pure context fact matcher.
 */
class EventRuleHasBadgeEvaluator implements EventRuleEvaluatorInterface
{
    /** Architecture Purpose - Evaluate one HAS_BADGE leaf against the shared player context. Porting Guidelines - Port as a pure context fact matcher. */
    public function evaluate($leafNode, EventRulePlayerContext $context)
    {
        $target = $leafNode['target'];
        $matched = $context->hasBadge($target);

        return new EventRuleEvaluationLeafResult($matched, 'HAS_BADGE', $target, [
            'member_id' => $context->getMemberId(),
        ]);
    }
}

/**
 * Architecture Purpose
 * - Shared evaluator for MEMBER_SEEN_TARGET leaf rules.
 *
 * Design Intent
 * - Keep seen-target fact matching in a narrow runtime evaluator that uses preloaded row-level context.
 *
 * Strict Constraints
 * - Evaluator reads context facts only.
 *
 * Porting Guidelines
 * - Port as a pure context fact matcher for target/row pairs.
 */
class EventRuleMemberSeenTargetEvaluator implements EventRuleEvaluatorInterface
{
    /** Architecture Purpose - Evaluate one MEMBER_SEEN_TARGET leaf against the shared player context. Porting Guidelines - Port as a pure context fact matcher. */
    public function evaluate($leafNode, EventRulePlayerContext $context)
    {
        $target = $leafNode['target'];
        $rowId = (int) $leafNode['row_id'];
        $matched = $context->hasSeenTarget($target, $rowId);

        return new EventRuleEvaluationLeafResult($matched, 'MEMBER_SEEN_TARGET', $target, [
            'member_id' => $context->getMemberId(),
            'row_id' => $rowId,
        ]);
    }
}