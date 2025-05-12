<?php

namespace AhmedEssam\LaraRabbit\Services\Serializers;

use AhmedEssam\LaraRabbit\Contracts\SerializerInterface;
use Exception;
use MessagePack\MessagePack;
use MessagePack\PackOptions;
use MessagePack\UnpackOptions;

class MessagePackSerializer implements SerializerInterface
{
    /**
     * @var MessagePack
     */
    protected $packer;
    
    /**
     * MessagePackSerializer constructor.
     */
    public function __construct()
    {
        // If MessagePack extension is not available, throw an exception
        if (!extension_loaded('msgpack') && !class_exists('MessagePack\MessagePack')) {
            throw new Exception('MessagePack extension or library is not available');
        }
    }
    
    /**
     * Serialize data to MessagePack format
     *
     * @param mixed $data
     * @return string
     */    public function serialize($data): string
    {
        // Use the extension if available, otherwise use the PHP library
        if (function_exists('\msgpack_pack')) {
            return \msgpack_pack($data);
        }
        
        return MessagePack::pack($data, PackOptions::FORCE_STR);
    }

    /**
     * Deserialize MessagePack data
     *
     * @param string $data
     * @return mixed
     */
    public function deserialize(string $data)
    {
        // Use the extension if available, otherwise use the PHP library
        if (function_exists('msgpack_unpack')) {
            return msgpack_unpack($data);
        }
        
        return MessagePack::unpack($data. UnpackOptions::BIGINT_AS_STR);
    }
}
