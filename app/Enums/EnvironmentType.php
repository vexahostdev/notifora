<?php

namespace App\Enums;

enum EnvironmentType: string
{
    case DEVELOPMENT = 'DEVELOPMENT';
    case PRODUCTION = 'PRODUCTION';

    public function keyPrefix(): string
    {
        return $this === self::PRODUCTION ? 'ntf_live_' : 'ntf_test_';
    }
}
