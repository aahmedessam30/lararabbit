<?php

namespace AhmedEssam\LaraRabbit\Services\Validation;

use AhmedEssam\LaraRabbit\Contracts\MessageValidatorInterface;
use AhmedEssam\LaraRabbit\Exceptions\SchemaNotFoundException;
use Illuminate\Support\Facades\Validator;

class LaravelValidator implements MessageValidatorInterface
{
    /**
     * Registered schemas
     *
     * @var array
     */
    protected array $schemas = [];
    
    /**
     * Validation errors
     *
     * @var array
     */
    protected array $errors = [];
    
    /**
     * Validate a message against a schema
     *
     * @param array $message
     * @param string $schemaName
     * @return bool
     * @throws SchemaNotFoundException
     */
    public function validate(array $message, string $schemaName): bool
    {
        if (!isset($this->schemas[$schemaName])) {
            throw new SchemaNotFoundException("Schema '{$schemaName}' not found");
        }
        
        $this->errors = [];
        
        $validator = Validator::make($message, $this->schemas[$schemaName]);
        
        if ($validator->fails()) {
            $this->errors = $validator->errors()->toArray();
            return false;
        }
        
        return true;
    }
    
    /**
     * Get validation errors
     *
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
    
    /**
     * Register a schema
     *
     * @param string $schemaName
     * @param array $schema
     * @return void
     */
    public function registerSchema(string $schemaName, array $schema): void
    {
        $this->schemas[$schemaName] = $schema;
    }
}
