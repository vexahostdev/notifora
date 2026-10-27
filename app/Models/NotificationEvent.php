<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEnvironment;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class NotificationEvent extends Model
{
    use BelongsToEnvironment, HasUlids;

    protected $guarded = [];

    protected $casts = ['payload' => 'array', 'overrides' => 'array', 'is_test' => 'boolean', 'dry_run' => 'boolean'];

    public function workflow()
    {
        return $this->belongsTo(Workflow::class);
    }

    public function version()
    {
        return $this->belongsTo(WorkflowVersion::class, 'workflow_version_id');
    }

    public function subscriber()
    {
        return $this->belongsTo(Subscriber::class);
    }

    public function execution()
    {
        return $this->hasOne(WorkflowExecution::class);
    }
}
