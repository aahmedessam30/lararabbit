<?php

namespace AhmedEssam\LaraRabbit\Services;

use Exception;
use Throwable;
use PhpAmqpLib\Message\AMQPMessage;
use Illuminate\Support\Facades\Log;
use AhmedEssam\LaraRabbit\Contracts\ConsumerInterface;
use AhmedEssam\LaraRabbit\Contracts\PublisherInterface;
use AhmedEssam\LaraRabbit\Contracts\SerializerInterface;
use AhmedEssam\LaraRabbit\Contracts\RabbitMQServiceInterface;
use AhmedEssam\LaraRabbit\Contracts\MessageValidatorInterface;
use AhmedEssam\LaraRabbit\Contracts\ConnectionManagerInterface;
use AhmedEssam\LaraRabbit\Services\Telemetry\Telemetry;
use AhmedEssam\LaraRabbit\Services\Resilience\RetryPolicy;
use AhmedEssam\LaraRabbit\Services\Resilience\CircuitBreaker;
use AhmedEssam\LaraRabbit\Services\Serializers\JsonSerializer;
use AhmedEssam\LaraRabbit\Services\Serializers\SerializerFactory;
use AhmedEssam\LaraRabbit\Exceptions\MessageValidationException;
use AhmedEssam\LaraRabbit\Exceptions\CircuitBreakerOpenException;

class RabbitMQService implements RabbitMQServiceInterface
{
    /**
     * @var ConnectionManagerInterface
     */
    protected ConnectionManagerInterface $connectionManager;

    /**
     * @var PublisherInterface
     */
    protected PublisherInterface $publisher;

    /**
     * @var ConsumerInterface
     */
    protected ConsumerInterface $consumer;

    /**
     * @var SerializerInterface
     */
    protected SerializerInterface $serializer;

    /**
     * @var MessageValidatorInterface|null
     */
    protected ?MessageValidatorInterface $validator;

    /**
     * @var RetryPolicy
     */
    protected RetryPolicy $retryPolicy;

    /**
     * @var CircuitBreaker
     */
    protected CircuitBreaker $circuitBreaker;

    /**
     * @var Telemetry
     */
    protected Telemetry $telemetry;

    /**
     * @var string
     */
    protected string $serializationFormat = 'json';

    /**
     * Create a new RabbitMQ service instance
     *
     * @param ConnectionManagerInterface $connectionManager
     * @param PublisherInterface $publisher
     * @param ConsumerInterface $consumer
     * @param MessageValidatorInterface|null $validator
     */
    public function __construct(
        ConnectionManagerInterface $connectionManager,
        PublisherInterface $publisher,
        ConsumerInterface $consumer,
        ?MessageValidatorInterface $validator = null
    ) {
        $this->connectionManager = $connectionManager;
        $this->publisher         = $publisher;
        $this->consumer          = $consumer;
        $this->validator         = $validator;

        $this->initializeComponents();
    }

    /**
     * Initialize components with default configurations
     *
     * @return void
     */
    protected function initializeComponents(): void
    {
        // Create default serializer
        $this->serializer = new JsonSerializer();

        // Initialize retry policy with default settings
        $this->retryPolicy = new RetryPolicy(
            config('rabbitmq.resilience.max_attempts', 3),
            config('rabbitmq.resilience.base_delay_ms', 100),
            config('rabbitmq.resilience.max_delay_ms', 5000)
        );

        // Initialize circuit breaker
        $this->circuitBreaker = new CircuitBreaker(
            'rabbitmq-publisher',
            config('rabbitmq.resilience.failure_threshold', 5),
            config('rabbitmq.resilience.reset_timeout', 30)
        );

        // Initialize telemetry
        $this->telemetry = new Telemetry();
    }

    /**
     * Get the connection manager
     *
     * @return ConnectionManagerInterface
     */
    public function getConnectionManager(): ConnectionManagerInterface
    {
        return $this->connectionManager;
    }

    /**
     * Get the publisher
     *
     * @return PublisherInterface
     */
    public function getPublisher(): PublisherInterface
    {
        return $this->publisher;
    }

    /**
     * Get the consumer
     *
     * @return ConsumerInterface
     */
    public function getConsumer(): ConsumerInterface
    {
        return $this->consumer;
    }

    /**
     * Set the serialization format
     *
     * @param string $format
     * @return $this
     */
    public function setSerializationFormat(string $format): self
    {
        $this->serializationFormat = $format;
        $this->serializer = SerializerFactory::create($format);
        return $this;
    }

