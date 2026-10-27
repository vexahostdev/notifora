<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MessageStatus;
use App\Http\Controllers\Controller;
use App\Models\ChannelProvider;
use App\Models\MessageLog;
use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;
use App\Services\AdminAudit;
use App\Services\HealthCheck;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class OpsController extends Controller
{
    public function index(HealthCheck $health)
    {
        $queues = collect(config('notifora.queues'))->map(function ($name) {
            try {
                return ['name' => $name, 'size' => Queue::size($name)];
            } catch (\Throwable) {
                return ['name' => $name, 'size' => null];
            }
        })->values();

        return view('admin.ops.index', [
            'checks' => $health->run(),
            'queues' => $queues,
            'driver' => config('queue.default'),
            'failedCount' => DB::table('failed_jobs')->count(),
            'heartbeat' => Cache::get('scheduler:heartbeat'),
        ]);
    }

    // --- Failed jobs -------------------------------------------------------------

    public function failed()
    {
        $jobs = DB::table('failed_jobs')->latest('id')->paginate(25)->through(function ($j) {
            $payload = json_decode($j->payload, true);

            return (object) [
                'id' => $j->id,
                'uuid' => $j->uuid,
                'queue' => $j->queue,
                'job' => class_basename($payload['displayName'] ?? 'unknown'),
                'error' => mb_substr(strtok($j->exception, "\n") ?: '', 0, 220),
                'failed_at' => $j->failed_at,
            ];
        });

        return view('admin.ops.failed', ['jobs' => $jobs]);
    }

    public function retry(string $uuid)
    {
        Artisan::call('queue:retry', ['id' => [$uuid]]);
        AdminAudit::record('ops.job_retried', 'failed_job', $uuid);

        return back()->with('success', 'Job dikembalikan ke antrean.');
    }

    public function forget(string $uuid)
    {
        Artisan::call('queue:forget', ['id' => $uuid]);
        AdminAudit::record('ops.job_deleted', 'failed_job', $uuid);

        return back()->with('success', 'Job gagal dihapus.');
    }

    public function retryAll()
    {
        $n = DB::table('failed_jobs')->count();
        Artisan::call('queue:retry', ['id' => ['all']]);
        AdminAudit::record('ops.jobs_retried_all', null, null, null, ['count' => $n]);

        return back()->with('success', "$n job dikembalikan ke antrean.");
    }

    public function flush()
    {
        $n = DB::table('failed_jobs')->count();
        Artisan::call('queue:flush');
        AdminAudit::record('ops.jobs_flushed', null, null, null, ['count' => $n]);

        return back()->with('success', "$n job gagal dihapus.");
    }

    // --- Provider, webhook, log global -------------------------------------------

    public function providers()
    {
        $providers = ChannelProvider::withoutGlobalScopes()->with('environment.workspace:id,name')->latest()->get()->map(function ($p) {
            $p->circuit_open = Cache::has("cb:open:{$p->id}");
            $p->recent_failures = (int) Cache::get("cb:fail:{$p->id}", 0);

            return $p;
        });

        return view('admin.ops.providers', ['providers' => $providers]);
    }

    public function webhooks(Request $request)
    {
        $subs = WebhookSubscription::withoutGlobalScopes()->with('environment.workspace:id,name')
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('consecutive_failures')->paginate(25)->withQueryString();

        $failed = WebhookDelivery::where('status', 'failed')->where('created_at', '>=', now()->subDay())->count();

        return view('admin.ops.webhooks', ['subs' => $subs, 'failed24h' => $failed]);
    }

    public function messages(Request $request)
    {
        $q = MessageLog::withoutGlobalScopes()->with('environment.workspace:id,name')->latest('id');
        if ($s = $request->query('status')) {
            $q->where('status', $s);
        }
        if ($c = $request->query('channel')) {
            $q->where('channel_type', $c);
        }
        if ($e = $request->query('error')) {
            $q->where('error_code', $e);
        }
        if ($term = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('id', $term)->orWhere('external_message_id', $term)->orWhere('destination_masked', 'like', "%$term%"));
        }
        $since = match ($request->query('range', '24h')) {
            '1h' => now()->subHour(), '24h' => now()->subDay(), '7d' => now()->subDays(7), default => null,
        };
        $since && $q->where('created_at', '>=', $since);

        return view('admin.ops.messages', [
            'logs' => $q->paginate(40)->withQueryString(),
            'statuses' => MessageStatus::cases(),
            'errors' => MessageLog::withoutGlobalScopes()->whereNotNull('error_code')->distinct()->pluck('error_code'),
        ]);
    }
}
