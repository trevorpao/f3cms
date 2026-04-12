<?php

namespace F3CMS;

class rPress extends Reaction
{
    const WORKFLOW_OPERATOR_ROLE = 'ROLE_PUBLISHER';

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

        $transactionStarted = false;

        try {
            $workflowTransition = self::applyWorkflowPublishedTransition($cu, $req, fStaff::_current('id'));
            $req = $workflowTransition['req'];

            mh()->begin();
            $transactionStarted = true;

            self::writeWorkflowPublishedTrace($workflowTransition['trace']);

            $published = fPress::published($req);
            if (empty($published)) {
                throw new \RuntimeException('Press status update failed.');
            }

            mh()->commit();
        } catch (\Throwable $e) {
            if ($transactionStarted) {
                mh()->rollback();
            }

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

        $workflowDefinition = self::loadWorkflowDefinition();
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
        $transition = self::resolveProjectedWorkflowTransition($projection, $actionCode);
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

    private static function resolveProjectedWorkflowTransition($projection, $actionCode)
    {
        if (empty($projection['available_transitions']) || !is_array($projection['available_transitions'])) {
            return null;
        }

        foreach ($projection['available_transitions'] as $transition) {
            if (!isset($transition['action_code']) || $transition['action_code'] !== $actionCode) {
                continue;
            }

            return $transition;
        }

        return null;
    }

    private static function writeWorkflowPublishedTrace($trace)
    {
        if (empty($trace)) {
            return;
        }

        mh()->insert(fPress::fmTbl('log'), [
            'parent_id' => $trace['parent_id'],
            'action_code' => $trace['action_code'],
            'old_state_code' => $trace['old_state_code'],
            'new_state_code' => $trace['new_state_code'],
            'insert_user' => $trace['insert_user'],
        ]);
    }

    private static function loadWorkflowDefinition()
    {
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

        return $workflowDefinition;
    }

    public static function resolveWorkflowActionCode($targetStatus)
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

        $req['page'] = ($req['page']) ? ($req['page'] - 1) : 1;

        $req['limit'] = max(min($req['limit'] * 1, 24), 3);

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
                if (!empty($req['query']['sub_tag'])) {
                    if (!is_array($req['query']['sub_tag'])) {
                        $req['query']['sub_tag'] = explode(',', $req['query']['sub_tag']);
                    }
                    $tags = array_merge($tags, fTag::bySlug($req['query']['sub_tag']));
                }

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
        $row['tags']      = fPress::lotsTag($row['id']);
        $row['authors']   = fPress::lotsAuthor($row['id']);
        $row['relateds']  = fPress::lotsRelated($row['id']);
        $row['meta']      = fPress::lotsMeta($row['id']);
        $row['terms']     = fPress::lotsTerm($row['id']);

        // read history file
        // $fc = new FCHelper('press');
        $row['history']        = []; // $fc->getLog('press_' . $row['id']);
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
        $row['tags']    = fPress::lotsTag($row['id']);
        $row['authors'] = fPress::lotsAuthor($row['id']);

        // $row['metas']   = fPress::lotsMeta($row['id']);

        return $row;
    }
}
