<?php

namespace F3CMS\LineEventHandler;

use F3CMS\LineEventHandler;
use LINE\LINEBot;
use LINE\LINEBot\Event\BeaconDetectionEvent;

class BeaconEventHandler implements LineEventHandler
{
    /** @var LINEBot */
    private $bot;
    /** @var \Monolog\Logger */
    private $logger;
    /* @var BeaconDetectionEvent $beaconEvent */
    private $beaconEvent;

    /**
     * BeaconEventHandler constructor.
     *
     * @param LINEBot         $bot
     * @param \Monolog\Logger $logger
     */
    public function __construct($bot, $logger, BeaconDetectionEvent $beaconEvent)
    {
        $this->bot         = $bot;
        $this->logger      = $logger;
        $this->beaconEvent = $beaconEvent;
    }

    /**
     * @throws \ReflectionException
     */
    public function handle()
    {
        // $this->bot->replyText(
        //     $this->beaconEvent->getReplyToken(),
        //     'Got beacon message ' . $this->beaconEvent->getHwid()
        // );

        return ['text', ['txt' => 'Got beacon message ' . $this->beaconEvent->getHwid()]];
    }
}
