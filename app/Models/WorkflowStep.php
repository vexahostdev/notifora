<?php

namespace App\Models;

use App\Enums\ChannelType;
use App\Enums\StepType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class WorkflowStep extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = ['type' => StepType::class, 'channel' => ChannelType::class, 'config' => 'array'];

    public function workflow()
    {
        return $this->belongsTo(Workflow::class);
    }

    public function template()
    {
        return $this->hasOne(StepTemplate::class);
    }
}
