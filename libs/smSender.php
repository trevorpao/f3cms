<?php

namespace F3CMS;

use Aws\Sns\SnsClient;
use F3CMS\Contracts\SmsProviderInterface;
use F3CMS\SmsHandler\AmazonSnsProvider;
use F3CMS\SmsHandler\Every8dProvider;
use F3CMS\SmsHandler\MitakeProvider;
use InvalidArgumentException;
use Throwable;

class smSender extends Helper
{
    public const DEFAULT_PROVIDER = 'mitake';

    /** @var array<string, SmsProviderInterface> */
    protected array $providers = [];

    protected string $defaultProvider;

    protected string $defaultCountryCode;

    protected ?QueueHelper $queueHelper;

    protected bool $queueEnabled = true;

    protected static ?self $instance = null;

    public function __construct(array $providers = [], string $defaultProvider = self::DEFAULT_PROVIDER, string $defaultCountryCode = '+886', ?QueueHelper $queueHelper = null)
    {
        foreach ($providers as $alias => $provider) {
            $this->addProvider((string) $alias, $provider);
        }

        if (empty($this->providers)) {
            $this->providers = self::buildDefaultProviders();
        }

        if (!isset($this->providers[$defaultProvider]) && !empty($this->providers)) {
            $defaultProvider = (string) array_key_first($this->providers);
        }

        if (empty($this->providers)) {
            throw new InvalidArgumentException('No SMS providers registered.');
        }

        $this->defaultProvider    = $defaultProvider;
        $this->defaultCountryCode = $this->normalizeCountryCode($defaultCountryCode);
        $this->queueHelper        = $queueHelper ?? $this->buildDefaultQueueHelper();
        $this->queueEnabled       = $this->queueHelper !== null;
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

    public static function send(string $phone, string $message, array $options = [], ?string $providerAlias = null): array
    {
        return self::instance()->dispatch($phone, $message, $options, $providerAlias);
    }

    public function dispatch(string $phone, string $message, array $options = [], ?string $providerAlias = null): array
    {
        $formattedPhone = $this->formatE164($phone);
        $pipeline       = $this->buildProviderPipeline($providerAlias, $options);

        if ($this->shouldQueue($options)) {
            $envelope = $this->buildEnvelope($formattedPhone, $message, $options, $pipeline);
            $this->enqueue($envelope);

            $queued = [
                'status'     => 'queued',
                'provider'   => null,
                'message_id' => $envelope['id'],
                'meta'       => ['batch_id' => $envelope['batch_id'], 'pipeline' => $pipeline],
            ];
            $this->logStatus($queued);

            return $queued;
        }

        $lastResult = null;
        foreach ($pipeline as $alias) {
            $provider = $this->getProvider($alias);

            $result = $provider->send($formattedPhone, $message, $options);
            $result['provider'] = $result['provider'] ?? get_class($provider);
            $result['phone']    = $formattedPhone;

            if (($result['status'] ?? 'failed') !== 'failed') {
                $this->logStatus($result);

                return $result;
            }

            $this->logStatus($result);
            $lastResult = $result;
        }

        $final = $lastResult ?? [
            'status'   => 'failed',
            'provider' => null,
            'error'    => 'No SMS provider succeeded.',
            'phone'    => $formattedPhone,
        ];
        $this->logStatus($final);

        return $final;
    }

    /**
     * @param array<int, array{phone:string,message:string,options?:array,provider?:string}> $messages
     */
    public function dispatchBulk(array $messages, array $options = []): array
    {
        $results = [];

        foreach ($messages as $idx => $payload) {
            if (empty($payload['phone']) || empty($payload['message'])) {
                $results[$idx] = [
                    'status' => 'failed',
                    'error'  => 'Phone and message are required.',
                ];
                continue;
            }

            $messageOptions = array_merge($options, $payload['options'] ?? []);
            $providerAlias  = $payload['provider'] ?? null;

            try {
                $results[$idx] = $this->dispatch($payload['phone'], $payload['message'], $messageOptions, $providerAlias);
            } catch (Throwable $e) {
                $results[$idx] = [
                    'status' => 'failed',
                    'error'  => $e->getMessage(),
                ];
                $this->logStatus($results[$idx]);
            }
        }

        return $results;
    }

    public function handleQueueMessage(string $payload): array
    {
        $data = json_decode($payload, true);
        if (!is_array($data)) {
            throw new InvalidArgumentException('Queue payload must be JSON.');
        }

        $required = ['phone', 'message', 'pipeline'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new InvalidArgumentException('Queue payload missing field: ' . $field);
            }
        }

        $options = $data['options'] ?? [];
        $phone   = $this->formatE164($data['phone']);
        $message = $data['message'];
        $pipeline = is_array($data['pipeline']) ? $data['pipeline'] : [$data['pipeline']];

        $this->ensureProviders($pipeline);

        foreach ($pipeline as $alias) {
            $provider = $this->getProvider($alias);
            $result   = $provider->send($phone, $message, $options);
            $result['provider'] = $result['provider'] ?? get_class($provider);
            $result['phone']    = $phone;

            if (($result['status'] ?? 'failed') !== 'failed') {
                $this->logStatus($result);

                return $result;
            }

            $this->logStatus($result);
        }

        $failure = [
            'status'   => 'failed',
            'provider' => null,
            'error'    => 'All queued providers failed.',
            'phone'    => $phone,
        ];
        $this->logStatus($failure);

        return $failure;
    }

