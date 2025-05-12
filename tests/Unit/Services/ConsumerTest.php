<?php

namespace AhmedEssam\LaraRabbit\Tests\Unit\Services;

use AhmedEssam\LaraRabbit\Contracts\ConnectionManagerInterface;
use AhmedEssam\LaraRabbit\Services\Consumer;
use AhmedEssam\LaraRabbit\Tests\TestCase;
use Mockery;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use ReflectionClass;

class ConsumerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test setupQueue method with default parameters.
     */
    public function test_setup_queue_with_default_parameters()
    {
        // Create mocks
        $mockChannel = Mockery::mock(AMQPChannel::class);
        $mockConnectionManager = Mockery::mock(ConnectionManagerInterface::class);
        
        // Set expectations
        $mockConnectionManager->shouldReceive('getChannel')
            ->once()
            ->andReturn($mockChannel);
            
        $mockConnectionManager->shouldReceive('getExchangeName')
            ->once()
            ->andReturn('test_exchange');
            
        $mockChannel->shouldReceive('queue_declare')
            ->once()
            ->withArgs(function ($queueName, $passive, $durable, $exclusive, $autoDelete) {
                return $queueName === 'test_queue' &&
                       $passive === false &&
                       $durable === true &&
                       $exclusive === false &&
                       $autoDelete === false;
            })
            ->andReturn(['test_queue', 0, 0]);
        
        $mockChannel->shouldReceive('queue_bind')
            ->once()
            ->withArgs(function ($queueName, $exchangeName, $bindingKey) {
                return $queueName === 'test_queue' &&
                       $exchangeName === 'test_exchange' &&
                       $bindingKey === 'test.key';
            });
            
        // Create consumer
        $consumer = new Consumer($mockConnectionManager);
        
        // Test setup queue
        $result = $consumer->setupQueue('test_queue', ['test.key']);
        
        // Assert fluent interface returns self
        $this->assertSame($consumer, $result);
        
        // Check configured queues property
        $reflection = new ReflectionClass($consumer);
        $configuredQueuesProperty = $reflection->getProperty('configuredQueues');
        $configuredQueuesProperty->setAccessible(true);
        $configuredQueues = $configuredQueuesProperty->getValue($consumer);
        
        $this->assertArrayHasKey('test_queue', $configuredQueues);
        $this->assertEquals(['test.key'], $configuredQueues['test_queue']['binding_keys']);
        $this->assertTrue($configuredQueues['test_queue']['durable']);
        $this->assertFalse($configuredQueues['test_queue']['auto_delete']);
    }
    
    /**
     * Test setupQueue method with custom parameters.
     */
    public function test_setup_queue_with_custom_parameters()
    {
        // Create mocks
        $mockChannel = Mockery::mock(AMQPChannel::class);
        $mockConnectionManager = Mockery::mock(ConnectionManagerInterface::class);
        
        // Set expectations
        $mockConnectionManager->shouldReceive('getChannel')
            ->once()
            ->andReturn($mockChannel);
            
        $mockConnectionManager->shouldReceive('getExchangeName')
            ->once()
            ->andReturn('test_exchange');
            
        $mockChannel->shouldReceive('queue_declare')
            ->once()
            ->withArgs(function ($queueName, $passive, $durable, $exclusive, $autoDelete) {
                return $queueName === 'test_queue' &&
                       $passive === false &&
                       $durable === false &&
                       $exclusive === false &&
                       $autoDelete === true;
            })
            ->andReturn(['test_queue', 0, 0]);
        
        $mockChannel->shouldReceive('queue_bind')
            ->times(2) // Binding two keys
            ->andReturnNull();
            
        // Create consumer
        $consumer = new Consumer($mockConnectionManager);
        
        // Test setup queue with custom parameters
        $result = $consumer->setupQueue(
            'test_queue', 
            ['test.key1', 'test.key2'], 
            false, // durable
            true   // autoDelete
        );
        
        // Assert fluent interface returns self
        $this->assertSame($consumer, $result);
    }
    
    /**
     * Test setupQueue handles exceptions.
     */
    public function test_setup_queue_handles_exceptions()
    {
        // Create mocks
        $mockConnectionManager = Mockery::mock(ConnectionManagerInterface::class);
        
        // Set expectations to throw exception
        $mockConnectionManager->shouldReceive('getChannel')
            ->once()
            ->andThrow(new \Exception('Channel error'));
            
        $mockConnectionManager->shouldReceive('getExchangeName')
            ->zeroOrMoreTimes()
            ->andReturn('test_exchange');
            
        // Create consumer
        $consumer = new Consumer($mockConnectionManager);
        
        // Test setup queue with exception
        $this->expectException(\Exception::class);
        $consumer->setupQueue('test_queue', ['test.key']);
    }

    /**
     * Test consume method sets up an unconsumed queue with binding keys.
     */
    public function test_consume_sets_up_queue_when_not_configured()
    {
        // Create mocks
        $mockChannel = Mockery::mock(AMQPChannel::class);
        $mockConnectionManager = Mockery::mock(ConnectionManagerInterface::class);
        
        // Set expectations for queue setup
        $mockConnectionManager->shouldReceive('getChannel')
            ->twice()  // Called once for setupQueue and once for consume
            ->andReturn($mockChannel);
            
        $mockConnectionManager->shouldReceive('getExchangeName')
            ->once()
            ->andReturn('test_exchange');
            
        $mockChannel->shouldReceive('queue_declare')
            ->once()
            ->andReturn(['test_queue', 0, 0]);
            
        $mockChannel->shouldReceive('queue_bind')
            ->once();
            
        // Setup for consume expectations
        $mockChannel->shouldReceive('basic_consume')
            ->once()
            ->withArgs(function ($queue, $consumerTag, $noLocal, $noAck, $exclusive, $noWait, $callback) {
                return $queue === 'test_queue' && 
                       $noAck === false && 
                       is_callable($callback);
            });
            
        $mockChannel->shouldReceive('is_consuming')
            ->once()
            ->andReturn(false);
            
        // Create consumer
        $consumer = new Consumer($mockConnectionManager);
        
        // Test consume with auto-setup queue
        $consumer->consume('test_queue', function($msg) {
            return true;
        }, ['test.binding.key']);
        
        // Verify that the method completed without exceptions
        $this->assertTrue(true);
    }

    /**
     * Test getMessageFromQueue method.
     */
    public function test_get_message_from_queue()
    {
        // Create mocks
        $mockChannel = Mockery::mock(AMQPChannel::class);
        $mockConnectionManager = Mockery::mock(ConnectionManagerInterface::class);
        $mockMessage = Mockery::mock(AMQPMessage::class);
        
        // Configure expectations for success path
        $mockConnectionManager->shouldReceive('getChannel')
            ->once()
            ->andReturn($mockChannel);
            
        $mockChannel->shouldReceive('is_open')
            ->once()
            ->andReturn(true);
            
        $mockChannel->shouldReceive('basic_get')
            ->once()
            ->withArgs(function ($queueName, $noAck) {
                return $queueName === 'test_queue' && $noAck === false;
            })
            ->andReturn($mockMessage);
            
        // Create consumer
        $consumer = new Consumer($mockConnectionManager);
        
        // Test getMessageFromQueue
        $result = $consumer->getMessageFromQueue('test_queue');
        
        // Assert
        $this->assertSame($mockMessage, $result);
    }

    /**
     * Test acknowledge method.
     */
    public function test_acknowledge_message()
    {
        // Create mocks
        $mockChannel = Mockery::mock(AMQPChannel::class);
        $mockMessage = Mockery::mock(AMQPMessage::class);
        $mockConnectionManager = Mockery::mock(ConnectionManagerInterface::class);
        
        // Set expectations for validateMessageOperation
        $mockMessage->shouldReceive('getChannel')
            ->once()
            ->andReturn($mockChannel);
            
        $mockMessage->shouldReceive('getChannel')
            ->once()
            ->andReturn($mockChannel);
            
        $mockChannel->shouldReceive('is_open')
            ->once()
            ->andReturn(true);
            
        $mockMessage->shouldReceive('getDeliveryTag')
            ->twice() // Once in validation, once in acknowledge
            ->andReturn(123);
            
        $mockChannel->shouldReceive('basic_ack')
            ->once()
            ->withArgs(function ($deliveryTag) {
                return $deliveryTag === 123;
            });
            
        // Create consumer
        $consumer = new Consumer($mockConnectionManager);
        
        // Test acknowledge
        $consumer->acknowledge($mockMessage);
        
        // Add an assertion to avoid risky test warning
        $this->assertTrue(true);
    }

    /**
     * Test reject method.
     */
    public function test_reject_message()
    {
        // Create mocks
        $mockChannel = Mockery::mock(AMQPChannel::class);
        $mockMessage = Mockery::mock(AMQPMessage::class);
        $mockConnectionManager = Mockery::mock(ConnectionManagerInterface::class);
        
        // Set expectations for validateMessageOperation
        $mockMessage->shouldReceive('getChannel')
            ->once()
            ->andReturn($mockChannel);
            
        $mockMessage->shouldReceive('getChannel')
            ->once()
            ->andReturn($mockChannel);
            
        $mockChannel->shouldReceive('is_open')
            ->once()
            ->andReturn(true);
            
        $mockMessage->shouldReceive('getDeliveryTag')
            ->twice() // Once in validation, once in reject
            ->andReturn(123);
            
        $mockChannel->shouldReceive('basic_reject')
            ->once()
            ->withArgs(function ($deliveryTag, $requeue) {
                return $deliveryTag === 123 && $requeue === true;
            });
            
        // Create consumer
        $consumer = new Consumer($mockConnectionManager);
        
        // Test reject with requeue
        $consumer->reject($mockMessage, true);
        
        // Add an assertion to avoid risky test warning
        $this->assertTrue(true);
    }
}
