<?php

use App\Models\MessageLog;
use App\Models\Subscriber;
use App\Services\InboxTokenService;

beforeEach(function () {
    $this->t = makeTenant();
    $this->h = ['Authorization' => 'Bearer '.$this->t['key']];
    makeWorkflow([['channel' => 'IN_APP', 'subject' => 'Pesanan {{ payload.invoiceId }}', 'body' => 'Halo {{ subscriber.first_name }}']]);
});

it('delivers in-app messages to the inbox and tracks unread', function () {
    $this->postJson('/v1/events/trigger', triggerBody(), $this->h)->assertStatus(202);

    $res = $this->getJson('/v1/subscribers/usr_1/inbox', $this->h)->assertOk();
    $res->assertJsonPath('data.0.title', 'Pesanan INV-1')->assertJsonPath('meta.unread_count', 1);

    $id = $res->json('data.0.id');
    $this->postJson('/v1/subscribers/usr_1/inbox/read', ['ids' => [$id]], $this->h)->assertOk()->assertJsonPath('data.unread_count', 0);
    $this->getJson('/v1/subscribers/usr_1/inbox?unread=1', $this->h)->assertJsonCount(0, 'data');

    expect(MessageLog::first()->status->value)->toBe('DELIVERED');
});

it('issues verifiable subscriber tokens bound to the environment', function () {
    $this->postJson('/v1/events/trigger', triggerBody(), $this->h);

    $token = $this->postJson('/v1/subscribers/usr_1/inbox-token', [], $this->h)->assertOk()->json('data.token');
    $claims = app(InboxTokenService::class)->verify($token);

    expect($claims['env'])->toBe($this->t['env']->id)
        ->and($claims['sub'])->toBe(Subscriber::first()->id);
    expect(app(InboxTokenService::class)->verify($token.'x'))->toBeNull();
    expect(app(InboxTokenService::class)->verify('a.b.c'))->toBeNull();
});

it('lets other environments see nothing of this inbox', function () {
    $this->postJson('/v1/events/trigger', triggerBody(), $this->h);
    $other = makeTenant();
    $this->getJson('/v1/subscribers/usr_1/inbox', ['Authorization' => 'Bearer '.$other['key']])->assertStatus(404);
});

it('protects internal endpoints with the shared secret', function () {
    $this->getJson('/v1/internal/inbox/unread?env=x&subscriber_id=y')->assertStatus(403);
    $this->postJson('/v1/events/trigger', triggerBody(), $this->h);
    $sub = Subscriber::first();

    $this->getJson("/v1/internal/inbox/unread?env={$this->t['env']->id}&subscriber_id={$sub->id}", ['X-Internal-Secret' => 'internal-test-secret'])
        ->assertOk()->assertJsonPath('data.unreadCount', 1);
});

it('erases a subscriber', function () {
    $this->postJson('/v1/events/trigger', triggerBody(), $this->h);
    $this->deleteJson('/v1/subscribers/usr_1', [], $this->h)->assertOk();
    $this->getJson('/v1/subscribers/usr_1', $this->h)->assertStatus(404);
});
