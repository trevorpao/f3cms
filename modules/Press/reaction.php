<?php

namespace F3CMS;

/**
 * Architecture Purpose
 * - Owner-side Reaction boundary for Press request orchestration, covering publish
 *   workflow transitions, trace-aware status mutation coordination, seen completion,
 *   and frontend press list/detail response shaping.
 *
 * Design Intent
 * - Keep request orchestration for Press close to the module while preserving the FORKS
 *   split: workflow/runtime evaluation stays in WorkflowEngine, persistence ownership
 *   stays in fPress, and this Reaction coordinates auth, request parsing, and response
 *   shaping across those collaborators.
 *
 * Strict Constraints
 * - This Reaction must not absorb Feed persistence semantics, schema decomposition, or
 *   generic workflow runtime ownership.
 * - Cross-module side effects such as Duty completion or page generation may be
 *   coordinated here, but module truth and persistence writes must remain with the
 *   owning collaborators.
 *
 * Porting Guidelines
 * - Port as the Press module's request/application orchestration layer.
 * - Preserve the split between request gates here, workflow evaluation in runtime
 *   helpers, and entity persistence in the Feed boundary.
 */
class rPress extends Reaction
{
    const WORKFLOW_OPERATOR_ROLE = 'ROLE_PUBLISHER';

    /**
     * Architecture Role
     * - Complete the Press "seen" side effect for the current member through the owner-
     *   side duty/task collaborator.
     *
     * Strict Constraints
     * - This method coordinates member gate, request parsing, and response shaping, but
     *   the actual task-completion business logic remains outside Reaction.
     *
     * Porting Note
     * - Port as a member-authenticated endpoint that delegates seen completion to the
     *   owning collaborator.
     */
    public function do_seen($f3, $args)
    {
        $memberId = (int) fMember::_current('id');
        if ($memberId <= 0) {
            return parent::_return(8106, ['msg' => 'Member login required.']);
        }

        $req = parent::_getReq();

        try {
            $result = self::completeSeenForMember($memberId, $req);

            return parent::_return(1, $result);
        } catch (\Throwable $e) {
            return parent::_return(8004, ['msg' => $e->getMessage()]);
        }
    }

    /**
     * Architecture Role
     * - Orchestrate Press publish/offline requests by validating the requested workflow
     *   transition, delegating trace-aware persistence to the Feed, and triggering the
     *   necessary post-transition side effects.
     *
     * Strict Constraints
     * - Workflow definition validation and transition projection may be coordinated here,
     *   but persistence ownership stays with fPress and workflow runtime ownership stays
     *   with WorkflowEngine.
     *
     * Porting Note
     * - Port as the Press publish-status command handler over Feed and WorkflowEngine.
     */
    public function do_published($f3, $args)
    {
        kStaff::_chkLogin();

        $req = parent::_getReq();

        $cu = fPress::one($req['id']);

        if (empty($cu)) {
            return parent::_return(8106);
        }

        try {
            $workflowTransition = self::applyWorkflowPublishedTransition($cu, $req, fStaff::_current('id'));
            $req = $workflowTransition['req'];

            fPress::publishWithWorkflowTrace($req, $workflowTransition['trace']);
        } catch (\Throwable $e) {
            return parent::_return(8004, ['msg' => $e->getMessage()]);
        }

        switch ($req['status']) {
            case fPress::ST_PUBLISHED:
                // TODO: add config to control this
                // $req['online_date'] = date('Y-m-d H:i:00', time() - 60); // DONT use local datetime
                //
                // TODO: 新增欄位檢查

                if (0 === f3()->get('cache.press')) {
                    oPress::buildPage(['slug' => $cu['id']]);
                }
                break;
            case fPress::ST_OFFLINED:
                // TODO: remove file when offlined
                break;
            default:
                break;
        }

        return self::_return(1, ['id' => $req['id']]);
    }

