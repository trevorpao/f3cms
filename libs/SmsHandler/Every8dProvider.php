<?php

namespace F3CMS\SmsHandler;

use F3CMS\Contracts\SmsProviderInterface;
use Throwable;

class Every8dProvider implements SmsProviderInterface
{
    private $uid;
    private $pwd;

    public function __construct($uid, $pwd)
    {
        $this->uid = $uid;
        $this->pwd = $pwd;
    }

    public function send(string $phone, string $message, array $options = []): array
    {
        $curl = curl_init('https://api.e8d.tw/API21/HTTP/SendSMS.ashx');

        try {
            $data = http_build_query([
                'UID'  => $this->uid,
                'PWD'  => $this->pwd,
                'MSG'  => $message,
                'DEST' => $phone,
            ]);

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
        // e.g. "msgid=123456789"
        if (preg_match('/msgid=([^&]+)/i', $response, $matches)) {
            return $matches[1];
        }

        return null;
    }
}