<?php

namespace F3CMS\OauthHandler;

use F3CMS\Contracts\OauthHandlerInterface;
use F3CMS\Helper;
use F3CMS\Feed; 

/**
 * GoogleOauthHandler 負責與 Google OAuth 2.0 互動，
 * 包含產生授權網址、交換 access token 與發送 API 請求。
 * 繼承 Helper 以共用紀錄、錯誤處理等基礎功能。
 */
class GoogleOauthHandler extends Helper implements OauthHandlerInterface
{
    /**
     * @var string
     */
    protected $gateway_url = '';

    /**
     * @var array
     */
    protected $state = [];

    /**
     * Google OAuth 應用程式的 Client ID（預設載入測試環境設定）。
     *
     * @var string
     */
    private $_client_id = '';
    /**
     * Google OAuth 應用程式的 Client Secret（預設載入測試環境設定）。
     *
     * @var string
     */
    private $_client_secret = '';

    /**
     * 用於 Basic Authorization 的 base64 token。
     *
     * @var string
     */
    private $_api_token = '';

    /**
     * Google API 版本（部分端點未使用，保留擴充）。
     *
     * @var string
     */
    private $_api_version = '';

    /**
     * Google OAuth 完成後回傳的 callback 路徑。
     *
     * @var string
     */
    private $_callback_url = '/auth/google/oauth2callback';

    /**
     * 定義 OAuth 流程需要的 API 指令與參數設定。
     *
     * @var array
     */
    private $_commands = [
        'auth' => [
            'action'       => 'auth',
            'method'       => 'GET',
            'return'       => '4',
            'endpoint'     => 'accounts.google.com/o/oauth2/v2',
            'extra_params' => [
                'scope'         => 'https://www.googleapis.com/auth/userinfo.profile https://www.googleapis.com/auth/userinfo.email',
                'response_type' => 'code',
            ],
        ],
        'token' => [
            'action'       => 'token',
            'method'       => 'POST',
            'return'       => '1',
            'endpoint'     => 'oauth2.googleapis.com',
            'extra_params' => [
                'grant_type' => 'authorization_code'
            ],
        ],
        'tokeninfo' => [
            'action'   => 'tokeninfo',
            'method'   => 'GET',
            'return'   => '4',
            'endpoint' => 'oauth2.googleapis.com',
        ],
    ];

    /**
     * 建構子：載入設定、初始化 gateway URL，並預先計算 Basic token。
     */
    public function __construct()
    {
        parent::__construct();

        $this->gateway_url = 'https://{$_api_endpoint}';

        $this->_client_id     = f3()->get('google.client_id');
        $this->_client_secret = f3()->get('google.client_secret');

        $this->_api_token = base64_encode($this->_client_id . ':' . $this->_client_secret);
    }

    /**
     * call - 組出指令對應的 URL 與參數後呼叫 API。
     *
     * @param string $command 指令名稱（如 auth、token）。
     * @param array  $request_data 傳入的額外參數。
     * @return array|false 成功回傳 API 結果，失敗回傳 false。
     */
    public function call(string $command, array $request_data = [])
    {
        if (!isset($this->_commands[$command])) {
            return false;
        }

        [$uri, $data] = $this->getURL($command, $request_data);
        // Send the HTTP request.

        return $this->_sendRequest($uri, $data, $this->_commands[$command]['method']);
    }

    /**
     * getURL - 依指令組成完整 URL 及序列化後的參數。
     *
     * @param string $command 指令名稱。
     * @param array  $request_data 傳入的額外參數。
     * @return array|false [0] URL、[1] 參數字串；不存在指令時回傳 false。
     */
    public function getURL(string $command, array $request_data = [])
    {
        if (!isset($this->_commands[$command])) {
            return false;
        }
        // Append the action.

        if (isset($this->_commands[$command]['action'])) {
            $action = $this->_commands[$command]['action'];
        } else {
            return false;
        }
        // Append the uri.

        if (isset($this->_commands[$command]['uri'])) {
            $uri = $this->_commands[$command]['uri'];
        } else {
            $uri = sprintf('%s/%s', $this->gateway_url, $action);
        }

        $uri = str_replace(['{$_api_endpoint}', '{$_api_version}'], [$this->_commands[$command]['endpoint'], $this->_api_version], $uri);

        $data = $this->_serialize($command, $request_data, $this->_commands[$command]['return']);

        return [$uri, $data];
    }

