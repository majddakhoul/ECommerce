<?php

namespace App\Exceptions;

use RuntimeException;

class InventoryUnavailableException extends RuntimeException
{
    public function __construct(string $message = 'The inventory service is unavailable. Please try again shortly.', ?\Throwable $previous = null)
    {
        parent::__construct($message, 503, $previous);
    }
}
