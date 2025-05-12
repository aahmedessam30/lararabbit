# Advanced Usage of LaraRabbit

This guide covers advanced usage patterns and techniques for getting the most out of LaraRabbit.

## Circuit Breaker Pattern

LaraRabbit implements the circuit breaker pattern to prevent cascading failures when RabbitMQ is experiencing issues:

```php
use AhmedEssam\LaraRabbit\Facades\RabbitMQ;
use AhmedEssam\LaraRabbit\Exceptions\CircuitBreakerOpenException;

try {
    RabbitMQ::publish('order.created', $orderData);
} catch (CircuitBreakerOpenException $e) {
    // Circuit is open, implement fallback strategy
    Cache::put("pending_message:{$orderId}", $orderData, now()->addHours(1));
    
    // Schedule retry
    dispatch(new RetryPublishJob($orderId))->delay(now()->addMinutes(5));
    
    // Log the event
    Log::warning('Circuit breaker open, message queued for later delivery', [
        'order_id' => $orderId,
        'circuit_state' => $e->getCircuitState()
    ]);
}
```

You can customize circuit breaker behavior in your configuration:

```php
'resilience' => [
    'failure_threshold' => env('RABBITMQ_RESILIENCE_FAILURE_THRESHOLD', 5),
    'reset_timeout' => env('RABBITMQ_RESILIENCE_RESET_TIMEOUT', 30),
],
```

## Working with Multiple Exchanges

While LaraRabbit uses a default exchange, you can work with multiple exchanges:

```php
// Get the connection manager
$connectionManager = app(ConnectionManagerInterface::class);

// Change the exchange
$connectionManager->setExchangeName('secondary_exchange');

// Now operations will use the new exchange
RabbitMQ::publish('order.created', $data);

// Reset to default exchange
$connectionManager->setExchangeName(config('rabbitmq.exchange.name'));
```

## Message Priority

RabbitMQ supports message priorities (0-9) if configured on the queue:

```php
// Setup a priority queue
RabbitMQ::setupQueue('priority_queue', ['task.created'], true, false, [
    'x-max-priority' => 10
]);

// Publish high-priority message
RabbitMQ::publish('task.created', $urgentTask, [
    'priority' => 9
]);

// Publish normal-priority message
RabbitMQ::publish('task.created', $normalTask, [
    'priority' => 5
]);

// Publish low-priority message
RabbitMQ::publish('task.created', $batchTask, [
    'priority' => 1
]);
```

## Predefined Queues

LaraRabbit supports predefined queues that can be configured once and reused across your application. This makes it easier to standardize queue configurations and avoid repetition.

### Configuration

Define your queues in the `config/rabbitmq-queues.php` file:

```php
return [
    'order_service' => [
        'name' => 'order_processing',
        'binding_keys' => ['order.created', 'order.updated', 'order.cancelled'],
        'durable' => true,
        'auto_delete' => false,
        'arguments' => [
            'x-message-ttl' => 3600000, // 1 hour
            'x-max-priority' => 10,
        ],
    ],
    'notification_service' => [
        'name' => 'notification_queue',
        'binding_keys' => ['notification.*'],
        'durable' => true,
        'auto_delete' => false,
    ],
];
```

### Using Predefined Queues

```php
use AhmedEssam\LaraRabbit\Facades\RabbitMQ;

// Set up a predefined queue
RabbitMQ::setupPredefinedQueue('order_service');

// Consume from a predefined queue
RabbitMQ::consumeFromPredefinedQueue('order_service', function ($data, $message) {
    // Process message
    return true;
});
```

## Custom Message Serializers

LaraRabbit supports MessagePack out of the box, but you can add your own serializers:

```php
// In a service provider
$this->app->bind('my-custom-serializer', function() {
    return new class implements SerializerInterface {
        public function serialize(array $data): string {
            // Your custom serialization logic
            return custom_format_encode($data);
        }
        
        public function deserialize(string $data): array {
            // Your custom deserialization logic
            return custom_format_decode($data);
        }
    };
});

// In your code
RabbitMQ::setSerializationFormat('my-custom-serializer');
```

## Delayed Message Delivery

Using the RabbitMQ Delayed Message Exchange plugin, you can implement delayed delivery:

```php
// Set up a queue with a delayed message exchange
RabbitMQ::setupQueue(
    'delayed_notifications',
    ['notification.delay'],
    true,
    false,
    [
        'x-delayed-type' => 'topic'
    ],
    'x-delayed-message' // Exchange type for delayed message exchange
);

// Publish a message to be delivered after 60 seconds
RabbitMQ::publish('notification.delay', $notificationData, [
    'headers' => [
        'x-delay' => 60000 // delay in milliseconds
    ]
]);
```

