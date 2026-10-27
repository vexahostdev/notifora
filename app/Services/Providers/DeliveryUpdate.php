<?php

namespace App\Services\Providers;

use App\Enums\MessageStatus;

final class DeliveryUpdate
{
    public function __construct(
        public readonly string $externalMessageId,
        public readonly MessageStatus $status,
        public readonly ?string $error = null,
    ) {}

    /** Petakan string status generik provider ke MessageStatus. */
    public static function mapStatus(?string $raw): ?MessageStatus
    {
        return match (strtolower((string) $raw)) {
            'queued', 'pending' => MessageStatus::QUEUED,
            'sent', 'accepted' => MessageStatus::SENT,
            'delivered', 'delivery' => MessageStatus::DELIVERED,
            'read', 'seen', 'opened', 'open' => MessageStatus::READ,
            'failed', 'error', 'bounced', 'bounce', 'undelivered', 'rejected' => MessageStatus::FAILED,
            default => null,
        };
    }
}
