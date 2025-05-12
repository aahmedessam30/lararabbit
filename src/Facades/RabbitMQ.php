<?php

namespace AhmedEssam\LaraRabbit\Facades;

use AhmedEssam\LaraRabbit\Contracts\RabbitMQServiceInterface;
use Illuminate\Support\Facades\Facade;

/**
 * @method static bool publish(string $routingKey, array $data, array $options = [])
 * @method static bool publishEvent(string $eventName, array $payload, array $options = [])
 * @method static bool publishBatch(array $messages)
 * @method static bool validateMessage(array $data, string $schemaName)
 * @method static \AhmedEssam\LaraRabbit\Contracts\RabbitMQServiceInterface setupQueue(string $queueName, array $bindingKeys = [], bool $durable = true, bool $autoDelete = false, array $arguments = [])
 * @method static \AhmedEssam\LaraRabbit\Contracts\RabbitMQServiceInterface setupDeadLetterQueue(string $sourceQueue, string $deadLetterQueue, array $bindingKeys = [])
 * @method static \AhmedEssam\LaraRabbit\Contracts\RabbitMQServiceInterface setupPredefinedQueue(string $queueKey)
 * @method static \AhmedEssam\LaraRabbit\Services\RabbitMQService setSerializationFormat(string $format)
 * @method static void consume(string $queueName, callable $callback, array $bindingKeys = [], bool $autoAck = false, array $arguments = [])
 * @method static void consumeFromPredefinedQueue(string $queueKey, callable $callback, bool $autoAck = false, array $arguments = [])
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