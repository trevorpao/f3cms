<?php

namespace F3CMS;

use F3CMS\Contracts\PaymentHandlerInterface;
use F3CMS\PaymentHandler\CUBEhelper;
use F3CMS\PaymentHandler\LinePayHelper;
use F3CMS\PaymentHandler\StripeHelper;
use F3CMS\PaymentHandler\TPPhelper;

/**
 * Cashier is a DI friendly orchestrator that routes high-level payment scenarios
 * to the underlying PaymentHandlerInterface implementations (CUBE, LinePay, Stripe, TPP...).
 */
class Cashier
{
    public const SCENARIO_INIT_PAYMENT         = 'init_payment';
    public const SCENARIO_CONFIRM_PAYMENT      = 'confirm_payment';
    public const SCENARIO_REFUND_PAYMENT       = 'refund_payment';
    public const SCENARIO_CANCEL_PAYMENT       = 'cancel_payment';
    public const SCENARIO_QUERY_TRANSACTION    = 'query_transaction';
    public const SCENARIO_ACKNOWLEDGE_CALLBACK = 'acknowledge_callback';
    public const SCENARIO_VERIFY_CALLBACK      = 'verify_callback';
    public const SCENARIO_SUBSCRIPTION_CREATE  = 'subscription_create';
    public const SCENARIO_SUBSCRIPTION_UPDATE  = 'subscription_update';
    public const SCENARIO_SUBSCRIPTION_CANCEL  = 'subscription_cancel';
    public const SCENARIO_CUSTOMER_CREATE      = 'customer_create';
    public const SCENARIO_PAYMENT_METHOD_ATTACH = 'payment_method_attach';
    public const SCENARIO_WEBHOOK_VERIFY       = 'webhook_verify';
    public const SCENARIO_WEBHOOK_CONSTRUCT    = 'webhook_construct';
    public const SCENARIO_TOKEN_PAYMENT        = 'token_payment';
    public const SCENARIO_BIND_CARD            = 'bind_card';

    /** @var array<string, PaymentHandlerInterface> */
    private $handlers = [];

    /** @var array<string, array<string, string>> */
    private $scenarioMaps = [];

    /**
     * @param array<string, PaymentHandlerInterface|array{handler: PaymentHandlerInterface, scenarios?: array<string,string>}> $handlers
     */
    public function __construct(array $handlers = [])
    {
        foreach ($handlers as $alias => $definition) {
            if ($definition instanceof PaymentHandlerInterface) {
                $this->registerHandler($alias, $definition);
                continue;
            }

            if (is_array($definition) && isset($definition['handler']) && $definition['handler'] instanceof PaymentHandlerInterface) {
                $this->registerHandler($alias, $definition['handler'], $definition['scenarios'] ?? []);
                continue;
            }

            throw new \InvalidArgumentException('Cashier expects PaymentHandlerInterface instances or definitions.');
        }
    }

    public function registerHandler(string $alias, PaymentHandlerInterface $handler, array $scenarioMap = []): void
    {
        $this->handlers[$alias] = $handler;
        $this->scenarioMaps[$alias] = $scenarioMap ?: $this->detectScenarioMap($handler);
    }

    public function hasHandler(string $alias): bool
    {
        return isset($this->handlers[$alias]);
    }

    public function getHandler(string $alias): PaymentHandlerInterface
    {
        if (!$this->hasHandler($alias)) {
            throw new \OutOfBoundsException(sprintf('Payment handler "%s" is not registered.', $alias));
        }

        return $this->handlers[$alias];
    }

    public function getScenarioMap(string $alias): array
    {
        return $this->scenarioMaps[$alias] ?? [];
    }

    /**
     * Generic dispatcher. `$scenarioOrCommand` can be either a high-level scenario constant
     * (e.g. Cashier::SCENARIO_INIT_PAYMENT) or a concrete command supported by the handler.
     */
    public function handle(string $alias, string $scenarioOrCommand, array $payload = [], string $method = 'POST')
    {
        $handler = $this->getHandler($alias);
        $command = $this->resolveCommand($alias, $scenarioOrCommand, $handler);

        if (!$handler->supportsCommand($command)) {
            throw new \RuntimeException(sprintf('Handler "%s" does not support command "%s".', $alias, $command));
        }

        return $handler->call($command, $payload, $method);
    }

    public function initPayment(string $alias, array $payload, string $method = 'POST')
    {
        return $this->handle($alias, self::SCENARIO_INIT_PAYMENT, $payload, $method);
    }

    public function confirmPayment(string $alias, array $payload, string $method = 'POST')
    {
        return $this->handle($alias, self::SCENARIO_CONFIRM_PAYMENT, $payload, $method);
    }

    public function refundPayment(string $alias, array $payload, string $method = 'POST')
    {
        return $this->handle($alias, self::SCENARIO_REFUND_PAYMENT, $payload, $method);
    }

    public function cancelPayment(string $alias, array $payload, string $method = 'POST')
    {
        return $this->handle($alias, self::SCENARIO_CANCEL_PAYMENT, $payload, $method);
    }

