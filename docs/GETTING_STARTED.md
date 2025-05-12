# Getting Started with LaraRabbit

This guide will help you get started with LaraRabbit, a comprehensive RabbitMQ integration package for Laravel.

## Prerequisites

- Laravel 8.0 or higher
- PHP 8.0 or higher
- RabbitMQ server (3.8+)

## Installation

```bash
composer require ahmedessam/lararabbit
```

The package will automatically register its service provider if you're using Laravel's package auto-discovery.

## Publishing Configuration

```bash
# Publish the main configuration file
php artisan vendor:publish --tag=lararabbit-config

# Publish the predefined queues configuration
php artisan vendor:publish --tag=lararabbit-queues-config
```

This will create a `config/rabbitmq.php` and `config/rabbitmq-queues.php` file with all general configuration options and predefined queue configurations.

## Basic Configuration

At a minimum, you should set these environment variables in your `.env` file:

```
RABBITMQ_HOST=localhost
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
RABBITMQ_VHOST=/
RABBITMQ_EXCHANGE_NAME=my_application
```

## First Steps

### 1. Publish a Message

Here's a simple example of how to publish a message:

```php
use AhmedEssam\LaraRabbit\Facades\RabbitMQ;

// Simple publish
RabbitMQ::publish('order.created', [
    'order_id' => 123,
    'customer_id' => 456,
    'amount' => 99.99
]);
```

### 2. Consume Messages

To consume messages from a queue:

```php
use AhmedEssam\LaraRabbit\Facades\RabbitMQ;

// Create a queue and bind it to relevant routing keys
RabbitMQ::setupQueue('orders_queue', ['order.created', 'order.updated']);

// Consume messages from the queue
RabbitMQ::consume('orders_queue', function($data, $message) {
    // Process your message
    Log::info('Received order', $data);
    
    // Return true to acknowledge the message, false to reject it
    return true;
});
```

### 3. Create a Laravel Command for the Consumer

For long-running consumers, it's best to create a Laravel command:

```php
<?php

namespace App\Console\Commands;

use AhmedEssam\LaraRabbit\Facades\RabbitMQ;
use Illuminate\Console\Command;

class ConsumeOrdersQueue extends Command
{
    protected $signature = 'rabbitmq:consume-orders';
    protected $description = 'Consume messages from the orders queue';

    public function handle()
    {
        $this->info('Starting consumer...');
        
        // Set up the queue
        RabbitMQ::setupQueue('orders_queue', ['order.created', 'order.updated']);
        
        // Start consuming
        RabbitMQ::consume('orders_queue', function($data, $message) {
            $this->info('Processing order: ' . ($data['order_id'] ?? 'unknown'));
            
            // Your business logic here
            
            return true; // Acknowledge the message
        });
    }
}
```

## Next Steps

- Check out the [Configuration Guide](./CONFIGURATION.md) for detailed configuration options
- Read [Publishing Messages](./PUBLISHING.md) for advanced publishing features
- Learn about [Consuming Messages](./CONSUMING.md) for consumer configurations
- Understand [Error Handling](./ERROR_HANDLING.md) for reliable messaging
