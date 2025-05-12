<?php

namespace AhmedEssam\LaraRabbit\Tests\Unit\Services\Resilience;

use AhmedEssam\LaraRabbit\Exceptions\CircuitBreakerOpenException;
use AhmedEssam\LaraRabbit\Services\Resilience\CircuitBreaker;
use AhmedEssam\LaraRabbit\Tests\TestCase;
use ReflectionClass;

class CircuitBreakerTest extends TestCase
{    /**
     * Test circuit breaker constructor sets initial state.
     */
    public function test_constructor_sets_initial_state()
    {
        $circuitBreaker = new CircuitBreaker('test-circuit', 5, 30);
        
        $reflection = new ReflectionClass($circuitBreaker);
        
        $nameProperty = $reflection->getProperty('name');
        $nameProperty->setAccessible(true);
        
        $failureThresholdProperty = $reflection->getProperty('failureThreshold');
        $failureThresholdProperty->setAccessible(true);
        
        $resetTimeoutProperty = $reflection->getProperty('resetTimeout');
        $resetTimeoutProperty->setAccessible(true);
        
        $stateProperty = $reflection->getProperty('state');
        $stateProperty->setAccessible(true);
        
        $failureCountProperty = $reflection->getProperty('failureCount');
        $failureCountProperty->setAccessible(true);
        
        // Assert properties were set correctly
        $this->assertEquals('test-circuit', $nameProperty->getValue($circuitBreaker));
        $this->assertEquals(5, $failureThresholdProperty->getValue($circuitBreaker));
        $this->assertEquals(30, $resetTimeoutProperty->getValue($circuitBreaker));
        $this->assertEquals(CircuitBreaker::STATE_CLOSED, $stateProperty->getValue($circuitBreaker));
        $this->assertEquals(0, $failureCountProperty->getValue($circuitBreaker));
    }
      /**
     * Test circuit breaker execute method works with successful function.
     */
    public function test_execute_with_successful_function()
    {
        $circuitBreaker = new CircuitBreaker('test-circuit', 3, 30);
        
        $result = $circuitBreaker->execute(function() {
            return 'success';
        });
        
        $this->assertEquals('success', $result);
        
        // Verify state remains closed
        $reflection = new ReflectionClass($circuitBreaker);
        $stateProperty = $reflection->getProperty('state');
        $stateProperty->setAccessible(true);
        
        $this->assertEquals(CircuitBreaker::STATE_CLOSED, $stateProperty->getValue($circuitBreaker));
    }
    
    /**
     * Test circuit breaker execute method with failing function opens the circuit.
     */
    public function test_execute_opens_circuit_after_threshold()
    {
        $circuitBreaker = new CircuitBreaker('test-circuit', 3, 30);
        
        // Execute failing function multiple times
        for ($i = 0; $i < 3; $i++) {
            try {
                $circuitBreaker->execute(function() {
                    throw new \Exception('Test failure');
                });
                $this->fail('Exception should have been thrown');
            } catch (\Exception $e) {
                $this->assertEquals('Test failure', $e->getMessage());
            }
        }
        
        // Now the circuit should be open
        $this->expectException(CircuitBreakerOpenException::class);
        
        // This should throw CircuitBreakerOpenException
        $circuitBreaker->execute(function() {
            return 'success';
        });
    }
      /**
     * Test circuit breaker reset method.
     */
    public function test_reset_method()
    {
        $circuitBreaker = new CircuitBreaker('test-circuit', 3, 30);
        
        // Trigger failures to open the circuit
        for ($i = 0; $i < 3; $i++) {
            try {
                $circuitBreaker->execute(function() {
                    throw new \Exception('Test failure');
                });
            } catch (\Exception $e) {
                // Expected
            }
        }
        
        // Verify circuit is open
        try {
            $circuitBreaker->execute(function() {
                return 'success';
            });
            $this->fail('Circuit should be open');
        } catch (CircuitBreakerOpenException $e) {
            // Expected
        }
        
        // Reset the circuit
        $reflection = new ReflectionClass($circuitBreaker);
        $resetMethod = $reflection->getMethod('reset');
        $resetMethod->setAccessible(true);
        $resetMethod->invoke($circuitBreaker);
        
        // Verify the circuit is closed again
        $stateProperty = $reflection->getProperty('state');
        $stateProperty->setAccessible(true);
        $failureCountProperty = $reflection->getProperty('failureCount');
        $failureCountProperty->setAccessible(true);
        
        $this->assertEquals(CircuitBreaker::STATE_CLOSED, $stateProperty->getValue($circuitBreaker));
        $this->assertEquals(0, $failureCountProperty->getValue($circuitBreaker));
        
        // Should be able to execute now
        $result = $circuitBreaker->execute(function() {
            return 'success after reset';
        });
        
        $this->assertEquals('success after reset', $result);
    }
      /**
     * Test checkState method behaves correctly after timeout.
     */
    public function test_check_state_respects_timeout()
    {
        // Create circuit breaker with short timeout
        $circuitBreaker = new CircuitBreaker('test-circuit', 3, 1); // 1 second timeout
        
        // Trigger failures to open the circuit
        for ($i = 0; $i < 3; $i++) {
            try {
                $circuitBreaker->execute(function() {
                    throw new \Exception('Test failure');
                });
            } catch (\Exception $e) {
                // Expected
            }
        }
        
        // Access internal properties
        $reflection = new ReflectionClass($circuitBreaker);
        $stateProperty = $reflection->getProperty('state');
        $stateProperty->setAccessible(true);
        $lastFailureTimeProperty = $reflection->getProperty('lastFailureTime');
        $lastFailureTimeProperty->setAccessible(true);
        
        // Verify circuit is open
        $this->assertEquals(CircuitBreaker::STATE_OPEN, $stateProperty->getValue($circuitBreaker));
        
        // Manually set last failure time to be older than the timeout
        $lastFailureTimeProperty->setValue($circuitBreaker, time() - 2); // 2 seconds ago
        
        // Get access to checkState method
        $checkStateMethod = $reflection->getMethod('checkState');
        $checkStateMethod->setAccessible(true);
        
        // After the timeout, checkState should allow a test request (half-open state)
        $checkStateMethod->invoke($circuitBreaker);
        
        // After timeout, circuit should transition to half-open to allow a test request
        $this->assertEquals(CircuitBreaker::STATE_HALF_OPEN, $stateProperty->getValue($circuitBreaker));
    }
}
