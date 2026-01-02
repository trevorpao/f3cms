<?php

namespace F3CMS;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * QueueHelper 將 RabbitMQ 佇列操作抽離成共用工具，
 * 可在發信、發簡訊或其他批次任務中重複使用。
 */
class QueueHelper
{
    /** @var array 目前生效的佇列設定 */
    protected $config = [];

    /** @var string|null 讀取 f3 設定時使用的 key */
    protected $configKey;

    /** @var AMQPStreamConnection|null */
    protected $connection;

    /** @var AMQPChannel|null */
    protected $channel;

    public function __construct(array $config = [], ?string $configKey = null)
    {
        $this->configKey = $configKey;
        $this->config    = $this->buildConfig($config);
    }

    /**
     * 回傳合併後的設定陣列。
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * 更新部分設定並重置連線。
     */
    public function mergeConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
        $this->config['batch_size'] = max(1, (int) $this->config['batch_size']);
        $this->resetConnection();
    }

    /**
     * 切換佇列啟用狀態。
     */
    public function setEnabled(bool $enabled): void
    {
        $this->config['enabled'] = $enabled;
    }

    /**
     * 依照 options 或設定判斷是否應使用佇列。
     */
    public function shouldQueue(array $options = []): bool
    {
        if (array_key_exists('async', $options)) {
            return (bool) $options['async'];
        }

        return (bool) $this->config['enabled'];
    }

    /**
     * 發佈多筆 payload 至 RabbitMQ，會自動處理批次與節流。
     *
     * @param array<int, array|string> $payloads
     */
    public function publish(array $payloads): void
    {
        if (empty($payloads)) {
            return;
        }

        $channel = $this->getChannel();

        foreach (array_chunk($payloads, $this->config['batch_size']) as $chunk) {
            foreach ($chunk as $payload) {
                $body = is_string($payload) ? $payload : json_encode($payload, JSON_UNESCAPED_UNICODE);
                $message = new AMQPMessage($body, [
                    'delivery_mode' => $this->config['persistent'] ? AMQPMessage::DELIVERY_MODE_PERSISTENT : AMQPMessage::DELIVERY_MODE_NON_PERSISTENT,
                    'content_type'  => 'application/json',
                ]);

                $channel->basic_publish(
                    $message,
                    $this->config['exchange'],
                    $this->config['routing_key']
                );
            }

            if ($this->config['throttle_microseconds'] > 0) {
                usleep((int) $this->config['throttle_microseconds']);
            }
        }
    }

    /**
     * 關閉連線資源，建議在長時間任務或 destruct 時呼叫。
     */
    public function close(): void
    {
        if ($this->channel instanceof AMQPChannel) {
            $this->channel->close();
        }

        if ($this->connection instanceof AMQPStreamConnection) {
            $this->connection->close();
        }

        $this->channel    = null;
        $this->connection = null;
    }

    public function __destruct()
    {
        $this->close();
    }

    /**
     * 建立 RabbitMQ 連線與頻道。
     */
    protected function getChannel(): AMQPChannel
    {
        if ($this->channel instanceof AMQPChannel) {
            return $this->channel;
        }

        $this->connection = new AMQPStreamConnection(
            $this->config['host'],
            $this->config['port'],
            $this->config['user'],
            $this->config['password'],
            $this->config['vhost']
        );

        $this->channel = $this->connection->channel();
        $this->channel->queue_declare(
            $this->config['queue'],
            false,
            $this->config['persistent'],
            false,
            false
        );

        return $this->channel;
    }

    /**
     * 建立設定：會先使用預設值，再從 f3 設定與傳入參數覆寫。
     */
    protected function buildConfig(array $override): array
    {
        $defaults = [
            'enabled'               => false,
            'host'                  => '127.0.0.1',
            'port'                  => 5672,
            'user'                  => 'guest',
            'password'              => 'guest',
            'vhost'                 => '/',
            'queue'                 => 'app.queue',
            'exchange'              => '',
            'routing_key'           => 'app.queue',
            'persistent'            => true,
            'batch_size'            => 100,
            'throttle_microseconds' => 0,
        ];

        if (function_exists('f3')) {
            if ($this->configKey && f3()->exists($this->configKey)) {
                $defaults = array_merge($defaults, (array) f3()->get($this->configKey));
            } elseif (f3()->exists('rabbitmq.default')) {
                $defaults = array_merge($defaults, (array) f3()->get('rabbitmq.default'));
            }
        }

        $config = array_merge($defaults, $override);
        $config['batch_size'] = max(1, (int) $config['batch_size']);

        return $config;
    }

    protected function resetConnection(): void
    {
        $this->close();
    }
}