    /**
     * _serialize - 整併必要參數並依需求格式化輸出。
     *
     * @param string $command 指令名稱。
     * @param array  $request_data 使用者傳入參數。
     * @param int    $returnType 輸出格式：1 query、2 加密、3 JSON、4 key-value、5 原陣列。
     * @return string|array 序列化結果。
     */
    private function _serialize($command, $request_data, $returnType = 1)
    {
        $hash_data = '';

        if (isset($this->_commands[$command]['default'])) {
            $request_data = array_merge($this->_commands[$command]['default'], $request_data);
        }

        if (isset($this->_commands[$command]['extra_params'])) {
            $request_data = array_merge($request_data, $this->_commands[$command]['extra_params']);
        }

        if (in_array($command, [
            'auth',
        ])) {
            $request_data['client_id'] = $this->_client_id;
            $request_data['nonce']     = strtolower(Feed::renderUniqueNo(12));
            $request_data['state']     = strtolower(Feed::renderUniqueNo(20));
            f3()->set('SESSION.google_state', $request_data['state']);
        }

        $request_data['redirect_uri'] = f3()->get('uri') . $this->_callback_url;

        self::_log(jsonEncode($request_data));

        $this->state = $request_data;

        // Serialize data.
        switch ($returnType) {
            case 2:
                $rtn = $this->_encrypt($request_data);
                break;
            case 3:
                $rtn = json_encode($request_data);
                break;
            case 4:
                $serialized_data = '';

                foreach ($request_data as $k => $v) {
                    $serialized_data .= sprintf('&%s=%s', $k, $v);
                }

                $rtn = substr($serialized_data, 1);
                break;
            case 5:
                $rtn = $request_data;
                break;
            default:
                $rtn = http_build_query($request_data);
                break;
        }

        return $rtn;
    }

    /**
     * _encrypt - 以 AES-256-CBC 將資料加密並 base64 編碼。
     *
     * @param array $data 要加密的資料。
     * @return string 加密字串。
     */
    private function _encrypt($data)
    {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));

        return base64_encode(openssl_encrypt(json_encode($data), 'aes-256-cbc', 'OZVYt3x2JWcJsr1bXTnUPLdBZ-EiPL7q', 0, $iv) . '::' . $iv);
    }

    /**
     * _decrypt - 將 _encrypt 的輸出解碼還原。
     *
     * @param string $string 加密字串。
     * @return string|false 解密結果。
     */
    private function _decrypt($string)
    {
        $string        = base64_decode($string);
        [$string, $iv] = explode('::', $string);

        return openssl_decrypt($string, 'aes-256-cbc', 'OZVYt3x2JWcJsr1bXTnUPLdBZ-EiPL7q', 0, $iv);
    }

    /**
     * _sendRequest - 透過 cURL 發送 HTTP 請求並解析回應。
     *
     * @param string $uri API URL。
     * @param string $postdata 已序列化參數。
     * @param string $method HTTP 方法，預設 POST。
     * @return mixed 成功回傳資料，錯誤時回傳狀態碼或 false。
     */
    private function _sendRequest($uri, $postdata, $method = 'POST')
    {
        $response = null;

        // Try cURL.
        if (function_exists('curl_version')) {
            $ch = curl_init();

            $options = [
                CURLOPT_HEADER         => true,
                CURLOPT_URL            => $uri,
                CURLOPT_REFERER        => $uri,
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false, // for test
                CURLOPT_SSL_VERIFYHOST => false, // for test
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                CURLOPT_CUSTOMREQUEST  => $method,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT        => 20,
                CURLOPT_USERAGENT      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36 f3cms/1.0',
                CURLOPT_HTTPHEADER     => [
                    'cache-control: no-cache',
                    // 'Content-Type: application/json; charset=UTF-8',
                    'Content-type: application/x-www-form-urlencoded',
                ],
            ];

            if (false !== strpos($uri, '$')) {
                $options[CURLOPT_URL]     = str_replace(
                    array_map(
                        function ($val) { return '${' . $val . '}'; },
                        array_keys($this->state)
                    ),
                    array_values($this->state),
                    $options[CURLOPT_URL]
                );
            }

            if ('GET' == $method) {
                $options[CURLOPT_URL] .= '?' . $postdata;
            } else {
                $options[CURLOPT_POSTFIELDS] = $postdata;
            }

            if (!empty($this->_api_token)) {
                $options[CURLOPT_HTTPHEADER][] = 'authorization: Basic ' . $this->_api_token;
            }

            curl_setopt_array($ch, $options);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if (false === $response) {
                return $this->_error(sprintf('cURL: %s', curl_error($ch)));
            }

            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $headerStr  = substr($response, 0, $headerSize);
            $bodyStr    = substr($response, $headerSize);

            curl_close($ch);
        }

        if (!empty($response)) {
            $rtn = json_decode($bodyStr, true);

            if (f3()->get('DEBUG') >= 1) {
                self::_log($bodyStr);
            }

            if (200 == $httpCode) {
                if (empty($rtn)) {
                    return $bodyStr; // not json
                } else {
                    return $rtn;
                }
            } elseif (401 == $httpCode) {
                return 401;
            } elseif (422 == $httpCode) {
                return 422;
            } else {
                if (f3()->get('DEBUG') >= 1) {
                    echo $bodyStr;
                    exit;
                } else {
                    if (function_exists('systemAlert')) {
                        \call_user_func('systemAlert', 'Google fail', $bodyStr);
                    } else {
                        self::_log('Google fail: ' . $bodyStr);
                    }
                }

                return 500;
            }
        } else {
            // No way to send HTTP request.
            return false;
        }
    }

    /**
     * _error - 格式化錯誤訊息方便追蹤。
     *
     * @param string $message 錯誤內容。
     * @return string 格式化後字串。
     */
    private function _error($message)
    {
        return sprintf('[Google] %s', $message);
    }

    /**
     * _log - 將偵錯資訊寫入 google_auth.log。
     *
     * @param string $str 記錄內容。
     * @return void
     */
    private static function _log($str)
    {
        $logger = new \Log('google_auth.log');
        $logger->write($str);
    }
}
