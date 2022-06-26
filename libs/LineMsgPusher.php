<?php

namespace F3CMS;

class LineMsgPusher
{
    /**
     * @var string
     */
    private static $uri = 'https://api.line.me/v2/bot/message';

    /**
     * @param $targetID
     * @param $text
     */
    public static function send($targetID, $text)
    {
        return self::request('push', [
            'to'       => $targetID,
            'messages' => [
                ['type' => 'text', 'text' => $text],
            ],
        ]);
    }

    /**
     * @param $action
     * @param $data
     *
     * @return mixed
     */
    public static function request($action, $data)
    {
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL            => self::$uri . '/' . $action,
            CURLOPT_RETURNTRANSFER => true,
            // CURLOPT_SSL_VERIFYPEER => false,
            // CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_HTTPHEADER     => [
                'authorization: Bearer ' . f3()->get('line_token'),
                'cache-control: no-cache',
                'content-type: application/json',
            ],
            CURLOPT_POSTFIELDS     => json_encode($data), // '{"to": "U428f5bc9e111ebc3c0e5370fddf3a7b2","messages": [{"type": "text","text": "Hello,Trevor~這是Line Bot API測試訊息"}]}'
        ]);

        $response = curl_exec($curl);
        $err      = curl_error($curl);

        curl_close($curl);

        if ($err) {
            return 'cURL Error #:' . $err;
        } else {
            return $response; // TODO: save a log
        }
    }
}
