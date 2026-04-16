<?php

namespace F3CMS\OauthHandler;

use F3CMS\Contracts\OauthHandlerInterface;
use F3CMS\Feed;
use F3CMS\Helper;

/**
 * 提供 OIDC provider 共用的 flow 骨架。
 *
 * command contract 支援的主要欄位：
 * - action: API path
 * - method: HTTP method
 * - uri: 指定完整 URI，若缺省則以 gateway_url/action 組合
 * - default: 預設 request data
 * - extra_params: 附加 request data
 * - return: 序列化型態，6 代表 GET 類型且不送 body/query payload
 * - auth: none|basic|bearer
 * - bearer_param: bearer token 在 request_data 中的鍵名
 * - skip_redirect: true 時不自動補 redirect_uri
 * - content_type: 自訂 Content-type header
 *
 * provider-specific 擴充應透過 registerCommand() 增量註冊或覆寫既有 command，
 * 不應直接複製整個 flow helper。
 */
abstract class AbstractOIDCFlowHelper extends Helper implements OauthHandlerInterface
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
     * @var string
     */
    protected $bearerToken = '';

    /**
     * @var string
     */
    protected $clientId = '';

    /**
     * @var string
     */
    protected $clientSecret = '';

    /**
     * @var string
     */
    protected $apiToken = '';

    /**
     * @var string
     */
    protected $callbackUrl = '';

    /**
     * @var array
     */
    protected $commands = [];

    public function __construct()
    {
        parent::__construct();

        foreach ($this->defaultCommands() as $name => $config) {
            $this->registerCommand($name, $config);
        }
    }

    public function call(string $command, array $request_data = [])
    {
        if (!$this->hasCommand($command)) {
            return false;
        }

        [$uri, $data] = $this->getURL($command, $request_data);

        return $this->sendRequest($command, $uri, $data, $this->getCommand($command)['method']);
    }

    public function getURL(string $command, array $request_data = [])
    {
        if (!$this->hasCommand($command)) {
            return false;
        }

        $commandConfig = $this->getCommand($command);
        $action = $commandConfig['action'] ?? null;
        if (empty($action)) {
            return false;
        }

        if (isset($commandConfig['uri'])) {
            $uri = $commandConfig['uri'];
        } else {
            $uri = sprintf('%s/%s', $this->gateway_url, $action);
        }

        $returnType = $commandConfig['return'] ?? 1;
        $data = $this->serializeCommandData($command, $request_data, $returnType);

        return [$uri, $data];
    }

    public function registerCommand(string $name, array $config): void
    {
        $this->commands[$name] = $config;
    }

    public function hasCommand(string $name): bool
    {
        return isset($this->commands[$name]);
    }

    public function getCommand(string $name): array
    {
        return $this->commands[$name] ?? [];
    }

    public function getCommands(): array
    {
        return $this->commands;
    }

    /**
     * 只放 generic OIDC command。
     * provider 專屬能力應在子類 constructor 以 registerCommand() 加入。
     */
    protected function defaultCommands(): array
    {
        return [
            'auth' => [
                'action' => 'oidc/v1/azp',
                'method' => 'GET',
                'extra_params' => [
                    'scope' => 'openid2 email openid profile',
                    'response_type' => 'code',
                ],
            ],
            'token' => [
                'action' => 'oidc/v1/token',
                'method' => 'POST',
                'auth' => 'basic',
                'extra_params' => [
                    'grant_type' => 'authorization_code',
                ],
            ],
            'userinfo' => [
                'action' => 'oidc/v1/userinfo',
                'method' => 'GET',
                'return' => 6,
                'auth' => 'bearer',
                'skip_redirect' => true,
            ],
        ];
    }

    protected function serializeCommandData(string $command, array $request_data, int $returnType = 1)
    {
        $commandConfig = $this->getCommand($command);

        if (isset($commandConfig['default'])) {
            $request_data = array_merge($commandConfig['default'], $request_data);
        }

        if (isset($commandConfig['extra_params'])) {
            $request_data = array_merge($request_data, $commandConfig['extra_params']);
        }

        $request_data = $this->prepareCommandRequestData($command, $request_data, $commandConfig);

        $this->logMessage(jsonEncode($request_data));
        $this->state = $request_data;

        switch ($returnType) {
            case 2:
                return $this->encryptPayload($request_data);
            case 3:
                return json_encode($request_data);
            case 4:
                $serializedData = '';
                foreach ($request_data as $key => $value) {
                    $serializedData .= sprintf('&%s=%s', $key, $value);
                }

                return substr($serializedData, 1);
            case 6:
                return '';
            default:
                return http_build_query($request_data);
        }
    }

    protected function prepareCommandRequestData(string $command, array $request_data, array $commandConfig): array
    {
        if (($commandConfig['auth'] ?? null) === 'bearer') {
            $tokenKey = $commandConfig['bearer_param'] ?? 'access_token';
            if (isset($request_data[$tokenKey])) {
                $this->bearerToken = $request_data[$tokenKey];
                unset($request_data[$tokenKey]);
            }
        }

        if ('auth' === $command) {
            // auth command 一律由 flow helper 補上 client_id/state/nonce。
            $request_data['client_id'] = $this->clientId;
            $request_data['nonce'] = strtolower(Feed::renderUniqueNo(12));
            $request_data['state'] = strtolower(Feed::renderUniqueNo(20));
            f3()->set('SESSION.oidc_state', $request_data['state']);
        }

        if (empty($commandConfig['skip_redirect']) && !empty($this->callbackUrl)) {
            $request_data['redirect_uri'] = $this->callbackUrl;
        }

        return $request_data;
    }

    protected function encryptPayload($data)
    {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));

        return base64_encode(openssl_encrypt(json_encode($data), 'aes-256-cbc', 'OZVYt3x2JWcJsr1bXTnUPLdBZ-EiPL7q', 0, $iv) . '::' . $iv);
    }

    protected function decryptPayload($string)
    {
        $string = base64_decode($string);
        [$string, $iv] = explode('::', $string);

        return openssl_decrypt($string, 'aes-256-cbc', 'OZVYt3x2JWcJsr1bXTnUPLdBZ-EiPL7q', 0, $iv);
    }

    protected function sendRequest(string $command, string $uri, $postdata, string $method = 'POST')
    {
        $response = null;
        $commandConfig = $this->getCommand($command);

        try {
            if (function_exists('curl_version')) {
                $ch = curl_init();

                $headers = [
                    'cache-control: no-cache',
                ];

                $contentType = $commandConfig['content_type'] ?? 'application/x-www-form-urlencoded';
                if (!empty($contentType)) {
                    $headers[] = 'Content-type: ' . $contentType;
                }

                $authHeader = $this->resolveAuthorizationHeader($commandConfig);
                if ('' !== $authHeader) {
                    $headers[] = $authHeader;
                }

                $options = [
                    CURLOPT_HEADER => true,
                    CURLOPT_URL => $uri,
                    CURLOPT_REFERER => $uri,
                    CURLOPT_POST => 'POST' === $method,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                    CURLOPT_CUSTOMREQUEST => $method,
                    CURLOPT_CONNECTTIMEOUT => 5,
                    CURLOPT_TIMEOUT => 20,
                    CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36 f3cms/1.0',
                    CURLOPT_HTTPHEADER => $headers,
                ];

                if (false !== strpos($uri, '$')) {
                    $options[CURLOPT_URL] = str_replace(
                        array_map(
                            function ($val) {
                                return '${' . $val . '}';
                            },
                            array_keys($this->state)
                        ),
                        array_values($this->state),
                        $options[CURLOPT_URL]
                    );
                }

                if ('GET' === $method && '' !== $postdata) {
                    $options[CURLOPT_URL] .= '?' . $postdata;
                } elseif ('GET' !== $method) {
                    $options[CURLOPT_POSTFIELDS] = $postdata;
                }

                curl_setopt_array($ch, $options);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

                if (false === $response) {
                    return $this->errorMessage(sprintf('cURL: %s', curl_error($ch)));
                }

                $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
                $bodyStr = substr($response, $headerSize);

                curl_close($ch);
            }

            if (empty($response)) {
                return false;
            }

            $decoded = json_decode($bodyStr, true);

            if (f3()->get('DEBUG') >= 1) {
                $this->logMessage($bodyStr);
            }

            if (200 == $httpCode) {
                return empty($decoded) ? $bodyStr : $decoded;
            }

            if (401 == $httpCode) {
                return 401;
            }

            if (422 == $httpCode) {
                return 422;
            }

            if (f3()->get('DEBUG') >= 1) {
                echo $bodyStr;
                exit;
            }

            if (function_exists('systemAlert')) {
                \call_user_func('systemAlert', $this->getFailureLabel(), $bodyStr);
            } else {
                $this->logMessage($this->getFailureLabel() . ': ' . $bodyStr);
            }

            return 500;
        } finally {
            if (($commandConfig['auth'] ?? null) === 'bearer') {
                $this->bearerToken = '';
            }
        }
    }

    protected function resolveAuthorizationHeader(array $commandConfig): string
    {
        if (($commandConfig['auth'] ?? null) === 'bearer' && !empty($this->bearerToken)) {
            return 'authorization: Bearer ' . $this->bearerToken;
        }

        if (($commandConfig['auth'] ?? null) === 'basic' && !empty($this->apiToken)) {
            return 'authorization: Basic ' . $this->apiToken;
        }

        return '';
    }

    protected function errorMessage(string $message): string
    {
        return sprintf('[%s] %s', $this->getErrorPrefix(), $message);
    }

    protected function logMessage(string $message): void
    {
        $logger = new \Log($this->getLogFile());
        $logger->write($message);
    }

    protected function getErrorPrefix(): string
    {
        return 'OIDC';
    }

    protected function getFailureLabel(): string
    {
        return 'OIDC fail';
    }

    protected function getLogFile(): string
    {
        return 'oidc.log';
    }
}