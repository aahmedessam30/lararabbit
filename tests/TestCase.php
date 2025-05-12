<?php

namespace AhmedEssam\LaraRabbit\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use AhmedEssam\LaraRabbit\Providers\RabbitMQServiceProvider;

abstract class TestCase extends BaseTestCase
{
    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app)
    {
        return [
            RabbitMQServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function defineEnvironment($app)
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        // Set up RabbitMQ configuration
        $app['config']->set('rabbitmq', [
            'connection' => [
                'host' => env('RABBITMQ_HOST', 'localhost'),
                'port' => env('RABBITMQ_PORT', 5672),
                'user' => env('RABBITMQ_USER', 'guest'),
                'password' => env('RABBITMQ_PASSWORD', 'guest'),
                'vhost' => env('RABBITMQ_VHOST', '/'),
                'ssl' => [
                    'enabled' => false,
                ],
                'heartbeat' => 60,
            ],
            'exchange' => [
                'name' => env('RABBITMQ_EXCHANGE', 'test_exchange'),
                'type' => 'topic',
                'passive' => false,
                'durable' => true,
                'auto_delete' => false,
            ],
            'queue' => [
                'durable' => true,
                'auto_delete' => false,
                'exclusive' => false,
                'consumer_exclusive' => false,
                'consumer_tag' => '',
                'prefetch_count' => 1,
                'prefetch_size' => 0,
            ],
            'retry' => [
                'max_attempts' => 3,
                'initial_interval' => 1000,
                'multiplier' => 2,
                'max_interval' => 10000,
            ],
            'circuit_breaker' => [
                'failure_threshold' => 5,
                'recovery_threshold' => 3,
                'timeout_seconds' => 30,
            ],
            'serializer' => 'json',
        ]);
    }
}
