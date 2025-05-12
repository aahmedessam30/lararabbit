# Error Handling in LaraRabbit

LaraRabbit provides comprehensive error handling for RabbitMQ operations to ensure reliable messaging even in unstable network conditions or during broker outages.

## Error Handling Features

### Connection Management
- Automatic reconnection after connection failures with exponential backoff
- Graceful handling of closed channels
- Recovery mechanisms for consumer processes
- Circuit breaker pattern to prevent cascading failures

### Delivery Tag Management
- Validation of delivery tags before operations
- Caching of delivery tags for improved reliability
- Protection against "PRECONDITION_FAILED - unknown delivery tag" errors

### Message Processing
- Configurable automatic acknowledgment based on callback return values
- Error isolation to prevent a single message failure from affecting others
- Dead letter queue support for failed messages

## Configuration

The following configuration options are available in your `config/rabbitmq.php` file:

```php
'consumer' => [
    // Whether to throw exceptions during message processing
    'throw_exceptions' => env('RABBITMQ_CONSUMER_THROW_EXCEPTIONS', false),
    
    // Whether to automatically acknowledge messages
    'auto_ack' => env('RABBITMQ_CONSUMER_AUTO_ACK', false),
    
    // Number of messages to prefetch
    'prefetch_count' => env('RABBITMQ_CONSUMER_PREFETCH_COUNT', 1),
    
    // Timeout for wait operations in seconds (0 = no timeout)
    'wait_timeout' => env('RABBITMQ_CONSUMER_WAIT_TIMEOUT', 0),
    
    // Delay in seconds before attempting to reconnect
    'reconnect_delay' => env('RABBITMQ_CONSUMER_RECONNECT_DELAY', 5),
    
    // Maximum number of reconnection attempts
    'reconnect_max_retries' => env('RABBITMQ_CONSUMER_RECONNECT_MAX_RETRIES', 3),
    
    // Whether to stop consumption on critical errors
    'stop_on_critical_error' => env('RABBITMQ_CONSUMER_STOP_ON_CRITICAL_ERROR', false),
    
    // Whether to requeue messages on processing errors
    'requeue_on_error' => env('RABBITMQ_CONSUMER_REQUEUE_ON_ERROR', false),
    
    // Maximum size of the delivery tag cache
    'delivery_tag_cache_size' => env('RABBITMQ_CONSUMER_DELIVERY_TAG_CACHE_SIZE', 1000),
    
    // Time-to-live for cached delivery tags in seconds
    'delivery_tag_cache_ttl' => env('RABBITMQ_CONSUMER_DELIVERY_TAG_CACHE_TTL', 3600),
],

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
```

## Handling Connection Failures

LaraRabbit handles connection failures with an intelligent reconnection strategy:

```php
// The consumer will automatically attempt to reconnect when a connection is lost
RabbitMQ::consume('queue_name', function($data, $message) {
    // Process message
    return true; // Acknowledge message
});
```

## Auto-Acknowledgement

You can simplify message handling by using the auto-acknowledgement feature:

```php
// Setting autoAck to true will automatically acknowledge messages
// Return true from your callback to acknowledge, false to reject
RabbitMQ::consume('queue_name', function($data, $message) {
    try {
        // Process message
        return true; // Message will be acknowledged
    } catch (\Exception $e) {
        return false; // Message will be rejected
    }
}, [], true); // The true parameter enables auto-acknowledgement
```

## Circuit Breaker Pattern

LaraRabbit implements the circuit breaker pattern to prevent cascading failures:

```php
// Publishing will automatically use the circuit breaker
try {
    RabbitMQ::publish('routing.key', $data);
} catch (\AhmedEssam\LaraRabbit\Exceptions\CircuitBreakerOpenException $e) {
    // The circuit is open due to multiple failures
    // You should implement a fallback strategy here
}
```

## Message Validation

Validate your messages before publishing to prevent protocol errors:

```php
// Register a schema
app(MessageValidatorInterface::class)->registerSchema('order.created', [
    'order_id' => 'required|integer',
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

## Best Practices

1. **Set appropriate timeouts**: Configure `wait_timeout` for your use case
2. **Use `prefetch_count` wisely**: Set to 1 for critical messages, higher for throughput
3. **Enable debug logging**: Set `RABBITMQ_DEBUG=true` in your .env for troubleshooting
4. **Configure circuit breaker thresholds**: Adjust based on your application's requirements
5. **Use dead letter queues**: Set up dead letter queues for failed message handling
6. **Monitor reconnection events**: Watch logs for reconnection attempts to detect issues
7. **Implement proper error handling in consumers**: Return appropriate values from callbacks
8. **Use schema validation**: Validate messages before publishing to prevent errors
9. **Set appropriate TTL for delivery tags**: Adjust cache TTL based on your processing time
10. **Implement application-level retries**: Use requeue_on_error for transient failures
