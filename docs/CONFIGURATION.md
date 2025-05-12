# Configuration Guide for LaraRabbit

LaraRabbit offers extensive configuration options to customize RabbitMQ integration according to your application's needs.

## Configuration File

The package configuration is stored in `config/rabbitmq.php`. Here's a breakdown of all available options:

## Connection Settings

```php
'connection' => [
    'host' => env('RABBITMQ_HOST', 'localhost'),
    'port' => env('RABBITMQ_PORT', 5672),
    'user' => env('RABBITMQ_USER', 'guest'),
    'password' => env('RABBITMQ_PASSWORD', 'guest'),
    'vhost' => env('RABBITMQ_VHOST', '/'),
    'ssl' => [
        'enabled' => env('RABBITMQ_SSL', false),
        'verify_peer' => env('RABBITMQ_SSL_VERIFY_PEER', true),
        'cafile' => env('RABBITMQ_SSL_CAFILE', null),
        'local_cert' => env('RABBITMQ_SSL_LOCAL_CERT', null),
        'local_key' => env('RABBITMQ_SSL_LOCAL_KEY', null),
        'passphrase' => env('RABBITMQ_SSL_PASSPHRASE', null),
    ],
    'heartbeat' => env('RABBITMQ_HEARTBEAT', 60),
    'connection_timeout' => env('RABBITMQ_CONNECTION_TIMEOUT', 3.0),
    'read_write_timeout' => env('RABBITMQ_READ_WRITE_TIMEOUT', 3.0),
    'keepalive' => env('RABBITMQ_KEEPALIVE', false),
],
```

| Option | Description | Default |
|--------|-------------|---------|
| `host` | RabbitMQ server hostname | localhost |
| `port` | RabbitMQ server port | 5672 |
| `user` | RabbitMQ username | guest |
| `password` | RabbitMQ password | guest |
| `vhost` | RabbitMQ virtual host | / |
| `ssl.enabled` | Whether to use SSL/TLS | false |
| `heartbeat` | Heartbeat interval in seconds | 60 |
| `connection_timeout` | Connection timeout in seconds | 3.0 |
| `read_write_timeout` | Read/write timeout in seconds | 3.0 |
| `keepalive` | Whether to use TCP keepalive | false |

## Exchange Settings

```php
'exchange' => [
    'name' => env('RABBITMQ_EXCHANGE_NAME', 'booking_events'),
    'type' => env('RABBITMQ_EXCHANGE_TYPE', 'topic'),
    'passive' => env('RABBITMQ_EXCHANGE_PASSIVE', false),
    'durable' => env('RABBITMQ_EXCHANGE_DURABLE', true),
    'auto_delete' => env('RABBITMQ_EXCHANGE_AUTO_DELETE', false),
],
```

| Option | Description | Default |
|--------|-------------|---------|
| `name` | Exchange name | booking_events |
| `type` | Exchange type (topic, direct, fanout, headers) | topic |
| `passive` | Whether to check if exchange exists without creating it | false |
| `durable` | Whether the exchange survives broker restarts | true |
| `auto_delete` | Whether to auto-delete the exchange when no queues are bound to it | false |

## Queue Settings

```php
'queue' => [
    'durable' => env('RABBITMQ_QUEUE_DURABLE', true),
    'auto_delete' => env('RABBITMQ_QUEUE_AUTO_DELETE', false),
    'exclusive' => env('RABBITMQ_QUEUE_EXCLUSIVE', false),
    'prefetch_count' => env('RABBITMQ_PREFETCH_COUNT', 1),
],
```

| Option | Description | Default |
|--------|-------------|---------|
| `durable` | Whether queues survive broker restarts | true |
| `auto_delete` | Whether to auto-delete queues when no consumers are connected | false |
| `exclusive` | Whether queues are exclusive to the connection | false |
| `prefetch_count` | Number of messages to prefetch (QoS) | 1 |

## Consumer Settings

```php
'consumer' => [
    'throw_exceptions' => env('RABBITMQ_CONSUMER_THROW_EXCEPTIONS', false),
    'auto_ack' => env('RABBITMQ_CONSUMER_AUTO_ACK', false),
    'prefetch_count' => env('RABBITMQ_CONSUMER_PREFETCH_COUNT', 1),
    'wait_timeout' => env('RABBITMQ_CONSUMER_WAIT_TIMEOUT', 0),
    'reconnect_delay' => env('RABBITMQ_CONSUMER_RECONNECT_DELAY', 5),
    'reconnect_max_retries' => env('RABBITMQ_CONSUMER_RECONNECT_MAX_RETRIES', 3),
    'stop_on_critical_error' => env('RABBITMQ_CONSUMER_STOP_ON_CRITICAL_ERROR', false),
    'requeue_on_error' => env('RABBITMQ_CONSUMER_REQUEUE_ON_ERROR', false),
    'delivery_tag_cache_size' => env('RABBITMQ_CONSUMER_DELIVERY_TAG_CACHE_SIZE', 1000),
    'delivery_tag_cache_ttl' => env('RABBITMQ_CONSUMER_DELIVERY_TAG_CACHE_TTL', 3600),
],
```

