<?php

namespace App\Models;

use App\Enums\MessageStatus;
use App\Models\Concerns\BelongsToEnvironment;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class MessageLog extends Model
{
    use BelongsToEnvironment, HasUlids;

    protected $guarded = [];

    protected $casts = [
        'status' => MessageStatus::class,
        'sent_at' => 'datetime', 'delivered_at' => 'datetime', 'read_at' => 'datetime', 'failed_at' => 'datetime',
    ];

    public function stepExecution()
    {
        return $this->belongsTo(StepExecution::class);
    }

    public function attemptsLog()
    {
        return $this->hasMany(DeliveryAttempt::class)->orderBy('attempt');
    }

    public function subscriber()
    {
        return $this->belongsTo(Subscriber::class);
    }

    public function workflow()
    {
        return $this->belongsTo(Workflow::class);
    }

    /** Transisi status satu arah (hanya maju). */
    public function advanceStatus(MessageStatus $next, array $extra = []): bool
    {
        if (! $this->status->canTransitionTo($next)) {
            return false;
        }
        $stamp = match ($next) {
            MessageStatus::SENT => 'sent_at',
            MessageStatus::DELIVERED => 'delivered_at',
            MessageStatus::READ => 'read_at',
            MessageStatus::FAILED => 'failed_at',
            default => null,
        };
        $this->update(['status' => $next, ...($stamp ? [$stamp => now()] : []), ...$extra]);

        return true;
    }
}
