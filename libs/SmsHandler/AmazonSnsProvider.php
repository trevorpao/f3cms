<?php

namespace F3CMS\SmsHandler;

use Aws\ResultInterface;
use Aws\Sns\SnsClient;
use F3CMS\Contracts\SmsProviderInterface;
use Throwable;

class AmazonSnsProvider implements SmsProviderInterface
{
    private $snsClient;

    public function __construct(SnsClient $client)
    {
        $this->snsClient = $client;
    }

    public function send(string $phone, string $message, array $options = []): array
    {
        try {
            $result = $this->snsClient->publish([
                'Message'     => $message,
                'PhoneNumber' => $phone,
            ]);

            $messageId = $this->extractMessageId($result);

            return [
                'status'     => 'sent',
                'provider'   => static::class,
                'message_id' => $messageId,
                'meta'       => ['result' => $this->normalizeResult($result)],
            ];
        } catch (Throwable $e) {
            return [
                'status'   => 'failed',
                'provider' => static::class,
                'error'    => $e->getMessage(),
            ];
        }
    }

    /** @param array|ResultInterface $result */
    protected function extractMessageId($result): ?string
    {
        if ($result instanceof ResultInterface) {
            return $result->get('MessageId');
        }

        if (is_array($result)) {
            return $result['MessageId'] ?? null;
        }

        return null;
    }

    /** @param array|ResultInterface $result */
    protected function normalizeResult($result)
    {
        if ($result instanceof ResultInterface) {
            return $result->toArray();
        }

        return $result;
    }
}
