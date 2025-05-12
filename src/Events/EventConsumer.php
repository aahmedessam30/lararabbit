<?php

namespace AhmedEssam\LaraRabbit\Events;

use AhmedEssam\LaraRabbit\Contracts\RabbitMQServiceInterface;
use Exception;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Message\AMQPMessage;

class EventConsumer
{
    /**
     * @var RabbitMQServiceInterface
     */
    protected RabbitMQServiceInterface $rabbitMQService;

    /**
     * @var Dispatcher
     */
    protected Dispatcher $events;

    /**
     * @var array
     */
    protected array $eventMap;

    /**
     * Create an event consumer
     *
     * @param RabbitMQServiceInterface $rabbitMQService
     * @param Dispatcher $events
     * @param array $eventMap Map of routing patterns to event classes
     */
    public function __construct(
        RabbitMQServiceInterface $rabbitMQService,
        Dispatcher $events,
        array $eventMap = []
    ) {
        $this->rabbitMQService = $rabbitMQService;
        $this->events = $events;
        $this->eventMap = $eventMap;
    }

    /**
     * Start consuming messages and dispatch them as events
     *
     * @param string $queueName Queue name to consume from
     * @param array $bindingKeys Array of binding keys to bind the queue to
     * @return void
     */
    public function consumeEvents(string $queueName, array $bindingKeys = []): void
    {
        $this->rabbitMQService->consume($queueName, function (AMQPMessage $message) {
            return $this->processMessage($message);
        }, $bindingKeys);
    }

    /**
     * Process a message and dispatch it as an event
     *
     * @param AMQPMessage $message
     * @return bool Success or failure
     */
    public function processMessage(AMQPMessage $message): bool
    {
        try {
            // Parse message body
            $body = json_decode($message->getBody(), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Failed to decode message body: ' . json_last_error_msg());
            }

            // Extract metadata
            $metadata = $body['_metadata'] ?? [];
            $eventClass = $metadata['event_class'] ?? null;

            // Get routing key from message
            $routingKey = $message->getRoutingKey();

            // Determine event class if not provided in metadata
            if (!$eventClass) {
                $eventClass = $this->determineEventClass($routingKey, $body);
            }

            // If no event class could be determined, log and return true (acknowledge)
            if (!$eventClass) {
                Log::warning("Could not determine event class for message with routing key: {$routingKey}");
                return true;
            }

            // Remove metadata from body
            if (isset($body['_metadata'])) {
                unset($body['_metadata']);
            }

            // Dispatch the event
            $this->dispatchEvent($eventClass, $body);

            return true;
        } catch (Exception $e) {
            Log::error('Error processing message: ' . $e->getMessage(), [
                'exception' => $e,
                'routing_key' => $message->getRoutingKey(),
                'delivery_tag' => $message->getDeliveryTag(),
            ]);

            return false;
        }
    }

    /**
     * Dispatch an event
     *
     * @param string $eventClass
     * @param array $data
     * @return void
     */
    protected function dispatchEvent(string $eventClass, array $data): void
    {
        // Check if event class exists
        if (!class_exists($eventClass)) {
            Log::warning("Event class does not exist: {$eventClass}");
            return;
        }

        // Create event instance
        try {
            // If the event has a constructor that accepts an array, use it
            if (method_exists($eventClass, 'fromArray')) {
                $event = $eventClass::fromArray($data);
            } else {
                // Otherwise try to instantiate with data as constructor parameters
                $event = new $eventClass($data);
            }

            // Dispatch the event
            $this->events->dispatch($event);

            if (config('rabbitmq.debug')) {
                Log::debug("Dispatched event: {$eventClass}");
            }
        } catch (Exception $e) {
            Log::error("Failed to create or dispatch event {$eventClass}: " . $e->getMessage(), [
                'exception' => $e,
                'data' => $data,
            ]);
        }
    }

    /**
     * Determine the event class from routing key and message body
     *
     * @param string $routingKey
     * @param array $body
     * @return string|null The event class
     */
    protected function determineEventClass(string $routingKey, array $body): ?string
    {
        // Look for direct matches in event map
        if (isset($this->eventMap[$routingKey])) {
            return $this->eventMap[$routingKey];
        }

        // Look for pattern matches
        foreach ($this->eventMap as $pattern => $class) {
            if (strpos($pattern, '*') !== false) {
                $regex = '/^' . str_replace(['*', '.'], ['[^.]+', '\.'], $pattern) . '$/';
                if (preg_match($regex, $routingKey)) {
                    return $class;
                }
            }
        }

        return null;
    }

    /**
     * Add a mapping from routing key pattern to event class
     *
     * @param string $routingPattern Routing key pattern (can include *)
     * @param string $eventClass Event class to instantiate
     * @return self
     */
    public function addEventMapping(string $routingPattern, string $eventClass): self
    {
        $this->eventMap[$routingPattern] = $eventClass;
        return $this;
    }

    /**
     * Set multiple event mappings
     *
     * @param array $eventMap
     * @return self
     */
    public function setEventMap(array $eventMap): self
    {
        $this->eventMap = $eventMap;
        return $this;
    }
}