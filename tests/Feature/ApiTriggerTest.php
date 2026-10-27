<?php

use App\Enums\ChannelType;
use App\Models\ApiKey;
use App\Models\MessageLog;
use App\Models\NotificationEvent;
use App\Models\Subscriber;
use App\Models\Workflow;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::fake(['wa.test/*' => Http::response(['messageId' => 'wa-1'], 200)]);
    $this->t = makeTenant();
    makeProvider(ChannelType::WHATSAPP);
    makeWorkflow([['channel' => 'WHATSAPP', 'body' => 'Halo {{ subscriber.first_name }}, invoice {{ payload.invoiceId }}']]);
    $this->h = ['Authorization' => 'Bearer '.$this->t['key']];
});

it('rejects requests without a valid API key', function () {
    $this->postJson('/v1/events/trigger', triggerBody())->assertStatus(401)->assertJsonPath('error.code', 'unauthenticated');
    $this->postJson('/v1/events/trigger', triggerBody(), ['Authorization' => 'Bearer ntf_test_nope'])->assertStatus(401);
});

it('rejects a revoked key', function () {
    $this->t['apiKey']->update(['revoked_at' => now()]);
    Cache::flush();
    $this->postJson('/v1/events/trigger', triggerBody(), $this->h)->assertStatus(401);
});

it('accepts a trigger with 202 and delivers through the provider', function () {
    $res = $this->postJson('/v1/events/trigger', triggerBody(), $this->h)
        ->assertStatus(202)
        ->assertJsonPath('data.status', 'QUEUED')
        ->assertHeader('X-Request-Id');

    Http::assertSent(fn ($r) => str_contains($r->url(), 'wa.test/api/send-message')
        && $r['phoneNumber'] === '6281234567890'
        && $r['message'] === 'Halo Budi, invoice INV-1');

    $log = MessageLog::first();
    expect($log->status->value)->toBe('SENT')
        ->and($log->destination_masked)->toBe('+62812****890')
        ->and($log->external_message_id)->toBe('wa-1')
        ->and($log->attempts)->toBe(1);

    $this->getJson('/v1/events/'.$res->json('data.event_id'), $this->h)
        ->assertOk()->assertJsonPath('data.status', 'COMPLETED');
});

it('validates input', function () {
    $this->postJson('/v1/events/trigger', ['workflow' => 'x'], $this->h)
        ->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');
});

it('returns 404 for unknown workflows and 422 for drafts', function () {
    $this->postJson('/v1/events/trigger', triggerBody(['workflow' => 'nope']), $this->h)->assertStatus(404);

    Workflow::first()->update(['status' => 'DRAFT']);
    $this->postJson('/v1/events/trigger', triggerBody(), $this->h)
        ->assertStatus(422)->assertJsonPath('error.code', 'workflow_inactive');
});

it('is idempotent: replays the same response and rejects a different body', function () {
    $h = [...$this->h, 'X-Idempotency-Key' => 'order-1'];

    $first = $this->postJson('/v1/events/trigger', triggerBody(), $h)->assertStatus(202);
    $second = $this->postJson('/v1/events/trigger', triggerBody(), $h)->assertStatus(202)->assertHeader('Idempotent-Replay', 'true');

    expect($second->json('data.event_id'))->toBe($first->json('data.event_id'));
    expect(NotificationEvent::count())->toBe(1);
    Http::assertSentCount(1);

    $this->postJson('/v1/events/trigger', triggerBody(['payload' => ['invoiceId' => 'OTHER']]), $h)
        ->assertStatus(409)->assertJsonPath('error.code', 'idempotency_conflict');
});

it('does not leak data across environments', function () {
    $this->postJson('/v1/events/trigger', triggerBody(), $this->h)->assertStatus(202);
    $eventId = NotificationEvent::first()->id;

    $other = makeTenant();
    $this->getJson('/v1/events/'.$eventId, ['Authorization' => 'Bearer '.$other['key']])->assertStatus(404);
    $this->getJson('/v1/messages', ['Authorization' => 'Bearer '.$other['key']])->assertOk()->assertJsonCount(0, 'data');
});

it('enforces quota with a grace margin', function () {
    config(['notifora.dev_events_monthly' => 2]);
    // limit 2, grace 10% -> floor(2.2)=2, jadi event ke-3 ditolak
    $this->postJson('/v1/events/trigger', triggerBody(), $this->h)->assertStatus(202);
    $this->postJson('/v1/events/trigger', triggerBody(), $this->h)->assertStatus(202);
    $this->postJson('/v1/events/trigger', triggerBody(), $this->h)->assertStatus(402)->assertJsonPath('error.code', 'quota_exceeded');
});

it('upserts subscribers without duplicating them', function () {
    $this->postJson('/v1/events/trigger', triggerBody(), $this->h);
    $this->postJson('/v1/events/trigger', triggerBody(['to' => 'usr_1']), $this->h);
    expect(Subscriber::count())->toBe(1);
    expect(Subscriber::first()->phone)->toBe('+6281234567890');
});

it('rate limits per API key', function () {
    $this->t['env']->workspace->update(['plan' => 'starter']);
    config(['notifora.plans.starter.rpm' => 2]);
    Cache::flush();
    $this->getJson('/v1/workflows', $this->h)->assertOk();
    $this->getJson('/v1/workflows', $this->h)->assertOk();
    $this->getJson('/v1/workflows', $this->h)->assertStatus(429)->assertJsonPath('error.code', 'rate_limited');
});

it('creates keys with hashed storage', function () {
    expect(ApiKey::first()->key_hash)->toBe(hash('sha256', $this->t['key']))
        ->and($this->t['key'])->toStartWith('ntf_test_');
});
