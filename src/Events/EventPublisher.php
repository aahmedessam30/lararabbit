<?php

namespace AhmedEssam\LaraRabbit\Events;

use AhmedEssam\LaraRabbit\Contracts\RabbitMQServiceInterface;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Str;

class EventPublisher
{
    /**
     * @var RabbitMQServiceInterface
     */
    protected RabbitMQServiceInterface $rabbitMQService;

    /**
     * @var array
     */
    protected array $eventMap;

    /**
     * Create an event publisher
     *
     * @param RabbitMQServiceInterface $rabbitMQService
     * @param array $eventMap Map of event classes to routing keys
     */
    public function __construct(
        RabbitMQServiceInterface $rabbitMQService,
        array $eventMap = []
    ) {
        $this->rabbitMQService = $rabbitMQService;
        $this->eventMap = $eventMap;
    }

    /**
     * Register event listeners
     *
     * @param Dispatcher $events
     * @return void
     */
    public function subscribe(Dispatcher $events): void
    {
        foreach ($this->eventMap as $event => $routingKey) {
            $events->listen($event, function ($event) use ($routingKey) {
                $this->publishEvent($event, $routingKey);
            });
        }
    }

    /**
     * Publish an event to RabbitMQ
     *
     * @param object $event The event object
     * @param string|null $routingKey Optional routing key override
     * @return bool Success or failure
     */
    public function publishEvent(object $event, ?string $routingKey = null): bool
    {
        // Get the event class name
        $eventClass = get_class($event);

        // Determine the routing key
        if (!$routingKey) {
            // Look up in the event map
            $routingKey = $this->eventMap[$eventClass] ?? null;

            // If not found, derive from class name
            if (!$routingKey) {
                $routingKey = $this->deriveRoutingKeyFromClassName($eventClass);
            }
        }

        // Convert event to array
        $data = $this->eventToArray($event);

        // Add metadata
        $data['_metadata'] = [
            'event_class' => $eventClass,
            'timestamp' => time(),
            'id' => (string) Str::uuid(),
        ];

        // Publish to RabbitMQ
        return $this->rabbitMQService->publish($routingKey, $data);
    }

    /**
     * Add an event to the event map
     *
     * @param string $eventClass
     * @param string $routingKey
     * @return self
     */
    public function addEvent(string $eventClass, string $routingKey): self
    {
        $this->eventMap[$eventClass] = $routingKey;
        return $this;
    }

    /**
     * Set multiple events in the event map
     *
     * @param array $eventMap
     * @return self
     */
    public function setEventMap(array $eventMap): self
    {
        $this->eventMap = $eventMap;
        return $this;
    }

    /**
     * Convert an event object to an array
     *
     * @param object $event
     * @return array
     */
    protected function eventToArray(object $event): array
    {
        // If the event has a toArray method, use it
        if (method_exists($event, 'toArray')) {
            return $event->toArray();
        }

        // Convert public properties to array
        $data = [];
        $reflectionClass = new \ReflectionClass($event);
        
        foreach ($reflectionClass->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            $propertyName = $property->getName();
            $data[$propertyName] = $property->getValue($event);
        }

        return $data;
    }

    /**
     * Derive a routing key from a class name
     *
     * @param string $className
     * @return string
     */
    protected function deriveRoutingKeyFromClassName(string $className): string
    {
        // Remove namespace
        $shortName = class_basename($className);
        
        // Remove "Event" suffix if it exists
        $shortName = preg_replace('/Event$/', '', $shortName);
        
        // Convert to snake case and lowercase
        $snakeCase = Str::snake($shortName);
        
        return strtolower($snakeCase);
    }
}