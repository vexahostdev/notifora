<?php

namespace App\Enums;

enum ChannelType: string
{
    case WHATSAPP = 'WHATSAPP';
    case EMAIL = 'EMAIL';
    case TELEGRAM = 'TELEGRAM';
    case IN_APP = 'IN_APP';

    public function label(): string
    {
        return match ($this) {
            self::WHATSAPP => 'WhatsApp',
            self::EMAIL => 'Email',
            self::TELEGRAM => 'Telegram',
            self::IN_APP => 'In-App',
        };
    }
}
