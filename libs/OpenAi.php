<?php
namespace F3CMS;

class OpenAi extends Helper
{
    const PORTRAIT = '1024x1792';
    const LANDSCAPE = '1792x1024';
    const SQUARE = '1024x1024';

    // $2.50 / 1M tokens
    // $0.00000250 / 1 input tokens
    // $0.00001000 / 1 output tokens
    const DEFAULT_MODE = 'gpt-4o-2024-08-06';
    const DEFAULT_IN   = 250;
    const DEFAULT_OUT  = 1000;

    // $0.00000015 / 1 input tokens
    // $0.00000060 / 1 output tokens
    const MINI_MODE = 'gpt-4o-mini-2024-07-18';
    const MINI_IN   = 15;
    const MINI_OUT  = 60;

    // GPT Image 2
    const DRAW_MODE = 'gpt-image-2';
    const DRAW_IN   = 0;
    const DRAW_OUT  = 800000;

    // $0.60 / 1M tokens
    // $0.00000060 / 1 input tokens
    // $0.015 minute
    const SPEECH_MODE = 'gpt-4o-mini-tts';
    const SPEECH_IN   = 60;
    const SPEECH_OUT  = 50000; // 平均 20 秒

    const MODERATION_MODE = 'omni-moderation-latest';
    const MODERATION_IN   = 0;
    const MODERATION_OUT  = 0;

    /**
     * @var array
     */
    private static $_commands = [
        'answer' => [
            'endpoint'       => '/v1/responses'
        ],
        'draw' => [
            'endpoint'       => '/v1/images/generations'
        ],
        'speech' => [
            'endpoint'       => '/v1/audio/speech'
        ],
        'whisper' => [
            'endpoint'       => '/v1/audio/transcriptions'
        ],
        'moderation' => [
            'endpoint'       => '/v1/moderations'
        ],
    ];

    /**
     * @param $prompt
     */
    public static function answer($prompt, $format = '', $opts = [])
    {
        $default = [
            'isMini' => 0,
            'max' => 8192,
            'withReqID' => 0,
            'scenario' => '',
            'roles' => ['editor', 'startup', 'counselor', 'engineer', 'pm'],
            'assistantInstructions' => '',
            'convId' => '',
        ];

        $opts = array_merge($default, $opts);

        $initialPrompts = [
            'editor' => 'Editor: Proofread, clear translation, improve expression, cultural fit.',
            'startup' => 'Startup: Legal compliance, financial analysis, training design, data supplement.',
            'counselor' => 'Counselor: Empathy, guide exploration, scientific advice, privacy.',
            'engineer' => 'Staff Engineer: SA, DBA, MIS capabilities.',
            'pm' => 'Scrum Master, Project Manager, Account Manager capabilities. Agile development expertise.'
        ];

        if (!empty($opts['assistantInstructions'])) {
            $combinedPrompt = $opts['assistantInstructions'];
        } else {
            $combinedPrompt = 'Provide clear, accurate, and organized answers. Request additional information if needed.' . PHP_EOL;

            foreach ($opts['roles'] as $role) {
                if (isset($initialPrompts[$role])) {
                    $combinedPrompt .= $initialPrompts[$role] . PHP_EOL;
                }
            }

            $combinedPrompt .= 'Determine the most suitable role based on the context and respond accordingly.' . PHP_EOL .
                'Follow the user\'s language preference.';
        }

        if (is_array($prompt)) {
            $msgs = [
                [
                    'role' => 'system', // 'system', 'assistant', 'user', 'function', 'tool', 'developer'
                    'content' => ((!empty($opts['scenario'])) ? '語境要求高於角色要求，語境要求：' . PHP_EOL . $opts['scenario'] . PHP_EOL . '角色要求：' : '') . $combinedPrompt
                ]
            ];

            foreach ($prompt as $idx => $row) {
                $msgs[] = ['role' => 'user', 'content' => $row['content']];
                $msgs[] = ['role' => 'assistant', 'content' => $row['reply']];
            }

        } else {
            return '再問我一次 (我沒有收到符合格式的問題)';
        }

        $max = min(max(500, $opts['max']), 16384);

        if (f3()->get('DEBUG') > 1) {
            f3()->set('openai.promptLog', jsonEncode($msgs));
        }

        $responsePayload = self::_buildResponsePayload($msgs, $opts, $max, $format);

        $conversationId = self::_ensureConversation($opts, $responsePayload);

        $needReqId = ($opts['withReqID'] == 1) || (!empty($opts['convId']));

        $result = self::_formatResponse(self::_call('answer', $responsePayload), $needReqId ? 1 : 0, $conversationId);

        if (!empty($opts['convId'])) {
            [$replyText, $conversationRef] = self::_ensureConversationResult($result);

            if ($opts['withReqID'] == 0) {
                return $replyText;
            }

            return [$replyText, $conversationRef];
        }

        return $result;
    }

