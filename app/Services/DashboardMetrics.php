<?php

namespace App\Services;

use App\Models\MessageLog;
use App\Models\NotificationEvent;
use App\Models\UsageCounter;
use Carbon\CarbonImmutable;

/** Metrik dashboard untuk environment aktif. */
class DashboardMetrics
{
    private const RANGES = ['24h' => 24, '7d' => 168, '30d' => 720];

    public function summary(string $range = '24h'): array
    {
        $hours = self::RANGES[$range] ?? 24;
        $since = now()->subHours($hours);
        $perHour = $hours <= 24;

        $events = NotificationEvent::where('created_at', '>=', $since)->count();
        $byStatus = MessageLog::where('created_at', '>=', $since)->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');
        $delivered = (int) ($byStatus['SENT'] ?? 0) + (int) ($byStatus['DELIVERED'] ?? 0) + (int) ($byStatus['READ'] ?? 0);
        $failed = (int) ($byStatus['FAILED'] ?? 0);
        $finished = $delivered + $failed;

        $latency = MessageLog::where('created_at', '>=', $since)->whereNotNull('sent_at')->latest('id')->limit(500)->get(['created_at', 'sent_at'])
            ->map(fn ($m) => $m->sent_at->diffInMilliseconds($m->created_at, true))->avg();

        return [
            'range' => $range,
            'kpi' => [
                'events' => $events,
                'success_rate' => $finished ? round($delivered / $finished * 100, 1) : null,
                'avg_latency_ms' => $latency !== null ? (int) round($latency) : null,
                'failed' => $failed,
            ],
            'series' => $this->series($since, $perHour),
            'channels' => MessageLog::where('created_at', '>=', $since)->selectRaw('channel_type, count(*) as c')->groupBy('channel_type')->pluck('c', 'channel_type'),
            'recent_failures' => MessageLog::where('status', 'FAILED')->latest('id')->limit(20)->get()
                ->map(fn ($m) => [
                    'id' => $m->id,
                    'channel' => $m->channel_type,
                    'destination' => $m->destination_masked,
                    'error' => $m->error_code,
                    'at' => $m->created_at->diffForHumans(),
                ]),
        ];
    }

    /** @return array{labels: string[], values: int[]} */
    private function series(\DateTimeInterface $since, bool $perHour): array
    {
        $fmt = $perHour ? 'Y-m-d H:00' : 'Y-m-d';
        $step = $perHour ? '1 hour' : '1 day';
        $buckets = [];
        for ($t = CarbonImmutable::instance($since)->startOf($perHour ? 'hour' : 'day'); $t <= now(); $t = $t->modify("+$step")) {
            $buckets[$t->format($fmt)] = 0;
        }

        NotificationEvent::where('created_at', '>=', $since)->limit(50000)->pluck('created_at')
            ->each(function ($at) use (&$buckets, $fmt) {
                $k = $at->format($fmt);
                if (isset($buckets[$k])) {
                    $buckets[$k]++;
                }
            });

        return [
            'labels' => array_map(fn ($k) => $perHour ? substr($k, 11) : substr($k, 5), array_keys($buckets)),
            'values' => array_values($buckets),
        ];
    }

    /** Pemakaian bulan berjalan per hari. */
    public function usageDaily(string $environmentId): array
    {
        $rows = UsageCounter::where('environment_id', $environmentId)
            ->whereBetween('date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->orderBy('date')->get();

        return $rows->map(fn ($r) => [
            'date' => $r->date->format('d M'),
            'events' => $r->events_received,
            'sent' => $r->messages_sent,
            'failed' => $r->messages_failed,
        ])->all();
    }
}
