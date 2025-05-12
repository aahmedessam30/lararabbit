<?php

namespace AhmedEssam\LaraRabbit\Services;

use AhmedEssam\LaraRabbit\Contracts\ConnectionManagerInterface;
use Exception;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;

class ConnectionManager implements ConnectionManagerInterface
{
    /**
     * @var AMQPStreamConnection|null
     */
    protected ?AMQPStreamConnection $connection = null;

    /**
     * @var AMQPChannel|null
     */
    protected ?AMQPChannel $channel = null;

    /**
     * @var string
     */
    protected string $exchangeName;

    /**
     * @var string
     */
    protected string $exchangeType;

    /**
     * @var bool
     */
    protected bool $exchangeDeclared = false;

    /**
     * Create a connection manager
     *
     * @param string|null $exchangeName Exchange name
     * @param string|null $exchangeType Exchange type (direct, topic, fanout, headers)
     */
    public function __construct(
        ?string $exchangeName = null,
        ?string $exchangeType = null
    ) {
        $this->exchangeName = $exchangeName ?? config('rabbitmq.exchange.name', 'booking_system');
        $this->exchangeType = $exchangeType ?? config('rabbitmq.exchange.type', AMQPExchangeType::TOPIC);
    }

    /**
     * Get or create a connection
     *
     * @return AMQPStreamConnection
     */
    public function getConnection(): AMQPStreamConnection
    {
        if ($this->connection === null || !$this->connection->isConnected()) {
            $this->connection = $this->createConnection();
        }

        return $this->connection;
    }    /**
     * Get or create a channel
     *
     * @return AMQPChannel
     * @throws Exception If a channel cannot be created
     */
    public function getChannel(): AMQPChannel
    {
        try {
            // Verify channel is valid and open
            if ($this->channel === null || !$this->channel->is_open()) {
                // Get a valid connection first
                if ($this->connection === null || !$this->connection->isConnected()) {
                    $this->connection = $this->createConnection();
                }
                
                // Create a new channel
                $this->channel = $this->connection->channel();
                
                // Declare exchange if not already done
                if (!$this->exchangeDeclared) {
                    $this->declareExchange();
                }
                
                if (config('rabbitmq.debug')) {
                    Log::debug('Created new RabbitMQ channel');
                }
            }
            
            return $this->channel;
        } catch (Exception $e) {
            // Log the error
            Log::error('Failed to get RabbitMQ channel: ' . $e->getMessage(), [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Attempt to clean up and recover
            try {
                $this->closeConnection();
            } catch (Exception $closeException) {
                // Just log and continue
                Log::debug('Error during connection cleanup: ' . $closeException->getMessage());
            }
            
            // Rethrow the original exception
            throw $e;
        }
    }

    /**
     * Get the exchange name
     *
     * @return string
     */
    public function getExchangeName(): string
    {
        return $this->exchangeName;
    }

    /**
     * Set the exchange name
     *
     * @param string $exchangeName
     * @return self
     */
    public function setExchangeName(string $exchangeName): self
    {
        $this->exchangeName = $exchangeName;
        $this->exchangeDeclared = false; // Reset so new exchange will be declared

        return $this;
    }    /**
     * Close the RabbitMQ connection
     *
     * @return void
     */
    public function closeConnection(): void
    {
        if ($this->channel !== null && $this->channel->is_open()) {
            try {
                $this->channel->close();
            } catch (Exception $e) {
                Log::warning('Failed to close RabbitMQ channel: ' . $e->getMessage());
            }
        }

        if ($this->connection !== null && $this->connection->isConnected()) {
            try {
                $this->connection->close();
            } catch (Exception $e) {
                Log::warning('Failed to close RabbitMQ connection: ' . $e->getMessage());
            }
        }

        $this->channel = null;
        $this->connection = null;
    }
    
    /**
     * Reconnect to RabbitMQ
     *
     * @return bool True if reconnection was successful
     */
    public function reconnect(): bool
    {
        $this->closeConnection();
        
        try {
            // Create a new connection
            $this->connection = $this->createConnection();
            $this->channel = $this->connection->channel();
            
            // Redeclare exchange if needed
            $this->exchangeDeclared = false;
            $this->declareExchange();
            
            if (config('rabbitmq.debug')) {
                Log::debug('Successfully reconnected to RabbitMQ');
            }
            
            return true;
        } catch (Exception $e) {
            Log::error('Failed to reconnect to RabbitMQ: ' . $e->getMessage(), [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Clean up any partially created connections
            $this->closeConnection();
            
            return false;
        }
    }

    /**
     * Create a connection to RabbitMQ
     *
     * @return AMQPStreamConnection
     */
    protected function createConnection(): AMQPStreamConnection
    {
        $host = config('rabbitmq.connection.host', 'localhost');
        $port = config('rabbitmq.connection.port', 5672);
        $user = config('rabbitmq.connection.user', 'guest');
        $password = config('rabbitmq.connection.password', 'guest');
        $vhost = config('rabbitmq.connection.vhost', '/');
        
        $connection = new AMQPStreamConnection(
            $host,
            $port,
            $user,
            $password,
            $vhost,
            false,  // insist
            'AMQPLAIN',  // login method
            null, // login response
            'en_US', // locale
            config('rabbitmq.connection.timeout', 3.0), // connection timeout
            config('rabbitmq.connection.read_write_timeout', 3.0), // read/write timeout
            null, // context
            config('rabbitmq.connection.keepalive', false), // keepalive
            config('rabbitmq.connection.heartbeat', 0) // heartbeat
        );
        
        if (config('rabbitmq.debug')) {
            Log::debug("Created RabbitMQ connection to {$host}:{$port}");
        }
        
        return $connection;
    }

    /**
     * Declare the exchange
     *
     * @return void
     */
    protected function declareExchange(): void
    {
        $channel = $this->channel;
        
        // Get configuration values
        $durable = config('rabbitmq.exchange.durable', true);
        $autoDelete = config('rabbitmq.exchange.auto_delete', false);
        $internal = config('rabbitmq.exchange.internal', false);
        $passive = config('rabbitmq.exchange.passive', false);
        
        try {
            // Declare the exchange
            $channel->exchange_declare(
                $this->exchangeName,
                $this->exchangeType,
                $passive,
                $durable,
                $autoDelete,
                $internal
            );
            
            $this->exchangeDeclared = true;
            
            if (config('rabbitmq.debug')) {
                Log::debug("Declared exchange '{$this->exchangeName}' of type '{$this->exchangeType}'", [
                    'durable' => $durable,
                    'auto_delete' => $autoDelete,
                ]);
            }
        } catch (Exception $e) {
            Log::error('Failed to declare exchange: ' . $e->getMessage(), [
                'exchange' => $this->exchangeName,
                'type' => $this->exchangeType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw $e;
        }
    }
}