    public function addProvider(string $alias, SmsProviderInterface $provider): void
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
            throw new InvalidArgumentException(sprintf('SMS provider "%s" is not registered.', $alias));
        }
        $this->defaultProvider = $alias;
    }

    public function setQueueHelper(?QueueHelper $helper): void
    {
        $this->queueHelper  = $helper;
        $this->queueEnabled = $helper !== null;
    }

    public function enableQueue(bool $enabled): void
    {
        $this->queueEnabled = $enabled;
    }

    public function setDefaultCountryCode(string $countryCode): void
    {
        $this->defaultCountryCode = $this->normalizeCountryCode($countryCode);
    }

    protected function buildProviderPipeline(?string $alias, array $options): array
    {
        $pipeline   = [];
        $primary    = $alias ?? ($options['provider'] ?? $this->defaultProvider);
        $pipeline[] = $this->normalizeAlias($primary);

        $fallbacks = $options['fallback_providers'] ?? [];
        foreach ((array) $fallbacks as $fallback) {
            $pipeline[] = $this->normalizeAlias((string) $fallback);
        }

        $pipeline = array_values(array_unique(array_filter($pipeline, fn ($candidate) => $this->hasProvider($candidate))));

        if (empty($pipeline)) {
            throw new InvalidArgumentException('No valid SMS providers found for pipeline.');
        }

        return $pipeline;
    }

    protected function ensureProviders(array $aliases): void
    {
        foreach ($aliases as $alias) {
            if (!$this->hasProvider($alias)) {
                throw new InvalidArgumentException(sprintf('SMS provider "%s" not registered.', $alias));
            }
        }
    }

    protected function getProvider(string $alias): SmsProviderInterface
    {
        $alias = $this->normalizeAlias($alias);
        if (!$this->hasProvider($alias)) {
            throw new InvalidArgumentException(sprintf('SMS provider "%s" is not registered.', $alias));
        }

        return $this->providers[$alias];
    }

    protected function formatE164(string $phone): string
    {
        $stripped = preg_replace('/[\s\-\.\(\)]/', '', trim($phone));
        if ($stripped === '') {
            throw new InvalidArgumentException('Phone number cannot be empty.');
        }

        if (strpos($stripped, '+') === 0) {
            $candidate = '+' . preg_replace('/[^0-9]/', '', substr($stripped, 1));
        } else {
            $digits = preg_replace('/[^0-9]/', '', $stripped);
            $digits = ltrim($digits, '0');
            if ($digits === '') {
                throw new InvalidArgumentException('Phone number must contain digits.');
            }
            $candidate = $this->defaultCountryCode . $digits;
        }

        $candidate = $this->applyCarrierRules($candidate);

        if (!preg_match('/^\+[1-9]\d{1,14}$/', $candidate)) {
            throw new InvalidArgumentException('Invalid E.164 phone: ' . $phone);
        }

        return $candidate;
    }

    protected function applyCarrierRules(string $phone): string
    {
        if (strpos($phone, '+88609') === 0) {
            return '+886' . substr($phone, 5);
        }

        return $phone;
    }

    protected function normalizeCountryCode(string $countryCode): string
    {
        $digits = preg_replace('/[^0-9]/', '', $countryCode);
        if ($digits === '') {
            return '+886';
        }

        return '+' . ltrim($digits, '0');
    }

    protected function normalizeAlias(string $alias): string
    {
        return strtolower(trim($alias));
    }

    protected function shouldQueue(array $options): bool
    {
        if (!$this->queueHelper || !$this->queueEnabled) {
            return false;
        }

        return ($options['queue'] ?? false) || ($options['async'] ?? false);
    }

    protected function enqueue(array $envelope): void
    {
        if (!$this->queueHelper) {
            throw new InvalidArgumentException('Queue helper is not configured.');
        }

        $this->queueHelper->publish([$envelope]);
    }

    protected function buildEnvelope(string $phone, string $message, array $options, array $pipeline): array
    {
        return [
            'id'        => $options['id'] ?? $this->generateMessageId(),
            'batch_id'  => $options['batch_id'] ?? $this->generateBatchId(),
            'phone'     => $phone,
            'message'   => $message,
            'options'   => $options,
            'pipeline'  => $pipeline,
            'created_at'=> time(),
        ];
    }

    protected function logStatus(array $status): void
    {
        try {
            $logger = new \Log('smsender.log');
            $logger->write(json_encode($status, JSON_UNESCAPED_UNICODE));
        } catch (Throwable $e) {
            // ignore logging failures
        }
    }

    protected function generateMessageId(): string
    {
        return uniqid('sms_', true);
    }

    protected function generateBatchId(): string
    {
        return uniqid('batch_', true);
    }

    /**
     * @return array<string, SmsProviderInterface>
     */
    protected static function buildDefaultProviders(): array
    {
        $providers = [];

        if (class_exists(MitakeProvider::class) && f3()->exists('sms.mitake.username')) {
            $providers['mitake'] = new MitakeProvider(
                f3()->get('sms.mitake.username'),
                f3()->get('sms.mitake.password'),
                f3()->get('sms.mitake.domain')
            );
        }

        if (class_exists(Every8dProvider::class) && f3()->exists('sms.every8d.uid')) {
            $providers['every8d'] = new Every8dProvider(
                f3()->get('sms.every8d.uid'),
                f3()->get('sms.every8d.pwd')
            );
        }

        if (
            class_exists(AmazonSnsProvider::class)
            && class_exists(SnsClient::class)
            && f3()->exists('aws.region')
            && f3()->exists('aws.key')
            && f3()->exists('aws.secret')
        ) {
            $providers['sns'] = new AmazonSnsProvider(new SnsClient([
                'version'     => 'latest',
                'region'      => f3()->get('aws.region'),
                'credentials' => [
                    'key'    => f3()->get('aws.key'),
                    'secret' => f3()->get('aws.secret'),
                ],
            ]));
        }

        return $providers;
    }

    protected function buildDefaultQueueHelper(): ?QueueHelper
    {
        if (!class_exists(QueueHelper::class)) {
            return null;
        }

        $config = [
            'queue'       => 'smsender.dispatch',
            'routing_key' => 'smsender.dispatch',
            'batch_size'  => 200,
        ];

        try {
            return new QueueHelper($config, 'rabbitmq.sms');
        } catch (Throwable $e) {
            return null;
        }
    }
}