<?php

namespace AhmedEssam\LaraRabbit\Exceptions;

use Exception;

class MessageValidationException extends Exception
{
    /**
     * Validation errors
     *
     * @var array
     */
    protected array $errors;

    /**
     * Create a new message validation exception instance
     *
     * @param array $errors
     * @param string $message
     */
    public function __construct(array $errors, string $message = "Message validation failed")
    {
        $this->errors = $errors;
        parent::__construct($message);
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
}
