<?php

namespace App\Enums;

enum MessageStatus: string
{
    case QUEUED = 'QUEUED';
    case SENT = 'SENT';
    case DELIVERED = 'DELIVERED';
    case READ = 'READ';
    case FAILED = 'FAILED';

    public function rank(): int
    {
        return match ($this) {
            self::QUEUED => 0,
            self::SENT => 1,
            self::DELIVERED => 2,
            self::READ => 3,
            self::FAILED => -1,
        };
    }

    /** Status hanya boleh maju; FAILED hanya valid dari QUEUED/SENT. */
    public function canTransitionTo(self $next): bool
    {
        if ($next === self::FAILED) {
            return in_array($this, [self::QUEUED, self::SENT], true);
        }

        return $this !== self::FAILED && $next->rank() > $this->rank();
    }
}
