<?php

namespace F3CMS\MailHandler;

use F3CMS\Contracts\MailProviderInterface;
use F3CMS\SesMailer;
use Throwable;

class SesMailProvider implements MailProviderInterface
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

		$sender      = $options['from'] ?? $this->config['from'];
		$attachments = $options['attachments'] ?? [];

		try {
			if (!empty($attachments)) {
				$result = SesMailer::sendRaw($sender, $recipients, $subject, $content, $attachments);
			} else {
				$result = SesMailer::send($sender, $recipients, $subject, $content);
			}
		} catch (Throwable $e) {
			return [
				'status'   => 'failed',
				'provider' => static::class,
				'error'    => $e->getMessage(),
			];
		}

		if (is_string($result)) {
			return [
				'status'   => 'failed',
				'provider' => static::class,
				'error'    => $result,
			];
		}

		$messageId = null;
		if (is_object($result) && method_exists($result, 'get')) {
			$messageId = $result->get('MessageId');
		} elseif (is_array($result) && isset($result['MessageId'])) {
			$messageId = $result['MessageId'];
		}

		return [
			'status'     => 'sent',
			'provider'   => static::class,
			'message_id' => $messageId,
			'meta'       => ['recipients' => $recipients],
		];
	}

	protected function buildConfig(array $override): array
	{
		$defaults = [
			'from' => f3()->exists('ses.from') ? f3()->get('ses.from') : f3()->get('webmaster'),
		];

		return array_merge($defaults, $override);
	}

	protected function filterRecipients(array $recipients): array
	{
		return array_values(array_filter(array_map('trim', $recipients), static function ($email) {
			return $email !== '';
		}));
	}
}
 