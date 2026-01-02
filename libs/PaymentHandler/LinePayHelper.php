<?php

namespace F3CMS\PaymentHandler;

use F3CMS\Contracts\PaymentHandlerInterface;

/**
 * LinePayHelper implements the shared payment handler contract for Line Pay.
 */
class LinePayHelper implements PaymentHandlerInterface
{
    private $_api_channel_id = '';
    private $_api_channel_secret = '';
    private $_gateway_uri = 'https://api-pay.line.me';
    private $_commands = [
        'checkout' => [
            'action' => 'v3/payments/request',
            'method' => 'POST',
        ],
        'confirm' => [
            'action' => 'v3/payments/${transactionId}/confirm',
            'method' => 'POST',
        ],
        'refund' => [
            'action' => 'v3/payments/${transactionId}/refund',
            'method' => 'POST',
        ],
    ];
    public static $debug = false;

    public function __construct($channel_id = null, $channel_secret = null)
    {
        if (!empty($channel_id)) {
            $this->_api_channel_id = $channel_id;
        }
        if (!empty($channel_secret)) {
            $this->_api_channel_secret = $channel_secret;
        }

        if ('production' != f3()->get('APP_ENV')) {
            $this->_gateway_uri = 'https://sandbox-api-pay.line.me';
        }

        $this->setDebugMode(f3()->get('DEBUG') >= 1);
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

        [$uri, $data] = $this->getURL($command, $requestData, 'array');
        $httpMethod = $this->_commands[$command]['method'] ?? $method;

        return $this->_sendRequest($uri, $data, $httpMethod);
    }

    public function getURL($command, array $requestData, $return = 'string')
    {
        if (!$this->supportsCommand($command)) {
            return false;
        }

        $action = $this->_commands[$command]['action'];
        $uri = sprintf('%s/%s', $this->_gateway_uri, $action);

        if ('string' === $return) {
            return sprintf('%s?%s', $uri, http_build_query($requestData));
        }

        return [$uri, $requestData];
    }

    private static function _generateNonce()
    {
        return date('c') . uniqid('-');
    }

    private function _sendRequest($uri, $request_data, $method = 'POST')
    {
        $response = null;

        if (false !== strpos($uri, '$')) {
            foreach ($request_data as $idx => $val) {
                $search = '${' . $idx . '}';
                $new = str_replace($search, $val, $uri);
                if ($new != $uri) {
                    $uri = $new;
                    unset($request_data[$idx]);
                }
            }
        }

        $nonce = self::_generateNonce();
        $signature = self::_signKey(
            $this->_api_channel_secret,
            str_replace($this->_gateway_uri, '', $uri),
            ('POST' === $method ? json_encode($request_data) : ''),
            $nonce
        );

        $ch = curl_init();

        $options = [
            CURLOPT_URL            => $uri,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json; charset=UTF-8',
                'X-LINE-ChannelId: ' . $this->_api_channel_id,
                'X-LINE-Authorization: ' . $signature,
                'X-LINE-Authorization-Nonce: ' . $nonce,
            ],
        ];

        if ('POST' === $method) {
            $options[CURLOPT_POSTFIELDS] = json_encode($request_data);
        }

        self::_log(sprintf('呼叫: %s', jsonEncode($options)));

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        self::_log(sprintf('回覆: %s', $response));

        if (200 === $httpCode) {
            return json_decode($response, true);
        }

        return false;
    }

    private static function _signKey($clientKey, $path, $query, $nonce)
    {
        $msg = $clientKey . $path . $query . $nonce;
        return base64_encode(hash_hmac('sha256', $msg, $clientKey, true));
    }

    private static function _log($str)
    {
        if (!self::$debug) {
            return;
        }

        $logger = new \Log('line_pay.log');
        $logger->write((is_string($str)) ? $str : jsonEncode($str));
    }
}
