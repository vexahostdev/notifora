<?php

use App\Enums\ChannelType;
use App\Models\ApiKey;
use App\Models\ChannelProvider;
use App\Models\Environment;
use App\Models\StepTemplate;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Services\WorkflowPublisher;
use App\Services\WorkspaceProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');
pest()->extend(TestCase::class)->in('Unit');

/**
 * Buat workspace + environment DEV + API key.
 *
 * @return array{user:User, env:Environment, key:string, apiKey:ApiKey}
 */
function makeTenant(string $plan = 'starter'): array
{
    $user = User::factory()->create();
    $ws = app(WorkspaceProvisioner::class)->create($user, 'Acme '.uniqid());
    $ws->update(['plan' => $plan]);

    $env = $ws->environments()->where('type', 'DEVELOPMENT')->first()->load('workspace');
    app()->instance('current.environment', $env);
    [$apiKey, $plain] = ApiKey::issue($env, 'test');

    return ['user' => $user, 'env' => $env, 'key' => $plain, 'apiKey' => $apiKey];
}

function useEnv(Environment $env): void
{
    app()->instance('current.environment', $env->loadMissing('workspace'));
}

function makeProvider(ChannelType $channel, array $creds = []): ChannelProvider
{
    $provider = ChannelProvider::providerFor($channel);

    return ChannelProvider::create([
        'channel' => $channel,
        'provider' => $provider,
        'name' => $provider,
        'credentials' => $creds ?: match ($channel) {
            ChannelType::WHATSAPP => ['endpoint_url' => 'https://wa.test', 'api_key' => 'wa-key-1234', 'session_id' => 's1'],
            ChannelType::EMAIL => ['endpoint_url' => 'https://mail.test', 'api_key' => 'mail-key-1234', 'from_email' => 'no-reply@notifora.test', 'from_name' => 'Notifora'],
            ChannelType::TELEGRAM => ['bot_token' => '123:abc'],
            default => [],
        },
        'is_default' => true,
    ]);
}

/**
 * Buat + publish workflow. Step: ['type'=>'CHANNEL','channel'=>'WHATSAPP','body'=>'..','subject'=>..,'fallback'=>index]
 */
function makeWorkflow(array $steps, array $attrs = [], bool $publish = true): Workflow
{
    $workflow = Workflow::create([
        'name' => 'Order Confirmed',
        'trigger_key' => 'order-confirmed',
        'status' => 'DRAFT',
        ...$attrs,
    ]);

    $created = [];
    foreach ($steps as $i => $s) {
        $created[$i] = WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_order' => $i + 1,
            'type' => $s['type'] ?? 'CHANNEL',
            'name' => $s['name'] ?? 'Step '.($i + 1),
            'channel' => $s['channel'] ?? null,
            'config' => $s['config'] ?? null,
        ]);
        if (($s['type'] ?? 'CHANNEL') === 'CHANNEL') {
            StepTemplate::create([
                'workflow_step_id' => $created[$i]->id,
                'subject' => $s['subject'] ?? null,
                'body' => $s['body'] ?? 'Halo {{ subscriber.first_name }}',
            ]);
        }
    }
    foreach ($steps as $i => $s) {
        if (isset($s['fallback'])) {
            $created[$i]->update(['fallback_step_id' => $created[$s['fallback']]->id]);
        }
    }

    if ($publish) {
        $errors = app(WorkflowPublisher::class)->validate($workflow);
        expect($errors)->toBe([]);
        app(WorkflowPublisher::class)->publish($workflow);
    }

    return $workflow->fresh();
}

function triggerBody(array $overrides = []): array
{
    return [
        'workflow' => 'order-confirmed',
        'to' => ['subscriberId' => 'usr_1', 'phone' => '0812-3456-7890', 'email' => 'budi@example.com', 'firstName' => 'Budi'],
        'payload' => ['invoiceId' => 'INV-1', 'amount' => 450000],
        ...$overrides,
    ];
}
