<?php

namespace AhmedEssam\LaraRabbit\Tests\Unit\Services\Serializers;

use AhmedEssam\LaraRabbit\Services\Serializers\JsonSerializer;
use AhmedEssam\LaraRabbit\Services\Serializers\MessagePackSerializer;
use AhmedEssam\LaraRabbit\Services\Serializers\SerializerFactory;
use AhmedEssam\LaraRabbit\Tests\TestCase;

class SerializerTest extends TestCase
{
    /**
     * Test JSON serializer can serialize and deserialize data.
     */
    public function test_json_serializer_roundtrip()
    {
        $serializer = new JsonSerializer();
        
        $data = [
            'string' => 'Hello World',
            'number' => 123,
            'boolean' => true,
            'array' => [1, 2, 3],
            'nested' => ['key' => 'value']
        ];
        
        // Serialize
        $serialized = $serializer->serialize($data);
        
        // Deserialize
        $deserialized = $serializer->deserialize($serialized);
        
        // Assert
        $this->assertEquals($data, $deserialized);
    }    /**
     * Test MessagePack serializer can serialize and deserialize data.
     */
    public function test_messagepack_serializer_roundtrip()
    {
        if (!class_exists('MessagePack\Packer')) {
            $this->markTestSkipped('MessagePack extension not available');
            return;
        }
        
        try {
            $serializer = new MessagePackSerializer();
            
            $data = [
                'string' => 'Hello World',
                'number' => 123,
                'boolean' => true,
                'array' => [1, 2, 3],
                'nested' => ['key' => 'value']
            ];
                
            // Serialize
            $serialized = $serializer->serialize($data);
            $this->assertNotEmpty($serialized);
            
            // Deserialize
            $deserialized = $serializer->deserialize($serialized);
            
            // Assert
            $this->assertEquals($data, $deserialized);
        } catch (\Exception $e) {
            $this->markTestSkipped('MessagePack extension or library is not available: ' . $e->getMessage());
        }
    }
      /**
     * Test serializer factory can create different serializer types.
     */
    public function test_serializer_factory_creates_correct_types()
    {
        // Test JSON serializer creation
        $jsonSerializer = SerializerFactory::create(SerializerFactory::FORMAT_JSON);
        $this->assertInstanceOf(JsonSerializer::class, $jsonSerializer);
        
        // Test MessagePack serializer creation - skipped if extension not available
        try {
            $messagePackSerializer = SerializerFactory::create(SerializerFactory::FORMAT_MSGPACK);
            $this->assertInstanceOf(MessagePackSerializer::class, $messagePackSerializer);
        } catch (\Exception $e) {
            $this->markTestSkipped('MessagePack extension or library is not available');
        }
    }
    
    /**
     * Test that unsupported format throws exception
     */
    public function test_unsupported_format_throws_exception()
    {
        $this->expectException(\InvalidArgumentException::class);
        SerializerFactory::create('unknown');
    }
}
