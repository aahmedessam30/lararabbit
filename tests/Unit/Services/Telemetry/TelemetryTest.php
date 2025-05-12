<?php

namespace AhmedEssam\LaraRabbit\Tests\Unit\Services\Telemetry;

use AhmedEssam\LaraRabbit\Services\Telemetry\Telemetry;
use AhmedEssam\LaraRabbit\Tests\TestCase;
use Exception;

class TelemetryTest extends TestCase
{
    /**
     * Test that telemetry can record operation timing
     */
    public function test_start_and_end_operation()
    {
        $telemetry = new Telemetry();
        
        // Start a test operation
        $telemetry->startOperation('test_operation');
        
        // Wait a small amount of time
        usleep(10000); // 10ms
        
        // End operation and get metrics
        $metrics = $telemetry->endOperation(['success' => true]);
        
        // Check result structure
        $this->assertArrayHasKey('operation', $metrics);
        $this->assertEquals('test_operation', $metrics['operation']);
        
        $this->assertArrayHasKey('started_at', $metrics);
        $this->assertArrayHasKey('duration_ms', $metrics);
        $this->assertArrayHasKey('success', $metrics);
        $this->assertTrue($metrics['success']);
        
        // Duration should be positive
        $this->assertGreaterThan(0, $metrics['duration_ms']);
    }
      /**
     * Test tracking an exception
     */
    public function test_track_exception()
    {
        $telemetry = new Telemetry();
        
        // Create exception
        $exception = new Exception('Test exception');
        
        // Start operation
        $telemetry->startOperation('test_exception');
        
        // Track exception
        $metrics = $telemetry->endOperation([
            'success' => false,
            'error' => $exception->getMessage(),
            'error_type' => get_class($exception)
        ]);
        
        // Check exception details
        $this->assertArrayHasKey('error', $metrics);
        $this->assertArrayHasKey('error_type', $metrics);
        $this->assertEquals('Exception', $metrics['error_type']);
        $this->assertEquals('Test exception', $metrics['error']);
        
        // Should still have basic metrics
        $this->assertArrayHasKey('operation', $metrics);
        $this->assertEquals('test_exception', $metrics['operation']);
    }
    
    /**
     * Test recording custom metrics
     */
    public function test_record_custom_metrics()
    {
        $telemetry = new Telemetry();
        
        // Start operation
        $telemetry->startOperation('custom_metrics_test');
        
        // Record custom metrics using endOperation
        $metrics = $telemetry->endOperation([
            'items_processed' => 100,
            'success_rate' => 0.95,
            'custom_data' => [
                'nested' => true
            ]
        ]);
        
        // Verify custom metrics
        $this->assertArrayHasKey('items_processed', $metrics);
        $this->assertEquals(100, $metrics['items_processed']);
        
        $this->assertArrayHasKey('success_rate', $metrics);
        $this->assertEquals(0.95, $metrics['success_rate']);
        
        $this->assertArrayHasKey('custom_data', $metrics);
        $this->assertIsArray($metrics['custom_data']);
        $this->assertArrayHasKey('nested', $metrics['custom_data']);
        $this->assertTrue($metrics['custom_data']['nested']);
    }
    
    /**
     * Test calling endOperation without starting
     */
    public function test_end_operation_without_starting_returns_empty_array()
    {
        $telemetry = new Telemetry();
        
        // End without start
        $metrics = $telemetry->endOperation();
        
        // Should be empty
        $this->assertEmpty($metrics);
    }
}
