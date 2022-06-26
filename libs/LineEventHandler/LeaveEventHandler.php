<?php

namespace F3CMS\LineEventHandler;

use F3CMS\LineEventHandler;
use LINE\LINEBot;
use LINE\LINEBot\Event\LeaveEvent;

class LeaveEventHandler implements LineEventHandler
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
     * @var LeaveEvent
     */
    private $leaveEvent;

    /**
     * LeaveEventHandler constructor.
     *
     * @param LINEBot         $bot
     * @param \Monolog\Logger $logger
     */
    public function __construct($bot, $logger, LeaveEvent $leaveEvent, $method)
    {
        $this->bot        = $bot;
        $this->logger     = $logger;
        $this->leaveEvent = $leaveEvent;
        $this->method     = $method;
    }

    /**
     * @return null
     */
    public function handle()
    {
        if ($this->leaveEvent->isGroupEvent()) {
            $boardID = $this->leaveEvent->getGroupId();
        } elseif ($this->leaveEvent->isRoomEvent()) {
            $boardID = $this->leaveEvent->getRoomId();
        } else {
            $boardID = 0;
            $this->logger->write('Unknown event type');

            return;
        }

        // if ($boardID != 0) {
        call_user_func_array('\\F3CMS\\' . $this->method, [$boardID]);
        // }

        // $this->logger->write(sprintf('Left %s %s', $this->leaveEvent->getType(), $id));

        return ['none', ['boardID' => $boardID]];
    }
}