    /**
     * Architecture Role
     * - Resolve the current published Press target for a member and delegate the seen
     *   completion to the Duty-side collaborator.
     *
     * Strict Constraints
     * - Press target lookup is coordinated here, but the owning completion logic must
     *   remain in the collaborator that owns seen-task semantics.
     *
     * Porting Note
     * - Port as a request-independent helper that maps member plus press identity into a
     *   seen-completion call.
     */
    public static function completeSeenForMember($memberId, $req = [])
    {
        $memberId = (int) $memberId;
        $req = is_array($req) ? $req : [];

        if ($memberId <= 0) {
            throw new \RuntimeException('Member login required.');
        }

        $pressId = (int) ($req['press_id'] ?? $req['id'] ?? 0);
        if ($pressId > 0) {
            $press = fPress::onePublished($pressId, 'id', 0);
        } else {
            $slug = trim((string) ($req['slug'] ?? ''));
            $press = ('' !== $slug) ? fPress::onePublished(parent::_slugify($slug), 'slug', 0) : null;
        }

        if (empty($press)) {
            throw new \RuntimeException('Published press not found for seen completion.');
        }

        $source = isset($req['source']) ? trim((string) $req['source']) : 'rPress';
        if ('' === $source) {
            $source = 'rPress';
        }

        return kDuty::completeTasksForSeenTarget($memberId, 'Press', (int) $press['id'], $source, $memberId);
    }

    /**
     * Architecture Role
     * - Translate a requested Press status change into a validated workflow transition
     *   plus trace payload ready for Feed-owned persistence.
     *
     * Strict Constraints
     * - This helper coordinates WorkflowEngine usage and builds trace data, but it must
     *   not itself mutate persistence state or become the workflow truth owner.
     *
     * Porting Note
     * - Port as the Press workflow-transition preparation step before persistence.
     */
    public static function applyWorkflowPublishedTransition($currentPress, $req, $operatorId)
    {
        $targetStatus = isset($req['status']) ? (string) $req['status'] : '';
        $currentStatus = isset($currentPress['status']) ? (string) $currentPress['status'] : '';

        if ('' === $targetStatus || $currentStatus === $targetStatus) {
            return [
                'req' => $req,
                'trace' => null,
            ];
        }

        $actionCode = self::resolveWorkflowActionCode($targetStatus);
        if (empty($actionCode)) {
            return [
                'req' => $req,
                'trace' => null,
            ];
        }

        $filePath = __DIR__ . '/flow.json';
        if (!is_file($filePath)) {
            throw new \RuntimeException('Press workflow flow.json not found.');
        }

        $fileContent = file_get_contents($filePath);
        if (false === $fileContent || '' === trim($fileContent)) {
            throw new \RuntimeException('Press workflow flow.json is empty.');
        }

        $workflowDefinition = json_decode($fileContent, true);
        if (JSON_ERROR_NONE !== json_last_error() || !is_array($workflowDefinition)) {
            throw new \RuntimeException('Press workflow flow.json is invalid JSON.');
        }

        $workflowEngine = new WorkflowEngine($workflowDefinition);
        $workflowEngine->validateDefinition();

        $runtimeContext = [
            'current_state_code' => $currentStatus,
            'operator_role_constant' => self::WORKFLOW_OPERATOR_ROLE,
        ];

        if (!$workflowEngine->canTransit($actionCode, $runtimeContext)) {
            throw new \RuntimeException('Workflow transition not allowed for requested Press status.');
        }

        $projection = $workflowEngine->project([
            'current_state_code' => $currentStatus,
        ]);
        $transition = null;
        if (!empty($projection['available_transitions']) && is_array($projection['available_transitions'])) {
            foreach ($projection['available_transitions'] as $projectedTransition) {
                if (!isset($projectedTransition['action_code']) || $projectedTransition['action_code'] !== $actionCode) {
                    continue;
                }

                $transition = $projectedTransition;
                break;
            }
        }

        if (empty($transition)) {
            throw new \RuntimeException('Workflow transition projection not found for requested Press status.');
        }

        $req['status'] = $transition['next_step_judgment']['next_state_code'];

        return [
            'req' => $req,
            'trace' => [
                'parent_id' => (int) $currentPress['id'],
                'action_code' => $transition['action_code'],
                'old_state_code' => $currentStatus,
                'new_state_code' => $req['status'],
                'insert_user' => (int) $operatorId,
            ],
        ];
    }

