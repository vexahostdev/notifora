<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Environment;
use App\Models\UsageCounter;
use Illuminate\Support\Facades\DB;

class UsageMeter
{
    /** Pemakaian bulan berjalan terakhir yang dihitung assertWithinQuota (hindari query ganda). */
    public int $lastUsed = 0;

    public function incrementEvent(Environment $env): void
    {
        $this->bump($env, 'events_received');
    }

    public function incrementSent(string $environmentId): void
    {
        $this->bump($environmentId, 'messages_sent');
    }

    public function incrementFailed(string $environmentId): void
    {
        $this->bump($environmentId, 'messages_failed');
    }

    public function monthlyEvents(Environment|string $env): int
    {
        $id = $env instanceof Environment ? $env->id : $env;

        return (int) UsageCounter::where('environment_id', $id)
            ->whereBetween('date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->sum('events_received');
    }

    public function limitFor(Environment $env): int
    {
        if (! $env->isProduction()) {
            return (int) config('notifora.dev_events_monthly');
        }

        return (int) ($env->workspace->planConfig()['events'] ?? 0);
    }

    /** Soft limit: lewat kuota + grace 10% baru ditolak (402). */
    public function assertWithinQuota(Environment $env): void
    {
        $limit = $this->limitFor($env);
        $hard = (int) floor($limit * (1 + config('notifora.quota_grace')));

        $this->lastUsed = $this->monthlyEvents($env);
        if ($this->lastUsed >= $hard) {
            throw new ApiException('quota_exceeded', 'Monthly event quota exceeded. Upgrade your plan.', 402, ['limit' => $limit]);
        }
    }

    private function bump(Environment|string $env, string $column): void
    {
        $id = $env instanceof Environment ? $env->id : $env;
        $today = now()->toDateString();

        DB::table('usage_counters')->upsert(
            [['environment_id' => $id, 'date' => $today, $column => 1, 'created_at' => now(), 'updated_at' => now()]],
            ['environment_id', 'date'],
            [$column => DB::raw($column.' + 1'), 'updated_at' => now()],
        );
    }
}
