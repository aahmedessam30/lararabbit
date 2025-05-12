<?php
namespace AhmedEssam\LaraRabbit\Tests\Unit\Services;

use AhmedEssam\LaraRabbit\Contracts\ConnectionManagerInterface;
use AhmedEssam\LaraRabbit\Contracts\ConsumerInterface;
use AhmedEssam\LaraRabbit\Contracts\MessageValidatorInterface;
use AhmedEssam\LaraRabbit\Contracts\PublisherInterface;
use AhmedEssam\LaraRabbit\Services\RabbitMQService;
use AhmedEssam\LaraRabbit\Tests\TestCase;
use Mockery;
use ReflectionClass;

class RabbitMQServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test the constructor properly sets dependencies.
     */
    public function test_constructor_sets_dependencies()
    {
        // Create mocks
        $mockConnectionManager = Mockery::mock(ConnectionManagerInterface::class);
        $mockPublisher = Mockery::mock(PublisherInterface::class);
        $mockConsumer = Mockery::mock(ConsumerInterface::class);
        $mockValidator = Mockery::mock(MessageValidatorInterface::class);
        
        // Create service with dependencies
        $service = new RabbitMQService(
            $mockConnectionManager,
            $mockPublisher,
            $mockConsumer,
            $mockValidator
        );
        
        // Use reflection to verify properties were set
        $reflection = new ReflectionClass($service);
        
        $connectionManagerProperty = $reflection->getProperty('connectionManager');
        $connectionManagerProperty->setAccessible(true);
        
        $publisherProperty = $reflection->getProperty('publisher');
        $publisherProperty->setAccessible(true);
        
        $consumerProperty = $reflection->getProperty('consumer');
        $consumerProperty->setAccessible(true);
        
        $validatorProperty = $reflection->getProperty('validator');
        $validatorProperty->setAccessible(true);
        
        // Assert dependencies were properly set
        $this->assertSame($mockConnectionManager, $connectionManagerProperty->getValue($service));
        $this->assertSame($mockPublisher, $publisherProperty->getValue($service));
        $this->assertSame($mockConsumer, $consumerProperty->getValue($service));
        $this->assertSame($mockValidator, $validatorProperty->getValue($service));
    }
    
    /**
     * Test setup queue method.
     */
    public function test_setup_queue_delegates_to_consumer()
    {
        // Create mocks
        $mockConnectionManager = Mockery::mock(ConnectionManagerInterface::class);
        $mockPublisher = Mockery::mock(PublisherInterface::class);
        $mockConsumer = Mockery::mock(ConsumerInterface::class);
        $mockValidator = Mockery::mock(MessageValidatorInterface::class);
        
        // Create service
        $service = new RabbitMQService(
            $mockConnectionManager,
            $mockPublisher,
            $mockConsumer,
            $mockValidator
        );
        
        // Set consumer expectations
        $mockConsumer->shouldReceive('setupQueue')
            ->once()
            ->withArgs(function ($queueName, $bindingKeys, $durable, $autoDelete) {
                return $queueName === 'test_queue' &&
                       $bindingKeys === ['test.key'] &&
                       $durable === true &&
                       $autoDelete === false;
            })
            ->andReturn($mockConsumer);
            
        // Test setup queue
        $result = $service->setupQueue('test_queue', ['test.key']);
        
        // Assert fluent interface returns the service
        $this->assertSame($service, $result);
    }
    
    /**
     * Test close connection method.
     */
    public function test_close_delegates_to_connection_manager()
    {
        // Create mocks
        $mockConnectionManager = Mockery::mock(ConnectionManagerInterface::class);
        $mockPublisher = Mockery::mock(PublisherInterface::class);
        $mockConsumer = Mockery::mock(ConsumerInterface::class);
        $mockValidator = Mockery::mock(MessageValidatorInterface::class);
        
        // Create service
        $service = new RabbitMQService(
            $mockConnectionManager,
            $mockPublisher,
            $mockConsumer,
            $mockValidator
        );
        
        // Set connection manager expectations
        $mockConnectionManager->shouldReceive('closeConnection')
            ->once();
            
        // Test close
        $service->closeConnection();
        
        // Verify all expectations were met (this adds an implicit assertion)
        $this->assertTrue(true);
    }
}
