<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasUuids;

    public const PENDING = 'PENDING';

    public const PAID = 'PAID';

    public const EXPIRED = 'EXPIRED';

    public const FAILED = 'FAILED';

    public const CANCELED = 'CANCELED';

    protected $guarded = [];

    protected $hidden = ['raw'];

    protected $casts = [
        'raw' => 'array',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
        'applied_at' => 'datetime',
        'amount' => 'integer',
        'months' => 'integer',
    ];

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    public function isPaid(): bool
    {
        return $this->status === self::PAID;
    }

    public function planLabel(): string
    {
        return config("notifora.plans.{$this->plan}.label", ucfirst($this->plan));
    }

    /** Nomor invoice berurutan per tahun: NTF-INV-2026-000001 */
    public static function nextNumber(): string
    {
        $year = now()->year;
        $last = static::where('number', 'like', "NTF-INV-$year-%")->orderByDesc('number')->value('number');
        $seq = $last ? ((int) substr($last, -6)) + 1 : 1;

        return sprintf('NTF-INV-%d-%06d', $year, $seq);
    }
}
