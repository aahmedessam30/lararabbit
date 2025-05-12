<?php

namespace AhmedEssam\LaraRabbit\Tests\Unit;

use AhmedEssam\LaraRabbit\Tests\TestCase;
use AhmedEssam\LaraRabbit\Services\Serializers\JsonSerializer;
use AhmedEssam\LaraRabbit\Services\Serializers\MessagePackSerializer;
use AhmedEssam\LaraRabbit\Services\Serializers\SerializerFactory;

class BasicTest extends TestCase
{
    /**
     * A basic test to ensure our test setup is working.
     */
    public function test_the_test_environment_is_working()
    {
        $this->assertTrue(true);
    }
    
    /**
     * Test JSON serializer works properly.
     */
    public function test_json_serializer()
    {
        $serializer = new JsonSerializer();
        $data = ['order_id' => 123, 'customer_id' => 456];
        
        $serialized = $serializer->serialize($data);
        $deserialized = $serializer->deserialize($serialized);
        
        $this->assertEquals($data, $deserialized);
    }
    
    /**
     * Test Serializer Factory returns correct serializer instance.
     */
    public function test_serializer_factory()
    {
        $jsonSerializer = SerializerFactory::create('json');
        $this->assertInstanceOf(JsonSerializer::class, $jsonSerializer);
        
        if (class_exists('MessagePack\Packer')) {
            $msgpackSerializer = SerializerFactory::create('msgpack');
            $this->assertInstanceOf(MessagePackSerializer::class, $msgpackSerializer);
        }
    }
}
