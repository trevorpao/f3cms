<?php

namespace F3CMS;

use F3CMS\Contracts\MailProviderInterface;
use F3CMS\MailHandler\MailgunMailProvider;
use F3CMS\MailHandler\PhpMailProvider;
use F3CMS\MailHandler\SesMailProvider;
use F3CMS\MailHandler\SmtpMailProvider;
use InvalidArgumentException;
use Traversable;

class Sender extends Helper
{
    public const DEFAULT_PROVIDER = 'smtp';

    /** @var array<string, MailProviderInterface> */
    protected array $providers = [];

    protected string $defaultProvider;

    protected ?string $fallbackRecipient;

    /** @var callable|null */
    protected $bodyRenderer;

    protected static ?self $instance = null;

    /**
     * @param array<string, MailProviderInterface> $providers
     */
    public function __construct(array $providers = [], string $defaultProvider = self::DEFAULT_PROVIDER, ?string $fallbackRecipient = null, ?callable $bodyRenderer = null)
    {
        foreach ($providers as $alias => $provider) {
            $this->addProvider($alias, $provider);
        }

        if (empty($providers) && empty($this->providers)) {
            $this->providers = self::buildDefaultProviders();
        }

        if (!isset($this->providers[$defaultProvider]) && !empty($this->providers)) {
            $defaultProvider = (string) array_key_first($this->providers);
        }

        $this->defaultProvider    = $defaultProvider;
        $this->fallbackRecipient  = $fallbackRecipient ?? (f3()->exists('webmaster') ? f3()->get('webmaster') : null);
        $this->bodyRenderer       = $bodyRenderer ?? [static::class, 'defaultRender'];
    }

    public static function setInstance(?self $sender): void
    {
        self::$instance = $sender;
    }

    public static function instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Backwards-compatible static helper.
     */
    public static function mail($subject, $content, $receiver = '', $service = self::DEFAULT_PROVIDER, array $options = [])
    {
        return self::instance()->send((string) $subject, (string) $content, $receiver, $options, (string) $service);
    }

    /**
     * Alias for mail(), kept for legacy calls.
     */
    public static function sendmail($subject, $content, $receiver = '', $service = self::DEFAULT_PROVIDER, array $options = [])
    {
        return self::mail($subject, $content, $receiver, $service, $options);
    }

    /**
     * Main entry when using DI. Accepts string/array recipients and forwards to providers.
     *
     * @param string|array<string>|Traversable<string>|null $recipients
     */
    public function send(string $subject, string $content, $recipients = null, array $options = [], ?string $providerAlias = null): array
    {
        $alias      = $providerAlias ?? ($options['provider'] ?? $this->defaultProvider);
        $provider   = $this->getProvider($alias);
        $recipients = $this->normalizeRecipients($recipients);

        if (empty($recipients)) {
            throw new InvalidArgumentException('No recipient specified and no fallback available.');
        }

        $result = $provider->send($subject, $content, $recipients, $options);
        if (!isset($result['provider'])) {
            $result['provider'] = get_class($provider);
        }

        return $result;
    }

    public function addProvider(string $alias, MailProviderInterface $provider): void
    {
        $this->providers[$this->normalizeAlias($alias)] = $provider;
    }

    public function hasProvider(string $alias): bool
    {
        return isset($this->providers[$this->normalizeAlias($alias)]);
    }

    public function setDefaultProvider(string $alias): void
    {
        $alias = $this->normalizeAlias($alias);
        if (!$this->hasProvider($alias)) {
            throw new InvalidArgumentException(sprintf('Mail provider "%s" is not registered.', $alias));
        }
        $this->defaultProvider = $alias;
    }

    public function render(string $template, array $data = []): string
    {
        $renderer = $this->bodyRenderer;

        return (string) $renderer($template, $data);
    }

    public static function renderBody($tmplname)
    {
        return self::instance()->render((string) $tmplname);
    }

    protected function getProvider(string $alias): MailProviderInterface
    {
        $alias = $this->normalizeAlias($alias);
        if (!isset($this->providers[$alias])) {
            throw new InvalidArgumentException(sprintf('Mail provider "%s" is not registered.', $alias));
        }

        return $this->providers[$alias];
    }

    /**
     * @param string|array<string>|Traversable<string>|null $recipients
     *
     * @return array<int, string>
     */
    protected function normalizeRecipients($recipients): array
    {
        if (is_string($recipients)) {
            $recipients = preg_split('/[;,]+/', $recipients) ?: [];
        } elseif ($recipients instanceof Traversable) {
            $recipients = iterator_to_array($recipients, false);
        } elseif (null === $recipients) {
            $recipients = [];
        } elseif (!is_array($recipients)) {
            $recipients = [$recipients];
        }

        $clean = array_values(array_filter(array_map('trim', $recipients), static function ($email) {
            return $email !== '';
        }));

        if (empty($clean) && $this->fallbackRecipient) {
            $clean[] = $this->fallbackRecipient;
        }

        return $clean;
    }

    protected function normalizeAlias(string $alias): string
    {
        return strtolower(trim($alias));
    }

    /**
     * @return array<string, MailProviderInterface>
     */
    protected static function buildDefaultProviders(): array
    {
        $providers = [];

        if (class_exists(SmtpMailProvider::class)) {
            $providers['smtp'] = new SmtpMailProvider();
        }

        if (class_exists(PhpMailProvider::class)) {
            $providers['mail'] = new PhpMailProvider();
        }

        if (class_exists(MailgunMailProvider::class)) {
            $providers['mailgun'] = new MailgunMailProvider();
        }

        if (class_exists(SesMailProvider::class)) {
            $providers['ses'] = new SesMailProvider();
        }

        return $providers;
    }

    protected static function defaultRender(string $template, array $data = []): string
    {
        $tp = Outfit::_origin();
        foreach ($data as $key => $value) {
            f3()->set($key, $value);
        }

        return $tp->render('mail/' . $template . '.html');
    }
}
