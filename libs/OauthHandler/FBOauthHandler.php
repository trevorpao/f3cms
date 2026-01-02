<?php

namespace F3CMS\OauthHandler;

use F3CMS\Contracts\OauthHandlerInterface;
use F3CMS\Helper;
use F3CMS\Feed; 

/**
 * FBOauthHandler 負責與 Facebook OAuth 進行互動，
 * 包含產生授權網址、交換 access token 以及呼叫 Graph API。
 * 透過 helper 基礎類別共用紀錄、錯誤處理等邏輯。
 */
class FBOauthHandler extends Helper implements OauthHandlerInterface
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
     * Facebook 應用程式的 Client ID。
     * 測試環境預設為 sense-info 帳號的設定。
     *
     * @var string
     */
    private $_client_id = '';
    /**
     * Facebook 應用程式的 Client Secret。
     * 測試環境預設為 sense-info 帳號的設定。
     *
     * @var string
     */
    private $_client_secret = '';

    /**
     * 組成 Basic Auth header 使用的 base64 token。
     *
     * @var string
     */
    private $_api_token = '';

    /**
     * 要使用的 Graph API 版本號。
     *
     * @var string
     */
    private $_api_version = 'v17.0';

    /**
     * OAuth 完成後 Facebook 重新導向的 callback URL。
     *
     * @var string
     */
    private $_callback_url = '/auth/facebook/int_callback';

    /**
     * 已定義的 API 指令集合，包含動作、HTTP 方法與額外參數。
     *
     * @var array
     */
    private $_commands = [
        'auth' => [
            'action'       => 'dialog/oauth',
            'method'       => 'GET',
            'return'       => '4',
            'endpoint'     => 'www',
            'extra_params' => [
                'scope'         => 'email',
                'response_type' => 'code',
            ],
        ],
        'token' => [
            'action'       => 'oauth/access_token',
            'method'       => 'GET',
            'return'       => '4',
            'endpoint'     => 'graph',
        ],
        'userinfo' => [
            'action'       => 'me',
            'method'       => 'GET',
            'return'       => '4',
            'endpoint'     => 'graph',
            'extra_params' => [
                'fields' => 'id,name,email',
            ],
        ],
    ];

    /**
     * 建構子會載入 Facebook 設定、組成 gateway 基底網址，
     * 並預先計算 Basic 認證所需的 token。
     */
    public function __construct()
    {
        parent::__construct();

        $this->gateway_url = 'https://{$_api_endpoint}.facebook.com/{$_api_version}';

        $this->_client_id     = f3()->get('facebook.client_id');
        $this->_client_secret = f3()->get('facebook.client_secret');

        $this->_api_token = base64_encode($this->_client_id . ':' . $this->_client_secret);
    }

    /**
     * call - 根據指令設定組成 URL 與參數後呼叫 API。
     *
     * @param string $command 指令代號，例如 auth、token。
     * @param array  $request_data 需要帶入的額外參數。
     * @return array|false 回傳 API 結果，失敗時回傳 false。
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
     * getURL - 組合完整的 API URL 與序列化後的參數。
     *
     * @param string $command 指令代號。
     * @param array  $request_data 需要帶入的額外參數。
     * @return array|false [0] 為 URL、[1] 為序列化字串，錯誤時為 false。
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
     * _serialize - 將參數依照指令設定進行整併與序列化。
     *
     * @param string $command 指令代號。
     * @param array  $request_data 使用者傳入的參數。
     * @param int    $returnType 決定輸出格式，1:query、2:加密、3:JSON、4:key-value。
     * @return string 序列化後的字串。
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
            f3()->set('SESSION.facebook_state', $request_data['state']);
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
                foreach ($request_data as $k => $v) {
                    $serialized_data .= sprintf('&%s=%s', $k, $v);
                }

                $rtn = substr($serialized_data, 1);
                break;
            default:
                $rtn = http_build_query($request_data);
                break;
        }

        return $rtn;
    }

    /**
     * _encrypt - 以 AES-256-CBC 將資料加密後再進行 base64。
     *
     * @param array $data 要加密的資料。
     * @return string 加密並編碼的字串。
     */
    private function _encrypt($data)
    {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));

        return base64_encode(openssl_encrypt(json_encode($data), 'aes-256-cbc', 'OZVYt3x2JWcJsr1bXTnUPLdBZ-EiPL7q', 0, $iv) . '::' . $iv);
    }

    /**
     * _decrypt - 將 _encrypt 產生的字串還原為原始資料。
     *
     * @param string $string 加密字串。
     * @return string|false 解密後的內容，失敗回傳 false。
     */
    private function _decrypt($string)
    {
        $string        = base64_decode($string);
        [$string, $iv] = explode('::', $string);

        return openssl_decrypt($string, 'aes-256-cbc', 'OZVYt3x2JWcJsr1bXTnUPLdBZ-EiPL7q', 0, $iv);
    }

    /**
     * _sendRequest - 透過 cURL 發送 HTTP 請求並處理回應。
     *
     * @param string $uri API 完整 URL。
     * @param string $postdata 已序列化的參數 / 查詢字串。
     * @param string $method HTTP 方法，預設 POST。
     * @return mixed 成功回傳陣列或字串，失敗回傳錯誤碼 / false。
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
                        \call_user_func('systemAlert', 'FB fail', $bodyStr);
                    } else {
                        self::_log('FB fail: ' . $bodyStr);
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
     * _error - 統一格式化錯誤訊息。
     *
     * @param string $message 錯誤內容。
     * @return string 格式化後的錯誤字串。
     */
    private function _error($message)
    {
        return sprintf('[FB] %s', $message);
    }

    /**
     * _log - 將偵錯資訊寫入 facebook_auth.log。
     *
     * @param string $str 要寫入的內容。
     * @return void
     */
    private static function _log($str)
    {
        $logger = new \Log('facebook_auth.log');
        $logger->write($str);
    }
}
