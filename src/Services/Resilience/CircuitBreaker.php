<?php

namespace AhmedEssam\LaraRabbit\Services\Resilience;

use AhmedEssam\LaraRabbit\Exceptions\CircuitBreakerOpenException;
use Closure;
use Exception;
use Illuminate\Support\Facades\Log;

class CircuitBreaker
{
    /**
     * Circuit states
     */
    const STATE_CLOSED = 'closed';
    const STATE_OPEN = 'open';
    const STATE_HALF_OPEN = 'half_open';
    
    /**
     * Current circuit state
     *
     * @var string
     */
    protected string $state = self::STATE_CLOSED;
    
    /**
     * Failure threshold before opening the circuit
     *
     * @var int
     */
    protected int $failureThreshold;
    
    /**
     * Reset timeout in seconds
     *
     * @var int
     */
    protected int $resetTimeout;
    
    /**
     * Current failure count
     *
     * @var int
     */
    protected int $failureCount = 0;
    
    /**
     * Last failure time
     *
     * @var int|null
     */
    protected ?int $lastFailureTime = null;
    
    /**
     * Circuit name for identification in logs
     *
     * @var string
     */
    protected string $name;
    
    /**
     * CircuitBreaker constructor
     *
     * @param string $name
     * @param int $failureThreshold
     * @param int $resetTimeout
     */
    public function __construct(string $name, int $failureThreshold = 5, int $resetTimeout = 30)
    {
        $this->name = $name;
        $this->failureThreshold = $failureThreshold;
        $this->resetTimeout = $resetTimeout;
    }
    
    /**
     * Execute a function with circuit breaker protection
     *
     * @param Closure $func
     * @return mixed
     * @throws CircuitBreakerOpenException
     * @throws Exception
     */
    public function execute(Closure $func)
    {
        $this->checkState();
        
        try {
            $result = $func();
            $this->recordSuccess();
            return $result;
        } catch (Exception $e) {
            $this->recordFailure();
            throw $e;
        }
    }
    
    /**
     * Check if the circuit can allow calls
     *
     * @throws CircuitBreakerOpenException
     */
    protected function checkState(): void
    {
        if ($this->state === self::STATE_OPEN) {
            // Check if reset timeout has passed
            if ($this->lastFailureTime !== null && time() - $this->lastFailureTime >= $this->resetTimeout) {
                $this->state = self::STATE_HALF_OPEN;
                Log::info("Circuit {$this->name} transitioned from OPEN to HALF-OPEN");
            } else {
                throw new CircuitBreakerOpenException("Circuit {$this->name} is OPEN");
            }
        }
    }
    
    /**
     * Record a successful call
     */
    protected function recordSuccess(): void
    {
        if ($this->state === self::STATE_HALF_OPEN) {
            // Reset on first success in half-open state
            $this->reset();
            Log::info("Circuit {$this->name} reset and transitioned to CLOSED");
        }
    }
    
    /**
     * Record a failed call
     */
    protected function recordFailure(): void
    {
        $this->failureCount++;
        $this->lastFailureTime = time();
        
        if ($this->state === self::STATE_HALF_OPEN || 
            ($this->state === self::STATE_CLOSED && $this->failureCount >= $this->failureThreshold)) {
            $this->state = self::STATE_OPEN;
            Log::warning("Circuit {$this->name} transitioned to OPEN after {$this->failureCount} failures");
        }
    }
    
    /**
     * Reset the circuit breaker
     */
    public function reset(): void
    {
        $this->state = self::STATE_CLOSED;
        $this->failureCount = 0;
        $this->lastFailureTime = null;
    }
    
    /**
     * Get the current state of the circuit
     *
     * @return string
     */
    public function getState(): string
    {
        return $this->state;
    }
}
