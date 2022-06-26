<?php

namespace F3CMS\LineEventHandler\MessageHandler;

use F3CMS\LineEventHandler;
use LINE\LINEBot;
use LINE\LINEBot\Event\MessageEvent\LocationMessage;

class LocationMessageHandler implements LineEventHandler
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
     * @var LocationMessage
     */
    private $locationMessage;

    /**
     * LocationMessageHandler constructor.
     *
     * @param LINEBot         $bot
     * @param \Monolog\Logger $logger
     */
    public function __construct($bot, $logger, LocationMessage $locationMessage, $method)
    {
        $this->bot             = $bot;
        $this->logger          = $logger;
        $this->locationMessage = $locationMessage;
        $this->method          = $method;
    }

    public function handle()
    {
        $data = [
            'id'        => $this->locationMessage->getMessageId(),
            'title'     => $this->locationMessage->getTitle(),
            'address'   => $this->locationMessage->getAddress(),
            'latitude'  => $this->locationMessage->getLatitude(),
            'longitude' => $this->locationMessage->getLongitude(),
            'user_id'   => $this->locationMessage->getUserId(),
        ];

        if ($this->locationMessage->isGroupEvent()) {
            $data['board_id'] = $this->locationMessage->getGroupId();
        } elseif ($this->locationMessage->isRoomEvent()) {
            $data['board_id'] = $this->locationMessage->getRoomId();
        } else {
            $data['board_id'] = null;
        }

        $data['title'] = !empty($data['title']) ? $data['title'] : '無名地點';

        $this->logger->write(sprintf(
            '%s is in %s (%s, %s)',
            ($data['title'] ? $data['title'] : '無名地點'), $data['address'], $data['latitude'], $data['longitude']
        ));

        call_user_func_array('\\F3CMS\\' . $this->method, [$data]);

        // $location = new LocationMessageBuilder(($title?$title:'無名地點'), $address, $latitude, $longitude); // 省略會出錯，原因不明？

        // TODO: save location

        // return ['none', ['txt' => '這裡有什麼？']];
        return ['none', ['txt' => '已保存']];
    }
}
