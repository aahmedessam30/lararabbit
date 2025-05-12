<?php

namespace AhmedEssam\LaraRabbit\Services\Serializers;

use AhmedEssam\LaraRabbit\Contracts\SerializerInterface;
use Exception;

class JsonSerializer implements SerializerInterface
{
    /**
     * Serialize data to JSON string
     *
     * @param mixed $data
     * @return string
     * @throws Exception
     */
    public function serialize($data): string
    {
        $result = json_encode($data, JSON_UNESCAPED_UNICODE);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('JSON serialization error: ' . json_last_error_msg());
        }
        
        return $result;
    }

    /**
     * Deserialize JSON string to data
     *
     * @param string $data
     * @return mixed
     * @throws Exception
     */
    public function deserialize(string $data)
    {
        $result = json_decode($data, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('JSON deserialization error: ' . json_last_error_msg());
        }
        
        return $result;
    }
}
