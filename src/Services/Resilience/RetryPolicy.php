<?php

namespace AhmedEssam\LaraRabbit\Services\Resilience;

use Closure;
use Exception;
use Illuminate\Support\Facades\Log;

class RetryPolicy
{
    /**
     * Maximum number of retry attempts
     *
     * @var int
     */
    protected int $maxAttempts;
    
    /**
     * Base delay in milliseconds
     *
     * @var int
     */
    protected int $baseDelayMs;
    
    /**
     * Maximum delay in milliseconds
     *
     * @var int
     */
    protected int $maxDelayMs;
    
    /**
     * Jitter factor (0-1) to add randomness to delays
     *
     * @var float
     */
    protected float $jitterFactor;
    
    /**
     * RetryPolicy constructor
     *
     * @param int $maxAttempts
     * @param int $baseDelayMs
     * @param int $maxDelayMs
     * @param float $jitterFactor
     */
    public function __construct(
        int $maxAttempts = 3, 
        int $baseDelayMs = 100, 
        int $maxDelayMs = 5000,
        float $jitterFactor = 0.2
    ) {
        $this->maxAttempts = $maxAttempts;
        $this->baseDelayMs = $baseDelayMs;
        $this->maxDelayMs = $maxDelayMs;
        $this->jitterFactor = $jitterFactor;
    }
    
    /**
     * Execute with retry
     *
     * @param Closure $operation
     * @param array $retryableExceptions
     * @param callable|null $onRetry
     * @return mixed
     * @throws Exception
     */
    public function execute(
        Closure $operation, 
        array $retryableExceptions = [Exception::class],
        callable $onRetry = null
    ) {
        $attempt = 0;
        
        while (true) {
            $attempt++;
            
            try {
                return $operation();
            } catch (Exception $e) {
                // Check if the exception is retryable
                $shouldRetry = false;
                foreach ($retryableExceptions as $exceptionClass) {
                    if ($e instanceof $exceptionClass) {
                        $shouldRetry = true;
                        break;
                    }
                }
                
                // Don't retry if not a retryable exception or max attempts reached
                if (!$shouldRetry || $attempt >= $this->maxAttempts) {
                    throw $e;
                }
                
                // Calculate delay with exponential backoff and jitter
                $delay = min(
                    $this->maxDelayMs,
                    $this->baseDelayMs * pow(2, $attempt - 1)
                );
                
                // Add jitter
                if ($this->jitterFactor > 0) {
                    $jitter = $delay * $this->jitterFactor;
                    $delay = $delay - $jitter + (mt_rand() / mt_getrandmax()) * ($jitter * 2);
                }
                
                // Log the retry
                Log::warning("Retry attempt {$attempt}/{$this->maxAttempts} after {$delay}ms due to: {$e->getMessage()}");
                
                // Call the onRetry callback if provided
                if ($onRetry !== null) {
                    $onRetry($attempt, $e, $delay);
                }
                
                // Sleep before the next attempt
                usleep($delay * 1000);
            }
        }
    }
}
