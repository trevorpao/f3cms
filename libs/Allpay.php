<?php

namespace F3CMS;

class Allpay extends Helper
{
    // 測試環境
    /**
     * @var string
     */
    public $gateway_url = 'http://payment-stage.allpay.com.tw'; //交易網址(測試環境)
    /**
     * @var string
     */
    public $merchant_id = '2000132'; //商店代號
    /**
     * @var string
     */
    public $hash_key = '5294y06JbISpM5x9'; //HashKey
    /**
     * @var string
     */
    public $hash_iv = 'v77hoKGq4kWxNNIS'; //HashIV

    // var $gateway_url = "https://payment.allpay.com.tw"; //交易網址(正式環境)
    // var $merchant_id = ""; //商店代號
    // var $hash_key    = ""; //HashKey
    // var $hash_iv     = ""; //HashIV

    /**
     * @var array
     */
    private $_commands = [
        'Checkout' => [
            'action'       => '/Cashier/AioCheckOut',
            'extra_params' => [
                'PaymentType' => 'aio',
            ],
        ],
    ];

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @param $rtn_data
     */
    public function check($rtn_data)
    {
        $chkCode1 = $rtn_data['CheckMacValue'];
        unset($rtn_data['CheckMacValue']);
        ksort($rtn_data, SORT_NATURAL | SORT_FLAG_CASE);
        $chkCode2 = $this->_getMacValue($rtn_data);

        return ($chkCode2 == $chkCode1) ? 1 : 0;
    }

    /**
     * call - Send request to Mobile Payment API.
     *
     * @param string $command      request type
     * @param array  $request_data request data array
     *
     * @return array
     */
    public function call($command, $request_data, $mode = 'curl')
    {
        if (!isset($this->_commands[$command])) {
            return false;
        }

        $request_data['MerchantID'] = $this->merchant_id;

        [$uri, $data] = $this->getURL($command, $request_data, 'array');
        // Send the HTTP request.

        return $this->_sendRequest($uri, $data, $mode);
    }

    /**
     * getURL - Return API URL
     *
     * @param string $command      request type
     * @param array  $request_data request data array
     * @param string $return       array or string
     *
     * @return mixed
     */
    public function getURL($command, $request_data, $return = 'string')
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

        $data = $this->_serialize($command, $request_data, $return);

        return ('string' == $return) ? sprintf('%s?%s', $uri, $data) : [
            $uri,
            $data,
        ];
    }

    /**
     * replace all letters
     *
     * @param string $value - target string
     *
     * @return string - final string
     */
    private function _replaceChar($value)
    {
        $search_list = [
            '%2d',
            '%5f',
            '%2e',
            '%21',
            '%2a',
            '%28',
            '%29',
        ];
        $replace_list = [
            '-',
            '_',
            '.',
            '!',
            '*',
            '(',
            ')',
        ];
        $value = str_replace($search_list, $replace_list, $value);

        return $value;
    }

    /**
     * get check code
     *
     * @param string $hash_key - hash seed
     * @param string $hash_iv  - hash seed part2
     * @param array  $params   - all params
     *
     * @return string - check code
     */
    private function _getMacValue($params)
    {
        $encode_str = 'HashKey=' . $this->hash_key;
        foreach ($params as $key => $value) {
            $encode_str .= sprintf('&%s=%s', $key, $value);
        }
        $encode_str .= '&HashIV=' . $this->hash_iv;
        $encode_str = strtolower(urlencode($encode_str));
        $encode_str = $this->_replaceChar($encode_str);

        return strtoupper(md5($encode_str));
    }

    /**
     * _serialize - Serialize data.
     *
     * @param string $command      request type
     * @param array  $request_data request data array
     *
     * @return string
     */
    private function _serialize($command, $request_data, $return)
    {
        $hash_data    = '';
        $extra_params = $this->_commands[$command]['extra_params'];
        $request_data = array_merge($request_data, $extra_params);

        ksort($request_data, SORT_NATURAL | SORT_FLAG_CASE); // 調整ksort排序規則--依自然排序法(大小寫不敏感)
        $request_data['CheckMacValue'] = $this->_getMacValue($request_data);
        $serialized_data               = '';

        foreach ($request_data as $key => $value) {
            $serialized_data .= sprintf('&%s=%s', $key, $value);
        }

        return ('string' == $return) ? substr($serialized_data, 1) : $request_data;
    }

    /**
     * _sendRequest - Make API request.
     *
     * @param string $uri          API URI
     * @param array  $request_data request data in serialized form
     *
     * @return mixed
     */
    private function _sendRequest($uri, $request_data, $mode = 'curl')
    {
        if ('curl' == $mode) {
            // Try cURL.
            if (function_exists('curl_version')) {
                $ch = curl_init($uri);

                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $request_data);

                $response = curl_exec($ch);

                if (false === $response) {
                    return $this->_error(sprintf('cURL: %s', curl_error($ch)));
                }

                curl_close($ch);

                return $response;
            }
            // Try file_get_contents.

            if (ini_get('allow_url_fopen')) {
                $options = [
                    'http' => [
                        'method'  => 'POST',
                        'header'  => 'Content-Type: application/x-www-form-urlencoded',
                        'content' => $request_data,
                    ],
                ];

                $context  = stream_context_create($options);
                $response = file_get_contents($_uri, false, $context);

                return $response;
            }
            // No way to send HTTP request.

            return false;
        } else {
            $html_code = '<form method="post" id="allpay_form" action="' . $uri . '">';

            foreach ($request_data as $key => $val) {
                $html_code .= "<input type='hidden' name='" . $key . "' value='" . $val . "'><BR>";
            }
            // $html_code.= "<input  class='button04' type='submit' value='送出'>";
            $html_code .= '</form> <script> setTimeout(function() {$("#allpay_form")[0].submit(); }, 2000); </script> ';

            return $html_code;
        }
    }
}
