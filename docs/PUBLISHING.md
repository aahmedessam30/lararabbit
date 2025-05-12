# Publishing Messages with LaraRabbit

LaraRabbit provides a powerful and flexible API for publishing messages to RabbitMQ exchanges. This guide covers all the publishing features available.

## Basic Publishing

The simplest way to publish a message is using the `publish` method:

```php
use AhmedEssam\LaraRabbit\Facades\RabbitMQ;

RabbitMQ::publish('order.created', [
    'order_id' => 123,
    'customer_id' => 456,
    'amount' => 99.99
]);
```

The `publish` method takes three parameters:
- `$routingKey`: The routing key for message delivery (e.g., `order.created`)
- `$data`: An array of data to be sent in the message body
- `$properties`: (Optional) Additional message properties

## Message Properties

You can customize message properties using the third parameter:

```php
RabbitMQ::publish('order.created', $data, [
    'message_id' => $uuid,              // Unique message identifier
    'correlation_id' => $requestId,     // Correlation ID for request-response pattern
    'reply_to' => 'response_queue',     // Queue name for response messages
    'expiration' => '60000',            // Message expiration in milliseconds
    'priority' => 5,                    // Message priority (0-9)
    'timestamp' => time(),              // Message timestamp
    'headers' => [                      // Custom headers
        'source' => 'api',
        'tenant_id' => 'client123'
    ]
]);
```

## Event Publishing

For event-based architectures, LaraRabbit provides an `publishEvent` method that automatically adds metadata to your events:

```php
RabbitMQ::publishEvent('OrderCreated', [
    'order_id' => 123,
    'customer_id' => 456,
    'amount' => 99.99
]);
```

This method:
- Uses the event name as the routing key
- Adds timestamp and event name to the payload
- Automatically generates a message ID
- Adds correlation ID from request headers if available
- Adds event-specific headers for better interoperability

The event data structure looks like this:

```php
[
    'event' => 'OrderCreated',
    'timestamp' => 1620000000.123,
    'payload' => [
        'order_id' => 123,
        'customer_id' => 456,
        'amount' => 99.99
    ]
]
```

You can add additional event metadata and options:

```php
RabbitMQ::publishEvent('OrderCreated', $orderData, [
    'message_id' => $uuid,
    'headers' => [
        'source_system' => 'ordering_service',
        'version' => '1.0'
    ]
]);
```

## Batch Publishing

For high-throughput scenarios, you can publish multiple messages in a batch:

```php
$messages = [
    [
        'routingKey' => 'order.created',
        'data' => ['order_id' => 123],
        'properties' => ['priority' => 5]
    ],
    [
        'routingKey' => 'order.created',
        'data' => ['order_id' => 124]
    ],
    [
        'routingKey' => 'payment.processed',
        'data' => ['payment_id' => 789]
    ]
];

RabbitMQ::publishBatch($messages);
```

Batch publishing:
- Is more efficient for sending multiple messages
- Uses connection more effectively
- Automatically chunks large batches based on configured batch size
- Provides detailed progress logging for large batches
- Tracks statistics on processed, failed, and successful messages

## Message Validation

LaraRabbit can validate your messages against schemas before publishing:

```php
// Register a schema
app(MessageValidatorInterface::class)->registerSchema('order.created', [
    'order_id' => 'required|integer',
    'customer_id' => 'required|integer', 
    'amount' => 'required|numeric'
]);

// Publish with validation
try {
    RabbitMQ::publish('order.created', $data, [
        'schema' => 'order.created'
    ]);
} catch (\AhmedEssam\LaraRabbit\Exceptions\MessageValidationException $e) {
    // Handle validation error
    Log::error('Message validation failed', ['errors' => $e->getErrors()]);
}
```

## Custom Serialization

LaraRabbit supports different serialization formats:

```php
// Set serialization format for all subsequent messages
RabbitMQ::setSerializationFormat('msgpack');

// Or specify format per message
RabbitMQ::publish('order.created', $data, [
    'serialization_format' => 'json'
]);
```

Supported formats:
- `json`: Standard JSON encoding (default)
- `msgpack`: MessagePack binary format (more efficient)

## Error Handling

Publishing operations are protected by circuit breaker and retry mechanisms:

```php
try {
    RabbitMQ::publish('order.created', $data);
} catch (\AhmedEssam\LaraRabbit\Exceptions\CircuitBreakerOpenException $e) {
    // Circuit is open due to multiple failures
    Log::warning('Circuit breaker open, using fallback', [
        'routing_key' => 'order.created',
        'circuit_state' => $e->getCircuitState()
    ]);
    
    // Implement fallback strategy here
    $this->storeMessageForLaterDelivery($data);
}
```

## Working with Headers Exchange

If you're using a headers exchange, you can use custom headers for message routing:

```php
RabbitMQ::publish('', $data, [
    'headers' => [
        'category' => 'electronics',
        'region' => 'europe',
        'priority' => 'high'
    ]
]);
```

## Best Practices

1. **Use meaningful routing keys**: Follow a consistent convention like `entity.action` (e.g., `order.created`, `payment.failed`)
2. **Include correlation IDs**: When implementing request-response patterns or distributed transactions
3. **Set appropriate message properties**: Use expiration for time-sensitive messages
4. **Validate messages**: Use schema validation to catch errors early
5. **Handle publishing errors**: Implement fallback strategies for when RabbitMQ is unavailable
6. **Use batch publishing** for high-volume scenarios
7. **Monitor publish operations**: Enable debug logging to track publishing activity
8. **Use MessagePack** for improved performance with large messages
