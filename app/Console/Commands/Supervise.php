<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;

/**
 * Supervisor proses bawaan: menjalankan worker tiap antrean + scheduler dalam satu perintah,
 * me-restart yang mati (dengan backoff), dan menulis status ke cache untuk Admin → Workers.
 *
 * Cocok sebagai satu-satunya proses di container/systemd/Supervisor:
 *   php artisan notifora:supervise
 * "queue:restart" dari panel admin membuat worker keluar dengan rapi lalu dihidupkan lagi di sini.
 */
class Supervise extends Command
{
    protected $signature = 'notifora:supervise {--dry-run : Tampilkan rencana proses tanpa menjalankan} {--max-seconds=0 : Berhenti sendiri setelah N detik (0 = selamanya)} {--force : Jalankan walau QUEUE_CONNECTION=sync}';

    protected $description = 'Jalankan & awasi worker antrean + scheduler (pengganti Supervisor/systemd sederhana)';

    private bool $stopping = false;

    /** @var array<string, array{cmd:array, proc:?Process, restarts:int, started:?int, last_exit:?int, next_start:int, queue:?string}> */
    private array $children = [];

    public function handle(): int
    {
        $conn = config('queue.default');
        if ($conn === 'sync' && ! $this->option('force')) {
            $this->error('QUEUE_CONNECTION=sync: job berjalan inline, tidak ada worker yang perlu diawasi. Gunakan redis/database (atau --force).');

            return self::FAILURE;
        }

        $this->plan($conn);

        if ($this->option('dry-run')) {
            foreach ($this->children as $name => $c) {
                $this->line(sprintf('%-22s %s', $name, implode(' ', array_map(fn ($a) => str_contains($a, ' ') ? '"'.$a.'"' : $a, $c['cmd']))));
            }
            $this->line(sprintf('%-22s %s', 'scheduler', 'php artisan schedule:run  (tiap menit)'));

            return self::SUCCESS;
        }

        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            foreach ([SIGINT, SIGTERM] as $sig) {
                pcntl_signal($sig, fn () => $this->stopping = true);
            }
        }

        $started = time();
        $max = (int) $this->option('max-seconds');
        $lastPublish = 0;
        $lastMinute = null;
        $tickProcs = [];
        $this->info('Supervisor aktif (pid '.getmypid().', '.count($this->children).' worker, koneksi '.$conn.').');

        while (! $this->stopping && ($max === 0 || time() - $started < $max)) {
            $now = time();

            foreach ($this->children as $name => &$c) {
                if ($c['proc'] && ! $c['proc']->isRunning()) {
                    $c['last_exit'] = $c['proc']->getExitCode();
                    $ranFor = $now - (int) $c['started'];
                    $c['restarts'] = $ranFor > 60 ? 1 : $c['restarts'] + 1;   // proses yang sehat mereset backoff
                    $c['next_start'] = $now + ($ranFor > 60 ? 0 : min(30, 2 ** min($c['restarts'], 5)));
                    $c['proc'] = null;
                }
                if (! $c['proc'] && $now >= $c['next_start']) {
                    $c['proc'] = $this->spawn($c['cmd']);
                    $c['started'] = $now;
                }
            }
            unset($c);

            // Scheduler: sekali tiap menit
            $minute = intdiv($now, 60);
            if ($minute !== $lastMinute) {
                $lastMinute = $minute;
                $tickProcs[] = $this->spawn([PHP_BINARY, base_path('artisan'), 'schedule:run', '--no-interaction']);
            }
            $tickProcs = array_values(array_filter($tickProcs, fn (Process $p) => $p->isRunning()));

            if ($now - $lastPublish >= 5) {
                $lastPublish = $now;
                $this->publish($started);
            }

            usleep(500_000);
        }

        $this->info('Menghentikan worker...');
        foreach ($this->children as $c) {
            $c['proc']?->stop(20);
        }
        Cache::forget('supervisor:status');

        return self::SUCCESS;
    }

    private function plan(string $conn): void
    {
        $w = config('notifora.workers');
        foreach (config('notifora.queues') as $key => $queue) {
            $n = max(0, (int) ($w['procs'][$key] ?? 1));
            for ($i = 1; $i <= $n; $i++) {
                $this->children["worker:{$key}#{$i}"] = [
                    'cmd' => [PHP_BINARY, base_path('artisan'), 'queue:work', $conn, "--queue={$queue}", "--sleep={$w['sleep']}", "--tries={$w['tries']}", "--max-time={$w['max_time']}", "--memory={$w['memory']}", '--no-interaction'],
                    'proc' => null, 'restarts' => 0, 'started' => null, 'last_exit' => null, 'next_start' => 0, 'queue' => $queue,
                ];
            }
        }
    }

    private function spawn(array $cmd): Process
    {
        $p = new Process($cmd, base_path(), null, null, null);
        $p->start();

        return $p;
    }

    private function publish(int $startedAt): void
    {
        $rows = [];
        foreach ($this->children as $name => $c) {
            $rows[] = [
                'name' => $name,
                'queue' => $c['queue'],
                'pid' => $c['proc']?->getPid(),
                'running' => (bool) $c['proc']?->isRunning(),
                'restarts' => $c['restarts'],
                'started' => $c['started'],
                'last_exit' => $c['last_exit'],
            ];
        }
        Cache::put('supervisor:status', ['pid' => getmypid(), 'host' => gethostname(), 'started_at' => $startedAt, 'updated_at' => time(), 'children' => $rows], 120);
    }
}
