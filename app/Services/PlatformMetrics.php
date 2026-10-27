<?php

namespace App\Services;

use App\Models\ChannelProvider;
use App\Models\EmailLog;
use App\Models\MessageLog;
use App\Models\NotificationEvent;
use App\Models\Payment;
use App\Models\UsageCounter;
use App\Models\User;
use App\Models\WebhookSubscription;
use App\Models\Workspace;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Metrik lintas tenant untuk dashboard & laporan operator. Semua query menghindari scope environment. */
class PlatformMetrics
{
    public function overview(): array
    {
        $now = now();
        $paidActive = Workspace::where('plan', '!=', 'free')
            ->where(fn ($q) => $q->whereNull('plan_expires_at')->orWhere('plan_expires_at', '>', $now));

        $eventsMonth = (int) UsageCounter::whereBetween('date', [$now->copy()->startOfMonth()->toDateString(), $now->copy()->endOfMonth()->toDateString()])->sum('events_received');
        $msg7 = MessageLog::withoutGlobalScopes()->where('created_at', '>=', $now->copy()->subDays(7))->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status');
        $ok = (int) (($msg7['SENT'] ?? 0) + ($msg7['DELIVERED'] ?? 0) + ($msg7['READ'] ?? 0));
        $bad = (int) ($msg7['FAILED'] ?? 0);

        return [
            'kpi' => [
                'workspaces' => Workspace::count(),
                'suspended' => Workspace::whereNotNull('suspended_at')->count(),
                'paid' => (clone $paidActive)->count(),
                'users' => User::count(),
                'admins' => User::where('is_platform_admin', true)->count(),
                'new_users_7d' => User::where('created_at', '>=', $now->copy()->subDays(7))->count(),
                'events_today' => NotificationEvent::withoutGlobalScopes()->where('created_at', '>=', $now->copy()->startOfDay())->count(),
                'events_month' => $eventsMonth,
                'success_rate_7d' => ($ok + $bad) ? round($ok / ($ok + $bad) * 100, 1) : null,
                'revenue_month' => (int) Payment::where('status', Payment::PAID)->where('paid_at', '>=', $now->copy()->startOfMonth())->sum('amount'),
                'revenue_30d' => (int) Payment::where('status', Payment::PAID)->where('paid_at', '>=', $now->copy()->subDays(30))->sum('amount'),
                'mrr' => $this->mrr($paidActive),
                'pending_payments' => Payment::where('status', Payment::PENDING)->count(),
            ],
            'alerts' => $this->alerts(),
            'series' => [
                'events' => $this->daily(NotificationEvent::withoutGlobalScopes(), 'created_at', 30),
                'revenue' => $this->daily(Payment::where('status', Payment::PAID), 'paid_at', 30, 'amount'),
                'signups' => $this->daily(User::query(), 'created_at', 30),
            ],
            'recent_signups' => User::latest()->limit(6)->get(['id', 'name', 'email', 'created_at']),
            'recent_payments' => Payment::with('workspace:id,name')->latest()->limit(6)->get(),
        ];
    }

    /** Perkiraan MRR: harga bulanan plan dari workspace berbayar yang masih aktif. */
    public function mrr($paidActiveQuery = null): int
    {
        $q = $paidActiveQuery ?? Workspace::where('plan', '!=', 'free')->where(fn ($w) => $w->whereNull('plan_expires_at')->orWhere('plan_expires_at', '>', now()));
        $counts = (clone $q)->selectRaw('plan, count(*) c')->groupBy('plan')->pluck('c', 'plan');
        $plans = config('notifora.plans');

        $total = 0;
        foreach ($counts as $plan => $c) {
            $total += $c * ($plans[$plan]['price'] ?? 0);
        }

        return (int) $total;
    }

