<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEnvironment;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Workflow extends Model
{
    use BelongsToEnvironment, HasUuids;

    protected $guarded = [];

    protected $casts = ['is_critical' => 'boolean'];

    public function steps()
    {
        return $this->hasMany(WorkflowStep::class)->orderBy('step_order');
    }

    public function versions()
    {
        return $this->hasMany(WorkflowVersion::class);
    }

    public function publishedVersion()
    {
        return $this->belongsTo(WorkflowVersion::class, 'published_version_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'ACTIVE' && $this->published_version_id !== null;
    }
}
