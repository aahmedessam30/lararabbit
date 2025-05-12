<?php

namespace AhmedEssam\LaraRabbit\Providers;

use AhmedEssam\LaraRabbit\Contracts\ConnectionManagerInterface;
use AhmedEssam\LaraRabbit\Contracts\ConsumerInterface;
use AhmedEssam\LaraRabbit\Contracts\MessageValidatorInterface;
use AhmedEssam\LaraRabbit\Contracts\PublisherInterface;
use AhmedEssam\LaraRabbit\Contracts\RabbitMQServiceInterface;
use AhmedEssam\LaraRabbit\Contracts\SerializerInterface;
use AhmedEssam\LaraRabbit\Services\ConnectionManager;
use AhmedEssam\LaraRabbit\Services\Consumer;
use AhmedEssam\LaraRabbit\Services\Publisher;
use AhmedEssam\LaraRabbit\Services\RabbitMQService;
use AhmedEssam\LaraRabbit\Services\Serializers\JsonSerializer;
use AhmedEssam\LaraRabbit\Services\Serializers\SerializerFactory;
use AhmedEssam\LaraRabbit\Services\Telemetry\Telemetry;
use AhmedEssam\LaraRabbit\Services\Validation\LaravelValidator;
use Illuminate\Support\ServiceProvider;

class RabbitMQServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        // Merge main config
        $this->mergeConfigFrom(
            __DIR__ . '/../config/rabbitmq.php',
            'rabbitmq'
        );
        
        // Merge queues config
        $this->mergeConfigFrom(
            __DIR__ . '/../config/rabbitmq-queues.php',
            'rabbitmq-queues'
        );

        // Bind ConnectionManager
        $this->app->singleton(ConnectionManagerInterface::class, function ($app) {
            return new ConnectionManager(
                config('rabbitmq.exchange.name'),
                config('rabbitmq.exchange.type')
            );
        });

        // Bind serializer services
        $this->app->bind(SerializerInterface::class, function ($app) {
            $format = config('rabbitmq.serialization.format', 'json');
            return SerializerFactory::create($format);
        });

        // Bind message validator
        $this->app->singleton(MessageValidatorInterface::class, function ($app) {
            return new LaravelValidator();
        });

        // Bind telemetry service
        $this->app->singleton(Telemetry::class, function ($app) {
            return new Telemetry();
        });

        // Bind Publisher
        $this->app->singleton(PublisherInterface::class, function ($app) {
            return new Publisher(
                $app->make(ConnectionManagerInterface::class)
            );
        });

        // Bind Consumer
        $this->app->singleton(ConsumerInterface::class, function ($app) {
            return new Consumer(
                $app->make(ConnectionManagerInterface::class)
            );
        });

        // Bind RabbitMQService
        $this->app->singleton(RabbitMQServiceInterface::class, function ($app) {
            return new RabbitMQService(
                $app->make(ConnectionManagerInterface::class),
                $app->make(PublisherInterface::class),
                $app->make(ConsumerInterface::class),
                $app->make(MessageValidatorInterface::class)
            );
        });

        // Provide alias for the service
        $this->app->alias(RabbitMQServiceInterface::class, 'rabbitmq');
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        // Register commands if running in console
        if ($this->app->runningInConsole()) {
            // Publish config
            $this->publishes([
                __DIR__ . '/../config/rabbitmq.php' => config_path('rabbitmq.php'),
            ], 'lararabbit-config');
            
            // Publish queues config
            $this->publishes([
                __DIR__ . '/../config/rabbitmq-queues.php' => config_path('rabbitmq-queues.php'),
            ], 'lararabbit-queues-config');

            // Register Artisan commands
            $this->commands([
                \AhmedEssam\LaraRabbit\Console\Commands\RabbitMQListQueuesCommand::class,
                \AhmedEssam\LaraRabbit\Console\Commands\RabbitMQPurgeQueueCommand::class,
            ]);
        }

        // Register logging channel
        $this->app['config']->set('logging.channels.rabbitmq', [
            'driver' => 'daily',
            'path' => storage_path('logs/rabbitmq.log'),
            'level' => config('rabbitmq.logging.level', 'debug'),
            'days' => config('rabbitmq.logging.days', 14),
            'replace_placeholders' => true,
        ]);
    }
}
