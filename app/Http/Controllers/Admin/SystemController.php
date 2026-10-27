<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminAudit;
use App\Services\Brand;
use App\Services\DatabaseBackup;
use App\Services\PlatformSettings;
use App\Services\ProductionAudit;
use App\Services\WorkerMonitor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/** Admin → Sistem: workers, kesiapan production, backup, log aplikasi, perkakas, branding. */
class SystemController extends Controller
{
    private const TOOLS = [
        'cache-clear' => ['cache:clear', 'Cache aplikasi dibersihkan.'],
        'view-clear' => ['view:clear', 'Cache view dibersihkan.'],
        'optimize' => ['optimize', 'Config, route, dan view di-cache ulang.'],
        'optimize-clear' => ['optimize:clear', 'Semua cache bootstrap dibersihkan.'],
    ];

    // --- Workers & scheduler -------------------------------------------------------

    public function workers(WorkerMonitor $monitor)
    {
        return view('admin.system.workers', [
            'driver' => config('queue.default'),
            'queues' => $monitor->queues(),
            'supervisor' => $monitor->supervisor(),
            'schedulerBeat' => $monitor->schedulerBeat(),
            'schedulerAlive' => $monitor->schedulerAlive(),
            'failed' => $monitor->failedCount(),
            'oldest' => $monitor->oldestWaitingSeconds(),
            'problems' => $monitor->problems(),
            'procs' => config('notifora.workers.procs'),
            'cronFallback' => config('notifora.workers.cron_fallback'),
            'tools' => array_keys(self::TOOLS),
        ]);
    }

    /** Worker keluar dengan rapi setelah job berjalan selesai; supervisor/Supervisor/systemd menghidupkan kembali dengan kode terbaru. */
    public function restartWorkers()
    {
        Artisan::call('queue:restart');
        AdminAudit::record('system.workers_restarted');

        return back()->with('success', 'Sinyal restart dikirim. Worker akan keluar setelah job berjalan selesai dan dihidupkan kembali oleh supervisor.');
    }

    /** Proses antrean saat ini juga (maks. 20 detik) — berguna saat worker mati atau untuk pengujian. */
    public function drain()
    {
        if (config('queue.default') === 'sync') {
            return back()->with('error', 'Driver antrean sync: tidak ada antrean untuk diproses.');
        }
        $t = microtime(true);
        Artisan::call('queue:work', ['--stop-when-empty' => true, '--max-time' => 20, '--queue' => implode(',', config('notifora.queues'))]);
        AdminAudit::record('system.queue_drained');

        return back()->with('success', 'Antrean diproses selama '.round(microtime(true) - $t, 1).' detik.');
    }

    public function runSchedule()
    {
        Artisan::call('schedule:run');
        AdminAudit::record('system.schedule_run');

        return back()->with('success', 'Scheduler dijalankan sekali: '.(Str::of(Artisan::output())->squish()->limit(160) ?: 'tidak ada tugas yang jatuh tempo.'));
    }

    public function tool(string $tool)
    {
        abort_unless(isset(self::TOOLS[$tool]), 404);
        [$cmd, $msg] = self::TOOLS[$tool];
        try {
            Artisan::call($cmd);
        } catch (Throwable $e) {
            return back()->with('error', 'Gagal: '.Str::limit($e->getMessage(), 160));
        }
        AdminAudit::record('system.tool', null, $tool);

        return back()->with('success', $msg);
    }

    // --- Kesiapan production --------------------------------------------------------

    public function readiness(ProductionAudit $audit)
    {
        $groups = $audit->run();

        return view('admin.system.readiness', ['groups' => $groups, 'sum' => $audit->summarize($groups)]);
    }

    // --- Backup -----------------------------------------------------------------

    public function backups(DatabaseBackup $backup)
    {
        return view('admin.system.backups', [
            'files' => $backup->list(),
            'last' => Cache::get('backup:last_ok'),
            'error' => Cache::get('backup:last_error'),
            'driver' => config('database.default'),
            'keep' => config('notifora.backup.keep'),
            'enabled' => config('notifora.backup.enabled'),
        ]);
    }

    public function backupRun(DatabaseBackup $backup)
    {
        try {
            $name = $backup->run();
            $backup->prune((int) config('notifora.backup.keep'));
        } catch (Throwable $e) {
            return back()->with('error', 'Backup gagal: '.Str::limit($e->getMessage(), 240));
        }
        AdminAudit::record('system.backup_created', 'backup', $name);

        return back()->with('success', "Backup dibuat: $name");
    }

    public function backupDownload(DatabaseBackup $backup, string $name)
    {
        $path = $backup->path($name);
        abort_unless($path, 404);
        AdminAudit::record('system.backup_downloaded', 'backup', $name);

        return response()->download($path);
    }

    public function backupDestroy(DatabaseBackup $backup, string $name)
    {
        abort_unless($backup->delete($name), 404);
        AdminAudit::record('system.backup_deleted', 'backup', $name);

        return back()->with('success', 'Backup dihapus.');
    }

