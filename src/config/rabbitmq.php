<?php

return [
    /*
    |--------------------------------------------------------------------------
    | RabbitMQ Connection Settings
    |--------------------------------------------------------------------------
    |
    | Here you can configure the connection settings for RabbitMQ server.
    |
    */
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

    /*
    |--------------------------------------------------------------------------
    | RabbitMQ Exchange Settings
    |--------------------------------------------------------------------------
    |
    | Here you can configure the exchange settings for RabbitMQ.
    |
    */
    'exchange' => [
        'name' => env('RABBITMQ_EXCHANGE_NAME', 'booking_events'),
        'type' => env('RABBITMQ_EXCHANGE_TYPE', 'topic'),
        'passive' => env('RABBITMQ_EXCHANGE_PASSIVE', false),
        'durable' => env('RABBITMQ_EXCHANGE_DURABLE', true),
        'auto_delete' => env('RABBITMQ_EXCHANGE_AUTO_DELETE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | RabbitMQ Queue Settings
    |--------------------------------------------------------------------------
    |
    | Default settings for queues created by this package.
    |
    */
    'queue' => [
        'durable' => env('RABBITMQ_QUEUE_DURABLE', true),
        'auto_delete' => env('RABBITMQ_QUEUE_AUTO_DELETE', false),
        'exclusive' => env('RABBITMQ_QUEUE_EXCLUSIVE', false),
        'prefetch_count' => env('RABBITMQ_PREFETCH_COUNT', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | RabbitMQ Message Settings
    |--------------------------------------------------------------------------
    |
    | Default settings for messages published by this package.
    |
    */
    'message' => [
        'content_type' => env('RABBITMQ_MESSAGE_CONTENT_TYPE', 'application/json'),
        'delivery_mode' => env('RABBITMQ_MESSAGE_DELIVERY_MODE', 2), // persistent
    ],    /*
    |--------------------------------------------------------------------------
    | RabbitMQ Retry Settings
    |--------------------------------------------------------------------------
    |
    | Configure connection retry settings.
    |
    */
    'retry' => [
        'enabled' => env('RABBITMQ_RETRY_ENABLED', true),
        'max_attempts' => env('RABBITMQ_RETRY_MAX_ATTEMPTS', 3),
        'delay_ms' => env('RABBITMQ_RETRY_DELAY_MS', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Resilience Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for retry and circuit breaker patterns.
    |
    */
    'resilience' => [
        // Retry policy settings
        'max_attempts' => env('RABBITMQ_RESILIENCE_MAX_ATTEMPTS', 3),
        'base_delay_ms' => env('RABBITMQ_RESILIENCE_BASE_DELAY_MS', 100),
        'max_delay_ms' => env('RABBITMQ_RESILIENCE_MAX_DELAY_MS', 5000),
        'jitter_factor' => env('RABBITMQ_RESILIENCE_JITTER_FACTOR', 0.2),

        // Circuit breaker settings
        'failure_threshold' => env('RABBITMQ_RESILIENCE_FAILURE_THRESHOLD', 5),
        'reset_timeout' => env('RABBITMQ_RESILIENCE_RESET_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Publisher Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for the publisher.
    |
    */
    'publisher' => [
        'batch_size' => env('RABBITMQ_PUBLISHER_BATCH_SIZE', 100),
        'confirm_select' => env('RABBITMQ_PUBLISHER_CONFIRM_SELECT', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Consumer Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for the consumer.
    |
    */    
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
    
    /*
    |--------------------------------------------------------------------------
    | Serialization Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for message serialization.
    |
    */
    'serialization' => [
        'format' => env('RABBITMQ_SERIALIZATION_FORMAT', 'json'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug Mode
    |--------------------------------------------------------------------------
    |
    | Enable additional logging for debugging purposes.
    |
    */
    'debug' => env('RABBITMQ_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Logging Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for logging RabbitMQ events.
    |
    */
    'logging' => [
        'level'   => env('RABBITMQ_LOGGING_LEVEL', 'debug'),
        'channel' => env('RABBITMQ_LOGGING_CHANNEL', 'rabbitmq'),
        'days'    => env('RABBITMQ_LOGGING_DAYS', 14),
    ],
];
