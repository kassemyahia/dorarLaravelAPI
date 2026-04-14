<?php

namespace App\Exceptions;

use Exception;

class ApiException extends Exception
{
    public function __construct(string $message, int $statusCode = 500)
    {
        parent::__construct($message, $statusCode);
    }

    public function statusCode(): int
    {
        return $this->getCode() > 0 ? $this->getCode() : 500;
    }
}
