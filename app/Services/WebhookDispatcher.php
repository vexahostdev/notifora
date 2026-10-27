<?php

namespace App\Services;

use App\Jobs\DeliverCustomerWebhookJob;
use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;
use Illuminate\Support\Str;

class WebhookDispatcher
{
    public const EVENTS = [
        'message.sent', 'message.delivered', 'message.read', 'message.failed',
        'execution.completed', 'execution.failed',
    ];

    public function dispatch(string $environmentId, string $type, array $data): void
    {
        $subs = WebhookSubscription::withoutGlobalScopes()
            ->where('environment_id', $environmentId)
            ->where('status', 'active')
            ->get()
            ->filter(fn ($s) => in_array($type, $s->events ?? [], true) || in_array('*', $s->events ?? [], true));

        foreach ($subs as $sub) {
            $delivery = WebhookDelivery::create([
                'webhook_subscription_id' => $sub->id,
                'event_type' => $type,
                'payload' => [
                    'id' => 'evt_'.Str::lower(Str::random(20)),
                    'type' => $type,
                    'created_at' => now()->toIso8601String(),
                    'data' => $data,
                ],
                'status' => 'pending',
                'created_at' => now(),
            ]);
            DeliverCustomerWebhookJob::dispatch($delivery->id)->onQueue(config('notifora.queues.webhooks'));
        }
    }
}
