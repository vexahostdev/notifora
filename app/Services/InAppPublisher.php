<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

/** Publish event real-time ke socket-engine lewat Redis pub/sub. */
class InAppPublisher
{
    public function publish(string $environmentId, string $subscriberId, string $type, array $data): void
    {
        if (config('notifora.socket.publish_driver') !== 'redis') {
            return;
        }

        try {
            Redis::publish(config('notifora.socket.channel'), json_encode([
                'env' => $environmentId,
                'subscriber_id' => $subscriberId,
                'type' => $type,
                'data' => $data,
            ]));
        } catch (Throwable $e) {
            // Real-time bersifat best-effort; pesan tetap ada di DB dan terbaca via polling.
            Log::warning('inapp.publish_failed', ['error' => $e->getMessage()]);
        }
    }
}
