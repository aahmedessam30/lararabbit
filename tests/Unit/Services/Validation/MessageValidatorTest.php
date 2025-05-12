<?php

namespace AhmedEssam\LaraRabbit\Tests\Unit\Services\Validation;

use AhmedEssam\LaraRabbit\Exceptions\SchemaNotFoundException;
use AhmedEssam\LaraRabbit\Services\Validation\LaravelValidator;
use AhmedEssam\LaraRabbit\Tests\TestCase;

class MessageValidatorTest extends TestCase
{
    /**
     * Test that validator can register and validate using a schema
     */
    public function test_register_and_validate_schema()
    {
        $validator = new LaravelValidator();
        
        // Register a schema
        $schema = [
            'order_id' => 'required|integer',
            'customer_id' => 'required|integer',
            'total' => 'required|numeric|min:0',
            'items' => 'required|array',
        ];
        
        $validator->registerSchema('order.created', $schema);
        
        // Valid message
        $validMessage = [
            'order_id' => 12345,
            'customer_id' => 67890,
            'total' => 99.99,
            'items' => ['item1', 'item2']
        ];
        
        $this->assertTrue($validator->validate($validMessage, 'order.created'));
        $this->assertEmpty($validator->getErrors());
        
        // Invalid message
        $invalidMessage = [
            'order_id' => 'not-an-integer',
            'total' => -10,
            // Missing customer_id and items
        ];
        
        $this->assertFalse($validator->validate($invalidMessage, 'order.created'));
        $errors = $validator->getErrors();
        
        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('order_id', $errors);
        $this->assertArrayHasKey('customer_id', $errors);
        $this->assertArrayHasKey('total', $errors);
        $this->assertArrayHasKey('items', $errors);
    }
    
    /**
     * Test that validating with a non-existent schema throws an exception
     */
    public function test_validate_nonexistent_schema_throws_exception()
    {
        $this->expectException(SchemaNotFoundException::class);
        
        $validator = new LaravelValidator();
        $validator->validate(['sample' => 'data'], 'nonexistent.schema');
    }
    
    /**
     * Test schema registering overwrites existing schema
     */
    public function test_register_schema_overwrites_existing()
    {
        $validator = new LaravelValidator();
        
        // Register initial schema
        $initialSchema = [
            'field1' => 'required|string',
            'field2' => 'required|integer',
        ];
        
        $validator->registerSchema('test.schema', $initialSchema);
        
        // Valid for initial schema
        $validForInitial = [
            'field1' => 'test',
            'field2' => 123,
        ];
        
        $this->assertTrue($validator->validate($validForInitial, 'test.schema'));
        
        // Register new schema with same name
        $newSchema = [
            'field1' => 'required|string',
            'field3' => 'required|email', // New field
            // field2 is now gone
        ];
        
        $validator->registerSchema('test.schema', $newSchema);
        
        // Now the same message should be invalid
        $this->assertFalse($validator->validate($validForInitial, 'test.schema'));
        $errors = $validator->getErrors();
        
        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('field3', $errors);
    }
}
