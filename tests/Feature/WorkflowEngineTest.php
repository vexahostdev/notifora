<?php

use App\Enums\ChannelType;
use App\Jobs\ResumeAfterDelayJob;
use App\Models\ChannelProvider;
use App\Models\MessageLog;
use App\Models\NotificationEvent;
use App\Models\StepExecution;
use App\Models\Subscriber;
use App\Models\SubscriberPreference;
use App\Models\Workflow;
use App\Services\WorkflowEngine;
use App\Services\WorkflowPublisher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->t = makeTenant();
    $this->h = ['Authorization' => 'Bearer '.$this->t['key']];
    makeProvider(ChannelType::WHATSAPP);
    makeProvider(ChannelType::EMAIL);
});

function steps(): array
{
    return StepExecution::orderBy('step_order')->get()->map(fn ($s) => $s->status->value.($s->skip_reason ? ':'.$s->skip_reason : ''))->all();
}

it('falls back to the alternative channel when the primary fails permanently', function () {
    Http::fake([
        'wa.test/*' => Http::response(['error' => 'invalid number'], 400),
        'mail.test/*' => Http::response(['id' => 'mail-1'], 200),
    ]);
    makeWorkflow([
        ['channel' => 'WHATSAPP', 'fallback' => 1],
        ['channel' => 'EMAIL', 'subject' => 'Order {{ payload.invoiceId }}', 'body' => 'Terima kasih'],
    ]);

    $this->postJson('/v1/events/trigger', triggerBody(), $this->h)->assertStatus(202);

    expect(steps())->toBe(['FAILED', 'COMPLETED']);
    $logs = MessageLog::orderBy('created_at')->get();
    expect($logs)->toHaveCount(2)
        ->and($logs->firstWhere('channel_type', 'WHATSAPP')->status->value)->toBe('FAILED')
        ->and($logs->firstWhere('channel_type', 'WHATSAPP')->error_code)->toBe('http_400')
        ->and($logs->firstWhere('channel_type', 'EMAIL')->status->value)->toBe('SENT');
    expect(NotificationEvent::first()->execution->status->value)->toBe('COMPLETED');
});

it('does not run a fallback step when the primary succeeds', function () {
    Http::fake(['wa.test/*' => Http::response(['messageId' => 'a'], 200)]);
    makeWorkflow([
        ['channel' => 'WHATSAPP', 'fallback' => 1],
        ['channel' => 'EMAIL', 'subject' => 'x', 'body' => 'y'],
    ]);

    $this->postJson('/v1/events/trigger', triggerBody(), $this->h);

    expect(steps())->toBe(['COMPLETED', 'SKIPPED:not_needed']);
    Http::assertNotSent(fn ($r) => str_contains($r->url(), 'mail.test'));
});

it('marks the execution FAILED when every channel fails', function () {
    Http::fake(['wa.test/*' => Http::response('boom', 400)]);
    makeWorkflow([['channel' => 'WHATSAPP']]);

    $this->postJson('/v1/events/trigger', triggerBody(), $this->h);

    expect(NotificationEvent::first()->execution->status->value)->toBe('FAILED');
});

it('fails with no_provider_configured when the channel has no provider', function () {
    ChannelProvider::where('channel', 'WHATSAPP')->delete();
    makeWorkflow([['channel' => 'WHATSAPP']]);

    $this->postJson('/v1/events/trigger', triggerBody(), $this->h);

    expect(MessageLog::first()->error_code)->toBe('no_provider_configured');
});

it('skips opted-out channels but not for critical workflows', function () {
    Http::fake(['wa.test/*' => Http::response(['messageId' => 'a'], 200)]);
    $wf = makeWorkflow([['channel' => 'WHATSAPP']]);

    $this->postJson('/v1/events/trigger', triggerBody(), $this->h);
    $sub = Subscriber::first();
    SubscriberPreference::create(['subscriber_id' => $sub->id, 'workflow_id' => $wf->id, 'channel' => 'WHATSAPP', 'enabled' => false]);

    $this->postJson('/v1/events/trigger', triggerBody(), $this->h);
    expect(StepExecution::orderBy('id')->get()->last()->skip_reason)->toBe('opted_out');
    Http::assertSentCount(1);

    $wf->update(['is_critical' => true]);
    $this->postJson('/v1/events/trigger', triggerBody(), $this->h);
    Http::assertSentCount(2);
});

