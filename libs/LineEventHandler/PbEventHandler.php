<?php

namespace F3CMS\LineEventHandler;

use F3CMS\LineEventHandler;
use F3CMS\LineMsgBuilder;
use LINE\LINEBot;
use LINE\LINEBot\Event\PostbackEvent;

class PbEventHandler implements LineEventHandler
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
     * @var PostbackEvent
     */
    private $postbackEvent;

    /**
     * PostbackEventHandler constructor.
     *
     * @param LINEBot         $bot
     * @param \Monolog\Logger $logger
     */
    public function __construct($bot, $logger, PostbackEvent $postbackEvent, $method)
    {
        $this->bot           = $bot;
        $this->logger        = $logger;
        $this->postbackEvent = $postbackEvent;
        $this->method        = $method;
    }

    /**
     * @throws \ReflectionException
     */
    public function handle()
    {
        // $this->bot->replyText(
        //     $this->postbackEvent->getReplyToken(),
        //     'Got postback ' . $this->postbackEvent->getPostbackData()
        // );

        $parmas = $this->postbackEvent->getPostbackParams();

        parse_str($this->postbackEvent->getPostbackData(), $data);

        if (isset($data['search'])) {
            switch ($data['search']) {
                case 'img':
                case 'file':
                case 'video':
                case 'audio':
                    // $rtn = ['message', ['msg' => LineMsgBuilder::carousel([
                    //     ['title' => 'foo1', 'info' => 'bar1', 'cover' => '/static/buttons/1040.jpg'],
                    //     ['title' => 'foo2', 'info' => 'bar2', 'cover' => '/static/buttons/1040.jpg']
                    // ], '先前存入的檔案')]];

                    $msg = LineMsgBuilder::mircoFlex([
                        [
                            'cover' => 'https://example.com/photo1.png',
                            'title' => 'Arm Chair, White',
                            'price' => 49.99,
                            'stock' => true,
                        ],
                        '112' => [
                            'cover' => 'https://example.com/photo2.png',
                            'title' => 'Metal Desk Lamp',
                            'price' => 11.99,
                            'stock' => false,
                        ],
                    ], '先前存入的檔案');

                    $this->logger->write(json_encode($msg->buildMessage()));

                    $rtn = ['message', ['msg' => $msg]];

                    break;
                default:
                    $rtn = ['text', ['txt' => 'coming soon']];
                    break;
            }
        } elseif (isset($data['close'])) {
            $rtn = ['text', ['txt' => '選單關閉']];
        } elseif (!empty($parmas)) {
            // save datetime
            // pickedDate::{"datetime":"2021-02-07T00:00"}
            $data['pickedDate'] = $parmas['datetime'];

            $DT = [
                // 'id'   => $this->postbackEvent->getMessageId(), // PostBack is not a message
                'user_id'   => $this->postbackEvent->getUserId(),
                'target_ts' => $parmas['datetime'],
            ];

            if ($this->postbackEvent->isGroupEvent()) {
                $DT['board_id'] = $this->postbackEvent->getGroupId();
            } elseif ($this->postbackEvent->isRoomEvent()) {
                $DT['board_id'] = $this->postbackEvent->getRoomId();
            } else {
                $DT['board_id'] = null;
            }

            call_user_func_array('\\F3CMS\\' . $this->method, [$DT]);

            $rtn = ['text', ['txt' => '這天要做什麼？']];
        }

        return $rtn;
    }
}
