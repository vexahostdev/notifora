<?php

namespace App\Exceptions;

use App\Support\ApiResponse;
use RuntimeException;

class ApiException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 422,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    public function render()
    {
        return ApiResponse::error($this->errorCode, $this->getMessage(), $this->status, $this->details);
    }
}
