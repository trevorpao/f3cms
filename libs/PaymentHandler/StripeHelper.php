<?php

namespace F3CMS\PaymentHandler;

use F3CMS\Contracts\PaymentHandlerInterface;
use F3CMS\Helper;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;

/**
 * StripeHelper implements PaymentHandlerInterface on top of stripe/stripe-php SDK.
 */
class StripeHelper extends Helper implements PaymentHandlerInterface
{
    private $_api_key = '';
    private $_webhook_secret = '';
    private $_client_config = [];
    private $_client;
    private $_logger;
    private static $debug = false;

    private $_commands = [
        'payment_intent.create'   => 'createPaymentIntent',
        'payment_intent.retrieve' => 'retrievePaymentIntent',
        'payment_intent.confirm'  => 'confirmPaymentIntent',
        'payment_intent.cancel'   => 'cancelPaymentIntent',
        'customer.create'         => 'createCustomer',
        'payment_method.attach'   => 'attachPaymentMethod',
        'subscription.create'     => 'createSubscription',
        'subscription.retrieve'   => 'retrieveSubscription',
        'subscription.update'     => 'updateSubscription',
        'subscription.cancel'     => 'cancelSubscription',
        'webhook.verify'          => 'verifyWebhookSignature',
        'webhook.construct'       => 'constructWebhookEvent',
    ];

    public function __construct($apiKey = null, $webhookSecret = null, array $clientConfig = [])
    {
        parent::__construct();

        $this->_client_config = $clientConfig;
        $this->_logger        = new \Log('stripe.log');

        if (!empty($apiKey)) {
            $this->setApiKey($apiKey, $clientConfig);
        }

        if (!empty($webhookSecret)) {
            $this->_webhook_secret = $webhookSecret;
        }

        $this->setDebugMode(f3()->get('DEBUG') >= 1);
    }

    public function supportsCommand($command)
    {
        return isset($this->_commands[$command]);
    }

    public function setDebugMode($enabled)
    {
        self::$debug = (bool)$enabled;
    }

    public function isDebugModeEnabled()
    {
        return (bool)self::$debug;
    }

    public function call($command, array $requestData = [], $method = 'POST')
    {
        if (!$this->supportsCommand($command)) {
            return false;
        }

        $this->log(sprintf('Stripe call [%s] %s', $command, json_encode($requestData, JSON_UNESCAPED_SLASHES)));

        switch ($command) {
            case 'payment_intent.create':
                return $this->createPaymentIntent($requestData);
            case 'payment_intent.retrieve':
                return $this->retrievePaymentIntent($requestData['id'] ?? '', $requestData['query'] ?? []);
            case 'payment_intent.confirm':
                return $this->confirmPaymentIntent($requestData['id'] ?? '', $requestData['payload'] ?? []);
            case 'payment_intent.cancel':
                return $this->cancelPaymentIntent($requestData['id'] ?? '', $requestData['payload'] ?? []);
            case 'customer.create':
                return $this->createCustomer($requestData);
            case 'payment_method.attach':
                return $this->attachPaymentMethod($requestData['id'] ?? '', $requestData['payload'] ?? []);
            case 'subscription.create':
                return $this->createSubscription($requestData);
            case 'subscription.retrieve':
                return $this->retrieveSubscription($requestData['id'] ?? '', $requestData['query'] ?? []);
            case 'subscription.update':
                return $this->updateSubscription($requestData['id'] ?? '', $requestData['payload'] ?? []);
            case 'subscription.cancel':
                return $this->cancelSubscription($requestData['id'] ?? '', $requestData['payload'] ?? []);
            case 'webhook.verify':
                return $this->verifyWebhookSignature(
                    $requestData['payload'] ?? '',
                    $requestData['signature'] ?? '',
                    $requestData['secret'] ?? null,
                    $requestData['tolerance'] ?? 300
                );
            case 'webhook.construct':
                return $this->constructWebhookEvent(
                    $requestData['payload'] ?? '',
                    $requestData['signature'] ?? '',
                    $requestData['secret'] ?? null,
                    $requestData['tolerance'] ?? 300
                );
            default:
                return false;
        }
    }

