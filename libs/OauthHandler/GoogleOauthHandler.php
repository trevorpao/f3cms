<?php

namespace F3CMS\OauthHandler;

use F3CMS\Feed; 

/**
 * GoogleOauthHandler 負責與 Google OAuth 2.0 互動，
 * 包含產生授權網址、交換 access token 與發送 API 請求。
 * 繼承 Helper 以共用紀錄、錯誤處理等基礎功能。
 */
class GoogleOauthHandler extends AbstractOauthHandler
{
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
    protected $_api_token = '';

    /**
     * Google API 版本（部分端點未使用，保留擴充）。
     *
     * @var string
     */
    protected $_api_version = '';

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
    protected $_commands = [
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

    protected function augmentRequestData(string $command, array $request_data): array
    {
        if ('auth' === $command) {
            $request_data['client_id'] = $this->_client_id;
            $request_data['nonce']     = strtolower(Feed::renderUniqueNo(12));
            $request_data['state']     = strtolower(Feed::renderUniqueNo(20));
            f3()->set('SESSION.google_state', $request_data['state']);
        }

        $request_data['redirect_uri'] = f3()->get('uri') . $this->_callback_url;

        return $request_data;
    }

    protected function usesBasicAuthHeader(): bool
    {
        return true;
    }

    protected function getBasicAuthToken(): string
    {
        return $this->_api_token;
    }

    protected function getErrorPrefix(): string
    {
        return 'Google';
    }

    protected function getFailureLabel(): string
    {
        return 'Google fail';
    }

    protected function getLogFile(): string
    {
        return 'google_auth.log';
    }
}