    /**
     * Architecture Role
     * - Map the requested Press target status to the workflow action code expected by
     *   the Press workflow definition.
     *
     * Strict Constraints
     * - Status-to-action mapping belongs to Press workflow orchestration semantics and
     *   should not drift into generic status helpers.
     *
     * Porting Note
     * - Port as a small Press-specific workflow action resolver.
     */
    private static function resolveWorkflowActionCode($targetStatus)
    {
        switch ((string) $targetStatus) {
            case fPress::ST_PUBLISHED:
                return 'PUBLISH';
            case fPress::ST_OFFLINED:
                return 'OFFLINE';
            default:
                return null;
        }
    }

    /**
     * Architecture Role
     * - Serve the frontend "more" listing action by shaping request input, optional tag
     *   resolution, and Press list projections.
     *
     * Strict Constraints
     * - Request-side list shaping belongs here, but list retrieval and relation truth
     *   remain owned by the Feed boundary.
     *
     * Porting Note
     * - Port as a frontend list/load-more endpoint over Press feed queries.
     */
    public function do_more($f3, $args)
    {
        $query = [
            'm.status' => [fPress::ST_PUBLISHED, fPress::ST_CHANGED],
        ];

        $req = parent::_getReq();

        $req['page']  = (isset($req['page'])) ? intval($req['page']) - 1 : 0;
        $req['limit'] = (!empty($req['limit'])) ? max(min($req['limit'] * 1, fPress::PAGELIMIT), 12) : fPress::PAGELIMIT;

        if (!empty($req['pid'])) {
            if (is_numeric($req['pid'])) {
                $tag = fTag::one($req['pid'], 'id', ['status' => fTag::ST_ON], false);
            } else {
                $tag = fTag::one(parent::_slugify($req['pid']), 'slug', ['status' => fTag::ST_ON], false);
            }
        } else {
            $tag = null;
        }

        if (!empty($req['query'])) {
            if (is_string($req['query'])) {
                $query['l.title[~]'] = urldecode(str_replace('q=', '', $req['query']));
            } else {
                if (!empty($req['query']['q'])) {
                    $query['l.title[~]'] = $req['query']['q'];
                }
            }
        }

        if (!empty($tag)) {
            $rtn = fPress::lotsByTag($tag['id'], $req['page'], $req['limit']);
        } else {
            $rtn = fPress::limitRows($query, $req['page'], $req['limit']);
        }

        $rtn['subset'] = \__::map($rtn['subset'], function ($row) {
            return self::handleIteratee($row);
        });

        return self::_return(1, $rtn);
    }

    /**
     * Architecture Role
     * - Enrich one Press row into the detail payload used by backend/frontend callers.
     *
     * Strict Constraints
     * - Detail payload shaping belongs here, but related/meta/term data ownership stays
     *   with the Feed methods that retrieve those structures.
     *
     * Porting Note
     * - Port as a Press detail response assembler over Feed-owned read helpers.
     */
    public static function handleRow($row = [])
    {
        $row = self::handleIteratee($row);
        $row['relateds'] = fPress::lotsRelated($row['id']);
        $row['meta'] = fPress::lotsMeta($row['id']);
        $row['terms'] = fPress::lotsTerm($row['id']);
        $row['history'] = [];
        $row['status_publish'] = $row['status'];

        return $row;
    }

    /**
     * Architecture Role
     * - Enrich one Press row with the lightweight relation payload needed by list views.
     *
     * Strict Constraints
     * - Iteratee shaping remains a Reaction response concern; do not turn it into a
     *   persistence-side projection that would blur Feed ownership.
     *
     * Porting Note
     * - Port as a list-row response shaper for Press list endpoints.
     */
    public static function handleIteratee($row = [])
    {
        $row['tags'] = fPress::lotsTag($row['id']);
        $row['authors'] = fPress::lotsAuthor($row['id']);

        return $row;
    }
}
