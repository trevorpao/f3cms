<?php

namespace F3CMS\OauthHandler;

use F3CMS\Contracts\OauthHandlerInterface;
use F3CMS\Helper;
use F3CMS\Feed; 

/**
 * LineOauthHandler 封裝與 LINE Login OAuth 2.0 的互動流程，
 * 包含產生授權網址、交換 access token 以及驗證 ID token。
 * 
 * https://developers.line.biz/en/docs/line-login/integrate-line-login/
 */
class LineOauthHandler extends Helper implements OauthHandlerInterface
{
    /**
     * Gateway URL 樣板，供動態替換 endpoint 與版本使用。
     *
     * @var string
     */
    protected $gateway_url = '';

    /**
     * 用來記錄最後一次請求的參數資料。
     *
     * @var array
     */
    protected $state = [];

    /**
     * LINE Login channel 的 client id。
     *
     * @var string
     */
    private $_client_id = '';

    /**
     * LINE Login channel 的 client secret。
     *
     * @var string
     */
    private $_client_secret = '';

    /**
     * Basic Authorization header 會用到的 base64 token。
     *
     * @var string
     */
    private $_api_token = '';

    /**
     * LINE OAuth API 的路徑版本。
     *
     * @var string
     */
    private $_api_version = 'oauth2/v2.1';

    /**
     * LINE OAuth 完成後回傳的 callback route。
     *
     * @var string
     */
    private $_callback_url = '/auth/line/callback';

    /**
     * 定義所有可用指令及其 endpoint、HTTP 方法與額外參數。
     *
     * @var array
     */
    private $_commands = [
        'auth' => [
            'action'       => 'authorize',
            'method'       => 'GET',
            'return'       => 4,
            'endpoint'     => 'access',
            'extra_params' => [
                'response_type' => 'code',
                'scope'         => 'profile openid email',
            ],
        ],
        'token' => [
            'action'       => 'token',
            'method'       => 'POST',
            'return'       => 1,
            'endpoint'     => 'api',
            'extra_params' => [
                'grant_type' => 'authorization_code',
            ],
        ],
        'verify' => [
            'action'   => 'verify',
            'method'   => 'POST',
            'return'   => 1,
            'endpoint' => 'api',
        ],
    ];

    /**
     * 建構子：載入設定、組成 gateway URL 並建立 Basic token。
     */
    public function __construct()
    {
        parent::__construct();

        $this->gateway_url = 'https://{$_api_endpoint}.line.me/{$_api_version}';

        $this->_client_id     = f3()->get('line_client_id');
        $this->_client_secret = f3()->get('line_client_secret');

        $this->_api_token = base64_encode($this->_client_id . ':' . $this->_client_secret);
    }

    /**
     * call - 依指令組出 URL 及參數後發送 HTTP 請求。
     *
     * @param string $command 指令名稱（auth/token/verify）。
     * @param array  $request_data 額外參數。
     * @return array|false
     */
    public function call(string $command, array $request_data = [])
    {
        if (!isset($this->_commands[$command])) {
            return false;
        }

        [$uri, $data] = $this->getURL($command, $request_data);

        return $this->_sendRequest($uri, $data, $this->_commands[$command]['method']);
    }

