<?php

namespace App\Models;

use App\Enums\MemberRole;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Workspace extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'plan_expires_at' => 'datetime',
        'plan_started_at' => 'datetime',
        'suspended_at' => 'datetime',
        'custom_limits' => 'array',
    ];

    public function environments()
    {
        return $this->hasMany(Environment::class);
    }

    public function members()
    {
        return $this->hasMany(Member::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Plan yang berlaku sekarang. Plan berbayar dengan tanggal berakhir melewati masa tenggang
     * jatuh ke "free". Plan tanpa tanggal berakhir (diberikan manual) tidak kedaluwarsa.
     */
    public function effectivePlanKey(): string
    {
        $plan = $this->plan;
        if (! $plan || $plan === 'free' || ! isset(config('notifora.plans')[$plan])) {
            return 'free';
        }
        if ($this->plan_expires_at && $this->graceEndsAt()->isPast()) {
            return 'free';
        }

        return $plan;
    }

    public function planConfig(): array
    {
        $key = $this->effectivePlanKey();
        $base = $key === 'free' ? config('notifora.free_plan') : config("notifora.plans.$key");

        // Batas kustom dari admin menimpa nilai plan (hanya events, rpm, retention_days)
        $custom = collect($this->custom_limits ?? [])
            ->only(['events', 'rpm', 'retention_days'])
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->map(fn ($v) => (int) $v)->all();

        return array_merge($base, $custom);
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    public function graceEndsAt(): ?Carbon
    {
        return $this->plan_expires_at?->copy()->addDays((int) config('notifora.billing.grace_days'));
    }

    /** Plan berbayar sudah lewat tanggal berakhir tetapi masih dalam masa tenggang. */
    public function inGrace(): bool
    {
        return $this->plan_expires_at && $this->plan_expires_at->isPast() && ! $this->graceEndsAt()->isPast();
    }

    /** Email OWNER & ADMIN, untuk notifikasi sistem. */
    public function adminEmails(): array
    {
        return $this->members()->with('user:id,email')
            ->whereIn('role', [MemberRole::OWNER->value, MemberRole::ADMIN->value])
            ->get()->pluck('user.email')->filter()->unique()->values()->all();
    }
}
