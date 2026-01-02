<?php

namespace F3CMS\Contracts;

interface MailProviderInterface
{
    /**
     * @param array<int, string> $recipients
     * @param array              $options 例如 cc、bcc、attachments、from 等。
     *
     * @return array{status:string, provider:string, message_id?:string, error?:string, meta?:mixed}
     */
    public function send(string $subject, string $content, array $recipients, array $options = []): array;
}
