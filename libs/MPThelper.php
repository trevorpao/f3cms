<?php
/**
 * Class MPThelper is an abstraction of communication with the MediaPartner API.
 *
 * Usage: $api = new MPThelper();
 * Methods:
 *     call ($command, $request_data)
 */

namespace F3CMS;

/**
 * MPThelper
 */
class MPThelper
{
    /**
     * @var string
     */
    private $_api_merchant = '';
    /**
     * @var string
     */
    private $_api_secret = '';
    /**
     * @var string
     */
    private $_gateway_uri = 'https://qa.mediapartner.tw'; // 'https://dev.mediapartner.tw/api'; //
    /**
     * @var array
     */
    private $_commands = [
        'ping' => [
            'action'    => 'webhook/v1/ping',
            'hash_keys' => ['merchant'],
        ],
        'answer' => [
            'action'    => 'webhook/v1/answer',
            'hash_keys' => ['merchant'],
        ],
        'ask' => [
            'action'    => 'webhook/v1/ask',
            'hash_keys' => ['merchant'],
        ],
        'transcoder' => [
            'action'    => 'api/video/transcoder',
            'hash_keys' => ['filename', 'merchant'],
        ],
        'datakey' => [
            'action'    => 'api/video/key',
            'hash_keys' => ['slug', 'merchant'],
        ],
        'm3u8' => [
            'action'    => 'api/video/m3u8',
            'hash_keys' => ['slug', 'merchant'],
        ],
    ];

    /**
     * __construct
     *
     * @param string $api_merchant video name
     * @param string $api_secret   API secret
     *
     * @return void
     */
    public function __construct($api_merchant = null, $api_secret = null)
    {
        if (!empty($api_merchant)) {
            $this->_api_merchant = $api_merchant;
        }
        if (!empty($api_secret)) {
            $this->_api_secret = $api_secret;
        }
    }

    /**
     * call - Send request to Mobile Payment API.
     *
     * @param string $command      request type
     * @param array  $request_data request data array
     *
     * @return array
     */
    public function call($command, $request_data)
    {
        if (!isset($this->_commands[$command])) {
            return false;
        }

        [$uri, $data] = $this->getURL($command, $request_data, 'array');

        // Send the HTTP request.
        return $this->_sendRequest($uri, $data);
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

        $action = 'video/path';

        // Append the action.
        if (isset($this->_commands[$command]['action'])) {
            $action = $this->_commands[$command]['action'];
        }

        // Append the uri.
        if (isset($this->_commands[$command]['uri'])) {
            $uri = $this->_commands[$command]['uri'];
        } else {
            $uri = sprintf('%s/%s', $this->_gateway_uri, $action);
        }

        $data = $this->_serialize($command, $request_data);

        // Obtain API URL
        return ('string' == $return) ? sprintf('%s?%s', $uri, $data) : [$uri, $data];
    }

    /**
     * _sendRequest - Make API request.
     *
     * @param string $uri          API URI
     * @param array  $request_data request data in serialized form
     *
     * @return array
     */
    private function _sendRequest($uri, $request_data)
    {
        $response = null;

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL            => $uri,
            CURLOPT_REFERER        => $uri,
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false, // only for test
            CURLOPT_SSL_VERIFYHOST => false, // only for test
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36 MPThelper/0.8',
            CURLOPT_POSTFIELDS     => $request_data,
        ]);

        self::_log(sprintf('uri: %s', $uri));

        $response = curl_exec($ch);
        if (false === $response) {
            return $this->_error(sprintf('cURL: %s', curl_error($ch)));
        }

        curl_close($ch);

        if (!empty($response)) {
            $rtn = jsonDecode($response);
            self::_log($rtn);

            if (empty($rtn)) {
                return $response;
            } else {
                return $rtn;
            }
        } else {
            // No way to send HTTP request.
            return false;
        }
    }

    /**
     * _serialize - Serialize data.
     *
     * @param string $command      request type
     * @param array  $request_data request data array
     *
     * @return string
     */
    private function _serialize($command = 'ping', $request_data = [])
    {
        if (!isset($this->_commands[$command])) {
            return false;
        }

        $hash_data = '';
        $hash_keys = $this->_commands[$command]['hash_keys'];

        $request_data['merchant'] = $this->_api_merchant;

        // Create the string to be hashed.
        foreach ($hash_keys as $k) {
            $hash_data .= ($request_data[$k] ?? '') . '-';
        }

        // Create hash key.
        $request_data['secret'] = hash_hmac('sha256', $hash_data . round(time() / 300), $this->_api_secret);

        self::_log($request_data);

        // Serialize data.
        $serialized_data = http_build_query($request_data, '', '&');

        self::_log(sprintf('serialized_data: %s', $serialized_data));

        return $serialized_data;
    }

    /**
     * _error - Composite error message.
     *
     * @param string $message error message
     *
     * @return string
     */
    private function _error($message)
    {
        return sprintf('[MediaPartner] %s', $message);
    }

    /**
     * @param $str
     */
    private static function _log($str)
    {
        $logger = new \Log('mediapartner.log');
        $logger->write((is_string($str)) ? $str : jsonEncode($str));
    }
}
