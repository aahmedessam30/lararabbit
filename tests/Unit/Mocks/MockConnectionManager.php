<?php

namespace AhmedEssam\LaraRabbit\Tests\Unit\Mocks;

use AhmedEssam\LaraRabbit\Contracts\ConnectionManagerInterface;
use Mockery;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;

class MockConnectionManager
{
    /**
     * Create a fully mocked ConnectionManager
     * 
     * This creates a mock that will not attempt to connect to a real RabbitMQ server
     * 
     * @return ConnectionManagerInterface
     */
    public static function create(): ConnectionManagerInterface
    {
        $mockConnectionManager = Mockery::mock(ConnectionManagerInterface::class);
        $mockChannel = Mockery::mock(AMQPChannel::class);
        $mockConnection = Mockery::mock(AMQPStreamConnection::class);
        
        // Mock the getChannel method
        $mockConnectionManager->shouldReceive('getChannel')
            ->andReturn($mockChannel);
            
        // Mock the getConnection method
        $mockConnectionManager->shouldReceive('getConnection')
            ->andReturn($mockConnection);
            
        // Mock the getExchangeName method
        $mockConnectionManager->shouldReceive('getExchangeName')
            ->andReturn('test_exchange');
            
        // Mock the setExchangeName method
        $mockConnectionManager->shouldReceive('setExchangeName')
            ->andReturnSelf();
            
        // Mock the closeConnection method
        $mockConnectionManager->shouldReceive('closeConnection')
            ->andReturnNull();
            
        // Mock the reconnect method
        $mockConnectionManager->shouldReceive('reconnect')
            ->andReturn(true);
            
        return $mockConnectionManager;
    }
    
    /**
     * Create a channel mock that simulates an open RabbitMQ channel
     * 
     * @return AMQPChannel
     */
    public static function createChannelMock(): AMQPChannel
    {
        $mockChannel = Mockery::mock(AMQPChannel::class);
        
        // Make channel appear to be open
        $mockChannel->shouldReceive('is_open')
            ->andReturn(true);
            
        // Setup common channel methods
        $mockChannel->shouldReceive('queue_declare')
            ->andReturn(['test_queue', 0, 0]);
            
        $mockChannel->shouldReceive('queue_bind')
            ->andReturnNull();
            
        $mockChannel->shouldReceive('basic_publish')
            ->andReturnNull();
            
        $mockChannel->shouldReceive('tx_select')
            ->andReturnNull();
            
        $mockChannel->shouldReceive('tx_commit')
            ->andReturnNull();
            
        $mockChannel->shouldReceive('tx_rollback')
            ->andReturnNull();
            
        return $mockChannel;
    }
}
