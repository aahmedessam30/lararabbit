<?php

namespace AhmedEssam\LaraRabbit\Console\Commands;

use AhmedEssam\LaraRabbit\Contracts\ConnectionManagerInterface;
use Illuminate\Console\Command;
use PhpAmqpLib\Exception\AMQPTimeoutException;

class RabbitMQPurgeQueueCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lararabbit:purge-queue {queue? : The queue name to purge (or all queues if not specified)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Purge messages from a RabbitMQ queue';

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
            $queueName = $this->argument('queue');
            $channel = $this->connectionManager->getChannel();
            
            if ($queueName) {
                // Purge a specific queue
                if (!$this->confirm("Are you sure you want to purge queue '{$queueName}'?")) {
                    $this->info('Purge cancelled');
                    return 0;
                }
                
                $this->info("Purging queue: {$queueName}");
                $messageCount = $channel->queue_purge($queueName);
                $this->info("Queue '{$queueName}' purged. {$messageCount} messages removed.");
            } else {
                // List all queues and offer to purge them
                $queues = $this->getQueues($channel);
                
                if (empty($queues)) {
                    $this->info('No queues found');
                    return 0;
                }
                
                $this->info("Available queues:");
                foreach ($queues as $index => $queue) {
                    $this->line(($index + 1) . ". {$queue}");
                }
                
                $selection = $this->choice(
                    'Select a queue to purge (or "all" to purge all queues)',
                    array_merge($queues, ['all', 'cancel']),
                    'cancel'
                );
                
                if ($selection === 'cancel') {
                    $this->info('Purge cancelled');
                    return 0;
                }
                
                if ($selection === 'all') {
                    if (!$this->confirm("Are you sure you want to purge ALL queues? This cannot be undone!")) {
                        $this->info('Purge cancelled');
                        return 0;
                    }
                    
                    $purgedCount = 0;
                    $totalMessages = 0;
                    
                    foreach ($queues as $queue) {
                        $this->info("Purging queue: {$queue}");
                        try {
                            $messageCount = $channel->queue_purge($queue);
                            $totalMessages += $messageCount;
                            $purgedCount++;
                            $this->info("Queue '{$queue}' purged. {$messageCount} messages removed.");
                        } catch (\Exception $e) {
                            $this->error("Failed to purge queue '{$queue}': " . $e->getMessage());
                        }
                    }
                    
                    $this->info("Purged {$purgedCount} queues. {$totalMessages} messages removed in total.");
                } else {
                    if (!$this->confirm("Are you sure you want to purge queue '{$selection}'?")) {
                        $this->info('Purge cancelled');
                        return 0;
                    }
                    
                    $this->info("Purging queue: {$selection}");
                    $messageCount = $channel->queue_purge($selection);
                    $this->info("Queue '{$selection}' purged. {$messageCount} messages removed.");
                }
            }
            
            return 0;
        } catch (AMQPTimeoutException $e) {
            $this->error('Connection to RabbitMQ timed out: ' . $e->getMessage());
            return 1;
        } catch (\Exception $e) {
            $this->error('Failed to purge queue: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Get all queues from RabbitMQ
     * 
     * @param \PhpAmqpLib\Channel\AMQPChannel $channel
     * @return array
     */
    protected function getQueues($channel): array
    {
        // Get queue names from the dedicated configuration
        $queuesConfig = config('rabbitmq-queues', []);
        $queues = [];

        // Extract names from the queues configuration
        foreach ($queuesConfig as $key => $queueConfig) {
            // Add the queue key
            $queues[] = $key;
            
            // Add the actual queue name if different from key
            if (isset($queueConfig['name']) && $queueConfig['name'] !== $key && !in_array($queueConfig['name'], $queues)) {
                $queues[] = $queueConfig['name'];
            }
        }
        
        // If no queues found in config, use default
        if (empty($queues)) {
            $queues[] = 'default';
        }
        
        return $queues;
    }
}
