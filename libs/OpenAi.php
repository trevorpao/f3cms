<?php
namespace F3CMS;

class OpenAi extends Helper
{
    const PORTRAIT = '1024x1792';
    const LANDSCAPE = '1792x1024';
    const SQUARE = '1024x1024';

    // $0.00000250 / 1 input tokens
    // $0.00001000 / 1 output tokens
    const DEFAULT_MODE = 'gpt-4o-2024-08-06';
    const DEFAULT_IN = 250;
    const DEFAULT_OUT = 1000;

    // $0.00000015 / 1 input tokens
    // $0.00000060 / 1 output tokens
    const MINI_MODE = 'gpt-4o-mini-2024-07-18';
    const MINI_IN = 15;
    const MINI_OUT = 60;

    /**
     * @var array
     */
    private static $_commands = [
        'answer' => [
            'endpoint'       => '/v1/chat/completions'
        ],
        'draw' => [
            'endpoint'       => '/v1/images/generations'
        ],
        'moderation' => [
            'endpoint'       => '/v1/moderations'
        ],
    ];

    /**
     * @param $prompt
     */
    public static function answer($prompt, $format = '', $isMini = 0)
    {
        if (is_array($prompt)) {
            $tmp = '';
            $ary = [];

            foreach ($prompt as $idx => $row) {
                if ($idx > 0) {
                    $tmp .= PHP_EOL;
                }

                // $tmp .= 'Human: ' . $row['content'] . PHP_EOL . 'AI: ' . $row['reply'];
                $tmp .= '' . $row['content'] . PHP_EOL . 'AI: ' . $row['reply'];

                $ary[] = ['role' => 'user', 'content' => $row['content']];
                $ary[] = ['role' => 'assistant', 'content' => $row['reply']];
            }

            $prompt = $tmp;
        } else {
            return '再問我一次 (我沒有收到符合格式的問題)';
        }

        $opts = [
            'model' => self::DEFAULT_MODE,

            // 'prompt' => $prompt,
            'messages' => $ary,
            'temperature' => 0.9, // 擬人(換句話說)
            'max_tokens' => 4096,
            'top_p' => 1,
            'frequency_penalty' => 0.0,
            'presence_penalty' => 0.6, // 以此增加談論新主題的可能性
            'user' => 'Junior Editor',
            // 'stop' => ["\n"]
            'stop' => ['Human: ', 'AI: ']
        ];

        if ($isMini) {
            $opts['model'] = self::MINI_MODE;
        }

        if (!empty($format)) {
            if (is_string($format)) {
                $format = jsonDecode($format);
            }

            $opts['response_format'] = $format;
        }

        return self::_format(self::_call('answer', $opts));
    }

    /**
     * @param $prompt
     * @param $shape
     */
    public static function draw($prompt, $shape = self::LANDSCAPE)
    {

        return self::_format(self::_call('draw', [
            'model' => 'dall-e-3',

            'prompt' => $prompt,
            'n' => 1,
            'size' => $shape, // 1024x1024, 1024x1792 or 1792x1024
            'style' => 'vivid', // vivid, natural
            'quality' => 'standard', // standard, hd
            'user' => 'Junior Editor',
        ]));
    }