    // --- Log aplikasi -------------------------------------------------------------

    public function logs(Request $request)
    {
        $file = $this->latestLog();
        $level = strtolower((string) $request->query('level', ''));
        $term = trim((string) $request->query('q', ''));
        $entries = [];

        if ($file) {
            $fh = fopen($file, 'rb');
            $size = filesize($file);
            $read = min($size, 600 * 1024);          // 600 KB terakhir cukup untuk diagnosis
            fseek($fh, $size - $read);
            $text = $read ? fread($fh, $read) : '';
            fclose($fh);

            $parts = preg_split('/^(?=\[\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2})/m', $text, -1, PREG_SPLIT_NO_EMPTY);
            foreach (array_reverse($parts) as $block) {
                if (! preg_match('/^\[([^\]]+)\] (\w+)\.(\w+): (.*)/s', $block, $m)) {
                    continue;
                }
                if ($level && strtolower($m[3]) !== $level) {
                    continue;
                }
                if ($term && stripos($block, $term) === false) {
                    continue;
                }
                $lines = explode("\n", rtrim($m[4]));
                $entries[] = ['at' => $m[1], 'env' => $m[2], 'level' => strtolower($m[3]), 'message' => Str::limit($lines[0], 300), 'detail' => Str::limit(implode("\n", array_slice($lines, 1)), 3000)];
                if (count($entries) >= 120) {
                    break;
                }
            }
        }

        return view('admin.system.logs', [
            'file' => $file ? basename($file) : null,
            'size' => $file ? filesize($file) : 0,
            'entries' => $entries,
            'level' => $level,
            'q' => $term,
        ]);
    }

    public function logDownload()
    {
        $file = $this->latestLog();
        abort_unless($file, 404);
        AdminAudit::record('system.log_downloaded');

        return response()->download($file);
    }

    private function latestLog(): ?string
    {
        $files = collect(File::glob(storage_path('logs/*.log')))->sortByDesc(fn ($f) => filemtime($f));

        return $files->first();
    }

    // --- Branding -----------------------------------------------------------------

    public function branding()
    {
        return view('admin.system.branding', [
            'v' => PlatformSettings::all(),
            'logo' => Brand::logoUrl(),
            'favicon' => Brand::faviconUrl(),
            'accent' => Brand::accent(),
        ]);
    }

    public function brandingUpdate(Request $request)
    {
        $d = $request->validate([
            'brand_name' => ['required', 'string', 'max:40'],
            'brand_tagline' => ['nullable', 'string', 'max:140'],
            'brand_accent' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'brand_footer' => ['nullable', 'string', 'max:200'],
            'support_email' => ['nullable', 'email', 'max:120'],
            'support_whatsapp' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\s\-]*$/'],
            'mail_from_name' => ['nullable', 'string', 'max:60'],
            'social_instagram' => ['nullable', 'url', 'max:160'],
            'social_x' => ['nullable', 'url', 'max:160'],
            'social_linkedin' => ['nullable', 'url', 'max:160'],
            'social_github' => ['nullable', 'url', 'max:160'],
            'company_legal_name' => ['nullable', 'string', 'max:120'],
            'company_address' => ['nullable', 'string', 'max:300'],
            'brand_logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            'brand_favicon' => ['nullable', 'file', 'mimes:png,ico', 'max:256'],
        ], [
            'brand_accent.regex' => 'Warna aksen harus heksadesimal, contoh #0033ff.',
            'brand_logo.max' => 'Logo maksimal 1 MB.',
            'brand_favicon.max' => 'Favicon maksimal 256 KB.',
        ]);

        foreach (['brand_name', 'brand_tagline', 'brand_accent', 'brand_footer', 'support_email', 'support_whatsapp', 'mail_from_name', 'social_instagram', 'social_x', 'social_linkedin', 'social_github', 'company_legal_name', 'company_address'] as $k) {
            PlatformSettings::set($k, isset($d[$k]) ? trim($d[$k]) : null);
        }

        foreach (['brand_logo' => 'remove_logo', 'brand_favicon' => 'remove_favicon'] as $field => $removeFlag) {
            $key = $field.'_path';
            $old = PlatformSettings::get($key);
            if ($request->hasFile($field)) {
                $new = $request->file($field)->storePublicly('branding', 'public');
                $old && Storage::disk('public')->delete($old);
                PlatformSettings::set($key, $new);
                PlatformSettings::set($key.'_v', (string) time());
            } elseif ($request->boolean($removeFlag) && $old) {
                Storage::disk('public')->delete($old);
                PlatformSettings::set($key, null);
            }
        }

        AdminAudit::record('system.branding_updated', null, null, null, ['name' => $d['brand_name'], 'accent' => $d['brand_accent']]);

        return back()->with('success', 'Branding disimpan. Perubahan langsung berlaku di seluruh situs dan email.');
    }
}
