<?php

namespace App\Jobs;

use App\Enums\ExecutionStatus;
use App\Models\Environment;
use App\Models\NotificationEvent;
use App\Models\WorkflowExecution;
use App\Services\WorkflowEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class ProcessWorkflowEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public array $backoff = [2, 10, 30];

    public function __construct(public string $eventId, public string $environmentId) {}

    public function handle(WorkflowEngine $engine): void
    {
        Environment::run($this->environmentId, function () use ($engine) {
            $event = NotificationEvent::find($this->eventId);
            if (! $event) {
                return;
            }
            $execution = WorkflowExecution::firstOrCreate(
                ['notification_event_id' => $event->id],
                ['environment_id' => $event->environment_id, 'status' => ExecutionStatus::PENDING],
            );
            $engine->start($execution);
        });
    }
}
