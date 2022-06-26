<?php

namespace F3CMS\LineEventHandler\MessageHandler;

use F3CMS\FSHelper;
use F3CMS\LineEventHandler;
use F3CMS\Reaction;
use F3CMS\Upload;
use LINE\LINEBot;
use LINE\LINEBot\Event\MessageEvent\ImageMessage;
use LINE\LINEBot\MessageBuilder\ImageMessageBuilder;

class ImageMessageHandler implements LineEventHandler
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
     * @var ImageMessage
     */
    private $imageMessage;

    /**
     * ImageMessageHandler constructor.
     *
     * @param LINEBot         $bot
     * @param \Monolog\Logger $logger
     * @param $req
     */
    public function __construct($bot, $logger, $req, ImageMessage $imageMessage, $method)
    {
        $this->bot          = $bot;
        $this->logger       = $logger;
        $this->req          = $req;
        $this->imageMessage = $imageMessage;
        $this->method       = $method;
    }

    /**
     * @return null
     */
    public function handle()
    {
        $replyToken = $this->imageMessage->getReplyToken();

        $contentProvider = $this->imageMessage->getContentProvider();
        if ($contentProvider->isExternal()) {
            // TODO: Download external source
            $this->bot->replyMessage(
                $replyToken,
                new ImageMessageBuilder(
                    $contentProvider->getOriginalContentUrl(),
                    $contentProvider->getPreviewImageUrl()
                )
            );

            return;
        }

        $contentId = $this->imageMessage->getMessageId();
        $image     = $this->bot->getMessageContent($contentId)->getRawBody();

        $this->logger->write('image::' . json_encode($this->bot->getMessageContent($contentId)->getHeaders()));

        $root = f3()->get('ROOT') . f3()->get('BASE');
        $path = '/upload/img/' . date('Y/m') . '/';

        if (!Upload::mkdir($root . $path) || !is_writable($root . $path)) {
            Reaction::_return('2006', ['msg' => 'failed to mkdir.']);
        }

        $filename = substr(md5(uniqid(microtime(), 1)), 0, 15) . '.jpg';
        $filePath = $root . $path . $filename;

        FSHelper::dumpFile($filePath, $image);

        $data = [
            'id'      => $contentId,
            'user_id' => $this->imageMessage->getUserId(),
            'type'    => 'Image',
            'uri'     => $path . $filename,
        ];

        if ($this->imageMessage->isGroupEvent()) {
            $data['board_id'] = $this->imageMessage->getGroupId();
        } elseif ($this->imageMessage->isRoomEvent()) {
            $data['board_id'] = $this->imageMessage->getRoomId();
        } else {
            $data['board_id'] = null;
        }

        call_user_func_array('\\F3CMS\\' . $this->method, [$data]);

        // TODO: img to text(OCR)

        // return ['text', ['txt' => '是否要轉成文字格式？']];
        return ['none', ['txt' => '已保存']];
    }
}
