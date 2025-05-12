<?php

namespace AhmedEssam\LaraRabbit\Contracts;

interface PublisherInterface
{
    /**
     * Publish a message to the exchange
     *
     * @param string $routingKey Routing key (e.g., booking.created, payment.confirmed)
     * @param array $data Data to be sent
     * @param array $properties Additional message properties
     * @return bool Success or failure
     */
    public function publish(string $routingKey, array $data, array $properties = []): bool;

    /**
     * Publish a batch of messages to the exchange
     *
     * @param array $messages Array of message data [routingKey, data, properties]
     * @return bool Success or failure
     */
    public function publishBatch(array $messages): bool;

    /**
     * Generate a unique message ID
     * 
     * @return string
     */
    public function generateMessageId(): string;
}