    /** Hal yang perlu perhatian operator. */
    public function alerts(): array
    {
        $alerts = [];
        $failedJobs = DB::table('failed_jobs')->count();
        if ($failedJobs > 0) {
            $alerts[] = ['tone' => 'danger', 'text' => "$failedJobs job gagal menunggu ditinjau", 'url' => route('admin.ops.failed')];
        }
        $degraded = ChannelProvider::withoutGlobalScopes()->get()->filter(fn ($p) => $p->status === 'degraded' || Cache::has("cb:open:{$p->id}"))->count();
        if ($degraded > 0) {
            $alerts[] = ['tone' => 'warn', 'text' => "$degraded provider bermasalah (circuit terbuka)", 'url' => route('admin.ops.providers')];
        }
        $hooks = WebhookSubscription::withoutGlobalScopes()->where('status', 'disabled')->count();
        if ($hooks > 0) {
            $alerts[] = ['tone' => 'warn', 'text' => "$hooks webhook pelanggan dinonaktifkan otomatis", 'url' => route('admin.ops.webhooks')];
        }
        $mailFailed = EmailLog::where('status', 'failed')->where('created_at', '>=', now()->subDay())->count();
        if ($mailFailed > 0) {
            $alerts[] = ['tone' => 'danger', 'text' => "$mailFailed email sistem gagal terkirim (24 jam)", 'url' => route('admin.emails.logs', ['status' => 'failed'])];
        }
        $stale = Payment::where('status', Payment::PENDING)->where('expires_at', '<', now()->subHour())->count();
        if ($stale > 0) {
            $alerts[] = ['tone' => 'warn', 'text' => "$stale invoice melewati batas waktu belum disapu", 'url' => route('admin.payments.index', ['status' => 'PENDING'])];
        }
        if (! PlatformSettings::bool('signup_enabled')) {
            $alerts[] = ['tone' => 'info', 'text' => 'Pendaftaran akun baru sedang ditutup', 'url' => route('admin.settings')];
        }
        if (PlatformSettings::bool('maintenance')) {
            $alerts[] = ['tone' => 'danger', 'text' => 'MODE MAINTENANCE AKTIF — pelanggan melihat halaman pemeliharaan', 'url' => route('admin.settings')];
        }

        return $alerts;
    }

    /**
     * Deret harian $days hari terakhir.
     *
     * @return array{labels: string[], values: int[]}
     */
    public function daily($query, string $column, int $days, ?string $sumColumn = null): array
    {
        $since = now()->subDays($days - 1)->startOfDay();
        $rows = (clone $query)->where($column, '>=', $since)->get([$column, ...($sumColumn ? [$sumColumn] : [])]);

        $buckets = [];
        for ($i = 0; $i < $days; $i++) {
            $buckets[$since->copy()->addDays($i)->format('Y-m-d')] = 0;
        }
        foreach ($rows as $r) {
            $k = $r->{$column}->format('Y-m-d');
            if (isset($buckets[$k])) {
                $buckets[$k] += $sumColumn ? (int) $r->{$sumColumn} : 1;
            }
        }

        return ['labels' => array_map(fn ($d) => substr($d, 5), array_keys($buckets)), 'values' => array_values($buckets)];
    }

    /** Pendapatan & pendaftaran per bulan (12 bulan terakhir) + per plan. */
    public function revenueReport(): array
    {
        $since = now()->subMonths(11)->startOfMonth();
        $paid = Payment::where('status', Payment::PAID)->where('paid_at', '>=', $since)->get(['plan', 'amount', 'paid_at', 'discount']);
        $months = [];
        for ($i = 0; $i < 12; $i++) {
            $months[$since->copy()->addMonths($i)->format('Y-m')] = ['revenue' => 0, 'count' => 0, 'discount' => 0, 'signups' => 0];
        }
        $byPlan = [];
        foreach ($paid as $p) {
            $k = $p->paid_at->format('Y-m');
            if (isset($months[$k])) {
                $months[$k]['revenue'] += $p->amount;
                $months[$k]['count']++;
                $months[$k]['discount'] += $p->discount;
            }
            $byPlan[$p->plan] = ($byPlan[$p->plan] ?? 0) + $p->amount;
        }
        foreach (User::where('created_at', '>=', $since)->get(['created_at']) as $u) {
            $k = $u->created_at->format('Y-m');
            if (isset($months[$k])) {
                $months[$k]['signups']++;
            }
        }
        arsort($byPlan);

        return ['months' => $months, 'by_plan' => $byPlan, 'total' => array_sum(array_column($months, 'revenue'))];
    }

    /** Tenant dengan event terbanyak bulan ini. */
    public function topTenants(int $limit = 10)
    {
        return UsageCounter::query()
            ->join('environments', 'environments.id', '=', 'usage_counters.environment_id')
            ->join('workspaces', 'workspaces.id', '=', 'environments.workspace_id')
            ->whereBetween('usage_counters.date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->groupBy('workspaces.id', 'workspaces.name', 'workspaces.plan')
            ->selectRaw('workspaces.id, workspaces.name, workspaces.plan, sum(usage_counters.events_received) as events, sum(usage_counters.messages_failed) as failed')
            ->orderByDesc('events')->limit($limit)->get();
    }
}
