<?php

namespace F3CMS;

class rPress extends Reaction
{
    const WORKFLOW_OPERATOR_ROLE = 'ROLE_PUBLISHER';

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
     * @param $f3
     * @param $args
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
     * @param $f3
     * @param $args
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
     * @param array $row
     *
     * @return mixed
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
     * @param array $row
     *
     * @return mixed
     */
    public static function handleIteratee($row = [])
    {
        $row['tags'] = fPress::lotsTag($row['id']);
        $row['authors'] = fPress::lotsAuthor($row['id']);

        return $row;
    }
}