    /**
     * @param $prompt
     * @param $shape
     */
    public static function draw($prompt, $shape = self::LANDSCAPE)
    {
        return self::_format(self::_call('draw', [
            'model' => self::DRAW_MODE,

            'prompt' => $prompt,
            'n' => 1,
            'size' => $shape,
            'quality' => 'medium',
            'output_format' => 'png',
            'moderation' => 'auto',
        ]));
    }

    public static function text($filename, $prompt = '')
    {
        $root    = f3()->get('ROOT') . f3()->get('BASE'); // rtrim(f3()->get('abspath'), '/');
        $path    = '/../../upload/'. f3()->get('upload_dir') .'';

        if (file_exists($root . $path . $filename)) {
            $opt = [
                'model' => 'whisper-1',
                'file' => $root . $path . $filename,
                'response_format' => 'vtt',
            ];

            if ($prompt != '') {
                $opt['prompt'] = $prompt;
                $opt['response_format'] = 'json';
                $opt['model'] = 'gpt-4o-transcribe';
            }

            $response = self::_call('whisper', $opt);
        } else {
            $response = [
                'error' => [
                    'type' => 'invalid_request_error'
                ]
            ];
        }

        return $response;
    }

    public static function dialogue($dialogues, $filename = '')
    {
        $root    = f3()->get('ROOT') . f3()->get('BASE'); // rtrim(f3()->get('abspath'), '/');
        $path    = '/upload/'. f3()->get('upload_dir') .'mp3/' . date('Y/m') . '/';

        if (!FSHelper::mkdir($root . $path) || !is_writable($root . $path)) {
            Reaction::_return('2006', ['msg' => 'failed to mkdir.']);
        }

        if (empty($filename)) {
            $filename = substr(md5(uniqid(microtime(), 1)), 0, 15);
        }

        $silence = self::_silence(3);

        $filename = $path . $filename .'.mp3';

        foreach ($dialogues as $index => $dialogue) {
            $audioContent = self::_speech($dialogue['text'], $dialogue['role']);
            FSHelper::appendToFile($root . $filename, $silence . $audioContent);
            usleep(300000); // 0.3s
        }

        return ['filename' => $filename];
    }

