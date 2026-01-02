<?php

namespace F3CMS\MailHandler;

use F3CMS\Contracts\MailProviderInterface;

class SmtpMailProvider implements MailProviderInterface
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

		$smtp = new \SMTP(
			$this->config['host'],
			$this->config['port'],
			$this->config['security'],
			$this->config['account'],
			$this->config['password']
		);

		$subject   = $this->encode($subject);
		$fromName  = $this->encode($options['from_name'] ?? $this->config['from_name']);
		$fromEmail = $options['from'] ?? $this->config['from'];

		$smtp->set('From', sprintf('"%s" <%s>', $fromName, $fromEmail));

		$primary = array_shift($recipients);
		$smtp->set('To', '<' . trim($primary) . '>');

		if (!empty($recipients)) {
			$smtp->set('Cc', $this->formatAddressList($recipients));
		} elseif ($this->config['webmaster'] && strcasecmp($primary, $this->config['webmaster']) !== 0) {
			$smtp->set('Bcc', '<' . $this->config['webmaster'] . '>');
		}

		if (!empty($options['cc'])) {
			$smtp->set('Cc', $this->formatAddressList((array) $options['cc']));
		}

		if (!empty($options['bcc'])) {
			$smtp->set('Bcc', $this->formatAddressList((array) $options['bcc']));
		}

		$smtp->set('Content-Type', 'text/html; charset=UTF-8');
		$smtp->set('Subject', $subject);
		$smtp->set('Errors-to', '<' . $fromEmail . '>');

		$sent = $smtp->send($content, true);
		$this->log($smtp->log());

		if (!$sent) {
			return [
				'status'   => 'failed',
				'provider' => static::class,
				'error'    => 'SMTP transport reported a failure.',
			];
		}

		return [
			'status'   => 'sent',
			'provider' => static::class,
			'meta'     => ['recipients' => $primary],
		];
	}

	protected function buildConfig(array $override): array
	{
		$defaults = [
			'host'      => f3()->get('smtp_host'),
			'port'      => f3()->get('smtp_port'),
			'security'  => f3()->exists('smtp_security') ? f3()->get('smtp_security') : 'SSL',
			'account'   => f3()->get('smtp_account'),
			'password'  => f3()->get('smtp_password'),
			'from'      => f3()->exists('smtp_from') ? f3()->get('smtp_from') : f3()->get('smtp_account'),
			'from_name' => f3()->get('smtp_name'),
			'webmaster' => f3()->get('webmaster'),
		];

		return array_merge($defaults, $override);
	}

	protected function filterRecipients(array $recipients): array
	{
		return array_values(array_unique(array_filter(array_map('trim', $recipients), static function ($email) {
			return $email !== '';
		})));
	}

	protected function formatAddressList(array $emails): string
	{
		$filtered = $this->filterRecipients($emails);

		return implode(',', array_map(static function ($email) {
			return '<' . $email . '>';
		}, $filtered));
	}

	protected function encode(string $value): string
	{
		return '=?UTF-8?B?' . base64_encode($value) . '?=';
	}

	protected function log(string $text): void
	{
		try {
			$logger = new \Log('smtp.log');
			$logger->write($text);
		} catch (\Exception $e) {
			// ignore logging failure
		}
	}
}
 