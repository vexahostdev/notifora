<?php

use App\Enums\ChannelType;
use App\Jobs\DeliverCustomerWebhookJob;
use App\Models\MessageLog;
use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Http::fake(['wa.test/*' => Http::response(['messageId' => 'wa-9'], 200)]);
    $this->t = makeTenant();
    $this->h = ['Authorization' => 'Bearer '.$this->t['key']];
    $this->provider = makeProvider(ChannelType::WHATSAPP);
    makeWorkflow([['channel' => 'WHATSAPP']]);
    $this->postJson('/v1/events/trigger', triggerBody(), $this->h);
    $this->hook = "/v1/hooks/vexahost_wa/{$this->provider->webhook_token}";
});

it('moves message status forward only', function () {
    $this->postJson($this->hook, ['messageId' => 'wa-9', 'status' => 'delivered'])->assertOk()->assertJsonPath('data.updated', true);
    expect(MessageLog::first()->status->value)->toBe('DELIVERED');

    // webhook terlambat tidak boleh memundurkan status
    $this->postJson($this->hook, ['messageId' => 'wa-9', 'status' => 'sent'])->assertOk()->assertJsonPath('data.updated', false);
    expect(MessageLog::first()->status->value)->toBe('DELIVERED');

    $this->postJson($this->hook, ['messageId' => 'wa-9', 'status' => 'read']);
    expect(MessageLog::first()->status->value)->toBe('READ');
    expect(MessageLog::first()->read_at)->not->toBeNull();
});

it('rejects webhooks with a bad token or wrong provider', function () {
    $this->postJson('/v1/hooks/vexahost_wa/'.str_repeat('x', 40), ['messageId' => 'wa-9', 'status' => 'read'])->assertStatus(404);
    $this->postJson("/v1/hooks/postivo/{$this->provider->webhook_token}", ['messageId' => 'wa-9', 'status' => 'read'])->assertStatus(404);
});

it('ignores unknown message ids', function () {
    $this->postJson($this->hook, ['messageId' => 'unknown', 'status' => 'read'])->assertOk()->assertJsonPath('data.accepted', false);
});

it('delivers signed outbound webhooks', function () {
    Http::fake([
        'wa.test/*' => Http::response(['messageId' => 'wa-10'], 200),
        'client.test/*' => Http::response('ok', 200),
    ]);
    $sub = WebhookSubscription::create(['url' => 'https://client.test/hook', 'secret' => 'whsec_test', 'events' => ['message.sent']]);

    $this->postJson('/v1/events/trigger', triggerBody(), $this->h);

    Http::assertSent(function ($r) {
        if (! str_contains($r->url(), 'client.test')) {
            return false;
        }
        preg_match('/t=(\d+),v1=([a-f0-9]{64})/', $r->header('X-Notifora-Signature')[0], $m);
        $expected = hash_hmac('sha256', $m[1].'.'.$r->body(), 'whsec_test');

        return hash_equals($expected, $m[2]) && $r->header('X-Notifora-Event')[0] === 'message.sent';
    });
    expect(WebhookDelivery::first()->status)->toBe('success');
});

it('retries failed outbound webhooks and blocks private targets', function () {
    Http::fake(['client.test/*' => Http::response('err', 500)]);
    $sub = WebhookSubscription::create(['url' => 'https://client.test/hook', 'secret' => 's', 'events' => ['*']]);
    $d = WebhookDelivery::create(['webhook_subscription_id' => $sub->id, 'event_type' => 'message.sent', 'payload' => ['a' => 1], 'created_at' => now()]);

    Queue::fake([DeliverCustomerWebhookJob::class]);
    (new DeliverCustomerWebhookJob($d->id))->handle();
    $d->refresh();
    expect($d->status)->toBe('pending')->and($d->attempt)->toBe(1)->and($d->next_retry_at)->not->toBeNull();
    Queue::assertPushed(DeliverCustomerWebhookJob::class);

    config(['app.env' => 'production']);
    app()->detectEnvironment(fn () => 'production');
    $sub->update(['url' => 'https://127.0.0.1/hook']);
    $d2 = WebhookDelivery::create(['webhook_subscription_id' => $sub->id, 'event_type' => 'message.sent', 'payload' => [], 'created_at' => now()]);
    (new DeliverCustomerWebhookJob($d2->id))->handle();
    expect($d2->refresh()->response_snippet)->toStartWith('blocked');
});
