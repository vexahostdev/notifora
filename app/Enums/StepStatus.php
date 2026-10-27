<?php

namespace App\Enums;

enum StepStatus: string
{
    case PENDING = 'PENDING';
    case QUEUED = 'QUEUED';
    case PROCESSING = 'PROCESSING';
    case WAITING = 'WAITING';
    case COMPLETED = 'COMPLETED';
    case FAILED = 'FAILED';
    case SKIPPED = 'SKIPPED';

    public function isFinal(): bool
    {
        return in_array($this, [self::COMPLETED, self::FAILED, self::SKIPPED], true);
    }
}