    /**
     * getURL - 依指令組成完整 API URL 與序列化參數。
     *
     * @param string $command 指令名稱。
     * @param array  $request_data 額外參數。
     * @return array|false
     */
    public function getURL(string $command, array $request_data = [])
    {
        if (!isset($this->_commands[$command])) {
            return false;
        }

        $action = $this->_commands[$command]['action'] ?? null;
        if (empty($action)) {
            return false;
        }

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
     * _serialize - 將參數整併並依設定輸出成不同格式。
     *
     * @param string $command 指令名稱。
     * @param array  $request_data 傳入參數。
     * @param int    $returnType 1:query、2:encrypt、3:JSON、4:key-value 字串、5:原陣列。
     * @return string|array
     */
    private function _serialize($command, $request_data, $returnType = 1)
    {
        if (isset($this->_commands[$command]['default'])) {
            $request_data = array_merge($this->_commands[$command]['default'], $request_data);
        }

        if (isset($this->_commands[$command]['extra_params'])) {
            $request_data = array_merge($request_data, $this->_commands[$command]['extra_params']);
        }

        if ('auth' === $command) {
            $request_data['client_id'] = $this->_client_id;
            $request_data['state']     = strtolower(Feed::renderUniqueNo(20));
            $request_data['nonce']     = strtolower(Feed::renderUniqueNo(12));
            $request_data['redirect_uri'] = f3()->get('uri') . $this->_callback_url;
            f3()->set('SESSION.line_state', $request_data['state']);
        }

        if ('token' === $command) {
            $request_data['client_id']     = $this->_client_id;
            $request_data['client_secret'] = $this->_client_secret;
            if (empty($request_data['redirect_uri'])) {
                $request_data['redirect_uri'] = f3()->get('uri') . $this->_callback_url;
            }
        }

        if ('verify' === $command) {
            $request_data['client_id'] = $this->_client_id;
        }

        self::_log(jsonEncode($request_data));
        $this->state = $request_data;

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
     * _encrypt - 以 AES-256-CBC 將資料加密後再 base64 編碼。
     *
     * @param array $data 要加密的資料。
     * @return string
     */
    private function _encrypt($data)
    {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));

        return base64_encode(openssl_encrypt(json_encode($data), 'aes-256-cbc', 'OZVYt3x2JWcJsr1bXTnUPLdBZ-EiPL7q', 0, $iv) . '::' . $iv);
    }

    /**
     * _decrypt - 將 _encrypt 的結果還原為原始字串。
     *
     * @param string $string 加密後的字串。
     * @return string|false
     */
    private function _decrypt($string)
    {
        $string        = base64_decode($string);
        [$string, $iv] = explode('::', $string);

        return openssl_decrypt($string, 'aes-256-cbc', 'OZVYt3x2JWcJsr1bXTnUPLdBZ-EiPL7q', 0, $iv);
    }

    /**
     * _sendRequest - 透過 cURL 送出 HTTP 請求並處理回應。
     *
     * @param string $uri API URL。
     * @param string $postdata 已序列化參數。
     * @param string $method HTTP 方法。
     * @return mixed
     */
    private function _sendRequest($uri, $postdata, $method = 'POST')
    {
        $response = null;

        if (function_exists('curl_version')) {
            $ch = curl_init();

            $options = [
                CURLOPT_HEADER         => true,
                CURLOPT_URL            => $uri,
                CURLOPT_REFERER        => $uri,
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
                CURLOPT_CUSTOMREQUEST  => $method,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT        => 20,
                CURLOPT_USERAGENT      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36 f3cms/1.0',
                CURLOPT_HTTPHEADER     => [
                    'cache-control: no-cache',
                    'Content-type: application/x-www-form-urlencoded',
                ],
            ];

            if (false !== strpos($uri, '$')) {
                $options[CURLOPT_URL] = str_replace(
                    array_map(
                        function ($val) { return '${' . $val . '}'; },
                        array_keys($this->state)
                    ),
                    array_values($this->state),
                    $options[CURLOPT_URL]
                );
            }

            if ('GET' === $method) {
                $options[CURLOPT_URL] .= '?' . $postdata;
            } else {
                $options[CURLOPT_POSTFIELDS] = $postdata;
            }

            curl_setopt_array($ch, $options);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if (false === $response) {
                return $this->_error(sprintf('cURL: %s', curl_error($ch)));
            }

            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $bodyStr    = substr($response, $headerSize);

            curl_close($ch);
        }

        if (!empty($response)) {
            $rtn = json_decode($bodyStr, true);

            if (f3()->get('DEBUG') >= 1) {
                self::_log($bodyStr);
            }

            if (200 == $httpCode) {
                return empty($rtn) ? $bodyStr : $rtn;
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
                        \call_user_func('systemAlert', 'LINE OAuth fail', $bodyStr);
                    } else {
                        self::_log('LINE OAuth fail: ' . $bodyStr);
                    }
                }

                return 500;
            }
        }

        return false;
    }

    /**
     * _error - 統一錯誤訊息格式。
     *
     * @param string $message 錯誤內容。
     * @return string
     */
    private function _error($message)
    {
        return sprintf('[LINE] %s', $message);
    }

    /**
     * _log - 將偵錯資訊寫入 line_auth.log。
     *
     * @param string $str 記錄內容。
     * @return void
     */
    private static function _log($str)
    {
        $logger = new \Log('line_auth.log');
        $logger->write($str);
    }
}