    /**
     * Validate message against a schema
     *
     * @param array $data
     * @param string $schemaName
     * @return bool
     * @throws MessageValidationException
     */
    public function validateMessage(array $data, string $schemaName): bool
    {
        if (!$this->validator) {
            return true;
        }

        if (!$this->validator->validate($data, $schemaName)) {
            $errors = $this->validator->getErrors();
            throw new MessageValidationException($errors);
        }

        return true;
    }

    /**
     * Publish a message to an exchange with a routing key
     *
     * @param string $routingKey The routing key for the message
     * @param array $data The message data
     * @param array $options Additional options for the message
     * @return bool
     * @throws CircuitBreakerOpenException If circuit is open
     */
    public function publish(string $routingKey, array $data, array $options = []): bool
    {
        $this->telemetry->startOperation('publish');

        try {
            // Validate message if schema provided
            $this->validateMessageIfNeeded($data, $options);

            return $this->circuitBreaker->execute(function () use ($routingKey, $data, $options) {
                return $this->retryPolicy->execute(function () use ($routingKey, $data, $options) {
                    $result = $this->publisher->publish($routingKey, $data, $options);

                    if (!$result) {
                        throw new Exception("Failed to publish message to {$routingKey}");
                    }

                    return true;
                });
            });
        } catch (CircuitBreakerOpenException $e) {
            $this->handlePublishException($e, $routingKey, ['circuit_state' => $this->circuitBreaker->getState()]);
            throw $e;
        } catch (Throwable $e) {
            $this->handlePublishException($e, $routingKey);
            return false;
        }

        $this->telemetry->recordSuccess([
            'routing_key' => $routingKey
        ]);

        return true;
    }

    /**
     * Validate message if schema is provided in options
     * 
     * @param array $data
     * @param array $options
     * @return void
     * @throws MessageValidationException
     */
    protected function validateMessageIfNeeded(array $data, array $options): void
    {
        if (isset($options['schema']) && $this->validator) {
            $this->validateMessage($data, $options['schema']);
        }
    }

    /**
     * Handle exceptions during publish operations
     * 
     * @param Throwable $e
     * @param string $routingKey
     * @param array $additionalContext
     * @return void
     */
    protected function handlePublishException(Throwable $e, string $routingKey, array $additionalContext = []): void
    {
        $context = array_merge([
            'routing_key' => $routingKey,
            'error' => $e->getMessage(),
            'exception' => get_class($e)
        ], $additionalContext);

        $this->telemetry->recordFailure($e, $context);
        Log::error('Failed to publish message: ' . $e->getMessage(), $context);
    }

