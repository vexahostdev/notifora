<?php

namespace App\Services;

use App\Enums\ChannelType;
use App\Enums\ExecutionStatus;
use App\Enums\StepStatus;
use App\Enums\StepType;
use App\Jobs\DispatchChannelStepJob;
use App\Jobs\ResumeAfterDelayJob;
use App\Models\MessageLog;
use App\Models\StepExecution;
use App\Models\WorkflowExecution;
use App\Support\Phone;
use Illuminate\Support\Facades\Cache;

/**
 * Eksekusi workflow linear: FILTER -> DELAY -> CHANNEL (dengan fallback per step).
 * Job di-dispatch SETELAH lock dilepas supaya aman untuk queue driver "sync".
 */
class WorkflowEngine
{
    public function __construct(
        private TemplateCompiler $templates,
        private PreferenceResolver $prefs,
        private WebhookDispatcher $hooks,
    ) {}

    public function start(WorkflowExecution $exec): void
    {
        if ($exec->steps()->exists()) {
            return; // idempoten terhadap redelivery job
        }
        $exec->load('event.version', 'event.subscriber', 'event.workflow');

        foreach ($exec->event->version->snapshot['steps'] as $def) {
            StepExecution::create([
                'workflow_execution_id' => $exec->id,
                'workflow_step_id' => $def['id'],
                'step_order' => $def['order'],
                'type' => $def['type'],
                'status' => StepStatus::PENDING,
            ]);
        }

        $exec->update(['status' => ExecutionStatus::PROCESSING, 'started_at' => now()]);
        $this->drive($exec, 0);
    }

    /** Dipanggil setelah sebuah step selesai (sukses / dilewati). */
    public function advance(StepExecution $done): void
    {
        $this->drive($done->execution, $done->resume_after_order ?? $done->step_order);
    }

    /** Step channel gagal permanen: jalankan fallback bila ada, kalau tidak lanjut. */
    public function onStepFailed(StepExecution $failed): void
    {
        $exec = $failed->execution;
        $fbId = $failed->definition()['fallback_step_id'] ?? null;
        $fallback = $fbId
            ? $exec->steps()->where('workflow_step_id', $fbId)->where('status', StepStatus::PENDING->value)->first()
            : null;

        if ($fallback) {
            $fallback->update(['resume_after_order' => $failed->step_order]);
            $this->drive($exec, $failed->step_order, $fallback);

            return;
        }

        $this->advance($failed);
    }

    public function cancel(WorkflowExecution $exec): void
    {
        $exec->steps()
            ->whereIn('status', [StepStatus::PENDING->value, StepStatus::WAITING->value, StepStatus::QUEUED->value])
            ->update(['status' => StepStatus::SKIPPED->value, 'skip_reason' => 'canceled']);
        $exec->update(['status' => ExecutionStatus::CANCELED, 'finished_at' => now()]);
    }

    // ------------------------------------------------------------------

    private function drive(WorkflowExecution $exec, int $after, ?StepExecution $first = null): void
    {
        $pending = [];

        Cache::lock("exec:{$exec->id}", 15)->block(5, function () use ($exec, $after, $first, &$pending) {
            $exec->refresh()->load('event.version', 'event.subscriber', 'event.workflow');
            if ($exec->status === ExecutionStatus::CANCELED) {
                return;
            }
            $fallbackIds = collect($exec->event->version->snapshot['steps'])->pluck('fallback_step_id')->filter()->all();

            while (true) {
                $next = $first;
                $first = null;
                $next ??= $exec->steps()
                    ->where('status', StepStatus::PENDING->value)
                    ->where('step_order', '>', $after)
                    ->get()
                    ->first(fn ($s) => ! in_array($s->workflow_step_id, $fallbackIds, true));

                if (! $next) {
                    $this->finalize($exec);

                    return;
                }

                $outcome = $this->execute($exec, $next, $pending);
                if ($outcome === 'wait') {
                    return;
                }
                if ($outcome === 'stop') {
                    $this->finalize($exec);

                    return;
                }
                $after = $next->resume_after_order ?? $next->step_order;
            }
        });

        foreach ($pending as $job) {
            dispatch($job);
        }
    }

