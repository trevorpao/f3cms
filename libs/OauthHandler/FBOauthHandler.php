<?php

namespace F3CMS\OauthHandler;

use F3CMS\Feed; 

/**
 * FBOauthHandler 負責與 Facebook OAuth 進行互動，
 * 包含產生授權網址、交換 access token 以及呼叫 Graph API。
 * 透過 helper 基礎類別共用紀錄、錯誤處理等邏輯。
 */
class FBOauthHandler extends AbstractOauthHandler
{
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
    protected $_api_token = '';

    /**
     * 要使用的 Graph API 版本號。
     *
     * @var string
     */
    protected $_api_version = 'v17.0';

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
    protected $_commands = [
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

    protected function augmentRequestData(string $command, array $request_data): array
    {
        if ('auth' === $command) {
            $request_data['client_id'] = $this->_client_id;
            $request_data['nonce']     = strtolower(Feed::renderUniqueNo(12));
            $request_data['state']     = strtolower(Feed::renderUniqueNo(20));
            f3()->set('SESSION.facebook_state', $request_data['state']);
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
        return 'FB';
    }

    protected function getFailureLabel(): string
    {
        return 'FB fail';
    }

    protected function getLogFile(): string
    {
        return 'facebook_auth.log';
    }
}
