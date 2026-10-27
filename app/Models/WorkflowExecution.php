<?php

namespace App\Models;

use App\Enums\ExecutionStatus;
use App\Models\Concerns\BelongsToEnvironment;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class WorkflowExecution extends Model
{
    use BelongsToEnvironment, HasUlids;

    protected $guarded = [];

    protected $casts = ['status' => ExecutionStatus::class, 'started_at' => 'datetime', 'finished_at' => 'datetime'];

    public function event()
    {
        return $this->belongsTo(NotificationEvent::class, 'notification_event_id');
    }

    public function steps()
    {
        return $this->hasMany(StepExecution::class)->orderBy('step_order');
    }
}
