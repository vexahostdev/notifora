<?php

namespace App\Console\Commands;

use App\Mail\Mails;
use App\Models\User;
use App\Services\HealthCheck;
use App\Services\SystemNotifier;
use App\Services\WorkerMonitor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/** Memeriksa worker/scheduler/backup/health dan mengirim email ke operator bila ada masalah (dedupe 30 menit). */
class Watchdog extends Command
{
    protected $signature = 'notifora:watchdog {--quiet-ok : Tidak mencetak apa pun saat sehat}';

    protected $description = 'Pantau kesehatan worker, scheduler, backup & komponen; peringatkan operator';

    public function handle(WorkerMonitor $monitor, HealthCheck $health, SystemNotifier $notifier): int
    {
        $problems = $monitor->problems();
        foreach ($health->run() as $c) {
            if ($c['status'] === 'fail') {
                $problems[] = "{$c['name']}: {$c['detail']}";
            }
        }

        if (! $problems) {
            Cache::forget('watchdog:last_hash');
            $this->option('quiet-ok') || $this->info('Semua sehat.');

            return self::SUCCESS;
        }

        foreach ($problems as $p) {
            $this->warn($p);
        }

        // Dedupe: kirim ulang hanya bila isi masalah berubah atau sudah lewat 30 menit
        $hash = md5(preg_replace('/\d+(\.\d+)?/', '#', implode('|', $problems))); // angka (hitungan, ms) tidak dianggap masalah baru
        if (Cache::get('watchdog:last_hash') === $hash) {
            return self::SUCCESS;
        }
        Cache::put('watchdog:last_hash', $hash, 1800);

        $to = User::where('is_platform_admin', true)->whereNull('suspended_at')->pluck('email')->all();
        $notifier->deliver($to, Mails::opsAlert($problems));
        $this->line('Peringatan dikirim ke '.count($to).' operator.');

        return self::SUCCESS;
    }
}
