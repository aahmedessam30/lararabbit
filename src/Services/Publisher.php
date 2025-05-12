<?php

namespace AhmedEssam\LaraRabbit\Services;

use AhmedEssam\LaraRabbit\Contracts\ConnectionManagerInterface;
use AhmedEssam\LaraRabbit\Contracts\PublisherInterface;
use Exception;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

class Publisher implements PublisherInterface
{
    /**
     * @var ConnectionManagerInterface
     */
    protected ConnectionManagerInterface $connectionManager;

    /**
     * Create a publisher
     *
     * @param ConnectionManagerInterface $connectionManager
     */
    public function __construct(ConnectionManagerInterface $connectionManager)
    {
        $this->connectionManager = $connectionManager;
    }

    /**
     * Publish a message to the exchange
     *
     * @param string $routingKey Routing key (e.g., booking.created, payment.confirmed)
     * @param array $data Data to be sent
     * @param array $properties Additional message properties
     * @return bool Success or failure
     */
    public function publish(string $routingKey, array $data, array $properties = []): bool
    {
        try {
            $channel      = $this->connectionManager->getChannel();
            $exchangeName = $this->connectionManager->getExchangeName();

            // Create default message properties
            $defaultProperties = [
                'content_type'  => 'application/json',
                'message_id'    => $properties['message_id'] ?? $this->generateMessageId(),
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            ];

            // Merge with custom properties
            $messageProperties = array_merge($defaultProperties, $properties);

            // Add application headers if provided
            if (isset($properties['headers']) && is_array($properties['headers'])) {
                $messageProperties['application_headers'] = new AMQPTable($properties['headers']);
                unset($properties['headers']);
            }

            // Create message
            $message = new AMQPMessage(
                json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $messageProperties
            );

            // Publish message
            $channel->basic_publish($message, $exchangeName, $routingKey);

            if (config('rabbitmq.debug')) {
                Log::debug("Published message to exchange '{$exchangeName}' with routing key '{$routingKey}'", [
                    'data_size' => strlen(json_encode($data)),
                ]);
            }

            return true;
        } catch (Exception $e) {
            Log::error('Failed to publish message: ' . $e->getMessage(), [
                'routing_key' => $routingKey,
                'exchange' => $this->connectionManager->getExchangeName(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Publish a batch of messages to the exchange
     *
     * @param array $messages Array of message data [routingKey, data, properties]
     * @return bool Success or failure
     */
    public function publishBatch(array $messages): bool
    {
        try {
            $channel      = $this->connectionManager->getChannel();
            $exchangeName = $this->connectionManager->getExchangeName();

            // Start transaction
            $channel->tx_select();

            foreach ($messages as $message) {
                // Extract message components
                $routingKey = $message['routing_key'] ?? null;
                $data = $message['data'] ?? [];
                $properties = $message['properties'] ?? [];

                if (!$routingKey) {
                    throw new Exception('Missing routing key for batch message');
                }

                // Create default message properties
                $defaultProperties = [
                    'content_type' => 'application/json',
                    'message_id'   => $properties['message_id'] ?? $this->generateMessageId(),
                    'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                ];

                // Merge with custom properties
                $messageProperties = array_merge($defaultProperties, $properties);

                // Add application headers if provided
                if (isset($properties['headers']) && is_array($properties['headers'])) {
                    $messageProperties['application_headers'] = new AMQPTable($properties['headers']);
                    unset($properties['headers']);
                }

                // Create message
                $amqpMessage = new AMQPMessage(
                    json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    $messageProperties
                );

                // Publish message
                $channel->basic_publish($amqpMessage, $exchangeName, $routingKey);
            }

            // Commit transaction
            $channel->tx_commit();

            if (config('rabbitmq.debug')) {
                Log::debug("Published batch of " . count($messages) . " messages to exchange '{$exchangeName}'");
            }

            return true;
        } catch (Exception $e) {
            // Rollback transaction if possible
            try {
                if (isset($channel) && $channel->is_open()) {
                    $channel->tx_rollback();
                }
            } catch (Exception $rollbackException) {
                Log::error('Failed to rollback transaction: ' . $rollbackException->getMessage());
            }

            Log::error('Failed to publish batch of messages: ' . $e->getMessage(), [
                'message_count' => count($messages),
                'exchange' => $this->connectionManager->getExchangeName(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Generate a unique message ID
     *
     * @return string
     */
    public function generateMessageId(): string
    {
        return uniqid("msg_", true) . '-' . time() . '-' . bin2hex(random_bytes(5));
    }
}