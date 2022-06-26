<?php

namespace F3CMS\LineEventHandler;

use F3CMS\LineEventHandler;
use LINE\LINEBot;
use LINE\LINEBot\Event\FollowEvent;

class FollowEventHandler implements LineEventHandler
{
    /**
     * @var LINEBot
     */
    private $bot;
    /**
     * @var \Monolog\Logger
     */
    private $logger;
    /**
     * @var FollowEvent
     */
    private $followEvent;

    /**
     * FollowEventHandler constructor.
     *
     * @param LINEBot         $bot
     * @param \Monolog\Logger $logger
     */
    public function __construct($bot, $logger, FollowEvent $followEvent, $method)
    {
        $this->bot         = $bot;
        $this->logger      = $logger;
        $this->followEvent = $followEvent;
        $this->method      = $method;
    }

    /**
     * @throws \ReflectionException
     */
    public function handle()
    {
        // $this->bot->replyText($this->followEvent->getReplyToken(), 'Got followed event');
        $data = $this->getProfile($this->followEvent->getUserId());

        if (!empty($data)) {
            call_user_func_array('\\F3CMS\\' . $this->method, [$data]);
        }

        return ['text', ['txt' => 'Got followed event']];
    }

    /**
     * @param $userId
     *
     * @throws \ReflectionException
     */
    private function getProfile($userId)
    {
        if (empty($userId)) {
            return null;
        }

        $response = $this->bot->getProfile($userId);
        if (!$response->isSucceeded()) {
            return null;
        }

        $profile = $response->getJSONDecodedBody();

        return [
            'id'           => $userId,
            'display_name' => $profile['displayName'],
            'slogan'       => $profile['statusMessage'],
        ];
    }
}
