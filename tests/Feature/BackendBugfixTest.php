<?php

use App\Enums\ExecutionStatus;
use App\Jobs\DispatchChannelStepJob;
use App\Models\Payment;
use App\Models\WorkflowExecution;
use App\Services\Billing\BillingService;
use App\Services\Providers\ProviderFactory;
use App\Services\UsageMeter;
use App\Services\WebhookDispatcher;
use App\Services\WorkflowEngine;
use App\Services\WorkflowPublisher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

it('returns 404 for an unknown channel slug instead of crashing', function () {
    $t = makeTenant();

    $this->actingAs($t['user'])->get('/console/channels/NOPE')->assertNotFound();
    $this->actingAs($t['user'])->put('/console/channels/NOPE', [])->assertNotFound();
    $this->actingAs($t['user'])->post('/console/channels/NOPE/test', ['to' => 'x'])->assertNotFound();
    $this->actingAs($t['user'])->delete('/console/channels/NOPE')->assertNotFound();
});

it('returns 404 when disabling 2FA that was never enabled', function () {
    $t = makeTenant();

    $this->actingAs($t['user'])
        ->delete('/security/2fa', ['password' => 'Password123', 'code' => '123456'])
        ->assertNotFound();
});

it('rejects subscriber ids longer than the database column', function () {
    $t = makeTenant();

    $this->putJson('/v1/subscribers/'.str_repeat('x', 192), [], ['Authorization' => 'Bearer '.$t['key']])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_failed');
});

it('rejects duplicate step uids when saving a workflow', function () {
    $t = makeTenant();
    $this->actingAs($t['user'])->post('/console/workflows', ['name' => 'Dup', 'trigger_key' => 'dup', 'priority' => 'NORMAL']);

    $workflow = App\Models\Workflow::where('trigger_key', 'dup')->firstOrFail();
    $step = ['uid' => 'same', 'type' => 'CHANNEL', 'name' => 'WA', 'channel' => 'WHATSAPP', 'config' => [], 'template' => ['body' => 'Halo']];

    $this->actingAs($t['user'])
        ->putJson("/console/workflows/{$workflow->id}", ['name' => 'Dup', 'priority' => 'NORMAL', 'steps' => [$step, $step]])
        ->assertStatus(422);
});

it('rejects malformed filter rules at publish time', function () {
    makeTenant();

    $workflow = makeWorkflow([
        ['type' => 'FILTER', 'config' => ['rules' => 'not-an-array']],
        ['channel' => 'WHATSAPP'],
    ], [], publish: false);

    expect(app(WorkflowPublisher::class)->validate($workflow))->not->toBeEmpty();
});

it('never downgrades a paid payment when a late expired callback arrives', function () {
    $t = makeTenant();
    $payment = Payment::create([
        'number' => 'NTF-INV-2026-900001',
        'workspace_id' => $t['env']->workspace_id,
        'user_id' => $t['user']->id,
        'plan' => 'pro',
        'months' => 1,
        'subtotal' => 299000,
        'discount' => 0,
        'amount' => 299000,
        'status' => Payment::PAID,
        'external_id' => 'NTF-TEST-900001',
        'expires_at' => now()->addDay(),
    ]);

    app(BillingService::class)->markUnpaid($payment, Payment::EXPIRED, 'expired');
    app(BillingService::class)->cancel($payment);

    expect($payment->fresh()->status)->toBe(Payment::PAID);
});

it('does not send a queued channel job after the workspace is suspended', function () {
    Http::fake(['wa.test/*' => Http::response(['messageId' => 'wa-1'], 200)]);
    $t = makeTenant();
    makeProvider(App\Enums\ChannelType::WHATSAPP);
    makeWorkflow([['channel' => 'WHATSAPP']]);

    Queue::fake([DispatchChannelStepJob::class]);
    $this->postJson('/v1/events/trigger', triggerBody(), ['Authorization' => 'Bearer '.$t['key']])->assertStatus(202);

    $job = null;
    Queue::assertPushed(DispatchChannelStepJob::class, function ($pushed) use (&$job) {
        $job = $pushed;

        return true;
    });

    $t['env']->workspace->update(['suspended_at' => now()]);
    $job->handle(app(ProviderFactory::class), app(WorkflowEngine::class), app(WebhookDispatcher::class), app(UsageMeter::class));

    expect(WorkflowExecution::first()->status)->toBe(ExecutionStatus::CANCELED);
    Http::assertNothingSent();
});
