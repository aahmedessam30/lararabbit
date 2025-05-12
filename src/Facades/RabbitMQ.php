<?php

namespace AhmedEssam\LaraRabbit\Facades;

use AhmedEssam\LaraRabbit\Contracts\RabbitMQServiceInterface;
use Illuminate\Support\Facades\Facade;

/**
 * @method static bool publish(string $routingKey, array $data, array $properties = [])
 * @method static bool publishBatch(array $messages)
 * @method static \AhmedEssam\LaraRabbit\Contracts\RabbitMQServiceInterface setupQueue(string $queueName, array $bindingKeys = [])
 * @method static void consume(string $queueName, callable $callback, array $bindingKeys = [])
 * @method static \PhpAmqpLib\Message\AMQPMessage|null getMessageFromQueue(string $queueName)
 * @method static void acknowledge(\PhpAmqpLib\Message\AMQPMessage $message)
 * @method static void reject(\PhpAmqpLib\Message\AMQPMessage $message, bool $requeue = false)
 * @method static void closeConnection()
 * @method static \AhmedEssam\LaraRabbit\Contracts\ConnectionManagerInterface getConnectionManager()
 * @method static \AhmedEssam\LaraRabbit\Contracts\PublisherInterface getPublisher()
 * @method static \AhmedEssam\LaraRabbit\Contracts\ConsumerInterface getConsumer()
 *
 * @see \AhmedEssam\LaraRabbit\Services\RabbitMQService
 */
class RabbitMQ extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return RabbitMQServiceInterface::class;
    }
}