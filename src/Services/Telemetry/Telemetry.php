<?php

namespace AhmedEssam\LaraRabbit\Services\Telemetry;

use Illuminate\Support\Facades\Log;
use Throwable;

class Telemetry
{
    /**
     * Start time of the current operation
     *
     * @var float|null
     */
    protected ?float $startTime = null;
    
    /**
     * Metrics data for the current run
     *
     * @var array
     */
    protected array $metrics = [];
    
    /**
     * Start timing an operation
     *
     * @param string $operation
     * @return void
     */
    public function startOperation(string $operation): void
    {
        $this->startTime = microtime(true);
        $this->metrics['operation'] = $operation;
        $this->metrics['started_at'] = date('Y-m-d H:i:s');
    }
    
    /**
     * End timing an operation and record metrics
     *
     * @param array $additionalData
     * @return array The metrics data
     */
    public function endOperation(array $additionalData = []): array
    {
        if ($this->startTime === null) {
            return [];
        }
        
        $duration = microtime(true) - $this->startTime;
        $this->metrics['duration_ms'] = round($duration * 1000, 2);
        $this->metrics = array_merge($this->metrics, $additionalData);
        
        // Reset start time
        $this->startTime = null;
        
        return $this->metrics;
    }
    
    /**
     * Record a successful operation with metrics
     *
     * @param array $additionalData
     * @return void
     */
    public function recordSuccess(array $additionalData = []): void
    {
        $metrics = $this->endOperation(array_merge(['status' => 'success'], $additionalData));
        
        if (!empty($metrics)) {
            Log::info("RabbitMQ operation completed", $metrics);
        }
    }
    
    /**
     * Record a failed operation with metrics
     *
     * @param Throwable $exception
     * @param array $additionalData
     * @return void
     */
    public function recordFailure(Throwable $exception, array $additionalData = []): void
    {
        $metrics = $this->endOperation(array_merge([
            'status' => 'failure',
            'error' => $exception->getMessage(),
            'error_class' => get_class($exception),
        ], $additionalData));
        
        if (!empty($metrics)) {
            Log::error("RabbitMQ operation failed", $metrics);
        }
    }
}
