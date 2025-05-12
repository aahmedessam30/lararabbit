<?php

namespace AhmedEssam\LaraRabbit\Services\Serializers;

use AhmedEssam\LaraRabbit\Contracts\SerializerInterface;
use InvalidArgumentException;

class SerializerFactory
{
    /**
     * Available serializers
     */
    const FORMAT_JSON = 'json';
    const FORMAT_MSGPACK = 'msgpack';
    
    /**
     * Create a serializer instance based on the format
     *
     * @param string $format
     * @return SerializerInterface
     * @throws InvalidArgumentException
     */
    public static function create(string $format): SerializerInterface
    {
        return match ($format) {
            self::FORMAT_JSON => new JsonSerializer(),
            self::FORMAT_MSGPACK => new MessagePackSerializer(),
            default => throw new InvalidArgumentException("Unsupported serialization format: {$format}"),
        };
    }
}
