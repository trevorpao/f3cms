<?php

namespace F3CMS\LineEventHandler;

use F3CMS\LineEventHandler;
use LINE\LINEBot;
use LINE\LINEBot\Event\JoinEvent;

class JoinEventHandler implements LineEventHandler
{
    /** @var LINEBot */
    private $bot;
    /** @var \Monolog\Logger */
    private $logger;
    /** @var JoinEvent */
    private $joinEvent;

    /**
     * JoinEventHandler constructor.
     *
     * @param LINEBot         $bot
     * @param \Monolog\Logger $logger
     */
    public function __construct($bot, $logger, JoinEvent $joinEvent, $method)
    {
        $this->bot       = $bot;
        $this->logger    = $logger;
        $this->joinEvent = $joinEvent;
        $this->method    = $method;
    }

    /**
     * @throws LINEBot\Exception\InvalidEventSourceException
     * @throws \ReflectionException
     */
    public function handle()
    {
        $data = [
            'type' => $this->joinEvent->getType(),
        ];

        if ($this->joinEvent->isGroupEvent()) {
            $data['id']   = $this->joinEvent->getGroupId();
            $data['meta'] = $this->getGroupInfo($data['id']);
            $data['txt']  = sprintf('Joined Group %s', $data['id']);
        } elseif ($this->joinEvent->isRoomEvent()) {
            $data['id']   = $this->joinEvent->getRoomId();
            $data['meta'] = $this->getRoomInfo($data['id']);
            $data['txt']  = sprintf('Joined Room %s', $data['id']);
        } else {
            $data['id'] = 0;
            $this->logger->write('Unknown event type');

            return;
        }

        if (!empty($data['meta'])) {
            call_user_func_array('\\F3CMS\\' . $this->method, [$data['meta']]);
        }

        // $this->bot->replyText(
        //     $this->joinEvent->getReplyToken(),
        //     sprintf('Joined %s %s', $this->joinEvent->getType(), $id)
        // );

        return ['text', $data];
    }

    /**
     * @param $groupId
     *
     * @throws \ReflectionException
     */
    private function getGroupInfo($groupId)
    {
        if (empty($groupId)) {
            return null;
        }

        $response = $this->bot->getGroupSummary($groupId);
        if (!$response->isSucceeded()) {
            return null;
        }

        $summary = $response->getJSONDecodedBody();

        $response = $this->bot->getGroupMembersCount($groupId);
        if (!$response->isSucceeded()) {
            return null;
        }

        $cnt = $response->getJSONDecodedBody();

        return ['id' => $groupId, 'type' => 'Group', 'display_name' => $summary['groupName'], 'cover' => $summary['pictureUrl'], 'counter' => $cnt['count']];
    }

    /**
     * @param $roomId
     *
     * @throws \ReflectionException
     */
    private function getRoomInfo($roomId)
    {
        if (empty($roomId)) {
            return null;
        }

        $response = $this->bot->getRoomMembersCount($groupId);
        if (!$response->isSucceeded()) {
            return null;
        }

        $cnt = $response->getJSONDecodedBody();

        return ['id' => $roomId, 'type' => 'Room', 'counter' => $cnt['count']];
    }
}
