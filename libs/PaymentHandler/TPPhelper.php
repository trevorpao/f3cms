<?php

namespace F3CMS\PaymentHandler;

use F3CMS\Contracts\PaymentHandlerInterface;
use F3CMS\Helper;

/**
 * TPPhelper implements the shared payment handler contract for Tappay.
 */
class TPPhelper extends Helper implements PaymentHandlerInterface
{
    private $_api_merchant = '';  
    private $_api_secret = '';
    private $_gateway_uri = 'https://sandbox.tappaysdk.com/tpc';
    private $_commands = [
        'start'    => [
            'action'  => 'payment/pay-by-prime',
            'method'  => 'POST',
            'default' => [
                'merchant_id'         => '',
                'three_domain_secure' => false,
            ],
            'extra_params' => [
                'remember'   => true,
                'result_url' => [],
            ],
        ],
        'take'    => [
            'action'  => 'payment/pay-by-token',
            'method'  => 'POST',
            'default' => [
                'merchant_id' => '',
            ],
            'extra_params' => [
                'currency'   => 'TWD',
                'result_url' => [],
            ],
        ],
        'bind'    => [
            'action'  => 'card/bind',
            'method'  => 'POST',
            'default' => [
                'merchant_id' => '',
            ],
            'extra_params' => [
                'currency'            => 'TWD',
                'three_domain_secure' => true,
                'result_url'          => [],
            ],
        ],
        'get'    => [
            'action'  => 'transaction/query',
            'method'  => 'POST',
            'default' => [
                'merchant_id'       => '',
                'transaction/query' => 1,
                'page'              => 0,
            ],
            'extra_params' => [
                'order_by' => [
                    'attribute'     => 'time',
                    'is_descending' => true,
                ],
            ],
        ],
    ];
    public static $debug = false;

    public function __construct()
    {
        parent::__construct();

        $this->_api_merchant = f3()->get('tappay.api_merchant');
        $this->_api_secret   = f3()->get('tappay.api_secret');
        $this->_api_bank     = f3()->get('tappay.api_bank');

        $this->setDebugMode(f3()->get('DEBUG') >= 1);

        if ('production' == f3()->get('APP_ENV')) {
            $this->_gateway_uri = 'https://prod.tappaysdk.com/tpc';
        }
    }

    public function supportsCommand($command)
    {
        return isset($this->_commands[$command]);
    }

    public function setDebugMode($enabled)
    {
        self::$debug = (bool)$enabled;
    }

    public function isDebugModeEnabled()
    {
        return (bool)self::$debug;
    }

    public function call($command, array $requestData = [], $method = 'POST')
    {
        if (!$this->supportsCommand($command)) {
            return false;
        }

        $this->cmd   = $command;
        $this->state = $requestData;

        $result = $this->getURL($this->cmd, $requestData, 'array');
        if (false === $result) {
            return false;
        }

        [$uri, $data] = $result;

        $this->_log(sprintf('uri: %s', $uri));

        $httpMethod = $this->_commands[$this->cmd]['method'] ?? $method;
        $this->response = $this->_sendRequest($uri, $data, $httpMethod);

        if ('production' != f3()->get('APP_ENV')) {
            fYell::insert('Tappay', $this->cmd,
                jsonEncode($this->state),
                jsonEncode($this->response)
            );
        } else {
            fYell::insert('Tappay', $this->cmd,
                '',
                jsonEncode($this->response)
            );
        }

        return $this->response;
    }

    public function getURL($command, array $requestData, $return = 'string')
    {
        if (!$this->supportsCommand($command)) {
            return false;
        }

        $this->cmd = $command;
        $action    = 'payment/pay-by-prime';

        if (isset($this->_commands[$this->cmd]['action'])) {
            $action = $this->_commands[$this->cmd]['action'];
        }

        if (isset($this->_commands[$this->cmd]['uri'])) {
            $uri = $this->_commands[$this->cmd]['uri'];
        } else {
            $uri = sprintf('%s/%s', $this->_gateway_uri, $action);
        }

        $data = $this->_serialize($requestData);

        if ('string' === $return) {
            return $uri;
        }

        return [$uri, $data];
    }

    private function _sendRequest($uri, $postdata, $method = 'POST')
    {
        $response = null;

        $ch = curl_init();

        $options = [
            CURLOPT_URL            => $uri,
            CURLOPT_REFERER        => $uri,
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36 F3CMS/0.8',
            CURLOPT_HTTPHEADER     => [
                'cache-control: no-cache',
                'Content-Type: application/json; charset=UTF-8',
                'x-api-key: ' . $this->_api_secret,
            ],
        ];

        $options[CURLOPT_POSTFIELDS] = $postdata;

        self::_log('POSTFIELDS:' . $options[CURLOPT_POSTFIELDS]);

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (false === $response) {
            return $this->_error(sprintf('cURL: %s', curl_error($ch)));
        }

        if (!empty($response)) {
            self::_log('response:' . $response);

            $rtn = jsonDecode($response);

            if (200 == $httpCode) {
                if (empty($rtn)) {
                    return $response;
                } else {
                    return $rtn;
                }
            } elseif (401 == $httpCode) {
                return 401;
            } elseif (422 == $httpCode) {
                return 422;
            } else {
                systemAlert('Tappay fail', $response);

                return 500;
            }
        }

        return false;
    }

    private function _serialize($request_data)
    {
        if (isset($this->_commands[$this->cmd]['default'])) {
            $request_data = array_merge($this->_commands[$this->cmd]['default'], $request_data);
        }

        if (isset($this->_commands[$this->cmd]['extra_params'])) {
            $request_data = array_merge($request_data, $this->_commands[$this->cmd]['extra_params']);
        }

        if (!isset($request_data['merchant_id'])) {
            $request_data['merchant_id'] = $this->_api_bank;
        }

        if (isset($request_data['result_url'])) {
            $request_data['result_url'] = $this->_get_result_url();
        }

        $request_data['partner_key'] = $this->_api_secret;

        return json_encode($request_data);
    }

    private function _get_result_url()
    {
        return [
            'frontend_redirect_url' => f3()->get('uri') . '/cashier/done/' . $this->state['cardholder']['member_id'],
            'backend_notify_url'    => f3()->get('uri') . '/cashier/receive/tappay/' . $this->state['cardholder']['member_id'],
            'go_back_url'           => f3()->get('uri') . '/cashier/retry/' . $this->state['cardholder']['member_id'],
        ];
    }

    private static function _error($message)
    {
        return sprintf('[TPP] %s', $message);
    }

    private static function _log($str)
    {
        if (!self::$debug) {
            return;
        }
        
        $logger = new \Log('tpp.log');
        $logger->write($str);
    }
}