it('honours a global opt-out even for critical workflows', function () {
    Http::fake();
    makeWorkflow([['channel' => 'WHATSAPP']], ['is_critical' => true]);
    $this->postJson('/v1/events/trigger', triggerBody(), $this->h);
    SubscriberPreference::create(['subscriber_id' => Subscriber::first()->id, 'workflow_id' => null, 'channel' => 'WHATSAPP', 'enabled' => false]);

    $this->postJson('/v1/events/trigger', triggerBody(), $this->h);
    Http::assertSentCount(1);
});

it('skips a step with no destination and continues', function () {
    Http::fake(['mail.test/*' => Http::response(['id' => 'm'], 200)]);
    makeWorkflow([
        ['channel' => 'TELEGRAM'],
        ['channel' => 'EMAIL', 'subject' => 's', 'body' => 'b'],
    ]);

    $this->postJson('/v1/events/trigger', triggerBody(), $this->h);

    expect(steps())->toBe(['SKIPPED:no_destination', 'COMPLETED']);
});

it('stops the workflow when a filter is false', function () {
    Http::fake(['wa.test/*' => Http::response(['messageId' => 'a'], 200)]);
    makeWorkflow([
        ['type' => 'FILTER', 'config' => ['rules' => [['path' => 'payload.amount', 'op' => 'gte', 'value' => 1000000]]]],
        ['channel' => 'WHATSAPP'],
    ]);

    $this->postJson('/v1/events/trigger', triggerBody(), $this->h); // 450000 < 1jt

    expect(steps())->toBe(['SKIPPED:filter_false', 'SKIPPED:filter_false']);
    Http::assertNothingSent();

    $this->postJson('/v1/events/trigger', triggerBody(['payload' => ['amount' => 2000000]]), $this->h);
    Http::assertSentCount(1);
});

it('waits on delay steps, resumes later, and can be canceled', function () {
    Http::fake(['wa.test/*' => Http::response(['messageId' => 'a'], 200)]);
    makeWorkflow([
        ['type' => 'DELAY', 'config' => ['seconds' => 3600]],
        ['channel' => 'WHATSAPP'],
    ]);

    Queue::fake([ResumeAfterDelayJob::class]);
    $res = $this->postJson('/v1/events/trigger', triggerBody(), $this->h)->assertStatus(202);

    expect(steps())->toBe(['WAITING', 'PENDING']);
    Queue::assertPushed(ResumeAfterDelayJob::class);
    Http::assertNothingSent();

    $delayStep = StepExecution::where('type', 'DELAY')->first();
    (new ResumeAfterDelayJob($delayStep->id, $this->t['env']->id))->handle(app(WorkflowEngine::class));
    expect(steps())->toBe(['COMPLETED', 'COMPLETED']);
    Http::assertSentCount(1);

    // cancel
    $res2 = $this->postJson('/v1/events/trigger', triggerBody(), $this->h);
    $this->deleteJson('/v1/events/'.$res2->json('data.event_id'), [], $this->h)->assertOk()->assertJsonPath('data.status', 'CANCELED');
    $waiting = StepExecution::where('type', 'DELAY')->get()->last();
    (new ResumeAfterDelayJob($waiting->id, $this->t['env']->id))->handle(app(WorkflowEngine::class));
    Http::assertSentCount(1);
});

it('applies per-trigger template overrides', function () {
    Http::fake(['wa.test/*' => Http::response(['messageId' => 'a'], 200)]);
    makeWorkflow([['channel' => 'WHATSAPP', 'body' => 'default']]);

    $this->postJson('/v1/events/trigger', triggerBody(['overrides' => ['channels' => ['whatsapp' => ['body' => 'Custom {{ payload.invoiceId }}']]]]), $this->h);

    Http::assertSent(fn ($r) => $r['message'] === 'Custom INV-1');
});

it('publishes validation errors for broken workflows', function () {
    $wf = makeWorkflow([['channel' => 'WHATSAPP', 'body' => '{{ payload.x']], [], publish: false);
    $errors = app(WorkflowPublisher::class)->validate($wf);
    expect($errors)->not->toBeEmpty();
});

it('keeps running executions on the version they started with', function () {
    Http::fake(['wa.test/*' => Http::response(['messageId' => 'a'], 200)]);
    $wf = makeWorkflow([['channel' => 'WHATSAPP', 'body' => 'v1']]);
    expect($wf->publishedVersion->version)->toBe(1);

    $wf->steps()->first()->template->update(['body' => 'v2']);
    app(WorkflowPublisher::class)->publish($wf);

    $this->postJson('/v1/events/trigger', triggerBody(), $this->h);
    Http::assertSent(fn ($r) => $r['message'] === 'v2');
    expect(Workflow::first()->versions()->count())->toBe(2);
});
