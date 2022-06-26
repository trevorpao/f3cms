<?php

namespace F3CMS\LineEventHandler\MessageHandler;

use F3CMS\FSHelper;
use F3CMS\LineEventHandler;
use F3CMS\Reaction;
use F3CMS\Upload;
use LINE\LINEBot;
use LINE\LINEBot\Event\MessageEvent\FileMessage;

class FileMessageHandler implements LineEventHandler
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
     * @var FileMessage
     */
    private $fileMessage;

    /**
     * FileMessageHandler constructor.
     *
     * @param LINEBot         $bot
     * @param \Monolog\Logger $logger
     * @param $req
     */
    public function __construct($bot, $logger, $req, FileMessage $fileMessage, $method)
    {
        $this->bot         = $bot;
        $this->logger      = $logger;
        $this->req         = $req;
        $this->fileMessage = $fileMessage;
        $this->method      = $method;
    }

    /**
     * @return null
     */
    public function handle()
    {
        $replyToken = $this->fileMessage->getReplyToken();

        $contentId    = $this->fileMessage->getMessageId();
        $old_filename = $this->fileMessage->getFileName();
        $file         = $this->bot->getMessageContent($contentId)->getRawBody();

        $this->logger->write('file::' . json_encode($this->bot->getMessageContent($contentId)->getHeaders()));

        $root = f3()->get('ROOT') . f3()->get('BASE');
        $path = '/upload/doc/' . date('Y/m') . '/';

        if (!Upload::mkdir($root . $path) || !is_writable($root . $path)) {
            Reaction::_return('2006', ['msg' => 'failed to mkdir.']);
        }

        $ext = array_reverse(preg_split('/\./D', $old_filename))[0];

        $filename = substr(md5(uniqid(microtime(), 1)), 0, 15) . '.' . $ext;
        $filePath = $root . $path . $filename;

        FSHelper::dumpFile($filePath, $file);

        $data = [
            'id'      => $contentId,
            'user_id' => $this->fileMessage->getUserId(),
            'type'    => 'File',
            'title'   => $old_filename,
            'uri'     => $path . $filename,
        ];

        if ($this->fileMessage->isGroupEvent()) {
            $data['board_id'] = $this->fileMessage->getGroupId();
        } elseif ($this->fileMessage->isRoomEvent()) {
            $data['board_id'] = $this->fileMessage->getRoomId();
        } else {
            $data['board_id'] = null;
        }

        call_user_func_array('\\F3CMS\\' . $this->method, [$data]);

        // TODO: pdf to text(OCR)

        // return ['none', ['txt' => '是否要加上備註？']];
        return ['none', ['txt' => '已保存']];
    }
}
