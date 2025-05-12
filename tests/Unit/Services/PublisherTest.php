<?php

namespace AhmedEssam\LaraRabbit\Tests\Unit\Services;

use AhmedEssam\LaraRabbit\Contracts\ConnectionManagerInterface;
use AhmedEssam\LaraRabbit\Services\Publisher;
use AhmedEssam\LaraRabbit\Tests\TestCase;
use Mockery;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

class PublisherTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test publishing a message successfully.
     */
    public function test_publish_success()
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
            
        $mockChannel->shouldReceive('basic_publish')
            ->once()
            ->withArgs(function (AMQPMessage $message, string $exchange, string $routingKey) {
                // Check that the message is set correctly
                $data = json_decode($message->getBody(), true);
                return $exchange === 'test_exchange' && 
                       $routingKey === 'test.route' &&
                       $data['key'] === 'value';
            })
            ->andReturnNull();
            
        // Create publisher
        $publisher = new Publisher($mockConnectionManager);
        
        // Test
        $result = $publisher->publish('test.route', ['key' => 'value']);
        
        // Assert
        $this->assertTrue($result);
    }
    
    /**
     * Test publishing a message with custom properties.
     */
    public function test_publish_with_custom_properties()
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
            
        $mockChannel->shouldReceive('basic_publish')
            ->once()
            ->withArgs(function (AMQPMessage $message, string $exchange, string $routingKey) {
                // Check the message properties
                $props = $message->get_properties();
                return $exchange === 'test_exchange' && 
                       $routingKey === 'test.route' &&
                       $props['correlation_id'] === '12345' &&
                       $props['delivery_mode'] === AMQPMessage::DELIVERY_MODE_PERSISTENT;
            })
            ->andReturnNull();
            
        // Create publisher
        $publisher = new Publisher($mockConnectionManager);
        
        // Test with custom properties
        $result = $publisher->publish('test.route', ['key' => 'value'], [
            'correlation_id' => '12345'
        ]);
        
        // Assert
        $this->assertTrue($result);
    }
      /**
     * Test publishing a message with exception handling.
     */
    public function test_publish_handles_exceptions()
    {
        // Create mocks
        $mockConnectionManager = Mockery::mock(ConnectionManagerInterface::class);
        
        // Set expectations to throw an exception
        $mockConnectionManager->shouldReceive('getChannel')
            ->once()
            ->andThrow(new \Exception('Connection error'));
            
        $mockConnectionManager->shouldReceive('getExchangeName')
            ->once()
            ->andReturn('test_exchange');
            
        // Create publisher
        $publisher = new Publisher($mockConnectionManager);
        
        // Test
        $result = $publisher->publish('test.route', ['key' => 'value']);
        
        // Assert that result is false due to exception
        $this->assertFalse($result);
    }    /**
     * Test batch publishing of messages using transactions.
     */
    public function test_batch_publish_using_transactions()
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
            
        $mockChannel->shouldReceive('tx_select')
            ->once();
            
        $mockChannel->shouldReceive('basic_publish')
            ->times(2)
            ->andReturnNull();
            
        $mockChannel->shouldReceive('tx_commit')
            ->once();
            
        // Create publisher
        $publisher = new Publisher($mockConnectionManager);
        
        // Messages to publish
        $messages = [
            ['routing_key' => 'test.route1', 'data' => ['key' => 'value1']],
            ['routing_key' => 'test.route2', 'data' => ['key' => 'value2']]
        ];
        
        // Test batch publish
        $result = $publisher->publishBatch($messages);
        
        // Assert
        $this->assertTrue($result);
    }    /**
     * Test batch publishing with transactions handling errors.
     */
    public function test_batch_publish_transactions_handle_errors()
    {
        // Create mocks
        $mockChannel = Mockery::mock(AMQPChannel::class);
        $mockConnectionManager = Mockery::mock(ConnectionManagerInterface::class);
        
        // Set expectations
        $mockConnectionManager->shouldReceive('getChannel')
            ->once()
            ->andReturn($mockChannel);
            
        $mockConnectionManager->shouldReceive('getExchangeName')
            ->twice()
            ->andReturn('test_exchange');
            
        $mockChannel->shouldReceive('tx_select')
            ->once();
            
        $mockChannel->shouldReceive('basic_publish')
            ->once()
            ->andThrow(new \Exception('Publish error'));
            
        $mockChannel->shouldReceive('tx_rollback')
            ->once();
            
        $mockChannel->shouldReceive('is_open')
            ->once()
            ->andReturn(true);
            
        // Create publisher
        $publisher = new Publisher($mockConnectionManager);
        
        // Messages to publish
        $messages = [
            ['routing_key' => 'test.route1', 'data' => ['key' => 'value1']]
        ];
        
        // Test batch publish with error
        $result = $publisher->publishBatch($messages);
        
        // Assert failure
        $this->assertFalse($result);
    }
}
