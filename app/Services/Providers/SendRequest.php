<?php

namespace App\Services\Providers;

use App\Models\Subscriber;

final class SendRequest
{
    public function __construct(
        public readonly string $environmentId,
        public readonly ?string $to,
        public readonly ?string $subject,
        public readonly string $content,
        public readonly ?string $html = null,
        public readonly ?Subscriber $subscriber = null,
        public readonly array $meta = [],
    ) {}
}
