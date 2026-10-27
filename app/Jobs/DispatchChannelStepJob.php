<?php

namespace App\Jobs;

use App\Enums\ChannelType;
use App\Enums\ExecutionStatus;
use App\Enums\MessageStatus;
use App\Enums\StepStatus;
use App\Exceptions\RetryableProviderException;
use App\Models\DeliveryAttempt;
use App\Models\Environment;
use App\Models\MessageLog;
use App\Models\StepExecution;
use App\Services\Providers\ProviderFactory;
use App\Services\Providers\ProviderResult;
use App\Services\Providers\SendRequest;
use App\Services\UsageMeter;
use App\Services\WebhookDispatcher;
use App\Services\WorkflowEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

class DispatchChannelStepJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public const MAX_ATTEMPTS = 3;

    /** Percobaan dibatasi oleh message_logs.attempts (bukan $tries) agar circuit-open tidak menghabiskan jatah. */
    public int $tries = 0;

    public array $backoff = [5, 15, 60];

    public int $uniqueFor = 300;

    public function __construct(public string $stepExecutionId, public string $environmentId)
    {
        $this->onQueue(config('notifora.queues.channels'));
    }

    public function uniqueId(): string
    {
        return $this->stepExecutionId;
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addMinutes(30);
    }

    public function handle(ProviderFactory $factory, WorkflowEngine $engine, WebhookDispatcher $hooks, UsageMeter $usage): void
    {
        Environment::run($this->environmentId, function () use ($factory, $engine, $hooks, $usage) {
            $step = StepExecution::with('execution.event.subscriber', 'execution.event.version', 'execution.event.workflow')
                ->find($this->stepExecutionId);

            if (! $step || $step->status->isFinal()) {
                return; // idempoten terhadap redelivery
            }
            $exec = $step->execution;
            if ($exec->status === ExecutionStatus::CANCELED) {
                $step->update(['status' => StepStatus::SKIPPED, 'skip_reason' => 'canceled']);

                return;
            }

            $def = $step->definition();
            $channel = ChannelType::from($def['channel']);
            $event = $exec->event;
            $log = MessageLog::where('step_execution_id', $step->id)->firstOrFail();

            $step->update(['status' => StepStatus::PROCESSING]);

            $provider = $factory->forStep($def, $event);
            $started = hrtime(true);
            $result = $provider->send(new SendRequest(
                environmentId: $this->environmentId,
                to: $event->subscriber->destinationFor($channel),
                subject: $step->rendered_subject,
                content: (string) $step->rendered_content,
                html: $step->rendered_html,
                subscriber: $event->subscriber,
                meta: ['action_url' => data_get($event->payload, '_action_url'), 'data' => data_get($event->payload, '_data')],
            ));
            $ms = (int) ((hrtime(true) - $started) / 1e6);

            if ($result->deferred) {
                $step->update(['status' => StepStatus::QUEUED]);
                $this->release(30);

                return;
            }

            $log->increment('attempts');
            DeliveryAttempt::create([
                'message_log_id' => $log->id,
                'attempt' => $log->attempts,
                'success' => $result->success,
                'retryable' => $result->retryable,
                'http_status' => $result->httpStatus,
                'duration_ms' => $ms,
                'raw_trace' => $result->redactedTrace(),
                'created_at' => now(),
            ]);

            if ($result->success) {
                $log->update([
                    'provider' => class_basename($provider),
                    'external_message_id' => $result->messageId,
                    'error_code' => null,
                    'error_message' => null,
                ]);
                $log->advanceStatus($result->delivered ? MessageStatus::DELIVERED : MessageStatus::SENT);
                if ($result->delivered && ! $log->sent_at) {
                    $log->update(['sent_at' => now()]);
                }
                $step->update(['status' => StepStatus::COMPLETED, 'executed_at' => now()]);
                $usage->incrementSent($this->environmentId);
                $hooks->dispatch($this->environmentId, 'message.sent', $this->webhookData($log, $exec));
                $engine->advance($step);

                return;
            }

            $canRetry = $result->retryable
                && config('queue.default') !== 'sync'
                && $log->attempts < self::MAX_ATTEMPTS;

            if ($canRetry) {
                $step->update(['status' => StepStatus::QUEUED]);
                throw new RetryableProviderException($result->error ?? 'Provider error');
            }

            $this->markFailed($step, $log, $result, $usage, $hooks);
            $engine->onStepFailed($step);
        });
    }

    /** Dipanggil queue bila job crash di luar alur normal. */
    public function failed(Throwable $e): void
    {
        Environment::run($this->environmentId, function () use ($e) {
            $step = StepExecution::with('execution')->find($this->stepExecutionId);
            if (! $step || $step->status->isFinal()) {
                return;
            }
            $log = MessageLog::where('step_execution_id', $step->id)->first();
            $result = new ProviderResult(false, false, error: $e->getMessage(), errorCode: 'job_failed');
            $this->markFailed($step, $log, $result, app(UsageMeter::class), app(WebhookDispatcher::class));
            app(WorkflowEngine::class)->onStepFailed($step);
        });
    }

    private function markFailed(StepExecution $step, ?MessageLog $log, ProviderResult $result, UsageMeter $usage, WebhookDispatcher $hooks): void
    {
        $step->update(['status' => StepStatus::FAILED, 'executed_at' => now()]);
        $log?->advanceStatus(MessageStatus::FAILED, [
            'error_code' => $result->errorCode,
            'error_message' => $result->error ? mb_substr($result->error, 0, 500) : null,
        ]);
        $usage->incrementFailed($this->environmentId);
        if ($log) {
            $hooks->dispatch($this->environmentId, 'message.failed', $this->webhookData($log, $step->execution));
        }
    }

    private function webhookData(MessageLog $log, $exec): array
    {
        return [
            'message_id' => $log->id,
            'execution_id' => $exec->id,
            'channel' => $log->channel_type,
            'status' => $log->status->value,
            'error_code' => $log->error_code,
        ];
    }
}
