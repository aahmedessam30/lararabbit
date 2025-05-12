<?php

namespace AhmedEssam\LaraRabbit\Contracts;

interface MessageValidatorInterface
{
    /**
     * Validate a message against a schema
     *
     * @param array $message
     * @param string $schemaName
     * @return bool
     */
    public function validate(array $message, string $schemaName): bool;
    
    /**
     * Get validation errors
     *
     * @return array
     */
    public function getErrors(): array;
    
    /**
     * Register a schema
     *
     * @param string $schemaName
     * @param array $schema
     * @return void
     */
    public function registerSchema(string $schemaName, array $schema): void;
}