    public function queryTransaction(string $alias, array $payload, string $method = 'POST')
    {
        return $this->handle($alias, self::SCENARIO_QUERY_TRANSACTION, $payload, $method);
    }

    public function acknowledgeCallback(string $alias, array $payload)
    {
        return $this->handle($alias, self::SCENARIO_ACKNOWLEDGE_CALLBACK, $payload);
    }

    public function verifyCallback(string $alias, array $payload)
    {
        return $this->handle($alias, self::SCENARIO_VERIFY_CALLBACK, $payload);
    }

    public function subscriptionCreate(string $alias, array $payload)
    {
        return $this->handle($alias, self::SCENARIO_SUBSCRIPTION_CREATE, $payload);
    }

    public function subscriptionUpdate(string $alias, array $payload)
    {
        return $this->handle($alias, self::SCENARIO_SUBSCRIPTION_UPDATE, $payload);
    }

    public function subscriptionCancel(string $alias, array $payload)
    {
        return $this->handle($alias, self::SCENARIO_SUBSCRIPTION_CANCEL, $payload);
    }

    public function createCustomer(string $alias, array $payload)
    {
        return $this->handle($alias, self::SCENARIO_CUSTOMER_CREATE, $payload);
    }

    public function attachPaymentMethod(string $alias, array $payload)
    {
        return $this->handle($alias, self::SCENARIO_PAYMENT_METHOD_ATTACH, $payload);
    }

    public function verifyWebhook(string $alias, array $payload)
    {
        return $this->handle($alias, self::SCENARIO_WEBHOOK_VERIFY, $payload);
    }

    public function constructWebhook(string $alias, array $payload)
    {
        return $this->handle($alias, self::SCENARIO_WEBHOOK_CONSTRUCT, $payload);
    }

    public function tokenPayment(string $alias, array $payload)
    {
        return $this->handle($alias, self::SCENARIO_TOKEN_PAYMENT, $payload);
    }

    public function bindCard(string $alias, array $payload)
    {
        return $this->handle($alias, self::SCENARIO_BIND_CARD, $payload);
    }

    private function resolveCommand(string $alias, string $scenarioOrCommand, PaymentHandlerInterface $handler = null): string
    {
        $handler = $handler ?: $this->getHandler($alias);

        if ($handler->supportsCommand($scenarioOrCommand)) {
            return $scenarioOrCommand;
        }

        $map = $this->scenarioMaps[$alias] ?? [];
        if (!isset($map[$scenarioOrCommand])) {
            throw new \InvalidArgumentException(sprintf(
                'Scenario "%s" is not mapped for handler "%s".',
                $scenarioOrCommand,
                $alias
            ));
        }

        return $map[$scenarioOrCommand];
    }

    private function detectScenarioMap(PaymentHandlerInterface $handler): array
    {
        switch (true) {
            case $handler instanceof CUBEhelper:
                return [
                    self::SCENARIO_INIT_PAYMENT         => 'init',
                    self::SCENARIO_QUERY_TRANSACTION    => 'query',
                    self::SCENARIO_ACKNOWLEDGE_CALLBACK => 'ack',
                    self::SCENARIO_VERIFY_CALLBACK      => 'verify',
                ];
            case $handler instanceof LinePayHelper:
                return [
                    self::SCENARIO_INIT_PAYMENT    => 'checkout',
                    self::SCENARIO_CONFIRM_PAYMENT => 'confirm',
                    self::SCENARIO_REFUND_PAYMENT  => 'refund',
                ];
            case $handler instanceof StripeHelper:
                return [
                    self::SCENARIO_INIT_PAYMENT          => 'payment_intent.create',
                    self::SCENARIO_CONFIRM_PAYMENT       => 'payment_intent.confirm',
                    self::SCENARIO_CANCEL_PAYMENT        => 'payment_intent.cancel',
                    self::SCENARIO_QUERY_TRANSACTION     => 'payment_intent.retrieve',
                    self::SCENARIO_SUBSCRIPTION_CREATE   => 'subscription.create',
                    self::SCENARIO_SUBSCRIPTION_UPDATE   => 'subscription.update',
                    self::SCENARIO_SUBSCRIPTION_CANCEL   => 'subscription.cancel',
                    self::SCENARIO_CUSTOMER_CREATE       => 'customer.create',
                    self::SCENARIO_PAYMENT_METHOD_ATTACH => 'payment_method.attach',
                    self::SCENARIO_WEBHOOK_VERIFY        => 'webhook.verify',
                    self::SCENARIO_WEBHOOK_CONSTRUCT     => 'webhook.construct',
                ];
            case $handler instanceof TPPhelper:
                return [
                    self::SCENARIO_INIT_PAYMENT      => 'start',
                    self::SCENARIO_TOKEN_PAYMENT     => 'take',
                    self::SCENARIO_BIND_CARD         => 'bind',
                    self::SCENARIO_QUERY_TRANSACTION => 'get',
                ];
            default:
                return [];
        }
    }
}