    /** @return 'continue'|'wait'|'stop' */
    private function execute(WorkflowExecution $exec, StepExecution $step, array &$pending): string
    {
        $step->setRelation('execution', $exec);
        $def = $step->definition();
        $event = $exec->event;
        $subscriber = $event->subscriber;

        switch ($step->type) {
            case StepType::FILTER:
                $ctx = ['payload' => $event->payload, 'subscriber' => $subscriber->templateContext()];
                if ($this->passes($def['config']['rules'] ?? [], $ctx)) {
                    $step->update(['status' => StepStatus::COMPLETED, 'executed_at' => now()]);

                    return 'continue';
                }
                $step->update(['status' => StepStatus::SKIPPED, 'skip_reason' => 'filter_false', 'executed_at' => now()]);
                $exec->steps()->where('status', StepStatus::PENDING->value)
                    ->update(['status' => StepStatus::SKIPPED->value, 'skip_reason' => 'filter_false']);

                return 'stop';

            case StepType::DELAY:
                $seconds = min((int) ($def['config']['seconds'] ?? 0), (int) config('notifora.limits.max_delay_seconds'));
                $step->update(['status' => StepStatus::WAITING, 'run_at' => now()->addSeconds($seconds)]);
                $pending[] = (new ResumeAfterDelayJob($step->id, $exec->environment_id))
                    ->onQueue(config('notifora.queues.default'))
                    ->delay($seconds);

                return 'wait';

            case StepType::CHANNEL:
                $channel = ChannelType::from($def['channel']);
                $destination = $subscriber->destinationFor($channel);

                if (! $destination) {
                    $step->update(['status' => StepStatus::SKIPPED, 'skip_reason' => 'no_destination']);

                    return 'continue';
                }
                if (! $this->prefs->allows($subscriber, $event->workflow, $channel)) {
                    $step->update(['status' => StepStatus::SKIPPED, 'skip_reason' => 'opted_out']);

                    return 'continue';
                }

                $tpl = $def['template'] ?? [];
                $override = data_get($event->overrides, 'channels.'.strtolower($channel->value), []);
                $ctx = [
                    'payload' => $event->payload,
                    'subscriber' => $subscriber->templateContext(),
                    'event' => ['id' => $event->id, 'created_at' => $event->created_at->toIso8601String()],
                    'workspace' => ['name' => $exec->environment->workspace->name ?? null],
                ];
                $subjectTpl = $override['subject'] ?? $tpl['subject'] ?? null;
                $bodyTpl = $override['body'] ?? $tpl['body'] ?? '';
                $htmlTpl = $override['body_html'] ?? $tpl['body_html'] ?? null;

                $step->update([
                    'status' => StepStatus::QUEUED,
                    'rendered_subject' => $subjectTpl !== null ? mb_substr($this->templates->render($subjectTpl, $ctx), 0, 500) : null,
                    'rendered_content' => $this->templates->render($bodyTpl, $ctx),
                    'rendered_html' => $htmlTpl ? $this->templates->render($htmlTpl, $ctx, true) : null,
                ]);

                MessageLog::firstOrCreate(['step_execution_id' => $step->id], [
                    'environment_id' => $exec->environment_id,
                    'subscriber_id' => $subscriber->id,
                    'workflow_id' => $event->workflow_id,
                    'channel_type' => $channel->value,
                    'destination_masked' => Phone::mask($destination),
                    'status' => 'QUEUED',
                ]);

                $pending[] = new DispatchChannelStepJob($step->id, $exec->environment_id);

                return 'wait';
        }

        return 'continue';
    }

    private function finalize(WorkflowExecution $exec): void
    {
        $exec->steps()->where('status', StepStatus::PENDING->value)
            ->update(['status' => StepStatus::SKIPPED->value, 'skip_reason' => 'not_needed']);

        $channelSteps = $exec->steps()->where('type', StepType::CHANNEL->value)->get();
        $exec->loadMissing('event.version', 'event.workflow');

        $completed = $channelSteps->where('status', StepStatus::COMPLETED)->count();
        $failed = $channelSteps->filter(function ($s) use ($channelSteps, $exec) {
            if ($s->status !== StepStatus::FAILED) {
                return false;
            }
            $fb = $exec->event->version->step($s->workflow_step_id)['fallback_step_id'] ?? null;

            return ! ($fb && $channelSteps->first(fn ($x) => $x->workflow_step_id === $fb && $x->status === StepStatus::COMPLETED));
        })->count();

        $status = $failed === 0 ? ExecutionStatus::COMPLETED : ($completed > 0 ? ExecutionStatus::PARTIAL : ExecutionStatus::FAILED);
        $exec->update(['status' => $status, 'finished_at' => now()]);

        $this->hooks->dispatch(
            $exec->environment_id,
            $status === ExecutionStatus::FAILED ? 'execution.failed' : 'execution.completed',
            [
                'execution_id' => $exec->id,
                'event_id' => $exec->notification_event_id,
                'workflow' => $exec->event->workflow->trigger_key,
                'status' => $status->value,
            ],
        );
    }

    /** Semua rule harus lolos (AND). Path: payload.x / subscriber.x */
    private function passes(array $rules, array $ctx): bool
    {
        foreach ($rules as $r) {
            $actual = data_get($ctx, $r['path'] ?? '');
            $expected = $r['value'] ?? null;

            $ok = match ($r['op'] ?? 'eq') {
                'eq' => (string) $actual === (string) $expected,
                'neq' => (string) $actual !== (string) $expected,
                'gt' => is_numeric($actual) && $actual > (float) $expected,
                'gte' => is_numeric($actual) && $actual >= (float) $expected,
                'lt' => is_numeric($actual) && $actual < (float) $expected,
                'lte' => is_numeric($actual) && $actual <= (float) $expected,
                'in' => in_array((string) $actual, array_map('trim', explode(',', (string) $expected)), true),
                'contains' => is_string($actual) && $expected !== null && str_contains($actual, (string) $expected),
                'exists' => $actual !== null && $actual !== '',
                default => false,
            };
            if (! $ok) {
                return false;
            }
        }

        return true;
    }
}
