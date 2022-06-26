<?php

namespace F3CMS;

/**
 * React any request
 */
class rIssue extends Reaction
{
    public static function booking($params)
    {
        $data = [
            // 'id'   => $this->postbackEvent->getMessageId(), // PostBack is not a message
            'user_id'    => f3()->get('event.userID'),
            'board_id'   => f3()->get('event.boardID'),
            'target_ts'  => $params['datetime'],
        ];
        fDatetime::add($data);

        return $params['datetime'] . ' 要做什麼？';
    }

    /**
     * @param $f3
     * @param $args
     */
    public function do_add_new($f3, $args)
    {
        $req = parent::_getReq();

        if (empty($req['title'])) {
            return parent::_return(8002, ['msg' => '姓名未填寫!!']);
        }

        if (empty($req['user_id'])) {
            return parent::_return(8002, ['msg' => 'userID 未填寫!!']);
        }

        fIssue::insert($req);

        return parent::_return(1, ['msg' => '已新增~~']);
    }
}
