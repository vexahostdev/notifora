<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = ['plans' => 'array', 'active' => 'boolean', 'expires_at' => 'datetime'];

    public function redemptions()
    {
        return $this->hasMany(CouponRedemption::class);
    }

    /** Pemakaian yang masih "memegang" kuota: pembayaran PENDING atau PAID. */
    public function usedCount(?string $workspaceId = null): int
    {
        return $this->redemptions()
            ->when($workspaceId, fn ($q) => $q->where('workspace_id', $workspaceId))
            ->whereHas('payment', fn ($q) => $q->whereIn('status', [Payment::PENDING, Payment::PAID]))
            ->count();
    }

    public function label(): string
    {
        return $this->type === 'percent' ? $this->value.'%' : 'Rp '.number_format($this->value, 0, ',', '.');
    }
}
