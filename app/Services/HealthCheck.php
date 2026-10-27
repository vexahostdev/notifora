<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Throwable;

/** Pemeriksaan kesehatan sistem untuk Admin → Operations. Tidak pernah melempar; selalu mengembalikan status. */
class HealthCheck
{
    /** @return array<int, array{name:string, status:string, detail:string}> status: ok|warn|fail */
    public function run(): array
    {
        $checks = [];
        $add = function (string $name, callable $fn) use (&$checks) {
            try {
                [$status, $detail] = $fn();
            } catch (Throwable $e) {
                [$status, $detail] = ['fail', mb_substr($e->getMessage(), 0, 160)];
            }
            $checks[] = compact('name', 'status', 'detail');
        };

        $add('Database', function () {
            $t = microtime(true);
            DB::select('select 1');

            return ['ok', config('database.default').' · '.round((microtime(true) - $t) * 1000, 1).' ms'];
        });
        $add('Cache', function () {
            Cache::put('health:probe', 'ok', 10);

            return [Cache::get('health:probe') === 'ok' ? 'ok' : 'fail', 'store: '.config('cache.default')];
        });
        $add('Queue', function () {
            $conn = config('queue.default');
            if ($conn === 'sync') {
                return ['warn', 'driver sync: job berjalan inline (tidak ada worker). Gunakan redis di staging/production.'];
            }
            $sizes = collect(config('notifora.queues'))->map(fn ($q) => $q.'='.Queue::size($q))->implode(' · ');

            return ['ok', "driver $conn · $sizes"];
        });
        $add('Redis', function () {
            if (config('queue.default') !== 'redis' && config('cache.default') !== 'redis' && config('notifora.socket.publish_driver') !== 'redis') {
                return ['warn', 'tidak dipakai di environment ini'];
            }

            return [Redis::connection()->ping() ? 'ok' : 'fail', 'PING'];
        });
        $add('Scheduler', function () {
            $beat = Cache::get('scheduler:heartbeat');
            if (! $beat) {
                return ['warn', 'belum ada denyut (jalankan schedule:work / cron)'];
            }
            $age = now()->timestamp - (int) $beat;

            return [$age < 7200 ? 'ok' : 'warn', 'denyut terakhir '.now()->subSeconds($age)->diffForHumans()];
        });
        $add('Email (SMTP)', function () {
            $mailer = config('mail.default');
            if (in_array($mailer, ['log', 'array'], true)) {
                return ['warn', "mailer '$mailer' — email tidak dikirim sungguhan"];
            }
            $cfg = config("mail.mailers.$mailer");
            $filled = ! empty($cfg['username'] ?? null) && ! empty($cfg['password'] ?? null);

            return [$filled ? 'ok' : 'fail', ($cfg['host'] ?? '?').':'.($cfg['port'] ?? '?').($filled ? ' · kredensial terisi' : ' · KREDENSIAL KOSONG')];
        });
        $add('Pembayaran (Xendit)', function () {
            $k = (bool) config('services.xendit.secret_key');
            $i = (bool) config('services.xendit.internal_secret') || (bool) config('services.xendit.webhook_token');

            return [($k && $i) ? 'ok' : (app()->environment(['local', 'development']) ? 'warn' : 'fail'), 'secret key: '.($k ? 'ada' : 'KOSONG').' · token callback: '.($i ? 'ada' : 'KOSONG')];
        });
        $add('Socket engine', function () {
            $url = config('admin.socket_health_url');
            if (! $url) {
                return ['warn', 'SOCKET_HEALTH_URL belum diatur'];
            }
            $r = Http::timeout(3)->get($url);

            return [$r->successful() ? 'ok' : 'fail', "$url → HTTP ".$r->status()];
        });
        $add('Job gagal', function () {
            $n = DB::table('failed_jobs')->count();

            return [$n === 0 ? 'ok' : 'warn', "$n job gagal"];
        });
        $add('Penyimpanan', function () {
            $free = @disk_free_space(storage_path());
            if ($free === false) {
                return ['warn', 'tidak dapat membaca ruang disk'];
            }
            $gb = round($free / 1073741824, 1);

            return [$gb > 2 ? 'ok' : 'warn', "$gb GB tersedia · storage ".(is_writable(storage_path()) ? 'writable' : 'READ-ONLY')];
        });

        return $checks;
    }
}
