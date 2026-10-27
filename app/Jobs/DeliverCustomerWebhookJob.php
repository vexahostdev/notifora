<?php

namespace App\Jobs;

use App\Models\Environment;
use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;
use App\Services\SafeUrl;
use App\Services\SystemNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Throwable;

class DeliverCustomerWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    /** Jeda antar percobaan (detik): 1m, 5m, 30m, 2j, 6j, 24j. */
    public const RETRY_DELAYS = [60, 300, 1800, 7200, 21600, 86400];

    public function __construct(public string $deliveryId) {}

    public function handle(): void
    {
        $delivery = WebhookDelivery::find($this->deliveryId);
        $sub = $delivery ? WebhookSubscription::withoutGlobalScopes()->find($delivery->webhook_subscription_id) : null;

        if (! $delivery || ! $sub || $delivery->status === 'success') {
            return;
        }
        if ($sub->status !== 'active') {
            $delivery->update(['status' => 'failed', 'response_snippet' => 'subscription disabled']);

            return;
        }

        $body = json_encode($delivery->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $attempt = $delivery->attempt + 1;
        $status = null;
        $snippet = null;
        $ok = false;

        $url = SafeUrl::inspect($sub->url);
        if (! $url['ok']) {
            $snippet = 'blocked: '.$url['reason'];
        } else {
            $ts = time();
            $sig = hash_hmac('sha256', $ts.'.'.$body, $sub->secret);
            $options = ['allow_redirects' => false];
            if ($url['ip'] && ! filter_var($url['host'], FILTER_VALIDATE_IP)) {
                // Pin DNS ke IP yang sudah diperiksa.
                $options['curl'] = [CURLOPT_RESOLVE => ["{$url['host']}:{$url['port']}:{$url['ip']}"]];
            }

            try {
                $res = Http::withOptions($options)->timeout(10)->connectTimeout(5)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                        'X-Notifora-Event' => $delivery->event_type,
                        'X-Notifora-Delivery' => $delivery->id,
                        'X-Notifora-Signature' => "t=$ts,v1=$sig",
                    ])
                    ->withBody($body, 'application/json')
                    ->post($sub->url);
                $status = $res->status();
                $snippet = mb_substr($res->body(), 0, 500);
                $ok = $res->successful();
            } catch (Throwable $e) {
                $snippet = mb_substr($e->getMessage(), 0, 500);
            }
        }

        if ($ok) {
            $delivery->update(['status' => 'success', 'attempt' => $attempt, 'http_status' => $status, 'response_snippet' => $snippet, 'next_retry_at' => null]);
            $sub->update(['consecutive_failures' => 0]);

            return;
        }

        $sub->increment('consecutive_failures');
        if ($sub->consecutive_failures >= 50) {
            $sub->update(['status' => 'disabled', 'disabled_at' => now()]);
            $ws = Environment::with('workspace')->find($sub->environment_id)?->workspace;
            if ($ws) {
                app(SystemNotifier::class)->webhookDisabled($ws, $sub->url, $sub->consecutive_failures);
            }
        }

        if ($attempt > count(self::RETRY_DELAYS)) {
            $delivery->update(['status' => 'failed', 'attempt' => $attempt, 'http_status' => $status, 'response_snippet' => $snippet, 'next_retry_at' => null]);

            return;
        }

        $delay = self::RETRY_DELAYS[$attempt - 1];
        $delivery->update(['status' => 'pending', 'attempt' => $attempt, 'http_status' => $status, 'response_snippet' => $snippet, 'next_retry_at' => now()->addSeconds($delay)]);
        static::dispatch($delivery->id)->onQueue(config('notifora.queues.webhooks'))->delay($delay);
    }
}
