<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEnvironment;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class InAppMessage extends Model
{
    use BelongsToEnvironment, HasUlids;

    protected $guarded = [];

    protected $casts = ['data' => 'array', 'read_at' => 'datetime', 'archived_at' => 'datetime'];

    public function subscriber()
    {
        return $this->belongsTo(Subscriber::class);
    }

    public function toFeed(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'action_url' => $this->action_url,
            'data' => $this->data,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
