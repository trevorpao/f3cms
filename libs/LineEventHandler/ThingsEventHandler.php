<?php

namespace F3CMS\LineEventHandler;

use F3CMS\LineEventHandler;
use LINE\LINEBot;
use LINE\LINEBot\Event\ThingsEvent;

class ThingsEventHandler implements LineEventHandler
{
    /** @var LINEBot */
    private $bot;
    /** @var \Monolog\Logger */
    private $logger;
    /** @var ThingsEvent */
    private $thingsEvent;

    /**
     * ThingsEventHandler constructor.
     *
     * @param LINEBot         $bot
     * @param \Monolog\Logger $logger
     */
    public function __construct($bot, $logger, ThingsEvent $thingsEvent)
    {
        $this->bot         = $bot;
        $this->logger      = $logger;
        $this->thingsEvent = $thingsEvent;
    }

    /**
     * @throws \ReflectionException
     */
    public function handle()
    {
        $text = 'Device ' . $this->thingsEvent->getDeviceId();
        switch ($this->thingsEvent->getThingsEventType()) {
            case ThingsEvent::TYPE_DEVICE_LINKED:
                $text .= ' was linked!';
                break;
            case ThingsEvent::TYPE_DEVICE_UNLINKED:
                $text .= ' was unlinked!';
                break;
            case ThingsEvent::TYPE_SCENARIO_RESULT:
                $result = $this->thingsEvent->getScenarioResult();
                $text .= ' executed scenario:' . $result->getScenarioId();
                break;
        }
        // $this->bot->replyText($this->thingsEvent->getReplyToken(), $text);

        return ['text', ['txt' => $text]];
    }
}
