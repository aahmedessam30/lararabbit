# Consuming Messages with LaraRabbit

This guide covers the various ways to consume messages using LaraRabbit.

## Setting Up Queues

Before consuming messages, you need to set up a queue and bind it to one or more routing keys:

```php
use AhmedEssam\LaraRabbit\Facades\RabbitMQ;

// Set up a queue and bind it to multiple routing keys
RabbitMQ::setupQueue(
    'orders_processing_queue',    // Queue name 
    ['order.created', 'order.updated'],  // Routing keys
    true,   // Durable queue
    false,  // Auto-delete queue
    [       // Optional queue arguments
        'x-max-length' => 10000,
        'x-message-ttl' => 3600000
    ]
);
```

## Basic Consumption

The basic way to consume messages is with the `consume` method:

```php
RabbitMQ::consume('orders_processing_queue', function ($data, $message) {
    // Process the message data
    Log::info('Processing order', $data);
    
    // Return true to acknowledge the message
    // Return false to reject it (will be requeued by default)
    return true;
});
```

The callback function receives:
- `$data`: The deserialized message body
- `$message`: The original AMQPMessage object for advanced scenarios

## Auto-Acknowledgement

LaraRabbit supports automatic acknowledgement based on the return value of your callback:

```php
RabbitMQ::consume(
    'orders_processing_queue', 
    function ($data, $message) {
        try {
            // Process message
            processOrder($data);
            return true;  // Message will be acknowledged
        } catch (\Exception $e) {
            Log::error('Failed to process order', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            return false; // Message will be rejected
        }
    },
    [],     // No additional binding keys
    true    // Enable auto-acknowledgement
);
```

With auto-acknowledgement enabled:
- Returning `true` from your callback will acknowledge the message
- Returning `false` will reject the message
- Throwing an exception will reject the message (behavior configurable via `requeue_on_error`)

## Manual Acknowledgement

For more control, you can manually acknowledge or reject messages:

```php
RabbitMQ::consume('orders_processing_queue', function ($data, $message) {
    try {
        // Process message
        $result = processOrder($data);
        
        if ($result->isSuccessful()) {
            // Acknowledge the message
            RabbitMQ::acknowledge($message);
        } else {
            // Reject message but don't requeue
            RabbitMQ::reject($message, false);
        }
    } catch (RetryableException $e) {
        // Reject message and requeue for later processing
        RabbitMQ::reject($message, true);
    } catch (\Exception $e) {
        // Reject message, don't requeue
        RabbitMQ::reject($message, false);
    }
    
    // No need to return anything when manually handling acknowledgements
});
```

## Getting Individual Messages

For scenarios where you want to get messages one at a time rather than using a continuous consumer:

```php
// Get a single message (non-blocking)
$message = RabbitMQ::getMessageFromQueue('orders_processing_queue');

if ($message) {
    $data = json_decode($message->getBody(), true);
    
    // Process the message
    
    // Acknowledge when done
    RabbitMQ::acknowledge($message);
}
```

## Creating a Consumer Command

For long-running consumers, create a dedicated Artisan command:

```php
<?php

namespace App\Console\Commands;

use AhmedEssam\LaraRabbit\Facades\RabbitMQ;
use Illuminate\Console\Command;

class ProcessOrdersQueue extends Command
{
    protected $signature = 'rabbitmq:process-orders';
    protected $description = 'Start consuming messages from the orders queue';

    public function handle()
    {
        $this->info('Starting order processing consumer...');
        
        // Set up the queue if not already done
        RabbitMQ::setupQueue('orders_processing_queue', ['order.created', 'order.updated']);
        
        // Start consuming
        RabbitMQ::consume('orders_processing_queue', function ($data, $message) {
            $this->info('Processing order #' . ($data['order_id'] ?? 'unknown'));
            
            try {
                // Your processing logic here
                
                return true; // Acknowledge
            } catch (\Exception $e) {
                $this->error('Failed to process order: ' . $e->getMessage());
                return false; // Reject
            }
        });
    }
}
```

Then run it with:

```bash
php artisan rabbitmq:process-orders
```

## Dead Letter Queues

LaraRabbit makes it easy to set up dead letter queues for handling failed messages:

```php
// Set up a dead letter queue for failed order processing
RabbitMQ::setupDeadLetterQueue(
    'orders_processing_queue',     // Main queue name
    'orders_failed_queue',         // Dead letter queue name
    ['order.failed'],              // Binding keys for dead letter queue
    [
        'x-message-ttl' => 86400000  // 24 hours retention
    ]
);

// Consume from the dead letter queue
RabbitMQ::consume('orders_failed_queue', function ($data, $message) {
    // Log failed messages
    Log::error('Processing failed order', $data);
    
    // Send alert
    Notification::route('slack', config('alerts.slack_webhook'))
        ->notify(new FailedOrderNotification($data));
    
    return true; // Acknowledge
});
```

## Reconnection and Error Handling

LaraRabbit handles connection failures automatically:

```php
// The consumer will automatically reconnect if the connection is lost
RabbitMQ::consume('orders_processing_queue', function ($data, $message) {
    // Your logic here
    return true;
});
```

You can customize the reconnection behavior in the configuration file.

## Best Practices

1. **Use prefetch count wisely**: Set to 1 for critical messages, higher for higher throughput
2. **Create dedicated commands** for each consumer
3. **Implement proper error handling** in your callback functions
4. **Set up dead letter queues** for failed message handling
5. **Use queue arguments** for TTL and max length to prevent queue overflow
6. **Monitor consumer activity** with logging
7. **Handle reconnection gracefully** with appropriate timeout and retry settings
8. **Scale horizontally** by running multiple consumer instances for high-volume queues
9. **Implement idempotent consumers** to handle potential message duplication
