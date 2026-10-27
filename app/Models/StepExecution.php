<?php

namespace App\Models;

use App\Enums\StepStatus;
use App\Enums\StepType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class StepExecution extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected $casts = [
        'status' => StepStatus::class,
        'type' => StepType::class,
        'run_at' => 'datetime',
        'executed_at' => 'datetime',
    ];

    public function execution()
    {
        return $this->belongsTo(WorkflowExecution::class, 'workflow_execution_id');
    }

    public function messageLog()
    {
        return $this->hasOne(MessageLog::class);
    }

    /** Definisi step dari snapshot versi workflow. */
    public function definition(): array
    {
        return $this->execution->event->version->step($this->workflow_step_id) ?? [];
    }
}
