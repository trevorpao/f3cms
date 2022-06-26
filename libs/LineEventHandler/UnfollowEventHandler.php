<?php

namespace F3CMS\LineEventHandler;

use F3CMS\LineEventHandler;
use LINE\LINEBot;
use LINE\LINEBot\Event\UnfollowEvent;

class UnfollowEventHandler implements LineEventHandler
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
     * @var UnfollowEvent
     */
    private $unfollowEvent;

    /**
     * UnfollowEventHandler constructor.
     *
     * @param LINEBot         $bot
     * @param \Monolog\Logger $logger
     */
    public function __construct($bot, $logger, UnfollowEvent $unfollowEvent, $method)
    {
        $this->bot           = $bot;
        $this->logger        = $logger;
        $this->unfollowEvent = $unfollowEvent;
        $this->method        = $method;
    }

    public function handle()
    {
        $this->logger->write(sprintf(
            'Unfollowed this bot %s %s',
            $this->unfollowEvent->getType(),
            $this->unfollowEvent->getUserId()
        ));

        $userID = $this->unfollowEvent->getUserId();

        call_user_func_array('\\F3CMS\\' . $this->method, [$userID]);

        return ['none', ['type' => $this->unfollowEvent->getType(), 'userID' => $this->unfollowEvent->getUserId()]];
    }
}
