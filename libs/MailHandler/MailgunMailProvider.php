<?php

namespace F3CMS\MailHandler;

use F3CMS\Contracts\MailProviderInterface;
use Mailgun\Mailgun;
use Throwable;

class MailgunMailProvider implements MailProviderInterface
{
	protected $config;

	public function __construct(array $config = [])
	{
		$this->config = $this->buildConfig($config);
	}

	public function send(string $subject, string $content, array $recipients, array $options = []): array
	{
		$recipients = $this->filterRecipients($recipients);
		if (empty($recipients)) {
			return [
				'status'   => 'failed',
				'provider' => static::class,
				'error'    => 'No recipient specified.',
			];
		}

		$payload = [
			'from'    => $options['from'] ?? $this->config['from'],
			'to'      => implode(',', $recipients),
			'subject' => $subject,
			'html'    => $content,
		];

		if (!empty($options['cc'])) {
			$payload['cc'] = implode(',', (array) $options['cc']);
		}

		$bcc = $this->filterRecipients((array) ($options['bcc'] ?? []));
		if ($this->config['webmaster'] && !in_array($this->config['webmaster'], $recipients, true)) {
			$bcc[] = $this->config['webmaster'];
		}
		$bcc = $this->filterRecipients($bcc);
		if (!empty($bcc)) {
			$payload['bcc'] = implode(',', $bcc);
		}

		if (!empty($options['attachments'])) {
			$payload['attachment'] = $this->prepareAttachments($options['attachments']);
		}

		try {
			$client = $this->getClient();
			if (method_exists($client, 'messages')) {
				$result = $client->messages()->send($this->config['domain'], $payload);
			} else {
				$result = call_user_func([$client, 'sendMessage'], $this->config['domain'], $payload);
			}
		} catch (Throwable $e) {
			return [
				'status'   => 'failed',
				'provider' => static::class,
				'error'    => $e->getMessage(),
			];
		}

		$this->log($result);

		$messageId = $this->extractMessageId($result);

		return [
			'status'     => 'sent',
			'provider'   => static::class,
			'message_id' => $messageId,
			'meta'       => ['recipients' => $recipients],
		];
	}

	protected function getClient(): Mailgun
	{
		if (class_exists(Mailgun::class) && method_exists(Mailgun::class, 'create')) {
			return Mailgun::create($this->config['key']);
		}

		return new Mailgun($this->config['key']);
	}

	protected function buildConfig(array $override): array
	{
		$defaults = [
			'key'       => f3()->exists('mailgun.key') ? f3()->get('mailgun.key') : '',
			'domain'    => f3()->exists('mailgun.domain') ? f3()->get('mailgun.domain') : '',
			'from'      => f3()->exists('mailgun.from') ? f3()->get('mailgun.from') : f3()->get('webmaster'),
			'webmaster' => f3()->get('webmaster'),
		];

		return array_merge($defaults, $override);
	}

	protected function filterRecipients(array $recipients): array
	{
		return array_values(array_filter(array_map('trim', $recipients), static function ($email) {
			return $email !== '';
		}));
	}

	protected function prepareAttachments(array $attachments): array
	{
		$prepared = [];
		foreach ($attachments as $attachment) {
			if (empty($attachment['path'])) {
				continue;
			}
			$path = $attachment['path'];
			if ($path[0] !== '/') {
				$path = rtrim(f3()->get('abspath'), '/') . '/' . ltrim($path, '/');
			}
			$prepared[] = ['filePath' => $path, 'filename' => $attachment['name'] ?? basename($path)];
		}

		return $prepared;
	}

	protected function extractMessageId($result): ?string
	{
		if (is_array($result) && isset($result['id'])) {
			return $result['id'];
		}

		if (is_object($result)) {
			if (method_exists($result, 'getId')) {
				return $result->getId();
			}

			if (property_exists($result, 'http_response_body') && isset($result->http_response_body->id)) {
				return $result->http_response_body->id;
			}
		}

		return null;
	}

	protected function log($payload): void
	{
		try {
			$logger = new \Log('smtp.log');
			$logger->write(is_string($payload) ? $payload : json_encode($payload));
		} catch (\Exception $e) {
			// ignore
		}
	}
}
