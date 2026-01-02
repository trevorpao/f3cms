<?php

namespace F3CMS\Contracts;

interface SmsProviderInterface
{
    /**
     * @param array<string, mixed> $options transport-specific options (e.g., sender ID, templates)
     *
     * @return array{status:string, provider?:string, message_id?:string, error?:string, meta?:mixed}
     */
    public function send(string $phone, string $message, array $options = []): array;
}
