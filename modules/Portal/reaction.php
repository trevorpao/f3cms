<?php

namespace F3CMS;

use F3CMS\LineEventHandler\BeaconEventHandler;
use F3CMS\LineEventHandler\FollowEventHandler;
use F3CMS\LineEventHandler\JoinEventHandler;
use F3CMS\LineEventHandler\LeaveEventHandler;
use F3CMS\LineEventHandler\MessageHandler\AMessageHandler;
use F3CMS\LineEventHandler\MessageHandler\FileMessageHandler;
use F3CMS\LineEventHandler\MessageHandler\ImageMessageHandler;
use F3CMS\LineEventHandler\MessageHandler\LocationMessageHandler;
use F3CMS\LineEventHandler\MessageHandler\StickerMessageHandler;
use F3CMS\LineEventHandler\MessageHandler\TextMessageHandler;
use F3CMS\LineEventHandler\MessageHandler\VMessageHandler;
use F3CMS\LineEventHandler\PbEventHandler;
use F3CMS\LineEventHandler\ThingsEventHandler;
use F3CMS\LineEventHandler\UnfollowEventHandler;
use LINE\LINEBot;
use LINE\LINEBot\Event\AccountLinkEvent;
use LINE\LINEBot\Event\BeaconDetectionEvent;
use LINE\LINEBot\Event\FollowEvent;
use LINE\LINEBot\Event\JoinEvent;
use LINE\LINEBot\Event\LeaveEvent;
use LINE\LINEBot\Event\MessageEvent;
use LINE\LINEBot\Event\MessageEvent\AudioMessage;
use LINE\LINEBot\Event\MessageEvent\FileMessage;
use LINE\LINEBot\Event\MessageEvent\ImageMessage;
use LINE\LINEBot\Event\MessageEvent\LocationMessage;
use LINE\LINEBot\Event\MessageEvent\StickerMessage;
use LINE\LINEBot\Event\MessageEvent\TextMessage;
use LINE\LINEBot\Event\MessageEvent\UnknownMessage;
use LINE\LINEBot\Event\MessageEvent\VideoMessage;
use LINE\LINEBot\Event\PostbackEvent;
use LINE\LINEBot\Event\ThingsEvent;
use LINE\LINEBot\Event\UnfollowEvent;
use LINE\LINEBot\Event\UnknownEvent;
use LINE\LINEBot\Exception\InvalidEventRequestException;
use LINE\LINEBot\Exception\InvalidSignatureException;
use LINE\LINEBot\HTTPClient\CurlHTTPClient;

