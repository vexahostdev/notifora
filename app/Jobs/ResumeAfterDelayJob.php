<?php

namespace App\Jobs;

use App\Enums\ExecutionStatus;
use App\Enums\StepStatus;
use App\Models\Environment;
use App\Models\StepExecution;
use App\Services\WorkflowEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class ResumeAfterDelayJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public function __construct(public string $stepExecutionId, public string $environmentId) {}

    public function handle(WorkflowEngine $engine): void
    {
        Environment::run($this->environmentId, function () use ($engine) {
            $step = StepExecution::with('execution')->find($this->stepExecutionId);
            if (! $step || $step->status !== StepStatus::WAITING) {
                return;
            }
            if ($step->execution->status === ExecutionStatus::CANCELED) {
                $step->update(['status' => StepStatus::SKIPPED, 'skip_reason' => 'canceled']);

                return;
            }
            if ($step->execution->environment?->workspace?->isSuspended()) {
                $engine->cancel($step->execution);

                return;
            }
            $step->update(['status' => StepStatus::COMPLETED, 'executed_at' => now()]);
            $engine->advance($step);
        });
    }
}
