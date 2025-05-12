<?php

return [    /*
    |--------------------------------------------------------------------------
    | Predefined Queues
    |--------------------------------------------------------------------------
    |
    | Define your application queues here with their binding keys and properties.
    | This makes it easier to consume from predefined queues without repeating
    | configuration in your consumer code.
    |
    */
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
];
