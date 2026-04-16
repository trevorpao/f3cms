<?php

namespace F3CMS\OauthHandler;

use F3CMS\Feed; 

/**
 * LineOauthHandler 封裝與 LINE Login OAuth 2.0 的互動流程，
 * 包含產生授權網址、交換 access token 以及驗證 ID token。
 * 
 * https://developers.line.biz/en/docs/line-login/integrate-line-login/
 */
class LineOauthHandler extends AbstractOauthHandler
{
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
    protected $_api_version = 'oauth2/v2.1';

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
    protected $_commands = [
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

    protected function augmentRequestData(string $command, array $request_data): array
    {
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

        return $request_data;
    }

    protected function getErrorPrefix(): string
    {
        return 'LINE';
    }

    protected function getFailureLabel(): string
    {
        return 'LINE OAuth fail';
    }

    protected function getLogFile(): string
    {
        return 'line_auth.log';
    }
}