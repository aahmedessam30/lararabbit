<?php

namespace AhmedEssam\LaraRabbit\Contracts;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\AMQPStreamConnection;

interface ConnectionManagerInterface
{
    /**
     * Get or create a connection
     *
     * @return AMQPStreamConnection
     */
    public function getConnection(): AMQPStreamConnection;

    /**
     * Get or create a channel
     *
     * @return AMQPChannel
     */
    public function getChannel(): AMQPChannel;

    /**
     * Get the exchange name
     *
     * @return string
     */
    public function getExchangeName(): string;

    /**
     * Set the exchange name
     *
     * @param string $exchangeName
     * @return self
     */
    public function setExchangeName(string $exchangeName): self;

    /**
     * Close the RabbitMQ connection
     *
     * @return void
     */
    public function closeConnection(): void;

    /**
     * Reconnect to RabbitMQ
     *
     * @return bool True if reconnection was successful
     */
    public function reconnect(): bool;
}