<?php

namespace App\Enums;

enum ExecutionStatus: string
{
    case PENDING = 'PENDING';
    case PROCESSING = 'PROCESSING';
    case COMPLETED = 'COMPLETED';
    case PARTIAL = 'PARTIAL';
    case FAILED = 'FAILED';
    case CANCELED = 'CANCELED';

    public function isFinal(): bool
    {
        return in_array($this, [self::COMPLETED, self::PARTIAL, self::FAILED, self::CANCELED], true);
    }
}
