<?php

namespace F3CMS\LineEventHandler\MessageHandler;

use F3CMS\LineEventHandler;
use F3CMS\LineMsgBuilder;
use LINE\LINEBot;
use LINE\LINEBot\Event\MessageEvent\TextMessage;
use LINE\LINEBot\MessageBuilder\TextMessageBuilder;
use LINE\LINEBot\QuickReplyBuilder\ButtonBuilder\QuickReplyButtonBuilder;
use LINE\LINEBot\QuickReplyBuilder\QuickReplyMessageBuilder;
// use \LINE\LINEBot\TemplateActionBuilder\CameraRollTemplateActionBuilder;
// use \LINE\LINEBot\TemplateActionBuilder\CameraTemplateActionBuilder;
use LINE\LINEBot\TemplateActionBuilder\DatetimePickerTemplateActionBuilder;
use LINE\LINEBot\TemplateActionBuilder\LocationTemplateActionBuilder;
use LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder;
use LINE\LINEBot\TemplateActionBuilder\UriTemplateActionBuilder;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
 * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
 */
class TextMessageHandler implements LineEventHandler
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
     * @var TextMessage
     */
    private $textMessage;

    /**
     * TextMessageHandler constructor.
     *
     * @param $bot
     * @param $logger
     * @param $req
     */
    public function __construct($bot, $logger, $req, TextMessage $textMessage, $method)
    {
        $this->bot         = $bot;
        $this->logger      = $logger;
        $this->req         = $req;
        $this->textMessage = $textMessage;
        $this->method      = $method;
    }

    /**
     * @throws LINEBot\Exception\InvalidEventSourceException
     * @throws \ReflectionException
     */
    public function handle()
    {
        $text       = strtolower(trim($this->textMessage->getText()));
        $replyToken = $this->textMessage->getReplyToken();

        $this->logger->write("Got text message from $replyToken: $text");

        $data = [
            'content' => $text,
            'type'    => 'Command',
            'user_id' => $this->textMessage->getUserId(),
        ];

        if ($this->textMessage->isGroupEvent()) {
            $data['board_id'] = $this->textMessage->getGroupId();
        } elseif ($this->textMessage->isRoomEvent()) {
            $data['board_id'] = $this->textMessage->getRoomId();
        } else {
            $data['board_id'] = null;
        }

        switch ($text) {
            case 'profile::':
                $userId = $this->textMessage->getUserId();

                return $this->getProfile($userId);
                break;
            case 'summary::':
                $groupId = $this->textMessage->getGroupId();

                return $this->getSummary($groupId);
                break;
            case '找之前存放的圖片':
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

                return ['message', ['msg' => $msg]];
                break;
            case '找':
                $location  = new PostbackTemplateActionBuilder('地點', 'search=location', '找之前存放的地點');
                $issue     = new PostbackTemplateActionBuilder('工單', 'search=issue', '找之前存放的工單');
                $date      = new PostbackTemplateActionBuilder('日期', 'search=date', '找之前存放的日期');
                $img       = new PostbackTemplateActionBuilder('圖片', 'search=img', '找之前存放的圖片');
                $file      = new PostbackTemplateActionBuilder('檔案', 'search=file', '找之前存放的檔案');
                $video     = new PostbackTemplateActionBuilder('影片', 'search=video', '找之前存放的影片');
                $audio     = new PostbackTemplateActionBuilder('音檔', 'search=audio', '找之前存放的音檔');
                // $goolge   = new PostbackTemplateActionBuilder('連結', 'search=goolge', 'Google it');

                $quickReply = new QuickReplyMessageBuilder([
                    new QuickReplyButtonBuilder($issue),
                    new QuickReplyButtonBuilder($location),
                    new QuickReplyButtonBuilder($date),
                    new QuickReplyButtonBuilder($img),
                    new QuickReplyButtonBuilder($file),
                    new QuickReplyButtonBuilder($video),
                    new QuickReplyButtonBuilder($audio),
                ]);

                $obj = new TextMessageBuilder(
                    '請點選要尋找的項目(限用手機)',
                    $quickReply
                );

                return ['message', ['msg' => $obj]];
                break;
            case '日期?':
            case '日期？':
                $datetimePicker1 = new DatetimePickerTemplateActionBuilder(
                    'DATE TIME',
                    'pickedDate',
                    'datetime',
                    '2021-01-25t00:00',
                    '2022-01-24t23:59', // max
                    '2021-01-25t00:00'  // min
                );

                $datetimePicker2 = new DatetimePickerTemplateActionBuilder(
                    'DATE',
                    'pickedDate',
                    'date',
                    '2021-01-25',
                    '2022-01-24', // max
                    '2021-01-25'  // min
                );

                $quickReply = new QuickReplyMessageBuilder([
                    new QuickReplyButtonBuilder($datetimePicker1),
                    new QuickReplyButtonBuilder($datetimePicker2),
                ]);

                $obj = new TextMessageBuilder(
                    '請點選格式(限用手機)',
                    // new EmojiTextBuilder(
                    //     '$ click button! $',
                    //     new EmojiBuilder(0, '5ac1bfd5040ab15980c9b435', '001'),
                    //     new EmojiBuilder(16, '5ac1bfd5040ab15980c9b435', '001')
                    // ),
                    $quickReply
                );

                return ['message', ['msg' => $obj]];
                break;
            case 'hi line':
            case '嗨 line':
            case '嗨line':
            case '..':
            case '。。':
            case '‥':
                // QuickReply MODE
                $dtBtn = new DatetimePickerTemplateActionBuilder(
                    '挑選日期',
                    'pickedDate',
                    'datetime',
                    date('Y-m-d') . 't00:00',
                    date('Y-m-d', strtotime('+6 month')) . 't23:59', // max
                    date('Y-m-d', strtotime('-1 month')) . 't00:00'  // min
                );

                $locBtn = new LocationTemplateActionBuilder('加註地點');

                $doneBtn  = new PostbackTemplateActionBuilder('設定完成', 'status=Done');
                $closeBtn = new PostbackTemplateActionBuilder('關閉', 'close=1');

                $newBtn     = new UriTemplateActionBuilder('開新票', 'https://liff.line.me/1655687574-yBBlnQzg?status=New&boardID=C78961f5020675300a108dcbfe57940e7');
                $commentBtn = new UriTemplateActionBuilder('加上註解', 'https://liff.line.me/1655687574-yBBlnQzg?status=Comment&boardID=C78961f5020675300a108dcbfe57940e7');

                // Not Working:: https://line.me/R/ (2021/0222)
                // Deprecated:: line://

                $quickReply = new QuickReplyMessageBuilder([
                    new QuickReplyButtonBuilder($doneBtn, 'https://cdn.sense-info.co/icon/bot/success.png'),
                    new QuickReplyButtonBuilder($commentBtn, 'https://cdn.sense-info.co/icon/bot/comment.png'),
                    new QuickReplyButtonBuilder($newBtn, 'https://cdn.sense-info.co/icon/bot/add1.png'),

                    new QuickReplyButtonBuilder($dtBtn, 'https://cdn.sense-info.co/icon/bot/calenda.png'),
                    new QuickReplyButtonBuilder($locBtn, 'https://cdn.sense-info.co/icon/bot/globe.png'),

                    new QuickReplyButtonBuilder($closeBtn, 'https://cdn.sense-info.co/icon/bot/delete1.png'),
                ]);

                $obj = new TextMessageBuilder(
                    '工作選單(限用手機)',
                    $quickReply
                );
                // $this->bot->replyMessage($replyToken, $messageTemplate);

                // ButtonTemplate MODE
                // $imageUrl              = UrlBuilder::buildUrl(['static', 'buttons', '1040.jpg']);

                // $datetimePicker = new DatetimePickerTemplateActionBuilder(
                //     '挑選日期',
                //     'pickedDate',
                //     'datetime',
                //     '2021-01-25t00:00',
                //     '2022-01-24t23:59', // max
                //     '2021-01-25t00:00'  // min
                // );

                // $locationPicker = new LocationTemplateActionBuilder('加註地點');

                // $buttonTemplateBuilder = new ButtonTemplateBuilder(
                //     '工作選單',
                //     '請點選要執行的工作',
                //     null, // $imageUrl,
                //     [
                //         // new UriTemplateActionBuilder('Go to line.me', 'https://line.me'),
                //         $datetimePicker,
                //         $locationPicker,
                //         new PostbackTemplateActionBuilder('設定完成', 'status=Done'),
                //         new PostbackTemplateActionBuilder('開新票', 'status=New')
                //     ]
                // );
                // $obj = new TemplateMessageBuilder('限用手機', $buttonTemplateBuilder);
                // // $this->bot->replyMessage($replyToken, $templateMessage);

                return ['message', ['msg' => $obj]];
                break;
            default:
                // $this->echoBack($replyToken, $text);
                $txt   = '';
                $links = preg_grep('/((https?|ftp)\:\/\/(\S*?\.\S*?))([\s)\[\]{},;"\\\':<]|\.\s|$)/i', explode("\n", $text));
                if (!empty($links)) {
                    // TODO:: save links
                    $data['type'] = 'Link';
                    $txt          = '已保存';
                } else {
                    preg_match_all('/.*search::\s?(\S+).*/m', $text, $search);
                    if (!empty($search[0])) {
                        $txt = '查詢::' . trim($search[1][0]);
                    }

                    preg_match_all('/.*google::\s?(\S+).*/m', $text, $google);
                    if (!empty($google[0])) {
                        $txt = 'https://www.google.com/search?ie=UTF-8&q=' . trim($google[1][0]);
                    }

                    preg_match_all('/.*wiki::\s?(\S+).*/m', $text, $wiki);
                    if (!empty($wiki[0])) {
                        $txt = 'https://zh.wikipedia.org/wiki/' . trim($wiki[1][0]);
                    }
                }

                if ('' == $txt) {
                    return ['none', []];
                } else {
                    call_user_func_array('\\F3CMS\\' . $this->method, [$data]);

                    return ['text', ['txt' => $txt]];
                }

                break;
        }
    }

    /**
     * @param $userId
     *
     * @throws \ReflectionException
     */
    private function getProfile($userId)
    {
        if (!isset($userId)) {
            return ['text', ['txt' => "Bot can't use profile API without user ID"]];
        }

        $response = $this->bot->getProfile($userId);
        if (!$response->isSucceeded()) {
            return ['text', ['txt' => $response->getRawBody()]];
        }

        $profile = $response->getJSONDecodedBody();

        return ['text', ['txt' => 'User ID: ' . $userId . 'Display name: ' . $profile['displayName'] . 'Status message: ' . $profile['statusMessage']]];
    }

    /**
     * @param $groupId
     *
     * @throws \ReflectionException
     */
    private function getSummary($groupId)
    {
        if (!isset($groupId)) {
            return ['text', ['txt' => "Bot can't use profile API without group ID"]];
        }

        $response = $this->bot->getGroupSummary($groupId);
        if (!$response->isSucceeded()) {
            return ['text', ['txt' => $response->getRawBody()]];
        }

        $summary = $response->getJSONDecodedBody();

        return ['text', ['txt' => 'Group ID: ' . $groupId . 'Group name: ' . $summary['groupName'] . 'Picture: ' . $summary['pictureUrl']]];
    }
}