    public static function speech($text, $voice = '', $filename = '', $instructions = '')
    {
        $root    = rtrim(f3()->get('abspath') . 'upload/' . f3()->get('upload_dir') , '/');
        $path    = '/mp3/' . date('Y/m') . '/';

        if (!FSHelper::mkdir($root . $path) || !is_writable($root . $path)) {
            Reaction::_return('2006', ['msg' => 'failed to mkdir.']);
        }

        if (empty($filename)) {
            $filename = substr(md5(uniqid(microtime(), 1)), 0, 15);
        }

        $filename = $path . $filename .'.mp3';

        $audioContent = self::_speech($text, $voice, $instructions);
        FSHelper::appendToFile($root . $filename, $audioContent);

        return ['filename' => '/upload' . $filename];
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
            'model' => self::MODERATION_MODE,
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

    public static function splitTranscript($transcript, $maxLength = 500)
    {
        $paragraphs = explode(PHP_EOL, $transcript);
        $result = [];
        $currentSegment = '';

        foreach ($paragraphs as $paragraph) {
            if (mb_strlen($currentSegment) + mb_strlen($paragraph) + 1 > $maxLength) {
                $result[] = $currentSegment;
                $currentSegment = $paragraph;
            } else {
                if (!empty($currentSegment)) {
                    $currentSegment .= PHP_EOL;
                }
                $currentSegment .= $paragraph;
            }
        }

        if (!empty($currentSegment)) {
            $result[] = $currentSegment;
        }

        return $result;
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
     * https://www.mxp.tw/9765/
     * @param $str
     * @param array $charsets
     */
    public static function detect_unicode_charset($str, $charsets = ['Chinese'])
    {
        // 定義各個字符集的正則表達式
        $regex = array(
            'Arabic' => '/\p{Arabic}/u',
            'Bengali' => '/\p{Bengali}/u',
            'Chinese' => '/\p{Han}/u',
            'Cyrillic' => '/\p{Cyrillic}/u',
            'Devanagari' => '/\p{Devanagari}/u',
            'Georgian' => '/\p{Georgian}/u',
            'Greek' => '/\p{Greek}/u',
            'Gujarati' => '/\p{Gujarati}/u',
            'Gurmukhi' => '/\p{Gurmukhi}/u',
            'Hebrew' => '/\p{Hebrew}/u',
            'Hiragana' => '/\p{Hiragana}/u',
            'Kannada' => '/\p{Kannada}/u',
            'Katakana' => '/\p{Katakana}/u',
            'Khmer' => '/\p{Khmer}/u',
            'Korean' => '/\p{Hangul}/u',
            'Lao' => '/\p{Lao}/u',
            'Latin' => '/\p{Latin}/u',
            'Malayalam' => '/\p{Malayalam}/u',
            'Myanmar' => '/\p{Myanmar}/u',
            'Oriya' => '/\p{Oriya}/u',
            'Sinhala' => '/\p{Sinhala}/u',
            'Tamil' => '/\p{Tamil}/u',
            'Telugu' => '/\p{Telugu}/u',
            'Thai' => '/\p{Thai}/u',
        );

        // 遍歷字符集並查找相符的字符
        $matches = array();
        foreach ($charsets as $charset) {
            if (isset($regex[$charset]) && preg_match($regex[$charset], $str)) {
                $matches[] = $charset;
            }
        }

        return $matches;
    }

    private static function _silence($duration)
    {
        // return str_repeat("\0", $duration * 44100 * 2 * 2); // 44100 samples/sec, 2 channels, 2 bytes/sample
        // return FSHelper::openFile($root . '/assets/1sec_silence.mp3');
        return '';
    }

    private static function _speech($text, $voice = '', $instructions = '')
    {
        $voice = (empty($voice)) ? 'nova' : $voice;

        $response = self::_call('speech', [
            'model' => 'gpt-4o-mini-tts', // 'tts-1-hd', 'gpt-4o-mini-tts', 'tts-1',

            'voice' => strtolower($voice),
            'input' => $text,
            'instructions' => $instructions,
            'max_tokens' => 100,
        ]);

        return $response;
    }

    /**
     * @param $command
     * @param array $opts
     */
    private static function _call($command, $opts = [], $method = 'POST', $endpointOverride = '', $query = [])
    {
        $method = strtoupper($method);

        if (empty($endpointOverride)) {
            if (!isset(self::$_commands[$command]) || !isset(self::$_commands[$command]['endpoint'])) {
                return false;
            }
            $endpoint = self::$_commands[$command]['endpoint'];
        } else {
            $endpoint = $endpointOverride;
        }

        if ('' === $endpoint) {
            return false;
        }

        $uri = 'https://api.openai.com' . $endpoint;

        $needsResponsesBeta = false;

        if ($command === 'answer') {
            if ((0 === strpos($endpoint, '/v1/responses')) || (0 === strpos($endpoint, '/v1/conversations'))) {
                $needsResponsesBeta = true;
            }
        }

        if (!empty($query)) {
            $uri .= '?' . http_build_query($query);
        }

        $ch = curl_init();

        $httpHeaders = [
            'cache-control: no-cache',
            'Authorization: Bearer ' . f3()->get('openai.key'),
        ];

        if ($needsResponsesBeta) {
            $httpHeaders[] = 'OpenAI-Beta: responses=v2';
        }

        $options = [
            CURLOPT_HEADER         => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 600,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36 sense-info/0.8',
            CURLOPT_HTTPHEADER     => $httpHeaders,
            CURLOPT_URL            => $uri,
        ];

        if ('GET' === $method) {
            $options[CURLOPT_HTTPGET] = true;
        } else {
            $options[CURLOPT_CUSTOMREQUEST] = $method;

            if ('POST' === $method) {
                $options[CURLOPT_POST] = true;
            }

            if (!empty($opts['file'])) {
                $obj         = new \CURLFile($opts['file'], 'audio/mp3', basename($opts['file']));
                $opts['file'] = $obj;

                $options[CURLOPT_POSTFIELDS] = $opts;
                $options[CURLOPT_HTTPHEADER][] = 'Content-Type: multipart/form-data';
            } else {
                $payload = (in_array($method, ['POST', 'PUT', 'PATCH'])) ? json_encode($opts) : '';

                if ('' !== $payload) {
                    $options[CURLOPT_POSTFIELDS] = $payload;
                    $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json; charset=UTF-8';
                }
            }
        }

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (false === $response) {
            self::_log(sprintf('cURL: %s', curl_error($ch)));

            return ['error' => ['message' => 'cURL 執行失敗']];
        }

        if ($command != 'speech') {
            self::_log(sprintf('回覆: %s', $response));
        }

        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $bodyStr    = substr($response, $headerSize);


        if (empty($response)) {
            return ['error' => ['message' => '空的回應']];
        }

        if ($command == 'speech' && in_array($httpCode, [200, 400], true)) {
            return $response;
        }

        if ($command == 'whisper' && in_array($httpCode, [200, 400], true)) {
            return $bodyStr;
        }

        $rtn = jsonDecode($bodyStr);

        if (in_array($httpCode, [200, 201, 400], true)) {
            return (empty($rtn)) ? $bodyStr : $rtn;
        }

        return (int) $httpCode;
    }

    /**
     * @param $rtn
     */
    private static function _format($rtn = '', $withReqID = 0)
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
                if (f3()->get('openai.calculator') == 1) {
                    [$in_cost, $out_cost] = self::calculator($rtn['model'], $rtn['usage']['prompt_tokens'], $rtn['usage']['completion_tokens']);

                    fLlmtoken::add([
                        'id'                => $rtn['id'],
                        'model'             => $rtn['model'],
                        'prompt_tokens'     => $rtn['usage']['prompt_tokens'],
                        'completion_tokens' => $rtn['usage']['completion_tokens'],
                        'total_tokens'      => $rtn['usage']['total_tokens'],
                        'prompt_cost'       => $in_cost,
                        'completion_cost'   => $out_cost,
                        'total_cost'        => ($in_cost + $out_cost),
                    ]);
                }

                if (!empty($rtn['choices'][0]['text'])) {
                    if ($rtn['choices'][0]['finish_reason'] == 'length') {
                        $rtn['choices'][0]['text'] .= '………';
                    }

                    $msg = $rtn['choices'][0]['text'];
                } elseif (!empty($rtn['choices'][0]['message']) && !empty($rtn['choices'][0]['message']['content'])) {
                    $msg = $rtn['choices'][0]['message']['content'];
                } else {
                    $msg = '再問我一次 (我不適合回覆這個問題)';
                }

                return ($withReqID == 0) ? $msg : [$msg, $rtn['id']];
            }

            if (isset($rtn['data'])) {
                $image = $rtn['data'][0] ?? [];
                $imageUrl = '';

                if (!empty($image['url'])) {
                    $imageUrl = $image['url'];
                } elseif (!empty($image['b64_json'])) {
                    $imageUrl = FSHelper::saveBase64Image($image['b64_json'], 'png');
                }

                $id = '';
                if ('' !== $imageUrl && 0 === strpos($imageUrl, 'http')) {
                    $id = basename(parse_url($imageUrl, PHP_URL_PATH));
                    $id = (!empty($id)) ? pathinfo($id)['filename'] : '';
                }

                if ('' === $id) {
                    $id = 'img-' . secure_random_string(24);
                }

                // save token
                if (f3()->get('openai.calculator') == 1) {
                    [$in_cost, $out_cost] = self::calculator(self::DRAW_MODE, 0, 0);

                    fLlmtoken::add([
                        'id'                => $id,
                        'model'             => self::DRAW_MODE,
                        'prompt_cost'       => $in_cost,
                        'completion_cost'   => $out_cost,
                        'total_cost'        => ($in_cost + $out_cost),
                    ]);
                }

                return [
                    'explain' => $image['revised_prompt'] ?? '',
                    'img' => $imageUrl,
                    'b64' => $image['b64_json'] ?? '',
                    'reqID' => $id
                ];
            }

        } else {
            $msg = '再問我一次 ('. $msg .')';
            return ($withReqID == 0) ? $msg : [$msg, ''];
        }
    }

    private static function _formatResponse($rtn, $withReqID = 0, $contextId = '')
    {
        $msg = '';

        if (is_bool($rtn)) {
            $msg = 'unknown error';
        } elseif (is_numeric($rtn)) {
            $msg = 'status error: ' . $rtn;
        } elseif (is_string($rtn)) {
            $msg = 'format error';
        } elseif (is_array($rtn) && isset($rtn['error'])) {
            $msg = $rtn['error']['message'];
        }

        if ('' !== $msg) {
            $msg = '再問我一次 (' . $msg . ')';

            return ($withReqID == 0) ? $msg : [$msg, $contextId];
        }

        if (!is_array($rtn)) {
            $msg = '再問我一次 (response 無回覆)';

            return ($withReqID == 0) ? $msg : [$msg, $contextId];
        }

        $payload = isset($rtn['response']) && is_array($rtn['response']) ? $rtn['response'] : $rtn;

        if (!isset($payload['output'])) {
            return self::_format($payload, $withReqID);
        }

        self::_log(sprintf('回覆: %s', jsonEncode($payload['output'])));

        $reply = self::_extractResponseText($payload['output']);

        if ($reply === '') {
            $reply = '再問我一次 (response 無回覆)';
        }

        $conversationRef = $contextId;

        if ($conversationRef === '' && isset($rtn['conversation']['id'])) {
            $conversationRef = $rtn['conversation']['id'];
        } elseif ($conversationRef === '' && isset($rtn['conversation_id'])) {
            $conversationRef = $rtn['conversation_id'];
        }

        $reqId = $payload['id'] ?? ($rtn['id'] ?? $conversationRef);

        self::_logResponseUsage($payload, $reqId);

        return ($withReqID == 0) ? $reply : [$reply, $reqId];
    }

    private static function _buildResponsePayload(array $messages, array $opts, int $max, $format)
    {
        $model = ($opts['isMini']) ? self::MINI_MODE : self::DEFAULT_MODE;

        $payload = [
            'model' => $model,
            'input' => self::_messagesToResponseInput($messages),
            'temperature' => 0.9,
            'max_output_tokens' => $max,
            'top_p' => 1,
            'frequency_penalty' => 0.0,
            'presence_penalty' => 0.6,
            'metadata' => [
                'user' => 'Junior Editor',
            ],
            // 'stop' => ['Human: ', 'AI: '],
        ];

        if (!empty($format)) {
            if (is_string($format)) {
                $format = jsonDecode($format);
            }

            if (!empty($format)) {
                $payload['response_format'] = $format;
            }
        }

        return $payload;
    }

    private static function _messagesToResponseInput(array $messages): array
    {
        $input = [];

        foreach ($messages as $message) {
            $content = trim((string) ($message['content'] ?? ''));

            if ('' === $content) {
                continue;
            }

            $input[] = [
                'role' => $message['role'] ?? 'user',
                'content' => [
                    [
                        'type' => 'input_text',
                        'text' => $content,
                    ],
                ],
            ];
        }

        return $input;
    }

    private static function _extractResponseText($output)
    {
        if (!is_array($output)) {
            return '';
        }

        $buffer = '';

        foreach ($output as $block) {
            if (empty($block['content']) || !is_array($block['content'])) {
                continue;
            }

            foreach ($block['content'] as $content) {
                if (isset($content['text'])) {
                    $buffer .= $content['text'];
                    continue;
                }

                if (isset($content['value']) && is_string($content['value'])) {
                    $buffer .= $content['value'];
                    continue;
                }

                if (!empty($content['content']) && is_array($content['content'])) {
                    foreach ($content['content'] as $nested) {
                        if (isset($nested['text'])) {
                            $buffer .= $nested['text'];
                        }
                    }
                }
            }
        }

        return trim($buffer);
    }

    private static function _logResponseUsage(array $payload, string $reqId = '')
    {
        if (f3()->get('openai.calculator') != 1) {
            return;
        }

        if (empty($payload['usage'])) {
            return;
        }

        $promptTokens     = intval($payload['usage']['input_tokens'] ?? $payload['usage']['prompt_tokens'] ?? 0);
        $completionTokens = intval($payload['usage']['output_tokens'] ?? $payload['usage']['completion_tokens'] ?? 0);
        $totalTokens      = intval($payload['usage']['total_tokens'] ?? ($promptTokens + $completionTokens));

        if (0 === ($promptTokens + $completionTokens)) {
            return;
        }

        $model = $payload['model'] ?? self::DEFAULT_MODE;
        $entryId = $reqId ?: ($payload['id'] ?? ('response-' . secure_random_string(12)));

        [$in_cost, $out_cost] = self::calculator($model, $promptTokens, $completionTokens);

        fLlmtoken::add([
            'id'                => $entryId,
            'model'             => $model,
            'prompt_tokens'     => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens'      => $totalTokens,
            'prompt_cost'       => $in_cost,
            'completion_cost'   => $out_cost,
            'total_cost'        => ($in_cost + $out_cost),
        ]);
    }

    private static function _ensureConversation(array $opts, array &$payload)
    {
        $convKey = trim((string) ($opts['convId'] ?? ''));

        if ($convKey === '') {
            if (isset($payload['conversation'])) {
                unset($payload['conversation']);
            }

            return '';
        }

        $conversationId = self::_conversationThreadIdForUser($convKey);

        if ($conversationId === '') {
            $conversationId = self::_createConversationForUser($convKey, $payload['model'] ?? self::DEFAULT_MODE);
        }

        if ($conversationId === '') {
            if (isset($payload['conversation'])) {
                unset($payload['conversation']);
            }

            return '';
        }

        $payload['conversation'] = $conversationId;

        return $conversationId;
    }

    private static function _conversationThreadIdForUser($convId)
    {
        if ('' === trim((string) $convId)) {
            return '';
        }

        $row = fConversation::one($convId, 'conv_id', [], false);

        return $row['thread_id'] ?? '';
    }

    private static function _createConversationForUser($convId, $model)
    {
        $convId = trim((string) $convId);

        if ($convId === '') {
            return '';
        }

        $conversationPayload = [
            'model' => (!empty($model)) ? $model : self::DEFAULT_MODE,
            'metadata' => [
                'user' => $convId,
            ],
        ];

        $conversation = self::_call('answer', $conversationPayload, 'POST', '/v1/conversations');

        if (is_array($conversation) && !empty($conversation['id'])) {
            self::_persistConversationThread($convId, $conversation['id'], $model);

            return $conversation['id'];
        }

        if (is_array($conversation) && isset($conversation['error'])) {
            self::_log(sprintf('conversation create error (%s): %s', $convId, jsonEncode($conversation['error'])));
        }

        return '';
    }

    private static function _persistConversationThread($convId, $threadId, $model)
    {
        $convId   = trim((string) $convId);
        $threadId = trim((string) $threadId);

        if ($convId === '' || $threadId === '') {
            return;
        }

        fConversation::saveThread($convId, $threadId, $model);
    }

    private static function _ensureConversationResult($result)
    {
        if (is_array($result)) {
            $reply = $result[0] ?? '';
            $conversationRef = $result[1] ?? '';

            return [$reply, $conversationRef];
        }

        return [$result, ''];
    }

    private static function calculator($model, $prompt_tokens, $completion_tokens)
    {
        $in_cost = 0;
        $out_cost = 0;

        switch ($model) {
            case self::MINI_MODE:
                $in_cost  = intval($prompt_tokens) * self::MINI_IN;
                $out_cost = intval($completion_tokens) * self::MINI_OUT;
                break;
            case self::SPEECH_MODE:
                $in_cost  = intval($prompt_tokens) * self::SPEECH_IN;
                $out_cost = self::SPEECH_OUT;
                break;
            case self::DRAW_MODE:
                $in_cost  = 0;
                $out_cost = self::DRAW_OUT;
                break;
            default:
                // self::DEFAULT_MODE
                $in_cost  = intval($prompt_tokens) * self::DEFAULT_IN;
                $out_cost = intval($completion_tokens) * self::DEFAULT_OUT;
                break;
        }

        return [$in_cost, $out_cost];
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