    public function getURL($command, array $requestData, $return = 'string')
    {
        if (!$this->supportsCommand($command)) {
            return false;
        }

        $uri = sprintf('stripe://%s', $command);

        if ('string' === $return) {
            return $uri;
        }

        return [$uri, $requestData];
    }

    /**
     * 設定 API key 並建立 StripeClient。
     */
    public function setApiKey($apiKey, array $clientConfig = [])
    {
        if (empty($apiKey)) {
            throw new \InvalidArgumentException('Stripe API key cannot be empty.');
        }

        $this->_api_key = $apiKey;
        $this->_client_config = array_merge($this->_client_config, $clientConfig);
        $config = array_merge($this->_client_config, ['api_key' => $apiKey]);

        $this->_client = new StripeClient($config);
    }

    /**
     * 設定 webhook 簽章 secret。
     */
    public function setWebhookSecret($secret)
    {
        $this->_webhook_secret = $secret;
    }

    /**
     * 取得底層 StripeClient。
     */
    public function getClient()
    {
        if (!$this->_client) {
            if (empty($this->_api_key)) {
                throw new \RuntimeException('Stripe API key is required. Call setApiKey() first.');
            }

            $this->_client = new StripeClient(array_merge($this->_client_config, [
                'api_key' => $this->_api_key,
            ]));
        }

        return $this->_client;
    }

    public function createPaymentIntent(array $payload)
    {
        return $this->getClient()->paymentIntents->create($payload);
    }

    public function retrievePaymentIntent($paymentIntentId, array $query = [])
    {
        return $this->getClient()->paymentIntents->retrieve($paymentIntentId, $query);
    }

    public function confirmPaymentIntent($paymentIntentId, array $payload = [])
    {
        return $this->getClient()->paymentIntents->confirm($paymentIntentId, $payload);
    }

    public function cancelPaymentIntent($paymentIntentId, array $payload = [])
    {
        return $this->getClient()->paymentIntents->cancel($paymentIntentId, $payload);
    }

    public function createCustomer(array $payload)
    {
        return $this->getClient()->customers->create($payload);
    }

    public function attachPaymentMethod($paymentMethodId, array $payload)
    {
        return $this->getClient()->paymentMethods->attach($paymentMethodId, $payload);
    }

    public function createSubscription(array $payload)
    {
        return $this->getClient()->subscriptions->create($payload);
    }

    public function retrieveSubscription($subscriptionId, array $query = [])
    {
        return $this->getClient()->subscriptions->retrieve($subscriptionId, $query);
    }

    public function updateSubscription($subscriptionId, array $payload = [])
    {
        return $this->getClient()->subscriptions->update($subscriptionId, $payload);
    }

    public function cancelSubscription($subscriptionId, array $payload = [])
    {
        return $this->getClient()->subscriptions->cancel($subscriptionId, $payload);
    }

    /**
     * 以 Stripe Webhook 驗證簽章，成功回傳 true，否則 false。
     */
    public function verifyWebhookSignature($payload, $signatureHeader, $secret = null, $tolerance = 300)
    {
        try {
            $this->constructWebhookEvent($payload, $signatureHeader, $secret, $tolerance);
            return true;
        } catch (SignatureVerificationException $e) {
            $this->log('Stripe webhook verify failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 回傳 Stripe Event 物件，驗證失敗會拋出 SignatureVerificationException。
     */
    public function constructWebhookEvent($payload, $signatureHeader, $secret = null, $tolerance = 300)
    {
        $secret = $secret ?: $this->_webhook_secret;

        if (empty($payload) || empty($signatureHeader) || empty($secret)) {
            throw new \InvalidArgumentException('Webhook payload, signature header, and secret are required.');
        }

        return Webhook::constructEvent($payload, $signatureHeader, $secret, $tolerance);
    }

    private function log($message)
    {
        if (!self::$debug || !$this->_logger) {
            return;
        }

        $this->_logger->write($message);
    }
}