Note: This requires the [delayed_message_exchange](https://github.com/rabbitmq/rabbitmq-delayed-message-exchange) plugin to be installed in your RabbitMQ server.

## Message TTL and Queue TTL

Control message and queue lifetimes:

```php
// Set TTL for individual message (10 minutes)
RabbitMQ::publish('order.created', $data, [
    'expiration' => '600000' // milliseconds
]);

// Set default TTL for all messages in a queue (1 hour)
RabbitMQ::setupQueue('temporary_orders', ['order.temp'], true, false, [
    'x-message-ttl' => 3600000,  // message TTL in milliseconds
    'x-expires' => 86400000      // queue TTL in milliseconds (1 day)
]);
```

## Distributed Tracing

For microservices architectures, use correlation IDs for distributed tracing:

```php
// Middleware to generate and store correlation ID
class CorrelationIdMiddleware
{
    public function handle($request, Closure $next)
    {
        $correlationId = $request->header('X-Correlation-ID', Str::uuid()->toString());
        app()->instance('correlation-id', $correlationId);
        
        return $next($request)
            ->header('X-Correlation-ID', $correlationId);
    }
}

// Publishing with correlation ID
RabbitMQ::publish('order.created', $data, [
    'correlation_id' => app('correlation-id'),
    'headers' => [
        'x-correlation-id' => app('correlation-id')
    ]
]);

// Consuming and propagating correlation ID
RabbitMQ::consume('orders_queue', function ($data, $message) {
    $correlationId = $message->get('correlation_id');
    app()->instance('correlation-id', $correlationId);
    
    // Your processing logic with correlation ID in context
    Log::info('Processing order', ['correlation_id' => $correlationId]);
});
```

## Rate Limiting

Implement rate limiting for publishers:

```php
class RateLimitedPublisher
{
    protected $limiter;
    
    public function __construct(RateLimiter $limiter)
    {
        $this->limiter = $limiter;
    }
    
    public function publish(string $routingKey, array $data)
    {
        if ($this->limiter->tooManyAttempts('rabbitmq:publish', 100)) { // 100 msg/minute
            throw new RateLimitExceededException('Publishing rate limit exceeded');
        }
        
        $this->limiter->hit('rabbitmq:publish', 60); // 1 minute window
        
        return RabbitMQ::publish($routingKey, $data);
    }
}
```

## Bulk Fetch and Process

For batch processing scenarios:

```php
// Process messages in batches
$messages = [];
$messageCount = 0;

while ($messageCount < 100) {
    $message = RabbitMQ::getMessageFromQueue('batch_queue');
    
    if (!$message) {
        break; // No more messages
    }
    
    $messages[] = [
        'data' => json_decode($message->getBody(), true),
        'message' => $message
    ];
    $messageCount++;
}

// Process the batch
if (count($messages) > 0) {
    // Bulk process logic
    $result = BulkProcessor::process(collect($messages)->pluck('data'));
    
    // Acknowledge all messages
    foreach ($messages as $item) {
        RabbitMQ::acknowledge($item['message']);
    }
}
```

## Message Filtering

Implement custom message filtering in consumers:

```php
// Create a filtered consumer
RabbitMQ::consume('events_queue', function ($data, $message) {
    // Filter messages by content
    if (isset($data['priority']) && $data['priority'] === 'high') {
        // Process high priority messages
        processHighPriorityEvent($data);
    } else if (
        isset($data['tenant_id']) && 
        in_array($data['tenant_id'], $this->allowedTenants)
    ) {
        // Process messages for specific tenants
        processTenantEvent($data);
    } else {
        // Skip this message but still acknowledge it
        Log::debug('Skipping message based on filter criteria');
    }
    
    return true; // Acknowledge all messages
});
```

## Handling Large Messages

For very large messages, use reference passing instead of including the full payload:

```php
// Store large data in a cache or storage
$storageKey = 'large_payload_' . Str::uuid();
Storage::put("large-payloads/{$storageKey}.json", json_encode($largePayload));

// Publish only the reference
RabbitMQ::publish('large.message', [
    'reference_id' => $storageKey,
    'expires_at' => now()->addDay()->timestamp
]);

// Consuming large messages
RabbitMQ::consume('large_queue', function ($data, $message) {
    if (isset($data['reference_id'])) {
        // Retrieve actual payload
        $payload = Storage::get("large-payloads/{$data['reference_id']}.json");
        
        if ($payload) {
            $actualData = json_decode($payload, true);
            // Process the actual data
            processLargePayload($actualData);
            
            // Optionally cleanup
            if (Carbon::createFromTimestamp($data['expires_at'])->isPast()) {
                Storage::delete("large-payloads/{$data['reference_id']}.json");
            }
        }
    }
    
    return true;
});
```
