<?php

namespace F3CMS\LineEventHandler\MessageHandler;

use F3CMS\LineEventHandler;
use LINE\LINEBot;
use LINE\LINEBot\Event\MessageEvent\StickerMessage;
use LINE\LINEBot\MessageBuilder\StickerMessageBuilder;

class StickerMessageHandler implements LineEventHandler
{
    /** @var LINEBot */
    private $bot;
    /** @var \Monolog\Logger */
    private $logger;
    /** @var StickerMessage */
    private $stickerMessage;

    /**
     * StickerMessageHandler constructor.
     *
     * @param LINEBot         $bot
     * @param \Monolog\Logger $logger
     */
    public function __construct($bot, $logger, StickerMessage $stickerMessage)
    {
        $this->bot            = $bot;
        $this->logger         = $logger;
        $this->stickerMessage = $stickerMessage;
    }

    public function handle()
    {
        $packageId = $this->stickerMessage->getPackageId();
        $stickerId = $this->stickerMessage->getStickerId();
        // $this->bot->replyMessage($replyToken, new StickerMessageBuilder($packageId, $stickerId));

        return ['message', ['msg' => new StickerMessageBuilder($packageId, $stickerId)]];
    }
}
