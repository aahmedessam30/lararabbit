<?php

namespace AhmedEssam\LaraRabbit\Contracts;

use PhpAmqpLib\Message\AMQPMessage;

interface ConsumerInterface
{
    /**
     * Set up a queue and bind it to the exchange
     *
     * @param string $queueName Queue name
     * @param array $bindingKeys Array of binding keys to bind the queue to
     * @param bool $durable Whether the queue should survive broker restarts
     * @param bool $autoDelete Whether the queue should be deleted when no longer used
     * @param array $arguments Additional queue arguments (like x-dead-letter-exchange)
     * @return self
     */
    public function setupQueue(
        string $queueName,
        array $bindingKeys = [],
        bool $durable = true,
        bool $autoDelete = false,
        array $arguments = []
    ): self;    
    
    /**
     * Start consuming messages from a queue
     *
     * @param string $queueName Queue name to consume from
     * @param callable $callback Callback function to process messages
     * @param array $bindingKeys Array of binding keys to bind the queue to (if not already bound)
     * @param bool $autoAck Whether to automatically acknowledge messages
     * @param array $arguments Additional queue arguments (like x-dead-letter-exchange)
     * @return void
     */
    public function consume(
        string $queueName, 
        callable $callback, 
        array $bindingKeys = [],
        bool $autoAck = false,
        array $arguments = []
    ): void;

    /**
     * Get a single message from a queue (non-blocking)
     *
     * @param string $queueName Queue name to get message from
     * @return AMQPMessage|null The message or null if no message is available
     */
    public function getMessageFromQueue(string $queueName): ?AMQPMessage;

    /**
     * Acknowledge a message
     *
     * @param AMQPMessage $message
     * @return void
     */
    public function acknowledge(AMQPMessage $message): void;

    /**
     * Reject a message
     *
     * @param AMQPMessage $message
     * @param bool $requeue Whether to requeue the message
     * @return void
     */
    public function reject(AMQPMessage $message, bool $requeue = false): void;
}