    /**
     * Publish an event to the event exchange
     *
     * @param string $eventName The event name (used as routing key)
     * @param array $payload The event payload
     * @param array $options Additional options for the message
     * @return bool
     */
    public function publishEvent(string $eventName, array $payload, array $options = []): bool
    {
        try {
            $eventData = $this->prepareEventData($eventName, $payload);
            $options = $this->enhanceEventOptions($eventName, $options);

            return $this->publish($eventName, $eventData, $options);
        } catch (Throwable $e) {
            Log::error('Failed to publish event: ' . $e->getMessage(), [
                'event' => $eventName,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Prepare event data with metadata
     * 
     * @param string $eventName
     * @param array $payload
     * @return array
     */
    protected function prepareEventData(string $eventName, array $payload): array
    {
        return [
            'event' => $eventName,
            'timestamp' => microtime(true),
            'payload' => $payload,
        ];
    }

    /**
     * Enhance options with event-specific headers and correlation ID
     * 
     * @param string $eventName
     * @param array $options
     * @return array
     */
    protected function enhanceEventOptions(string $eventName, array $options): array
    {
        // Add correlation ID if available in current context
        if (!isset($options['correlation_id']) && app()->bound('request') && request()->hasHeader('X-Correlation-ID')) {
            $options['correlation_id'] = request()->header('X-Correlation-ID');
        }

        // Add event format headers
        $options['headers'] = array_merge($options['headers'] ?? [], [
            'event_type' => $eventName,
            'content_type' => "application/{$this->serializationFormat}",
            'serialization_format' => $this->serializationFormat
        ]);

        return $options;
    }

    /**
     * Publish a batch of messages to the exchange
     *
     * @param array $messages Array of message data [routingKey, data, properties]
     * @return bool Success or failure
     */
    public function publishBatch(array $messages): bool
    {
        $batchSize = config('rabbitmq.publisher.batch_size', 100);
        $this->telemetry->startOperation('publishBatch');

        try {
            $batchStats = $this->processBatchMessages($messages, $batchSize);

            $this->telemetry->recordSuccess([
                'total_messages' => $batchStats['total'],
                'processed_messages' => $batchStats['processed'],
                'failed_messages' => $batchStats['failed']
            ]);

            return $batchStats['allSuccessful'];
        } catch (Throwable $e) {
            $this->telemetry->recordFailure($e, [
                'total_messages' => count($messages)
            ]);

            Log::error('Failed to publish batch of messages: ' . $e->getMessage(), [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return false;
        }
    }

    /**
     * Process batch messages in chunks
     * 
     * @param array $messages
     * @param int $batchSize
     * @return array Statistics about batch processing
     */
    protected function processBatchMessages(array $messages, int $batchSize): array
    {
        $stats = [
            'total' => count($messages),
            'processed' => 0,
            'failed' => 0,
            'allSuccessful' => true
        ];

        // Process in smaller batches to optimize performance
        foreach (array_chunk($messages, $batchSize) as $batchIndex => $batch) {
            foreach ($batch as $message) {
                if (!$this->isValidBatchMessage($message)) {
                    Log::error('Invalid message format for batch publishing', [
                        'message' => $message
                    ]);
                    $stats['allSuccessful'] = false;
                    $stats['failed']++;
                    continue;
                }

                $routingKey = $message['routingKey'];
                $data = $message['data'];
                $properties = $message['properties'] ?? [];

                $success = $this->publish($routingKey, $data, $properties);

                if (!$success) {
                    $stats['allSuccessful'] = false;
                    $stats['failed']++;
                }

                $stats['processed']++;
            }

            $this->logBatchProgress($batchIndex, $stats, $batchSize);
        }

        return $stats;
    }

    /**
     * Check if a batch message has valid format
     * 
     * @param array $message
     * @return bool
     */
    protected function isValidBatchMessage(array $message): bool
    {
        return isset($message['routingKey']) && isset($message['data']);
    }

    /**
     * Log progress for large batches
     * 
     * @param int $batchIndex
     * @param array $stats
     * @param int $batchSize
     * @return void
     */
    protected function logBatchProgress(int $batchIndex, array $stats, int $batchSize): void
    {
        // Log progress for large batches
        if ($stats['total'] > $batchSize) {
            Log::info("Batch publishing progress", [
                'batch' => $batchIndex + 1,
                'processed' => $stats['processed'],
                'total' => $stats['total'],
                'failed' => $stats['failed']
            ]);
        }
    }

    /**
     * Close the RabbitMQ connection
     *
     * @return void
     */
    public function closeConnection(): void
    {
        $this->connectionManager->closeConnection();
    }

    /**
     * Set up a queue and bind it to the exchange
     *
     * @param string $queueName Queue name
     * @param array $bindingKeys Array of binding keys to bind the queue to
     * @param bool $durable Whether the queue should survive broker restarts
     * @param bool $autoDelete Whether the queue should be deleted when no longer used
     * @param array $arguments Additional queue arguments (like x-dead-letter-exchange)
     * @return RabbitMQServiceInterface
     */
    public function setupQueue(
        string $queueName,
        array $bindingKeys = [],
        bool $durable = true,
        bool $autoDelete = false,
        array $arguments = []
    ): RabbitMQServiceInterface {
        $this->consumer->setupQueue($queueName, $bindingKeys, $durable, $autoDelete, $arguments);
        return $this;
    }

    /**
     * Set up a dead letter queue for a source queue
     *
     * @param string $sourceQueue Source queue name
     * @param string $deadLetterQueue Dead letter queue name
     * @param array $bindingKeys Binding keys for the dead letter queue
     * @return RabbitMQServiceInterface
     */
    public function setupDeadLetterQueue(
        string $sourceQueue,
        string $deadLetterQueue,
        array $bindingKeys = []
    ): RabbitMQServiceInterface {
        // Set up the dead letter exchange and queue
        $deadLetterExchange = "{$deadLetterQueue}.exchange";

        // Declare dead letter exchange
        $channel = $this->connectionManager->getChannel();
        $channel->exchange_declare($deadLetterExchange, 'topic', false, true, false);

        // Declare dead letter queue
        $this->setupQueue($deadLetterQueue, $bindingKeys, true, false, []);

        // Update the source queue with dead letter settings
        $this->setupQueue($sourceQueue, [], true, false, [
            'x-dead-letter-exchange' => $deadLetterExchange,
            'x-dead-letter-routing-key' => $bindingKeys[0] ?? $sourceQueue
        ]);

        return $this;
    }

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
    ): void {
        $wrappedCallback = $this->createConsumerCallback($callback, $autoAck, $queueName);
        $this->consumer->consume($queueName, $wrappedCallback, $bindingKeys, $autoAck, $arguments);
    }

    /**
     * Create a wrapped callback for message consumption with error handling
     * 
     * @param callable $callback
     * @param bool $autoAck
     * @param string $queueName
     * @return callable
     */
    protected function createConsumerCallback(callable $callback, bool $autoAck, string $queueName): callable
    {
        return function ($data, AMQPMessage $message) use ($callback, $autoAck, $queueName) {
            $messageId = $this->getMessageId($message);
            $this->telemetry->startOperation('consume');

            try {
                // Process the message data
                if (!is_array($data)) {
                    $data = $this->deserializeMessageData($message);
                }

                // Execute the user callback with the deserialized data
                $result = $callback($data, $message);

                // Auto acknowledge if needed - only if autoAck is false and result is not false
                if (!$autoAck && $result !== false) {
                    $this->safeAcknowledge($message, $queueName, $messageId);
                }

                $this->telemetry->recordSuccess([
                    'queue' => $queueName,
                    'message_id' => $messageId
                ]);

                return $result;
            } catch (Throwable $e) {
                return $this->handleConsumerException($e, $message, $autoAck, $queueName, $messageId);
            }
        };
    }

    /**
     * Get message ID with fallback
     * 
     * @param AMQPMessage $message
     * @return string
     */
    protected function getMessageId(AMQPMessage $message): string
    {
        try {
            // Check if the message_id property exists in the properties array
            if (isset($message->get_properties()['message_id'])) {
                return $message->get('message_id');
            }
        } catch (Throwable $e) {
            Log::warning('Failed to get message ID: ' . $e->getMessage(), [
                'error'   => $e->getMessage(),
                'message' => $message->getBody()
            ]);
        }

        // Generate a unique ID if no message ID is available
        return $this->publisher->generateMessageId();
    }

    /**
     * Deserialize message data from AMQPMessage
     * 
     * @param AMQPMessage $message
     * @return array
     */
    protected function deserializeMessageData(AMQPMessage $message): array
    {
        $serializationFormat = $this->getMessageSerializationFormat($message);
        $serializer = SerializerFactory::create($serializationFormat);
        return $serializer->deserialize($message->getBody());
    }

    /**
     * Get serialization format from message with fallback
     * 
     * @param AMQPMessage $message
     * @return string
     */
    protected function getMessageSerializationFormat(AMQPMessage $message): string
    {
        try {
            $properties = $message->get_properties();
            if (
                isset($properties['application_headers']) &&
                is_array($properties['application_headers']) &&
                isset($properties['application_headers']['serialization_format'])
            ) {
                return $properties['application_headers']['serialization_format'];
            }
        } catch (Throwable $e) {
            // Ignore exception and use fallback
        }

        // Default to JSON
        return 'json';
    }

    /**
     * Safely acknowledge a message with error handling
     * 
     * @param AMQPMessage $message
     * @param string $queueName
     * @param string $messageId
     * @return void
     */
    protected function safeAcknowledge(AMQPMessage $message, string $queueName, string $messageId): void
    {
        try {
            $this->acknowledge($message);
        } catch (Throwable $ackException) {
            $errorMessage = $ackException->getMessage();
            Log::warning('Failed to acknowledge message: ' . $errorMessage, [
                'queue' => $queueName,
                'message_id' => $messageId,
                'exception' => get_class($ackException),
                'error' => $errorMessage
            ]);
        }
    }

    /**
     * Handle exceptions during message consumption
     * 
     * @param Throwable $e
     * @param AMQPMessage $message
     * @param bool $autoAck
     * @param string $queueName
     * @param string $messageId
     * @return false
     */
    protected function handleConsumerException(
        Throwable $e,
        AMQPMessage $message,
        bool $autoAck,
        string $queueName,
        string $messageId
    ) {
        $this->telemetry->recordFailure($e, [
            'queue' => $queueName,
            'message_id' => $messageId
        ]);

        // Reject the message and do not requeue if it's a permanent failure
        if (!$autoAck) {
            $requeue = !($e instanceof MessageValidationException);
            $this->safeReject($message, $requeue, $queueName, $messageId);
        }

        Log::error('Error processing RabbitMQ message: ' . $e->getMessage(), [
            'queue' => $queueName,
            'message_id' => $messageId,
            'exception' => get_class($e),
            'trace' => $e->getTraceAsString()
        ]);

        // Re-throw exception if configured to do so
        if (config('rabbitmq.consumer.throw_exceptions', false)) {
            throw $e;
        }

        return false;
    }

    /**
     * Safely reject a message with proper error handling
     *
     * @param AMQPMessage $message The message to reject
     * @param bool $requeue Whether to requeue the message
     * @param string $queueName The name of the queue
     * @param string $messageId The ID of the message
     * @return void
     */
    protected function safeReject(AMQPMessage $message, bool $requeue, string $queueName, string $messageId): void
    {
        try {
            $this->reject($message, $requeue);
        } catch (Throwable $rejectException) {
            $errorMessage = $rejectException->getMessage();
            Log::warning('Failed to reject message: ' . $errorMessage, [
                'queue' => $queueName,
                'message_id' => $messageId,
                'exception' => get_class($rejectException),
                'error' => $errorMessage,
                'requeue' => $requeue
            ]);
        }
    }

    /**
     * Get a single message from a queue (non-blocking)
     *
     * @param string $queueName Queue name to get message from
     * @return AMQPMessage|null The message or null if no message is available
     */
    public function getMessageFromQueue(string $queueName): ?AMQPMessage
    {
        return $this->consumer->getMessageFromQueue($queueName);
    }

    /**
     * Acknowledge a message
     *
     * @param AMQPMessage $message
     * @return void
     */
    public function acknowledge(AMQPMessage $message): void
    {
        $this->consumer->acknowledge($message);
    }

    /**
     * Reject a message
     *
     * @param AMQPMessage $message
     * @param bool $requeue Whether to requeue the message
     * @return void
     */
    public function reject(AMQPMessage $message, bool $requeue = false): void
    {
        $this->consumer->reject($message, $requeue);
    }

    /**
     * Set up a predefined queue from configuration
     *
     * @param string $queueKey The key of the queue in the configuration (e.g., 'user_events')
     * @return RabbitMQServiceInterface
     * @throws \InvalidArgumentException If the queue configuration is not found
     */
    public function setupPredefinedQueue(string $queueKey): RabbitMQServiceInterface
    {
        $queueConfig = $this->getPredefinedQueueConfig($queueKey);

        // Extract queue configuration with defaults
        $queueName   = $queueConfig['name'] ?? $queueKey;
        $bindingKeys = $queueConfig['binding_keys'] ?? [];
        $durable     = $queueConfig['durable'] ?? true;
        $autoDelete  = $queueConfig['auto_delete'] ?? false;
        $arguments   = $queueConfig['arguments'] ?? [];

        // Set up the queue
        return $this->setupQueue($queueName, $bindingKeys, $durable, $autoDelete, $arguments);
    }

    /**
     * Consume messages from a predefined queue
     *
     * @param string $queueKey The key of the queue in the configuration (e.g., 'user_events')
     * @param callable $callback Callback function to process messages
     * @param bool|null $autoAck Whether to automatically acknowledge messages (null to use config default)
     * @param array $arguments Additional arguments for the consumption
     * @return void
     * @throws \InvalidArgumentException If the queue configuration is not found
     */
    public function consumeFromPredefinedQueue(
        string $queueKey,
        callable $callback,
        bool $autoAck = false,
        array $arguments = []
    ): void {
        $queueConfig = $this->getPredefinedQueueConfig($queueKey);

        // Extract queue configuration with defaults
        $queueName   = $queueConfig['name'] ?? $queueKey;
        $bindingKeys = $queueConfig['binding_keys'] ?? [];

        // Ensure the queue is set up before consuming
        $this->setupPredefinedQueue($queueKey);

        // Start consuming
        $this->consume($queueName, $callback, $bindingKeys, $autoAck, $arguments);
    }

    /**
     * Get predefined queue configuration
     * 
     * @param string $queueKey
     * @return array
     * @throws \InvalidArgumentException
     */
    protected function getPredefinedQueueConfig(string $queueKey): array
    {
        // Get queues configuration
        $queuesConfig = config('rabbitmq-queues', []);

        if (!isset($queuesConfig[$queueKey])) {
            throw new \InvalidArgumentException("Queue configuration for '{$queueKey}' not found");
        }

        return $queuesConfig[$queueKey];
    }
}
