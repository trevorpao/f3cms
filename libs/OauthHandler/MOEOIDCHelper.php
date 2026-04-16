<?php

namespace F3CMS\OauthHandler;

/**
 * 教育雲 OIDC provider 實作。
 *
 * 這個類只保留 MOE 特有設定與 command 擴充：
 * - 覆寫 auth scope，加入 eduinfo
 * - 額外註冊 eduinfo command
 */
class MOEOIDCHelper extends AbstractOIDCFlowHelper
{
    /**
     * 先載入 generic OIDC flow，再補上 MOE provider 專屬 command。
     */
    public function __construct()
    {
        parent::__construct();

        $this->gateway_url = 'https://oidc.sso.edu.tw';
        $this->callbackUrl = '/auth/oidc/oauth2callback';
        $this->clientId = '8732d62c6d9c32c22edbb02d2e09432c';
        $this->clientSecret = 'eac563c9c9f7720dc57bb3b076e62a399da8a197fd777812f4fa402928a3bc82';

        if ('develop' != f3()->get('APP_ENV')) {
            $this->gateway_url = f3()->get('oidc.uri');
            $this->clientId = f3()->get('oidc.client_id');
            $this->clientSecret = f3()->get('oidc.client_secret');
        }

        $this->apiToken = base64_encode($this->clientId . ':' . $this->clientSecret);

        // MOE 在 auth scope 額外要求 eduinfo，因此以同名 command 覆寫預設 auth。
        $this->registerCommand('auth', [
            'action' => 'oidc/v1/azp',
            'method' => 'GET',
            'extra_params' => [
                'scope' => 'openid2 email openid profile eduinfo',
                'response_type' => 'code',
            ],
        ]);

        // eduinfo 不是 generic OIDC 能力，只在 MOE provider 註冊。
        $this->registerCommand('eduinfo', [
            'action' => 'moeresource/api/v1/oidc/eduinfo',
            'method' => 'GET',
            'return' => 6,
            'auth' => 'bearer',
            'skip_redirect' => true,
        ]);
    }
}