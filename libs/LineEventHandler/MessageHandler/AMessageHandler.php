<?php

namespace F3CMS\LineEventHandler\MessageHandler;

use F3CMS\FSHelper;
use F3CMS\LineEventHandler;
use F3CMS\Reaction;
use F3CMS\Upload;
use LINE\LINEBot;
use LINE\LINEBot\Event\MessageEvent\AudioMessage;
use LINE\LINEBot\MessageBuilder\AudioMessageBuilder;

class AMessageHandler implements LineEventHandler
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
     * @var
     */
    private $req;
    /**
     * @var AudioMessage
     */
    private $audioMessage;

    /**
     * AMessageHandler constructor.
     *
     * @param LINEBot         $bot
     * @param \Monolog\Logger $logger
     * @param $req
     */
    public function __construct($bot, $logger, $req, AudioMessage $audioMessage, $method)
    {
        $this->bot          = $bot;
        $this->logger       = $logger;
        $this->req          = $req;
        $this->audioMessage = $audioMessage;
        $this->method       = $method;
    }

    /**
     * @return null
     */
    public function handle()
    {
        $replyToken = $this->audioMessage->getReplyToken();

        $contentProvider = $this->audioMessage->getContentProvider();
        if ($contentProvider->isExternal()) {
            // TODO: Download external source
            $this->bot->replyMessage(
                $replyToken,
                new AudioMessageBuilder(
                    $contentProvider->getOriginalContentUrl(),
                    $this->audioMessage->getDuration()
                )
            );

            return;
        }

        $contentId = $this->audioMessage->getMessageId();
        $audio     = $this->bot->getMessageContent($contentId)->getRawBody();

        $root = f3()->get('ROOT') . f3()->get('BASE');
        $path = '/upload/audio/' . date('Y/m') . '/';

        if (!Upload::mkdir($root . $path) || !is_writable($root . $path)) {
            Reaction::_return('2006', ['msg' => 'failed to mkdir.']);
        }

        $filename = substr(md5(uniqid(microtime(), 1)), 0, 15) . '.mp4';
        $filePath = $root . $path . $filename;

        FSHelper::dumpFile($filePath, $audio);

        $data = [
            'id'      => $contentId,
            'user_id' => $this->audioMessage->getUserId(),
            'type'    => 'Audio',
            'uri'     => $path . $filename,
        ];

        if ($this->audioMessage->isGroupEvent()) {
            $data['board_id'] = $this->audioMessage->getGroupId();
        } elseif ($this->audioMessage->isRoomEvent()) {
            $data['board_id'] = $this->audioMessage->getRoomId();
        } else {
            $data['board_id'] = null;
        }

        call_user_func_array('\\F3CMS\\' . $this->method, [$data]);

        // TODO: save audio to text

        // return ['none', ['txt' => '是否要轉成文字格式？']];
        return ['none', ['txt' => '已保存']];
    }
}
