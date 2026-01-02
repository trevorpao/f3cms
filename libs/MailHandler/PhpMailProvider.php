<?php

namespace F3CMS\MailHandler;

use F3CMS\Contracts\MailProviderInterface;

class PhpMailProvider implements MailProviderInterface
{
	protected $config;

	public function __construct(array $config = [])
	{
		$this->config = $this->buildConfig($config);
	}

	public function send(string $subject, string $content, array $recipients, array $options = []): array
	{
		$to = $this->buildRecipientString($recipients);
		if ($to === '') {
			return [
				'status'   => 'failed',
				'provider' => static::class,
				'error'    => 'No recipient specified.',
			];
		}

		$fromAddress = $options['from'] ?? $this->config['from'];
		$fromName    = $options['from_name'] ?? $this->config['from_name'];
		$headers     = [];
		$headers[]   = 'Content-Type: text/html; charset="utf-8"';
		$headers[]   = 'Content-Transfer-Encoding: 8bit';
		$headers[]   = 'MIME-Version: 1.0';
		$headers[]   = sprintf('From: %s (%s)', $fromAddress, $fromName);

		if (!empty($options['bcc'])) {
			$headers[] = 'Bcc: ' . implode(',', (array) $options['bcc']);
		} elseif ($this->config['webmaster'] && strcasecmp($fromAddress, $this->config['webmaster']) !== 0) {
			$headers[] = 'Bcc: ' . $this->config['webmaster'];
		}

		if (!empty($options['cc'])) {
			$headers[] = 'Cc: ' . implode(',', (array) $options['cc']);
		}

		$success = mail($to, $subject, $content, implode(PHP_EOL, $headers));

		if (!$success) {
			return [
				'status'   => 'failed',
				'provider' => static::class,
				'error'    => 'mail() returned false.',
			];
		}

		return [
			'status'   => 'sent',
			'provider' => static::class,
			'meta'     => ['recipients' => $to],
		];
	}

	protected function buildRecipientString(array $recipients): string
	{
		$clean = array_values(array_filter(array_map('trim', $recipients), static function ($email) {
			return $email !== '';
		}));

		if (empty($clean)) {
			return '';
		}

		return implode(',', $clean);
	}

	protected function buildConfig(array $override): array
	{
		$defaults = [
			'from'      => f3()->exists('smtp_from') ? f3()->get('smtp_from') : f3()->get('smtp_account'),
			'from_name' => f3()->get('smtp_name'),
			'webmaster' => f3()->get('webmaster'),
		];

		return array_merge($defaults, $override);
	}
}
 