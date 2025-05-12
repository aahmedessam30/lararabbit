<?php

namespace AhmedEssam\LaraRabbit\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Exception\AMQPChannelClosedException;
use AhmedEssam\LaraRabbit\Contracts\ConsumerInterface;
use PhpAmqpLib\Exception\AMQPConnectionClosedException;
use AhmedEssam\LaraRabbit\Contracts\ConnectionManagerInterface;

class Consumer implements ConsumerInterface
{
    /**
     * @var ConnectionManagerInterface
     */
    protected ConnectionManagerInterface $connectionManager;

    /**
     * @var array
     */
    protected array $configuredQueues = [];

    /**
     * @var string
     */
    private string $logChannel;

    /**
     * Create a consumer
     *
     * @param ConnectionManagerInterface $connectionManager
     */
    public function __construct(ConnectionManagerInterface $connectionManager)
    {
        $this->connectionManager = $connectionManager;
        $this->logChannel        = config('rabbitmq.logging.channel', 'rabbitmq');
    }

    /**
     * Log debug details if debug is enabled
     * 
     * This method only logs if the rabbitmq.debug configuration is true
     *
     * @param string $message Debug message
     * @param array $context Context information
     * @return void
     */
    protected function logDebug(string $message, array $context = []): void
    {
        if (config('rabbitmq.debug')) {
            Log::channel($this->logChannel)->debug($message, $context);
        }
    }

