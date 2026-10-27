<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Throwable;

/**
 * Status worker, supervisor, dan scheduler. Dibaca oleh Admin → Workers, halaman kesehatan,
 * dan watchdog. Semua data berasal dari denyut yang ditulis proses itu sendiri ke cache.
 */
class WorkerMonitor
{
    public const WORKER_STALE = 90;      // detik tanpa denyut = worker dianggap mati

    public const SUPERVISOR_STALE = 20;

    public const SCHEDULER_STALE = 180;

    /** @return array<string, array{queue:string, size:?int, beat:?int, alive:bool}> */
    public function queues(): array
    {
        $out = [];
        foreach (config('notifora.queues') as $key => $name) {
            try {
                $size = Queue::size($name);
            } catch (Throwable) {
                $size = null;
            }
            $beat = Cache::get("worker:beat:{$name}");
            $out[$key] = [
                'queue' => $name,
                'size' => $size,
                'beat' => $beat ? (int) $beat : null,
                'alive' => $beat && (time() - (int) $beat) < self::WORKER_STALE,
            ];
        }

        return $out;
    }

    /** @return array{alive:bool, data:?array} */
    public function supervisor(): array
    {
        $d = Cache::get('supervisor:status');
        $alive = is_array($d) && (time() - (int) ($d['updated_at'] ?? 0)) < self::SUPERVISOR_STALE;

        return ['alive' => $alive, 'data' => is_array($d) ? $d : null];
    }

    public function schedulerBeat(): ?int
    {
        $b = Cache::get('scheduler:heartbeat');

        return $b ? (int) $b : null;
    }

    public function schedulerAlive(): bool
    {
        $b = $this->schedulerBeat();

        return $b !== null && (time() - $b) < self::SCHEDULER_STALE;
    }

    public function failedCount(): int
    {
        try {
            return DB::table('failed_jobs')->count();
        } catch (Throwable) {
            return 0;
        }
    }

    /** Usia job tertua yang menunggu (detik) — hanya untuk driver database. */
    public function oldestWaitingSeconds(): ?int
    {
        if (config('queue.default') !== 'database') {
            return null;
        }
        try {
            $min = DB::table(config('queue.connections.database.table', 'jobs'))->whereNull('reserved_at')->min('available_at');

            return $min ? max(0, time() - (int) $min) : 0;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Daftar masalah yang perlu perhatian operator. Kosong = sehat.
     *
     * @return array<int,string>
     */
    public function problems(): array
    {
        $p = [];
        $driver = config('queue.default');

        if ($driver === 'sync') {
            // Mode sync: pekerjaan berjalan inline, tidak ada worker untuk dipantau.
            return app()->isProduction() ? ['QUEUE_CONNECTION=sync di production: pekerjaan berjalan di dalam request dan tidak ada retry antrean.'] : [];
        }

        foreach ($this->queues() as $q) {
            if (($q['size'] ?? 0) > 0 && ! $q['alive']) {
                $p[] = "Antrean {$q['queue']} berisi {$q['size']} job tetapi tidak ada worker aktif.";
            }
        }
        $old = $this->oldestWaitingSeconds();
        if ($old !== null && $old > config('notifora.workers.backlog_alert_after')) {
            $p[] = 'Job tertua sudah menunggu '.intdiv($old, 60).' menit.';
        }
        if (! $this->schedulerAlive()) {
            $p[] = 'Scheduler tidak berdenyut (billing sweep, masa tenggang, dan retensi log tidak berjalan).';
        }
        if (($f = $this->failedCount()) >= 25) {
            $p[] = "$f job gagal menumpuk.";
        }
        if (($b = Cache::get('backup:last_ok')) === null && config('notifora.backup.enabled') && app()->isProduction()) {
            $p[] = 'Belum ada backup database yang berhasil.';
        } elseif ($b && (time() - (int) $b) > 36 * 3600 && config('notifora.backup.enabled')) {
            $p[] = 'Backup database terakhir lebih dari 36 jam lalu.';
        }

        return $p;
    }
}
