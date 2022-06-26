<?php

namespace F3CMS\LineEventHandler;

use F3CMS\LineEventHandler;
use LINE\LINEBot;
use LINE\LINEBot\Event\AccountLinkEvent;

class AccountLinkEventHandler implements LineEventHandler
{
    /** @var LINEBot */
    private $bot;
    /** @var \Monolog\Logger */
    private $logger;
    /* @var AccountLinkEvent $accountLinkEvent */
    private $accountLinkEvent;

    /**
     * BeaconEventHandler constructor.
     *
     * @param LINEBot         $bot
     * @param \Monolog\Logger $logger
     */
    public function __construct($bot, $logger, AccountLinkEvent $accountLinkEvent)
    {
        $this->bot              = $bot;
        $this->logger           = $logger;
        $this->accountLinkEvent = $accountLinkEvent;
    }

    /**
     * @throws \ReflectionException
     */
    public function handle()
    {
        // $this->bot->replyText(
        //     $this->accountLinkEvent->getReplyToken(),
        //     'Got account link event ' . $this->accountLinkEvent->getNonce()
        // );

        return ['text', ['txt' => 'Got account link event ' . $this->accountLinkEvent->getNonce()]];
    }
}