class rPortal extends Reaction
{
    /**
     * @param $f3
     * @param $args
     */
    public function do_line($f3, $args)
    {
        $req = parent::_getReq();

        $logger = new \Log('portal.log');

        $bot = new LINEBot(new CurlHTTPClient(f3()->get('line_token')), [
            'channelSecret' => f3()->get('line_secret'),
            // 'endpointBase' => $apiEndpointBase, // <= Normally, you can omit this
        ]);

        $signature = f3()->get('HEADERS')['X-Line-Signature'];
        if (empty($signature)) {
            $logger->write('Signature is missing');

            return parent::_return(400, 'Bad Request');
        }

        try {
            $events = $bot->parseEventRequest(f3()->get('BODY'), $signature);
        } catch (InvalidSignatureException $e) {
            $logger->write('Invalid signature');

            return parent::_return(400, 'Invalid signature');
        } catch (InvalidEventRequestException $e) {
            return parent::_return(400, 'Invalid event request');
        }

        $logger->write('body::' . f3()->get('BODY'));

        foreach ($events as $event) {
            /**
             * @var EventHandler $handler
             */
            $handler = null;

            $type = $event->getType();
            // $logger->write(sprintf('[Type: %s]', $type));

            $userID = $event->getUserId();
            if (!($event instanceof FollowEvent) && !empty($userID)) {
                $old = fUser::chk($userID);
                if (!$old || fUser::ST_OFF == $old['status']) {
                    $logger->write(sprintf('Unknown User[ID: %s]', $userID));
                    continue;
                } else {
                    $logger->write(sprintf('Followed User[ID: %s]', $userID));
                    f3()->set('event.userID', $userID);
                }
            }

            if ($event->isGroupEvent()) {
                $boardID = $event->getGroupId();
            } elseif ($event->isRoomEvent()) {
                $boardID = $event->getRoomId();
            } else {
                $boardID = null;
            }

            f3()->set('event.boardID', $boardID);

            // TODO: 查詢目前 board、issue、mode, 依模式建立前綴
            if (null == $boardID) {
                $board = [
                    'display_name' => '個人看板',
                    'mode'         => 'none',
                ];
            } else {
                $board = fBoard::one($boardID, 'id', [], false); // 不限狀態

                if (!($event instanceof JoinEvent)) {
                    if (empty($board)) {
                        $logger->write(sprintf('Not Existed Board[ID: %s]', $boardID));
                        continue;
                    } elseif (fBoard::ST_ON != $board['status']) {
                        $logger->write(sprintf('Leaved Board[displayName: %s]', $board['display_name']));
                        continue;
                    }
                }
            }

            // TODO: 會議模式

            if ($event instanceof MessageEvent) {
                if ($event instanceof TextMessage) {
                    // $handler = new TextMessageHandler($bot, $logger, $req, $event, 'fMessage::add');

                    [$txt, $parser] = msgParser::mapping($event);

                    if ('' != $txt && !empty($parser)) {
                        $msg = msgParser::run($txt, $parser);

                        if (is_string($msg)) {
                            $material = ['txt' => $msg];
                        } else {
                            $material = ['msg' => $msg]; // $msg->buildMessage();
                        }

                        $data = [
                            'content'  => $txt,
                            'type'     => 'Command',
                            'user_id'  => $userID,
                            'board_id' => $boardID,
                        ];

                        // $data['type'] = 'Link';

                        fMessage::add($data);
                    }
                } elseif ($event instanceof StickerMessage) {
                    $handler = new StickerMessageHandler($bot, $logger, $event);
                } elseif ($event instanceof LocationMessage) {
                    $handler = new LocationMessageHandler($bot, $logger, $event, 'fAddress::add');
                } elseif ($event instanceof ImageMessage) {
                    $handler = new ImageMessageHandler($bot, $logger, $req, $event, 'fAsset::add');
                } elseif ($event instanceof FileMessage) {
                    $handler = new FileMessageHandler($bot, $logger, $req, $event, 'fAsset::add');
                } elseif ($event instanceof AudioMessage) {
                    $handler = new AMessageHandler($bot, $logger, $req, $event, 'fAsset::add');
                } elseif ($event instanceof VideoMessage) {
                    $handler = new VMessageHandler($bot, $logger, $req, $event, 'fAsset::add');
                } elseif ($event instanceof UnknownMessage) {
                    $logger->write(sprintf(
                        'Unknown message type has come [message type: %s]',
                        $event->getMessageType()
                    ));
                } else {
                    // Unexpected behavior (just in case)
                    // something wrong if reach here
                    $logger->write(sprintf(
                        'Unexpected message type has come, something wrong [class name: %s]',
                        get_class($event)
                    ));
                    continue;
                }
            } elseif ($event instanceof UnfollowEvent) {
                $handler = new UnfollowEventHandler($bot, $logger, $event, 'fUser::unfollow');
            } elseif ($event instanceof FollowEvent) {
                $handler = new FollowEventHandler($bot, $logger, $event, 'fUser::follow');
            } elseif ($event instanceof JoinEvent) {
                $handler = new JoinEventHandler($bot, $logger, $event, 'fBoard::join');
            } elseif ($event instanceof LeaveEvent) {
                $handler = new LeaveEventHandler($bot, $logger, $event, 'fBoard::leave');
            } elseif ($event instanceof PostbackEvent) {
                // $handler = new PbEventHandler($bot, $logger, $event, 'fPortal::postback');

                [$parmas, $parser] = pbParser::mapping($event);

                // $logger->write(json_encode($parmas));

                if ('' != $parmas && !empty($parser)) {
                    $msg = pbParser::run($parmas, $parser);

                    $logger->write(mh()->last());

                    if (is_string($msg)) {
                        // $logger->write($msg);
                        $material = ['txt' => $msg];
                    } else {
                        $logger->write(json_encode($msg->buildMessage(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                        $material = ['msg' => $msg];
                    }
                }
            } elseif ($event instanceof BeaconDetectionEvent) { // TODO:: how to test?
                $handler = new BeaconEventHandler($bot, $logger, $event);
            } elseif ($event instanceof AccountLinkEvent) { // TODO:: what can we do?
                $handler = new AccountLinkEventHandler($bot, $logger, $event);
            } elseif ($event instanceof ThingsEvent) { // TODO:: what can we do?
                $handler = new ThingsEventHandler($bot, $logger, $event);
            } elseif ($event instanceof UnknownEvent) {
                $logger->write(sprintf('Unknown message type has come [type: %s]', $event->getType()));
            } else {
                // Unexpected behavior (just in case)
                // something wrong if reach here
                $logger->write(sprintf(
                    'Unexpected event type has come, something wrong [class name: %s]',
                    get_class($event)
                ));
                continue;
            }

            if (!empty($handler)) {
                [$type, $material] = $handler->handle();
            }

            // $logger->write(mh()->last());
            // $logger->write(json_encode($material));

            // TODO:: msg builder 改寫

            if (array_key_exists('msg', $material)) {
                $response = $bot->replyMessage($event->getReplyToken(), $material['msg']);
            } elseif (array_key_exists('txt', $material)) {
                $response = $bot->replyText($event->getReplyToken(), $material['txt']);
                // } elseif (array_key_exists('meta', $material)) {
            //     try {
            //         // $class = '\F3CMS\f' . $module;
            //         // if (!method_exists($class, $method)) {
            //         //     return parent::_return(1004, array('class' => $class, 'method' => $method));
            //         // }

            //         // // Create a reflection instance of the module, and obtaining the action method.
            //         // $reflectionClass = new \ReflectionClass($class);

            //         // $reflectionInstance = $reflectionClass->newInstance();
            //         // $reflectionMethod = $reflectionClass->getMethod($method);

            //         // // Invoke module action.
            //         // $reflectionMethod->invokeArgs(
            //         //     $reflectionInstance,
            //         //     array($material['meta'])
            //         // );
            //     } catch (Exception $e) {
            //         return parent::_return($e->getCode());
            //     }
            }

            if (!$response->isSucceeded()) {
                // Failed
                $logger->write($response->getHTTPStatus() . ' ' . $response->getRawBody());

                return parent::_return($response->getHTTPStatus(), 'Failed');
            }
        }

        return parent::_return(1, 'OK');
    }
}