| Option | Description | Default |
|--------|-------------|---------|
| `throw_exceptions` | Whether to throw exceptions during message processing | false |
| `auto_ack` | Whether to automatically acknowledge messages based on callback return value | false |
| `prefetch_count` | Number of messages to prefetch (QoS) | 1 |
| `wait_timeout` | Timeout for wait operations in seconds (0 = no timeout) | 0 |
| `reconnect_delay` | Delay in seconds before attempting to reconnect | 5 |
| `reconnect_max_retries` | Maximum number of reconnection attempts | 3 |
| `stop_on_critical_error` | Whether to stop consumption on critical errors | false |
| `requeue_on_error` | Whether to requeue messages on processing errors | false |
| `delivery_tag_cache_size` | Maximum size of the delivery tag cache | 1000 |
| `delivery_tag_cache_ttl` | Time-to-live for cached delivery tags in seconds | 3600 |

## Resilience Settings

```php
'resilience' => [
    'max_attempts' => env('RABBITMQ_RESILIENCE_MAX_ATTEMPTS', 3),
    'base_delay_ms' => env('RABBITMQ_RESILIENCE_BASE_DELAY_MS', 100),
    'max_delay_ms' => env('RABBITMQ_RESILIENCE_MAX_DELAY_MS', 5000),
    'jitter_factor' => env('RABBITMQ_RESILIENCE_JITTER_FACTOR', 0.2),
    'failure_threshold' => env('RABBITMQ_RESILIENCE_FAILURE_THRESHOLD', 5),
    'reset_timeout' => env('RABBITMQ_RESILIENCE_RESET_TIMEOUT', 30),
],
```

| Option | Description | Default |
|--------|-------------|---------|
| `max_attempts` | Maximum number of retry attempts | 3 |
| `base_delay_ms` | Base delay between retries in milliseconds | 100 |
| `max_delay_ms` | Maximum delay between retries in milliseconds | 5000 |
| `jitter_factor` | Random jitter factor to add to delays (0.0 - 1.0) | 0.2 |
| `failure_threshold` | Number of failures before circuit breaker opens | 5 |
| `reset_timeout` | Time in seconds before circuit breaker resets to half-open | 30 |

## Publisher Settings

```php
'publisher' => [
    'batch_size' => env('RABBITMQ_PUBLISHER_BATCH_SIZE', 100),
    'confirm_select' => env('RABBITMQ_PUBLISHER_CONFIRM_SELECT', false),
],
```

| Option | Description | Default |
|--------|-------------|---------|
| `batch_size` | Maximum number of messages in a batch | 100 |
| `confirm_select` | Whether to use publisher confirms | false |

## Serialization Settings

```php
'serialization' => [
    'format' => env('RABBITMQ_SERIALIZATION_FORMAT', 'json'),
],
```

| Option | Description | Default |
|--------|-------------|---------|
| `format` | Serialization format ('json' or 'msgpack') | json |

## Debug and Logging

```php
'debug' => env('RABBITMQ_DEBUG', false),
'logging' => [
    'level'   => env('RABBITMQ_LOGGING_LEVEL', 'debug'),
    'channel' => env('RABBITMQ_LOGGING_CHANNEL', 'rabbitmq'),
    'days'    => env('RABBITMQ_LOGGING_DAYS', 14),
],
```

| Option | Description | Default |
|--------|-------------|---------|
| `debug` | Whether to enable debug mode | false |
| `logging.level` | Minimum logging level | debug |
| `logging.channel` | Log channel name | rabbitmq |
| `logging.days` | Number of days to keep logs | 14 |

## Predefined Queues Configuration

LaraRabbit supports predefined queues configuration in a separate file: `config/rabbitmq-queues.php`.

This allows you to centrally configure all your application queues with their binding keys and properties:

```php
<?php

return [
    // Example: Default queue configuration
    'default' => [
        'name'         => env('RABBITMQ_DEFAULT_QUEUE', 'default'),
        'binding_keys' => explode(',', env('RABBITMQ_DEFAULT_BINDING_KEYS', 'default')),
        'durable'      => env('RABBITMQ_DEFAULT_DURABLE', true),
        'auto_delete'  => env('RABBITMQ_DEFAULT_AUTO_DELETE', false),
        'arguments'    => [
            // Optional queue arguments can be added here
            // 'x-message-ttl' => 3600000, // Message TTL in milliseconds (1 hour)
            // 'x-dead-letter-exchange' => 'dlx', // Dead letter exchange
            // 'x-dead-letter-routing-key' => 'dlq.default', // Dead letter routing key
        ],
    ],
    
    // Add your application-specific queues here
    'user_events' => [
        'name'         => 'user_events_queue',
        'binding_keys' => ['user.*'],
        'durable'      => true,
        'auto_delete'  => false,
        'arguments'    => [],
    ],
];
```

You can publish this configuration file with:

```bash
php artisan vendor:publish --tag=lararabbit-queues-config
```

Predefined queues can be used with `setupPredefinedQueue` and `consumeFromPredefinedQueue` methods. See [Advanced Usage](ADVANCED.md) for more details.
