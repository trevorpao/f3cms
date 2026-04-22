<?php

namespace F3CMS;

use F3CMS\EventRule\RuleParser;

class EventRuleEngine extends Helper
{
    private $payload = [];
    private $options = [];
    private $registry;
    private $ast = null;

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

    public static function createDefaultRegistry()
    {
        return self::createRegistryForTypes(['WATCHED_VIDEO', 'EXAM_SCORE', 'HAS_BADGE', 'MEMBER_SEEN_TARGET']);
    }

    public static function createRegistryForTypes($types)
    {
        $registry = new EventRuleEvaluatorRegistry();

        foreach ((array) $types as $type) {
            $type = strtoupper(trim((string) $type));
            $registry->register($type, self::createEvaluator($type));
        }

        return $registry;
    }

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

    public function validatePayload()
    {
        return EventRulePayloadValidator::assertValid($this->payload, $this->options);
    }

    public function getPayload()
    {
        return $this->payload;
    }

    public function getAst()
    {
        if (null === $this->ast) {
            $this->validatePayload();
            $this->ast = RuleParser::parse($this->payload);
        }

        return $this->ast;
    }

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

    private function evaluateNode($node, EventRulePlayerContext $context)
    {
        if ('group' === $node['node_kind']) {
            return $this->evaluateGroupNode($node, $context);
        }

        return $this->evaluateLeafNode($node, $context);
    }

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

class EventRulePayloadValidator
{
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

class EventRuleEvaluatorRegistry
{
    private $evaluators = [];

    public function register($type, EventRuleEvaluatorInterface $evaluator)
    {
        $type = strtoupper(trim((string) $type));
        $this->evaluators[$type] = $evaluator;

        return $this;
    }

    public function resolve($type)
    {
        $type = strtoupper(trim((string) $type));
        if (!isset($this->evaluators[$type])) {
            throw new \RuntimeException('Event rule evaluator not found for type: ' . $type);
        }

        return $this->evaluators[$type];
    }
}

class EventRulePlayerContext
{
    private $payload = [];

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

    public function getMemberId()
    {
        return $this->payload['member_id'];
    }

    public function hasWatchedVideo($target)
    {
        return in_array((string) $target, $this->payload['watched_video_codes'], true);
    }

    public function hasBadge($target)
    {
        return in_array((string) $target, $this->payload['heraldry_codes'], true);
    }

    public function hasSeenTarget($target, $rowId)
    {
        $target = (string) $target;
        $rowId = (int) $rowId;

        if (!isset($this->payload['member_seen_targets'][$target]) || !is_array($this->payload['member_seen_targets'][$target])) {
            return false;
        }

        return in_array($rowId, array_map('intval', $this->payload['member_seen_targets'][$target]), true);
    }

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

    public function getAccountBalance()
    {
        return $this->payload['account_balance'];
    }

    public function getAccountStatus()
    {
        return $this->payload['account_status'];
    }
}

class EventRuleEvaluationResult
{
    private $matched = false;
    private $resultType = 'not_matched';
    private $failedNodePath = '$';
    private $trace = [];

    public function __construct($matched, $resultType, $failedNodePath, $trace = [])
    {
        $this->matched = (bool) $matched;
        $this->resultType = (string) $resultType;
        $this->failedNodePath = (string) $failedNodePath;
        $this->trace = is_array($trace) ? $trace : [];
    }

    public static function matched($path, $trace = [])
    {
        return new self(true, 'matched', (string) $path, $trace);
    }

    public static function notMatched($path, $trace = [])
    {
        return new self(false, 'not_matched', (string) $path, $trace);
    }

    public static function failClosed($resultType, $path, $trace = [])
    {
        return new self(false, (string) $resultType, (string) $path, $trace);
    }

    public static function fromLeafResult(EventRuleEvaluationLeafResult $leafResult, $path)
    {
        return new self(
            $leafResult->isMatched(),
            $leafResult->isMatched() ? 'matched' : 'not_matched',
            (string) $path,
            $leafResult->toArray()
        );
    }

    public function isMatched()
    {
        return $this->matched;
    }

    public function getResultType()
    {
        return $this->resultType;
    }

    public function getFailedNodePath()
    {
        return $this->failedNodePath;
    }

    public function isTerminalBusinessResult()
    {
        return in_array($this->resultType, ['matched', 'not_matched'], true);
    }

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

class EventRuleEvaluationLeafResult
{
    private $matched = false;
    private $type = '';
    private $target = null;
    private $detail = [];

    public function __construct($matched, $type, $target = null, $detail = [])
    {
        $this->matched = (bool) $matched;
        $this->type = (string) $type;
        $this->target = null === $target ? null : (string) $target;
        $this->detail = is_array($detail) ? $detail : [];
    }

    public function isMatched()
    {
        return $this->matched;
    }

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
    public function evaluate($leafNode, EventRulePlayerContext $context);
}

class EventRuleWatchedVideoEvaluator implements EventRuleEvaluatorInterface
{
    public function evaluate($leafNode, EventRulePlayerContext $context)
    {
        $target = $leafNode['target'];
        $matched = $context->hasWatchedVideo($target);

        return new EventRuleEvaluationLeafResult($matched, 'WATCHED_VIDEO', $target, [
            'member_id' => $context->getMemberId(),
        ]);
    }
}

class EventRuleExamScoreEvaluator implements EventRuleEvaluatorInterface
{
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

class EventRuleHasBadgeEvaluator implements EventRuleEvaluatorInterface
{
    public function evaluate($leafNode, EventRulePlayerContext $context)
    {
        $target = $leafNode['target'];
        $matched = $context->hasBadge($target);

        return new EventRuleEvaluationLeafResult($matched, 'HAS_BADGE', $target, [
            'member_id' => $context->getMemberId(),
        ]);
    }
}

class EventRuleMemberSeenTargetEvaluator implements EventRuleEvaluatorInterface
{
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