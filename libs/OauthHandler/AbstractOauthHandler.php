<?php

namespace F3CMS\OauthHandler;

use F3CMS\Contracts\OauthHandlerInterface;
use F3CMS\Helper;

abstract class AbstractOauthHandler extends Helper implements OauthHandlerInterface
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
     * @var array
     */
    protected $_commands = [];

    /**
     * @var string
     */
    protected $_api_version = '';

    public function call(string $command, array $request_data = [])
    {
        if (!isset($this->_commands[$command])) {
            return false;
        }

        [$uri, $data] = $this->getURL($command, $request_data);

        return $this->sendRequest($uri, $data, $this->_commands[$command]['method']);
    }

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

        $uri = str_replace(
            ['{$_api_endpoint}', '{$_api_version}'],
            [$this->_commands[$command]['endpoint'] ?? '', $this->_api_version],
            $uri
        );

        $returnType = $this->_commands[$command]['return'] ?? 1;
        $data = $this->serializeCommandData($command, $request_data, $returnType);

        return [$uri, $data];
    }

    protected function serializeCommandData($command, $request_data, $returnType = 1)
    {
        if (isset($this->_commands[$command]['default'])) {
            $request_data = array_merge($this->_commands[$command]['default'], $request_data);
        }

        if (isset($this->_commands[$command]['extra_params'])) {
            $request_data = array_merge($request_data, $this->_commands[$command]['extra_params']);
        }

        $request_data = $this->augmentRequestData($command, $request_data);

        $this->logMessage(jsonEncode($request_data));
        $this->state = $request_data;

        switch ($returnType) {
            case 2:
                return $this->encryptPayload($request_data);
            case 3:
                return json_encode($request_data);
            case 4:
                $serialized_data = '';
                foreach ($request_data as $k => $v) {
                    $serialized_data .= sprintf('&%s=%s', $k, $v);
                }

                return substr($serialized_data, 1);
            case 5:
                return $request_data;
            default:
                return http_build_query($request_data);
        }
    }

    protected function augmentRequestData(string $command, array $request_data): array
    {
        return $request_data;
    }

    protected function encryptPayload($data)
    {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));

        return base64_encode(openssl_encrypt(json_encode($data), 'aes-256-cbc', 'OZVYt3x2JWcJsr1bXTnUPLdBZ-EiPL7q', 0, $iv) . '::' . $iv);
    }

    protected function decryptPayload($string)
    {
        $string        = base64_decode($string);
        [$string, $iv] = explode('::', $string);

        return openssl_decrypt($string, 'aes-256-cbc', 'OZVYt3x2JWcJsr1bXTnUPLdBZ-EiPL7q', 0, $iv);
    }

    protected function sendRequest($uri, $postdata, $method = 'POST')
    {
        $response = null;

        if (function_exists('curl_version')) {
            $ch = curl_init();

            $options = [
                CURLOPT_HEADER         => true,
                CURLOPT_URL            => $uri,
                CURLOPT_REFERER        => $uri,
                CURLOPT_POST           => 'POST' === $method,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
                CURLOPT_CUSTOMREQUEST  => $method,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT        => 20,
                CURLOPT_USERAGENT      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36 f3cms/1.0',
                CURLOPT_HTTPHEADER     => $this->buildHttpHeaders(),
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
                return $this->error(sprintf('cURL: %s', curl_error($ch)));
            }

            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $bodyStr    = substr($response, $headerSize);

            curl_close($ch);
        }

        if (empty($response)) {
            return false;
        }

        $rtn = json_decode($bodyStr, true);

        if (f3()->get('DEBUG') >= 1) {
            $this->logMessage($bodyStr);
        }

        if (200 == $httpCode) {
            return empty($rtn) ? $bodyStr : $rtn;
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
    }

    protected function buildHttpHeaders(): array
    {
        $headers = [
            'cache-control: no-cache',
            'Content-type: application/x-www-form-urlencoded',
        ];

        if ($this->usesBasicAuthHeader() && !empty($this->getBasicAuthToken())) {
            $headers[] = 'authorization: Basic ' . $this->getBasicAuthToken();
        }

        return $headers;
    }

    protected function usesBasicAuthHeader(): bool
    {
        return false;
    }

    protected function getBasicAuthToken(): string
    {
        return '';
    }

    protected function error($message)
    {
        return sprintf('[%s] %s', $this->getErrorPrefix(), $message);
    }

    protected function logMessage($str): void
    {
        $logger = new \Log($this->getLogFile());
        $logger->write($str);
    }

    abstract protected function getErrorPrefix(): string;

    abstract protected function getFailureLabel(): string;

    abstract protected function getLogFile(): string;
}