<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class WorkflowVersion extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = ['snapshot' => 'array', 'published_at' => 'datetime'];

    public function workflow()
    {
        return $this->belongsTo(Workflow::class);
    }

    public function step(string $id): ?array
    {
        return collect($this->snapshot['steps'] ?? [])->firstWhere('id', $id);
    }
}
