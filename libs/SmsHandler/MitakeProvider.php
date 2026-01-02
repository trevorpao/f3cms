<?php

namespace F3CMS\SmsHandler;

use F3CMS\Contracts\SmsProviderInterface;
use Throwable;

class MitakeProvider implements SmsProviderInterface
{
    private $config;

    public function __construct($username, $password, $domain)
    {
        $this->config = ['username' => $username, 'password' => $password, 'domain' => $domain];
    }

    public function send(string $phone, string $message, array $options = []): array
    {
        $curl = curl_init();

        try {
            $data = http_build_query([
                'username' => $this->config['username'],
                'password' => $this->config['password'],
                'dstaddr'  => $phone,
                'smbody'   => $message,
            ]);

            curl_setopt($curl, CURLOPT_URL, "https://{$this->config['domain']}/b2c/mtk/SmSend?CharsetURL=UTF-8");
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

            $response = curl_exec($curl);
            if ($response === false) {
                throw new \RuntimeException(curl_error($curl));
            }

            return [
                'status'     => 'sent',
                'provider'   => static::class,
                'message_id' => $this->extractMessageId($response),
                'meta'       => ['raw' => $response],
            ];
        } catch (Throwable $e) {
            return [
                'status'   => 'failed',
                'provider' => static::class,
                'error'    => $e->getMessage(),
            ];
        } finally {
            if (is_resource($curl)) {
                curl_close($curl);
            }
        }
    }

    protected function extractMessageId(string $response): ?string
    {
        if (preg_match('/^ID=(.+)$/mi', $response, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }
}