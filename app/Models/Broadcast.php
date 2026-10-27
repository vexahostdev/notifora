<?php

namespace App\Models;

use App\Enums\MemberRole;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Broadcast extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = ['sent_at' => 'datetime'];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Daftar audiens yang tersedia untuk form. */
    public static function audiences(): array
    {
        $plans = collect(config('notifora.plans'))->mapWithKeys(fn ($p, $k) => ["plan:$k" => 'Plan '.$p['label']])->all();

        return ['all' => 'Semua workspace (aktif)', 'paid' => 'Hanya plan berbayar aktif', 'free' => 'Hanya plan Free'] + $plans;
    }

    /** Email unik Owner & Admin dari workspace yang tidak ditangguhkan sesuai audiens. */
    public static function recipientsFor(string $audience): array
    {
        $q = Workspace::query()->whereNull('suspended_at');
        if ($audience === 'paid') {
            $q->where('plan', '!=', 'free')->where(fn ($w) => $w->whereNull('plan_expires_at')->orWhere('plan_expires_at', '>', now()));
        } elseif ($audience === 'free') {
            $q->where(fn ($w) => $w->where('plan', 'free')->orWhere(fn ($x) => $x->whereNotNull('plan_expires_at')->where('plan_expires_at', '<', now())));
        } elseif (str_starts_with($audience, 'plan:')) {
            $q->where('plan', substr($audience, 5));
        }
        $ids = $q->pluck('id');

        return Member::with('user:id,email')->whereIn('workspace_id', $ids)
            ->whereIn('role', [MemberRole::OWNER->value, MemberRole::ADMIN->value])
            ->get()->pluck('user.email')->filter()->unique()->values()->all();
    }
}
