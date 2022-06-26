<?php

namespace F3CMS\LineEventHandler\MessageHandler;

use F3CMS\FSHelper;
use F3CMS\LineEventHandler;
use F3CMS\Reaction;
use F3CMS\Upload;
use LINE\LINEBot;
use LINE\LINEBot\Event\MessageEvent\VideoMessage;
use LINE\LINEBot\MessageBuilder\VideoMessageBuilder;

class VMessageHandler implements LineEventHandler
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
     * @var VideoMessage
     */
    private $videoMessage;

    /**
     * VMessageHandler constructor.
     *
     * @param LINEBot         $bot
     * @param \Monolog\Logger $logger
     * @param $req
     */
    public function __construct($bot, $logger, $req, VideoMessage $videoMessage, $method)
    {
        $this->bot          = $bot;
        $this->logger       = $logger;
        $this->req          = $req;
        $this->videoMessage = $videoMessage;
        $this->method       = $method;
    }

    /**
     * @return null
     */
    public function handle()
    {
        $replyToken = $this->videoMessage->getReplyToken();

        $contentProvider = $this->videoMessage->getContentProvider();
        if ($contentProvider->isExternal()) {
            // TODO: Download external source
            $this->bot->replyMessage(
                $replyToken,
                new VideoMessageBuilder(
                    $contentProvider->getOriginalContentUrl(),
                    $contentProvider->getPreviewImageUrl()
                )
            );

            return;
        }

        $contentId = $this->videoMessage->getMessageId();
        $video     = $this->bot->getMessageContent($contentId)->getRawBody();

        $root = f3()->get('ROOT') . f3()->get('BASE');
        $path = '/upload/video/' . date('Y/m') . '/';

        if (!Upload::mkdir($root . $path) || !is_writable($root . $path)) {
            Reaction::_return('2006', ['msg' => 'failed to mkdir.']);
        }

        $filename = substr(md5(uniqid(microtime(), 1)), 0, 15) . '.mp4';
        $filePath = $root . $path . $filename;

        FSHelper::dumpFile($filePath, $video);

        $data = [
            'id'      => $contentId,
            'user_id' => $this->videoMessage->getUserId(),
            'type'    => 'Video',
            'uri'     => $path . $filename,
        ];

        if ($this->videoMessage->isGroupEvent()) {
            $data['board_id'] = $this->videoMessage->getGroupId();
        } elseif ($this->videoMessage->isRoomEvent()) {
            $data['board_id'] = $this->videoMessage->getRoomId();
        } else {
            $data['board_id'] = null;
        }

        call_user_func_array('\\F3CMS\\' . $this->method, [$data]);

        return ['none', ['txt' => '已保存']];
    }
}
