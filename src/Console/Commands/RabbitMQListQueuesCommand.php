<?php

namespace AhmedEssam\LaraRabbit\Console\Commands;

use AhmedEssam\LaraRabbit\Contracts\ConnectionManagerInterface;
use Illuminate\Console\Command;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Exception\AMQPTimeoutException;

class RabbitMQListQueuesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lararabbit:list-queues';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all RabbitMQ queues with message counts and consumer details';

    /**
     * @var ConnectionManagerInterface
     */
    protected $connectionManager;

    /**
     * Create a new command instance.
     *
     * @param ConnectionManagerInterface $connectionManager
     */
    public function __construct(ConnectionManagerInterface $connectionManager)
    {
        parent::__construct();
        $this->connectionManager = $connectionManager;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        try {
            $channel = $this->connectionManager->getChannel();

            // Get queue info - this will return all queues
            $queues = $this->getQueuesInfo($channel);

            if (empty($queues)) {
                $this->info('No queues found');
                return 0;
            }

            // Display table of queues
            $headers = ['Queue Name', 'Messages', 'Consumers', 'State', 'Type'];
            $rows = [];

            foreach ($queues as $queue) {
                $rows[] = [
                    $queue['name'],
                    $queue['messages'],
                    $queue['consumers'],
                    $queue['state'],
                    $queue['type'] ?? 'classic'
                ];
            }

            $this->table($headers, $rows);
            $this->info("Total queues: " . count($queues));

            return 0;
        } catch (AMQPTimeoutException $e) {
            $this->error('Connection to RabbitMQ timed out: ' . $e->getMessage());
            return 1;
        } catch (\Exception $e) {
            $this->error('Failed to list queues: ' . $e->getMessage());
            return 1;
        } finally {
            // Don't close the connection as it may be used elsewhere
        }
    }
    /**
     * Get information about all queues
     * 
     * @param AMQPChannel $channel
     * @return array
     */
    protected function getQueuesInfo(AMQPChannel $channel): array
    {
        // Define known queue names or scan a configuration
        $queueNames = $this->getKnownQueueNames();

        // Process the queue data
        $queues = [];

        // Loop through each queue and get its details
        foreach ($queueNames as $queueName) {
            try {
                // Queue declare with passive=true to get info without modifying the queue
                list(, $messageCount, $consumerCount) = $channel->queue_declare(
                    $queueName,
                    true, // passive - just check if it exists
                    false,
                    false,
                    false
                );

                $queues[] = [
                    'name' => $queueName,
                    'messages' => $messageCount ?? 0,
                    'consumers' => $consumerCount ?? 0,
                    'state' => ($messageCount > 0) ? 'Active' : 'Idle',
                    'type' => 'classic'
                ];
            } catch (\Exception $e) {
                // Queue doesn't exist or cannot be accessed, skip it
                $this->comment("Queue '$queueName' not found or cannot be accessed.");
            }
        }

        return $queues;
    }

    /**
     * Get list of known queue names from configuration or application
     * 
     * @return array
     */    protected function getKnownQueueNames(): array
    {
        // Get queue names from the dedicated configuration
        $queuesConfig = config('rabbitmq-queues', []);

        // If we don't have any configured queues, use some defaults
        if (empty($queuesConfig)) {
            $this->warn('No queues found in configuration. Using default queue names.');
            return ['default'];
        }

        // Extract names from the queues configuration
        $queueNames = array_keys($queuesConfig);

        // Also include the actual queue names from the configuration
        foreach ($queuesConfig as $queueConfig) {
            if (isset($queueConfig['name']) && !in_array($queueConfig['name'], $queueNames)) {
                $queueNames[] = $queueConfig['name'];
            }
        }

        return $queueNames;
    }
}