    /**
     * 檢查訊息內容是否包含不雅文案
     *
     * @param string $content - 訊息內容
     * @return bool - 如果包含不雅文案，返回 true，否則返回 false
     */
    public static function isBadWords($content)
    {
        $response = self::_call('moderation', [
            'model' => 'omni-moderation-latest',
            'input' => $content,
        ]);

        // 檢查是否有任何不雅內容
        foreach ($response['results'] as $result) {
            if ($result['flagged']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param $string
     * @param $ary
     */
    public static function vsprintf($string, $ary)
    {
        return [['content' => vsprintf($string, $ary), 'reply' => '']];
    }

    /**
     * @param $command
     * @param array $opts
     */
    private static function _call($command, $opts = [])
    {
        if (!isset(self::$_commands[$command]) || !isset(self::$_commands[$command]['endpoint'])) {
            return false;
        }

        $endpoint = self::$_commands[$command]['endpoint'];

        $uri = sprintf('https://api.openai.com%s', $endpoint);

        $ch = curl_init();

        $options = [
            CURLOPT_HEADER         => true,
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false, // for test
            CURLOPT_SSL_VERIFYHOST => false, // for test
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 600,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36 sense-info/0.8',

            CURLOPT_HTTPHEADER     => [
                'cache-control: no-cache',
                'Content-Type: application/json; charset=UTF-8',
            ],

            // CURLOPT_URL => 'https://api.openai.com/v1/completions',
            CURLOPT_URL => $uri,
        ];

        $options[CURLOPT_POSTFIELDS] = json_encode($opts);

        $options[CURLOPT_HTTPHEADER][] = 'Authorization: Bearer ' . f3()->get('openai.key');

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (false === $response) {
            self::_log(sprintf('cURL: %s', curl_error($ch))); // 逾時 error
            exit;
        }

        if ($command != 'speech') {
            self::_log(sprintf('回覆: %s', $response));
        }

        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headerStr  = substr($response, 0, $headerSize);
        $bodyStr    = substr($response, $headerSize);

        curl_close($ch);

        if (!empty($response)) {
            if ($command == 'speech' && in_array($httpCode, [200, 400])) {
                return $response; // not json
            }

            $rtn = jsonDecode($bodyStr);

            if (in_array($httpCode, [200, 400])) {
                if (empty($rtn)) {
                    return $bodyStr; // not json
                } else {
                    // TODO: 加上 usage system alert
                    return $rtn;
                }
            } else {
                return (int) $httpCode;
            }
        } else {
            // No way to send HTTP request.
            return false;
        }
    }

    /**
     * @param $rtn
     */
    private static function _format($rtn = '')
    {
        $msg = '';
        if (is_bool($rtn)) {
            $msg = 'unknown error';
        }

        if (is_numeric($rtn)) {
            $msg = 'status error: ' . $rtn;
        }

        if (is_string($rtn)) {
            $msg = 'format error';
        }

        if (is_array($rtn) && isset($rtn['error'])) {
            $msg = $rtn['error']['message'];
        }

        if ('' == $msg) {

            if (isset($rtn['choices'])) {
                // $rtn['choices'][0]['text'] = trim($rtn['choices'][0]['text']);

                // save token
                $staff = rStaff::_CStaff('id');
                if (empty($staff)) {
                    $staff = 0;
                }

                if ($rtn['model'] == self::MINI_MODE) {
                    $in_cost = intval($rtn['usage']['prompt_tokens']) * self::MINI_IN;
                    $out_cost = intval($rtn['usage']['completion_tokens']) * self::MINI_OUT;
                } else {
                    $in_cost = intval($rtn['usage']['prompt_tokens']) * self::DEFAULT_IN;
                    $out_cost = intval($rtn['usage']['completion_tokens']) * self::DEFAULT_OUT;
                }

                mh()->insert(tpf() . 'llmtoken', [
                    'id' => $rtn['id'],
                    'model' => $rtn['model'],
                    'prompt_tokens' => $rtn['usage']['prompt_tokens'],
                    'completion_tokens' => $rtn['usage']['completion_tokens'],
                    'total_tokens' => $rtn['usage']['total_tokens'],
                    'prompt_cost' => $in_cost,
                    'completion_cost' => $out_cost,
                    'total_cost' => ($in_cost + $out_cost),
                    'insert_user' => $staff,
                ]);

                if (!empty($rtn['choices'][0]['text'])) {
                    if ($rtn['choices'][0]['finish_reason'] == 'length') {
                        $rtn['choices'][0]['text'] .= '………';
                    }

                    return $rtn['choices'][0]['text'];
                } elseif (!empty($rtn['choices'][0]['message'])) {
                    return $rtn['choices'][0]['message']['content'];
                } else {
                    return '再問我一次 (我不適合回覆這個問題)';
                }
            }

            if (isset($rtn['data'])) {
                return [
                    'explain' => $rtn['data'][0]['revised_prompt'],
                    'img' => $rtn['data'][0]['url']
                ];
            }

        } else {
            return '再問我一次 ('. $msg .')';
        }
    }

    /**
     * @param $str
     */
    private static function _log($str)
    {
        $logger = new \Log('openai.log');
        $logger->write((is_string($str)) ? $str : jsonEncode($str));
    }
}
