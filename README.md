# LaraRabbit: Elegant RabbitMQ integration for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ahmedessam/lararabbit.svg?style=flat-square)](https://packagist.org/packages/ahmedessam/lararabbit)
[![Total Downloads](https://img.shields.io/packagist/dt/ahmedessam/lararabbit.svg?style=flat-square)](https://packagist.org/packages/ahmedessam/lararabbit)
[![License](https://img.shields.io/packagist/l/ahmedessam/lararabbit.svg?style=flat-square)](https://packagist.org/packages/ahmedessam/lararabbit)

LaraRabbit is a comprehensive, production-ready RabbitMQ integration package for Laravel designed specifically for microservices architecture. It provides an elegant abstraction over the PHP-AMQPLIB library with resilience patterns, performance optimizations, and developer-friendly features.

## Features

- **Simple, Expressive API**: Intuitive interface for publishing and consuming messages
- **Resilience Patterns**: Circuit breaker and retry mechanisms to handle RabbitMQ outages gracefully
- **Automatic Reconnection**: Smart reconnection with exponential backoff and configurable retry limits
- **Multiple Serialization Formats**: Support for JSON and MessagePack serialization
- **Message Validation**: Schema validation for messages before publishing
- **Dead Letter Queue Support**: Built-in handling for failed messages
- **Telemetry & Observability**: Comprehensive logging and metrics
- **Batch Processing Optimization**: Efficient handling of large message batches with progress tracking
- **Delivery Tag Caching**: Improved reliability for message acknowledgement in complex scenarios
- **Configurable Error Handling**: Fine-grained control over error behavior
- **Predefined Queues**: Configure queues centrally and reuse them across your application
- **Event Publishing**: Structured events with automatic metadata and correlation tracking

## Installation

```bash
composer require ahmedessam/lararabbit
```

The package will automatically register its service provider if you're using Laravel's package auto-discovery.

## Configuration

Publish the configuration files:

```bash
# Publish the main configuration file
php artisan vendor:publish --tag=lararabbit-config

# Publish the predefined queues configuration
php artisan vendor:publish --tag=lararabbit-queues-config
```

This will create:
- `config/rabbitmq.php` file with all general configuration options
- `config/rabbitmq-queues.php` file for predefined queue configurations

## Basic Usage

### Publishing Messages

```php
use AhmedEssam\LaraRabbit\Facades\RabbitMQ;

// Simple publish
RabbitMQ::publish('order.created', [
    'order_id' => 123,
    'customer_id' => 456,
    'amount' => 99.99
]);

// Publish with options
RabbitMQ::publish('order.created', $data, [
    'message_id' => $uuid,
    'priority' => 5,
    'headers' => ['source' => 'api']
]);

// Publish event with automatic metadata
RabbitMQ::publishEvent('OrderCreated', [
    'order_id' => 123,
    'customer_id' => 456,
    'amount' => 99.99
]);

// Batch publishing
$messages = [
    [
        'routingKey' => 'order.created',
        'data' => ['order_id' => 123]
    ],
    [
        'routingKey' => 'order.created',
        'data' => ['order_id' => 124]
    ]
];
RabbitMQ::publishBatch($messages);
```

### Consuming Messages

```php
use AhmedEssam\LaraRabbit\Facades\RabbitMQ;
use PhpAmqpLib\Message\AMQPMessage;

// Set up a queue and bind it to the exchange
RabbitMQ::setupQueue('orders_service', ['order.created', 'order.updated']);

// Consume messages from a queue
RabbitMQ::consume('orders_service', function ($data, AMQPMessage $message) {
    // Process the message data
    echo "Received message: " . print_r($data, true);
    
    // If you return false, the message will not be acknowledged
    return true;
});
```

### Using Predefined Queues

```php
// Set up a predefined queue from configuration
RabbitMQ::setupPredefinedQueue('order_service');

// Consume from a predefined queue
RabbitMQ::consumeFromPredefinedQueue('order_service', function ($data, $message) {
    // Process the message data
    Log::info("Processing order", $data);
    return true;
});
```

### Using Dead Letter Queues

```php
use AhmedEssam\LaraRabbit\Facades\RabbitMQ;

// Set up a queue with a dead letter queue for failed messages
RabbitMQ::setupDeadLetterQueue(
    'orders_service',
    'orders_service_failed',
    ['order.failed']
);

// Consume messages from the dead letter queue
RabbitMQ::consume('orders_service_failed', function ($data, $message) {
    // Process failed messages
    Log::error('Failed to process order', $data);
});
```

### CLI Commands

List all queues:

```bash
php artisan lararabbit:list-queues
```

Purge a queue:

```bash
php artisan lararabbit:purge-queue orders_service
```

## Advanced Usage

### Message Validation

```php
// Register a schema
app(MessageValidatorInterface::class)->registerSchema('order.created', [
    'order_id' => 'required|integer',
    'customer_id' => 'required|integer',
    'amount' => 'required|numeric'
]);

// Publish with validation
RabbitMQ::publish('order.created', $data, [
    'schema' => 'order.created'
]);
```

### Serialization Formats

```php
// Set serialization format globally
RabbitMQ::setSerializationFormat('msgpack');

// Or use different formats for different messages
RabbitMQ::setSerializationFormat('json')->publish('order.created', $data);
RabbitMQ::setSerializationFormat('msgpack')->publish('inventory.updated', $data);
```

### Connection Management

The package handles connections efficiently, but you can manually manage them:

```php
// Get the connection manager
$connectionManager = RabbitMQ::getConnectionManager();

// Close connection when done
RabbitMQ::closeConnection();
```

### Error Handling and Reconnection

LaraRabbit provides robust error handling and automatic reconnection capabilities:

```php
// Configure error handling options in config/rabbitmq.php
'consumer' => [
    'throw_exceptions' => false,      // Whether to throw exceptions during processing
    'reconnect_delay' => 5,           // Seconds to wait before reconnection
    'reconnect_max_retries' => 3,     // Maximum reconnection attempts
    'stop_on_critical_error' => false,// Whether to stop consumption on critical errors
    'requeue_on_error' => false,      // Whether to requeue failed messages
],

// Consumption will automatically handle reconnection
RabbitMQ::consume('orders_service', function ($data, $message) {
    try {
        // Process message
        return true; // Acknowledge on success
    } catch (\Exception $e) {
        // Failed messages will be handled according to configuration
        return false; // Reject the message
    }
});
```

For more details on error handling, see [ERROR_HANDLING.md](docs/ERROR_HANDLING.md).

## Testing

The package includes a PHPUnit test suite. You can run the tests with:

```bash
composer test
```

You can also run individual test suites:

```bash
# Run only unit tests (no RabbitMQ connection required)
composer test:unit

# Run integration tests (requires RabbitMQ connection)
composer test:integration
```

> Note: Some tests may fail if a RabbitMQ server is not available. This is expected behavior as certain tests require an actual connection to RabbitMQ. These tests are skipped by default in CI environments.

## Documentation

LaraRabbit includes comprehensive documentation to help you get the most out of the package:

- [Getting Started Guide](docs/GETTING_STARTED.md) - Essential steps to begin using LaraRabbit
- [Configuration Guide](docs/CONFIGURATION.md) - Detailed configuration options
- [Publishing Messages](docs/PUBLISHING.md) - Advanced publishing techniques
- [Consuming Messages](docs/CONSUMING.md) - Consuming messages efficiently
- [Error Handling](docs/ERROR_HANDLING.md) - Robust error handling strategies
- [Advanced Usage](docs/ADVANCED.md) - Advanced patterns and techniques

## License

This package is open-sourced software licensed under the MIT license.

## Security

### Security Recommendations

When using LaraRabbit in production environments, consider these security recommendations:

1. **Use SSL/TLS**: Always enable the SSL connection to RabbitMQ in production environments by setting `RABBITMQ_SSL=true` and providing proper certificates.

2. **Custom Credentials**: Never use the default guest/guest credentials in production. Create a dedicated user with appropriate permissions.

3. **Message Validation**: Always use schema validation for messages to prevent malformed data.

4. **Secure Vhost**: Use a dedicated virtual host for your application and limit access to it.

5. **Network Segmentation**: Place RabbitMQ behind a firewall and only allow connections from trusted sources.

### Reporting Security Vulnerabilities

If you discover a security vulnerability within LaraRabbit, please send an email to [aahmedessam30@gmail.com](mailto:aahmedessam30@gmail.com). All security vulnerabilities will be promptly addressed.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details on how to contribute to this package.

## Credits

- [Ahmed Essam](https://github.com/aahmedessam30)
- [All Contributors](../../contributors)