    /**
     * Set up a queue and bind it to the exchange
     *
     * This method:
     * 1. Declares a queue with the specified parameters
     * 2. Binds the queue to the exchange with the provided binding keys
     * 3. Tracks the configured queue in the internal registry
     *
     * @param string $queueName Queue name
     * @param array $bindingKeys Array of binding keys to bind the queue to
     * @param bool $durable Whether the queue should survive broker restarts
     * @param bool $autoDelete Whether the queue should be deleted when no longer used
     * @param array $arguments Additional queue arguments (like x-dead-letter-exchange)
     * @return self For method chaining
     * @throws Exception If queue setup fails
     */
    public function setupQueue(
        string $queueName,
        array $bindingKeys = [],
        bool $durable = true,
        bool $autoDelete = false,
        array $arguments = []
    ): self {
        try {
            $channel = $this->connectionManager->getChannel();
            $exchangeName = $this->connectionManager->getExchangeName();

            // Declare queue
            $channel->queue_declare(
                $queueName,
                false,  // passive
                $durable,
                false,  // exclusive
                $autoDelete,
                false,  // nowait
                $arguments
            );

            // Bind queue to exchange with routing keys
            foreach ($bindingKeys as $bindingKey) {
                $channel->queue_bind($queueName, $exchangeName, $bindingKey);
            }

            // Mark this queue as configured
            $this->configuredQueues[$queueName] = [
                'binding_keys' => $bindingKeys,
                'durable' => $durable,
                'auto_delete' => $autoDelete,
                'arguments' => $arguments,
            ];

            $this->logDebug("Set up queue '{$queueName}' bound to exchange '{$exchangeName}'", [
                'binding_keys' => $bindingKeys,
                'durable' => $durable,
                'auto_delete' => $autoDelete,
                'arguments' => $arguments,
            ]);

            return $this;
        } catch (Exception $e) {
            Log::channel($this->logChannel)->error('Failed to set up queue: ' . $e->getMessage(), [
                'queue' => $queueName,
                'exchange' => $this->connectionManager->getExchangeName(),
                'binding_keys' => $bindingKeys,
                'arguments' => $arguments,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Start consuming messages from a queue
     *
     * This method sets up a message consumer and processes messages from the queue.
     * It handles the following tasks:
     * - Sets up the queue if needed
     * - Creates a wrapped callback to handle message processing
     * - Manages the message loop with robust error handling
     * - Handles reconnection if the connection drops
     *     * @param string $queueName Queue name to consume from
     * @param callable $callback Callback function to process messages
     * @param array $bindingKeys Array of binding keys to bind the queue to (if not already bound)
     * @param bool $autoAck Whether to automatically acknowledge messages
     * @param array $arguments Additional queue arguments (like x-dead-letter-exchange)
     * @return void
     * @throws Exception If consumption fails and can't be recovered
     */
    public function consume(
        string $queueName,
        callable $callback,
        array $bindingKeys = [],
        bool $autoAck = false,
        array $arguments = []
    ): void {
        try {

            // Set up queue if not already done or if binding keys are provided
            if (
                !isset($this->configuredQueues[$queueName]) ||
                (!empty($bindingKeys) && $this->configuredQueues[$queueName]['binding_keys'] !== $bindingKeys)
            ) {
                $this->setupQueue($queueName, $bindingKeys, true, false, $arguments);            }

            $channel = $this->connectionManager->getChannel();

            // Configure QoS if specified in config
            $prefetchCount = config('rabbitmq.consumer.prefetch_count', 0);

            if ($prefetchCount > 0) {
                $channel->basic_qos(null, $prefetchCount, false);
            }

            // Create wrapped callback
            $wrappedCallback = $this->createMessageHandler($callback, $queueName, $autoAck);

            // Set up consumption on the channel
            $this->setupConsumption($channel, $queueName, $wrappedCallback, $autoAck);

            Log::channel($this->logChannel)->info("Started consuming from queue '{$queueName}'");

            // Process messages until channel is closed
            $this->processMessagesUntilClosed($channel, $queueName, $wrappedCallback);
        } catch (Exception $e) {
            Log::channel($this->logChannel)->error('Failed to consume from queue: ' . $e->getMessage(), [
                'queue' => $queueName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Process messages until the channel is closed
     *
     * This method handles the message loop and checks for channel closure.
     *
     * @param \PhpAmqpLib\Channel\AMQPChannel $channel Channel to process messages from
     * @param string $queueName Queue name for logging purposes
     * @param callable $callback Callback to handle messages
     * @return void
     */
    protected function processMessagesUntilClosed(\PhpAmqpLib\Channel\AMQPChannel $channel, string $queueName, callable $callback): void
    {        while ($channel->is_consuming()) {
            try {
                $channel->wait(null, false, config('rabbitmq.consumer.wait_timeout', 0));
            } catch (Exception $e) {
                $this->reconnectOrHandleException($e, $queueName, $callback, $channel, false);
            }
        }
    }

    /**
     * Handle reconnection or exception during message processing
     *     * @param Exception $e Exception thrown during message processing
     * @param string $queueName Queue name for logging purposes
     * @param callable $callback Callback to handle messages
     * @param \PhpAmqpLib\Channel\AMQPChannel $channel The channel that raised the exception
     * @param bool $autoAck Whether to automatically acknowledge messages
     * @return void
     */
    protected function reconnectOrHandleException(Exception $e, string $queueName, callable $callback, \PhpAmqpLib\Channel\AMQPChannel $channel = null, bool $autoAck = false): void
    {
        if ($e instanceof AMQPConnectionClosedException || $e instanceof AMQPChannelClosedException) {
            // Handle reconnection
            $this->handleReconnection($queueName, $callback, $autoAck);
        } else {
            // Log the error and exit the loop
            Log::channel($this->logChannel)->error('Error during message processing: ' . $e->getMessage(), [
                'queue' => $queueName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Check if configured to stop consumption on critical errors
            $stopOnCriticalError = config('rabbitmq.consumer.stop_on_critical_error', false);

            if ($stopOnCriticalError && $channel) {
                Log::channel($this->logChannel)->warning('Stopping message consumption due to critical error');
                $this->closeChannelGracefully($channel);
            }
        }
    }    /**
     * Create a callback handler that processes messages
     *
     * @param callable $callback User-provided callback that will receive the decoded data and raw message
     * @param string $queueName Queue name for logging purposes
     * @param bool $autoAck Whether to automatically acknowledge messages
     * @return callable The wrapped callback function
     */
    protected function createMessageHandler(callable $callback, string $queueName, bool $autoAck = false): callable
    {
        return function (AMQPMessage $message) use ($callback, $queueName, $autoAck) {
            try {
                // Decode JSON message body
                $data = json_decode($message->getBody(), true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception('Invalid JSON in message body: ' . json_last_error_msg());
                }

                $result = $callback($data, $message);
                
                // Auto-acknowledge if enabled and callback returned true or null
                if (!$autoAck && $result !== false) {
                    $this->acknowledge($message);
                }
                
                return $result;
            } catch (Exception $e) {
                Log::channel($this->logChannel)->error('Error processing message from queue: ' . $e->getMessage(), [
                    'queue' => $queueName,
                    'error' => $e->getMessage(),
                    'delivery_tag' => $message->getDeliveryTag() ?? 'unknown',
                    'trace' => $e->getTraceAsString(),
                    'body' => $message->getBody()
                ]);// Reject the message
                $requeue = config('rabbitmq.consumer.requeue_on_error', false);
                $this->reject($message, $requeue);

                // If configured to throw exceptions, rethrow it
                if (config('rabbitmq.consumer.throw_exceptions', false)) {
                    throw $e;
                }
            }
        };
    }

    /**     * Set up consumption on a channel
     *
     * @param \PhpAmqpLib\Channel\AMQPChannel $channel Channel to set up consumption on
     * @param string $queueName Queue name to consume from
     * @param callable $callback Callback to handle messages
     * @param bool $autoAck Whether to automatically acknowledge messages
     * @return void
     */
    protected function setupConsumption(\PhpAmqpLib\Channel\AMQPChannel $channel, string $queueName, callable $callback, bool $autoAck = false): void
    {
        $channel->basic_consume(
            $queueName,
            '',     // consumer tag - empty = auto-generated
            false,  // no local - don't receive messages published by this consumer
            $autoAck,  // no ack - whether to require explicit acknowledgements
            false,  // exclusive - only this consumer can access the queue
            false,  // no wait - don't wait for a response
            $callback
        );
    }

    /**     * Handle reconnection to RabbitMQ after connection failure
     * 
     * This method handles the full reconnection process including:
     * - Logging the disconnection
     * - Waiting for the configured reconnect delay
     * - Reconnecting to RabbitMQ
     * - Re-setting up the queue and consumption
     *
     * @param string $queueName Queue name to reconnect to
     * @param callable $callback Callback to handle messages
     * @param bool $autoAck Whether to automatically acknowledge messages
     * @return \PhpAmqpLib\Channel\AMQPChannel New channel after reconnection
     */
    protected function handleReconnection(string $queueName, callable $callback, bool $autoAck = false): \PhpAmqpLib\Channel\AMQPChannel
    {
        Log::channel($this->logChannel)->warning('Consumer channel or connection closed unexpectedly', [
            'queue' => $queueName
        ]);

        // Set up retry parameters
        $maxRetries = config('rabbitmq.consumer.reconnect_max_retries', 3);
        $reconnectDelay = config('rabbitmq.consumer.reconnect_delay', 5);
        $attempt = 0;
        $channel = null;

        while ($attempt < $maxRetries) {
            $attempt++;
            try {
                // Wait before attempting to reconnect
                $this->logDebug("Attempting to reconnect (attempt {$attempt}/{$maxRetries}) in {$reconnectDelay} seconds");
                sleep($reconnectDelay);

                // Reconnect and get new channel
                $this->connectionManager->reconnect();
                $channel = $this->connectionManager->getChannel();

                if (!$channel) {
                    throw new Exception("Failed to get channel after reconnection");
                }

                // Re-setup the queue with the same parameters
                $queueConfig = $this->configuredQueues[$queueName] ?? [];

                $this->setupQueue(
                    $queueName,
                    $queueConfig['binding_keys'] ?? [],
                    $queueConfig['durable'] ?? true,
                    $queueConfig['auto_delete'] ?? false,
                    $queueConfig['arguments'] ?? []
                );

                // Restart consuming
                $this->setupConsumption($channel, $queueName, $callback, $autoAck);

                // Log successful reconnection
                Log::channel($this->logChannel)->info("Successfully reconnected and resumed consuming from queue '{$queueName}'");

                return $channel;
            } catch (Exception $e) {
                Log::channel($this->logChannel)->error("Reconnection attempt {$attempt}/{$maxRetries} failed: " . $e->getMessage(), [
                    'queue' => $queueName,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                // Exponential backoff for reconnect delay
                $reconnectDelay *= 2;
            }
        }

        Log::channel($this->logChannel)->critical("Failed to reconnect after {$maxRetries} attempts", [
            'queue' => $queueName
        ]);

        // If we reach here, all reconnection attempts have failed
        throw new AMQPConnectionClosedException("Failed to reconnect after {$maxRetries} attempts");
    }

    /**
     * Attempt to gracefully close a channel
     * 
     * This method safely closes a channel without throwing exceptions,
     * and logs any errors that occur during the close operation.
     *
     * @param \PhpAmqpLib\Channel\AMQPChannel|null $channel The channel to close
     * @return void
     */
    protected function closeChannelGracefully(?\PhpAmqpLib\Channel\AMQPChannel $channel): void
    {
        if (!$channel) {
            return;
        }

        try {
            if ($channel->is_open()) {
                $channel->close();
            }
        } catch (Exception $closeException) {
            // Already closed or can't be closed, just log it
            $this->logDebug('Could not close channel: ' . $closeException->getMessage());
        }
    }

    /**
     * Get a single message from a queue (non-blocking)
     * 
     * This method performs a non-blocking fetch of a single message from the
     * specified queue. The message is not auto-acknowledged, so you must call
     * acknowledge() or reject() after processing.
     *
     * @param string $queueName Queue name to get message from
     * @return AMQPMessage|null The message or null if no message is available
     */
    public function getMessageFromQueue(string $queueName): ?AMQPMessage
    {
        try {
            $channel = $this->connectionManager->getChannel();

            if (!$channel || !$channel->is_open()) {
                Log::channel($this->logChannel)->warning('Cannot get message: Channel is not open', [
                    'queue' => $queueName
                ]);
                return null;
            }

            // Get a single message, don't auto-acknowledge
            $message = $channel->basic_get($queueName, false);

            if ($message instanceof AMQPMessage) {
                return $message;
            }

            return null;
        } catch (Exception $e) {
            Log::channel($this->logChannel)->error('Failed to get message from queue: ' . $e->getMessage(), [
                'queue' => $queueName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return null;
        }
    }

    /**
     * Validate if a message can be acknowledged or rejected
     * 
     * This method performs several validation checks to ensure a message
     * can be properly acknowledged or rejected:
     * - Ensures the channel is available and open
     * - Validates that the delivery tag exists and is valid
     * 
     * @param AMQPMessage $message The message to validate
     * @return array Associative array with 'valid' (boolean) and 'reason' (string|null) keys
     */
    protected function validateMessageOperation(AMQPMessage $message): array
    {
        $channel = $message->getChannel();
        $deliveryTag = $message->getDeliveryTag();

        if (!$channel) {
            return ['valid' => false, 'reason' => 'No channel available'];
        }

        if (!$channel->is_open()) {
            return ['valid' => false, 'reason' => 'Channel is not open'];
        }

        if (!$deliveryTag) {
            return ['valid' => false, 'reason' => 'No delivery tag'];
        }
        if (!is_numeric($deliveryTag) || $deliveryTag <= 0) {
            return ['valid' => false, 'reason' => 'Invalid delivery tag value'];
        }

        return ['valid' => true, 'reason' => null];
    }

    /**
     * Acknowledge a message
     *
     * @param AMQPMessage $message
     * @return void
     */
    public function acknowledge(AMQPMessage $message): void
    {
        try {
            // Validate the message can be acknowledged
            $validation = $this->validateMessageOperation($message);

            if (!$validation['valid']) {
                Log::channel($this->logChannel)->warning("Cannot acknowledge message: {$validation['reason']}", [
                    'delivery_tag' => $message->getDeliveryTag() ?? 'unknown'
                ]);
                return;
            }

            $channel = $message->getChannel();
            $deliveryTag = $message->getDeliveryTag();

            // Only attempt to acknowledge if conditions are met
            $channel->basic_ack($deliveryTag);

            // Log the successful acknowledgment
            $this->logDebug('Successfully acknowledged message', ['delivery_tag' => $deliveryTag]);
        } catch (Exception $e) {
            Log::channel($this->logChannel)->warning('Failed to acknowledge message: ' . $e->getMessage(), [
                'error' => $e->getMessage(),
                'delivery_tag' => $message->getDeliveryTag() ?? 'unknown',
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Reject a message
     *
     * @param AMQPMessage $message Message to reject
     * @param bool $requeue Whether to requeue the message
     * @return void
     */
    public function reject(AMQPMessage $message, bool $requeue = false): void
    {
        try {
            // Validate the message can be rejected
            $validation = $this->validateMessageOperation($message);

            if (!$validation['valid']) {
                Log::channel($this->logChannel)->warning("Cannot reject message: {$validation['reason']}", [
                    'delivery_tag' => $message->getDeliveryTag() ?? 'unknown',
                    'requeue' => $requeue
                ]);
                return;
            }

            $channel = $message->getChannel();
            $deliveryTag = $message->getDeliveryTag();

            // Only attempt to reject if conditions are met
            $channel->basic_reject($deliveryTag, $requeue);

            $this->logDebug('Successfully rejected message', [
                'delivery_tag' => $deliveryTag,
                'requeue' => $requeue
            ]);
        } catch (Exception $e) {
            Log::channel($this->logChannel)->warning('Failed to reject message: ' . $e->getMessage(), [
                'error' => $e->getMessage(),
                'delivery_tag' => $message->getDeliveryTag() ?? 'unknown',
                'requeue' => $requeue,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
