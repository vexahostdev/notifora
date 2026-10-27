<?php

namespace App\Services;

use App\Models\EmailLog;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Daftar periksa kesiapan production (Admin → Sistem → Kesiapan). Hanya membaca konfigurasi & status;
 * tidak pernah menampilkan nilai rahasia.
 */
class ProductionAudit
{
    /** @return array<string, array<int, array{name:string, status:string, detail:string, fix:?string}>> dikelompokkan per kategori */
    public function run(): array
    {
        $prod = app()->isProduction();
        $r = [];
        $add = function (string $group, string $name, string $status, string $detail, ?string $fix = null) use (&$r) {
            $r[$group][] = compact('name', 'status', 'detail', 'fix');
        };
        // $strict: gagal (merah) di production, peringatan (kuning) di lingkungan lain
        $bad = fn () => $prod ? 'fail' : 'warn';

        // --- Aplikasi & keamanan
        $g = 'Aplikasi & keamanan';
        $add($g, 'APP_ENV', app()->environment('production', 'staging') ? 'ok' : 'warn', app()->environment(), 'Set APP_ENV=production (atau staging) di server.');
        $add($g, 'APP_DEBUG nonaktif', config('app.debug') ? $bad() : 'ok', config('app.debug') ? 'DEBUG AKTIF — stack trace & env bisa bocor' : 'nonaktif', 'Set APP_DEBUG=false.');
        $add($g, 'APP_URL memakai HTTPS', str_starts_with((string) config('app.url'), 'https://') ? 'ok' : $bad(), (string) config('app.url'), 'Set APP_URL=https://notifora.my.id dan pasang TLS.');
        $add($g, 'APP_KEY terisi', config('app.key') ? 'ok' : 'fail', config('app.key') ? 'terisi (backup terpisah!)' : 'KOSONG', 'php artisan key:generate --force, lalu simpan cadangannya di tempat aman: kredensial provider tenant dienkripsi dengan key ini.');
        $add($g, 'Cookie sesi Secure', config('session.secure') ? 'ok' : $bad(), config('session.secure') ? 'SESSION_SECURE_COOKIE=true' : 'belum aktif', 'Set SESSION_SECURE_COOKIE=true.');
        $add($g, 'Operator wajib 2FA', config('admin.require_2fa') ? 'ok' : $bad(), config('admin.require_2fa') ? 'ya' : 'tidak', 'Set ADMIN_2FA_REQUIRED=true.');
        $add($g, 'Trusted proxies', 'ok', 'semua proxy dipercaya (at: *)', 'Pastikan port PHP/Nginx tidak terbuka langsung ke internet, hanya lewat proxy/Cloudflare, agar header X-Forwarded-For tidak bisa dipalsukan.');
        $sock = (string) config('notifora.socket.jwt_secret');
        $add($g, 'SOCKET_JWT_SECRET kuat', strlen($sock) >= 32 ? 'ok' : $bad(), strlen($sock) >= 32 ? 'panjang cukup' : ($sock ? 'terlalu pendek' : 'kosong'), 'Isi acak ≥ 32 karakter: php -r "echo bin2hex(random_bytes(32));"');
        $internal = (string) config('services.xendit.internal_secret');
        $add($g, 'INTERNAL_WEBHOOK_SECRET kuat', strlen($internal) >= 24 ? 'ok' : $bad(), strlen($internal) >= 24 ? 'panjang cukup' : ($internal ? 'lemah / pendek' : 'kosong'), 'Ganti dengan nilai acak panjang di Notifora dan router VexaHost.');

        // --- Infrastruktur
        $g = 'Infrastruktur';
        $db = config('database.default');
        $add($g, 'Database production', in_array($db, ['mysql', 'mariadb', 'pgsql'], true) ? 'ok' : $bad(), $db, 'Gunakan MySQL (DB_CONNECTION=mysql); SQLite hanya untuk development.');
        $q = config('queue.default');
        $add($g, 'Queue driver', $q === 'sync' ? $bad() : 'ok', $q, 'Gunakan redis (atau database) agar worker memproses job di luar request.');
        $c = config('cache.default');
        $add($g, 'Cache store', in_array($c, ['array', 'file'], true) ? 'warn' : 'ok', $c, 'Gunakan redis agar cache, lock, dan rate limit dibagi antar proses.');
        $add($g, 'Config di-cache', app()->configurationIsCached() ? 'ok' : ($prod ? 'warn' : 'ok'), app()->configurationIsCached() ? 'config:cache aktif' : 'belum', 'Saat deploy: php artisan optimize.');
        $add($g, 'Storage dapat ditulis', is_writable(storage_path()) && is_writable(base_path('bootstrap/cache')) ? 'ok' : 'fail', 'storage & bootstrap/cache', 'chown/chmod agar user PHP dapat menulis.');
        $add($g, 'Storage link (logo/favicon)', is_link(public_path('storage')) || is_dir(public_path('storage')) ? 'ok' : 'warn', is_dir(public_path('storage')) ? 'ada' : 'belum', 'php artisan storage:link (diperlukan agar logo hasil unggahan tampil).');
        $missing = array_values(array_filter(array_unique(array_merge(['pdo', 'mbstring', 'openssl', 'curl', 'json', 'ctype', 'fileinfo', 'tokenizer', 'xml'], in_array($db, ['mysql', 'mariadb'], true) ? ['pdo_mysql'] : [])), fn ($e) => ! extension_loaded($e)));
        $add($g, 'Ekstensi PHP', $missing ? 'fail' : 'ok', $missing ? 'kurang: '.implode(', ', $missing) : 'PHP '.PHP_VERSION.' lengkap', 'Pasang ekstensi PHP yang kurang.');

        // --- Proses latar belakang
        $g = 'Proses latar belakang';
        $mon = app(WorkerMonitor::class);
        $sup = $mon->supervisor();
        $queues = $mon->queues();
        $alive = collect($queues)->where('alive', true)->count();
        if ($q === 'sync') {
            $add($g, 'Worker antrean', $bad(), 'driver sync — tidak ada worker', 'Set QUEUE_CONNECTION=redis lalu jalankan php artisan notifora:supervise (lihat deploy/).');
        } else {
            $add($g, 'Worker antrean', $alive === count($queues) ? 'ok' : ($alive ? 'warn' : 'fail'), "$alive/".count($queues).' antrean punya worker aktif', 'Jalankan supervisor: php artisan notifora:supervise (atau Supervisor/systemd di deploy/).');
        }
        $add($g, 'Supervisor bawaan', $sup['alive'] ? 'ok' : 'warn', $sup['alive'] ? 'aktif (pid '.($sup['data']['pid'] ?? '?').')' : 'tidak terdeteksi (boleh jika memakai Supervisor/systemd)', 'php artisan notifora:supervise');
        $add($g, 'Scheduler', $mon->schedulerAlive() ? 'ok' : $bad(), $mon->schedulerBeat() ? 'denyut '.now()->subSeconds(time() - $mon->schedulerBeat())->diffForHumans() : 'belum pernah berdenyut', 'Jalankan notifora:supervise, atau cron * * * * * php artisan schedule:run.');
        $lastBackup = Cache::get('backup:last_ok');
        $add($g, 'Backup database', $lastBackup && (time() - $lastBackup) < 36 * 3600 ? 'ok' : $bad(), $lastBackup ? 'terakhir '.now()->subSeconds(time() - $lastBackup)->diffForHumans() : (Cache::get('backup:last_error') ?: 'belum pernah'), 'Admin → Backup → Cadangkan sekarang; pastikan mysqldump terpasang dan salin backup ke penyimpanan luar server.');
        $failed = $mon->failedCount();
        $add($g, 'Job gagal', $failed === 0 ? 'ok' : ($failed < 25 ? 'warn' : 'fail'), "$failed job", 'Admin → Job gagal.');

        // --- Integrasi
        $g = 'Integrasi';
        $mailer = config('mail.default');
        $credMailer = $mailer === 'failover' ? (config('mail.mailers.stalwart.username') ? 'stalwart' : 'smtp') : $mailer;
        $creds = ! empty(config("mail.mailers.$credMailer.username")) && ! empty(config("mail.mailers.$credMailer.password"));
        $realMail = ! in_array($mailer, ['log', 'array'], true);
        $add($g, 'SMTP email', $realMail && $creds ? 'ok' : $bad(), $realMail ? ($creds ? "$mailer · kredensial terisi" : "$mailer · kredensial kosong") : "mailer '$mailer' (tidak mengirim)", 'Isi di Admin → Email → SMTP atau .env, lalu kirim email uji.');
        try {
            $sent = EmailLog::where('status', 'sent')->where('created_at', '>=', now()->subDays(7))->count();
            $add($g, 'Email terkirim 7 hari', $sent > 0 ? 'ok' : 'warn', "$sent email", 'Kirim email uji; pastikan domain pengirim terverifikasi dan SPF/DKIM/DMARC terpasang.');
        } catch (Throwable) {
        }
        $xk = (string) config('services.xendit.secret_key');
        $add($g, 'Xendit secret key', $xk ? ($prod && str_starts_with($xk, 'xnd_development') ? 'warn' : 'ok') : $bad(), $xk ? (str_starts_with($xk, 'xnd_production') ? 'kunci LIVE' : 'kunci test') : 'kosong', 'Isi XENDIT_SECRET_KEY.');
        $add($g, 'Token callback Xendit', config('services.xendit.webhook_token') || $internal ? 'ok' : $bad(), 'router VexaHost → prefix '.config('notifora.billing.external_prefix'), 'Isi XENDIT_WEBHOOK_TOKEN / INTERNAL_WEBHOOK_SECRET.');
        $add($g, 'Email dukungan', Brand::supportEmail() ? 'ok' : 'warn', Brand::supportEmail(), 'Atur di Admin → Branding.');

        return $r;
    }

    /** @return array{ok:int, warn:int, fail:int, total:int, score:int} */
    public function summarize(array $groups): array
    {
        $n = ['ok' => 0, 'warn' => 0, 'fail' => 0];
        foreach ($groups as $rows) {
            foreach ($rows as $row) {
                $n[$row['status']]++;
            }
        }
        $total = array_sum($n);

        return $n + ['total' => $total, 'score' => $total ? (int) round((($n['ok'] + $n['warn'] * 0.5) / $total) * 100) : 0];
    }
}
