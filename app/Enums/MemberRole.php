<?php

namespace App\Enums;

enum MemberRole: string
{
    case OWNER = 'OWNER';
    case ADMIN = 'ADMIN';
    case DEVELOPER = 'DEVELOPER';
    case VIEWER = 'VIEWER';

    public function level(): int
    {
        return match ($this) {
            self::OWNER => 4,
            self::ADMIN => 3,
            self::DEVELOPER => 2,
            self::VIEWER => 1,
        };
    }

    public function atLeast(self $min): bool
    {
        return $this->level() >= $min->level();
    }
}
