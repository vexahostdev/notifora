<?php

use App\Listeners\RecordWorkerHeartbeat;
use App\Models\EmailLog;
use App\Models\PlatformAuditLog;
use App\Models\User;
use App\Services\Brand;
use App\Services\DatabaseBackup;
use App\Services\MailSettings;
use App\Services\PlatformSettings;
use App\Services\ProductionAudit;
use App\Services\WorkerMonitor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->withoutVite();
    Mail::fake();
    Cache::flush();
    config(['admin.require_2fa' => false]);
    $this->admin = User::factory()->create(['name' => 'Root']);
    $this->admin->forceFill(['is_platform_admin' => true])->save();
    $this->actingAs($this->admin);
});

function operator(string $role): User
{
    $u = User::factory()->create();
    $u->forceFill(['is_platform_admin' => true, 'admin_role' => $role])->save();

    return $u;
}

// --- Peran operator ---------------------------------------------------------------

test('operator tanpa peran dianggap super dan bisa membuka semua bagian sistem', function () {
    foreach (['admin.system.workers', 'admin.system.readiness', 'admin.system.backups', 'admin.system.logs', 'admin.system.branding', 'admin.emails.smtp'] as $r) {
        $this->get(route($r))->assertOk();
    }
});

test('peran membatasi bagian panel', function () {
    $this->actingAs(operator('finance'));
    $this->get(route('admin.payments.index'))->assertOk();
    $this->get(route('admin.plans.index'))->assertOk();
    $this->get(route('admin.dashboard'))->assertOk();
    $this->get(route('admin.system.workers'))->assertForbidden();
    $this->get(route('admin.emails.smtp'))->assertForbidden();
    $this->get(route('admin.users.index'))->assertForbidden();

    $this->actingAs(operator('support'));
    $this->get(route('admin.users.index'))->assertOk();
    $this->get(route('admin.emails.logs'))->assertOk();
    $this->get(route('admin.emails.smtp'))->assertForbidden();   // kredensial SMTP hanya ops/super
    $this->get(route('admin.payments.index'))->assertForbidden();

    $this->actingAs(operator('ops'));
    $this->get(route('admin.system.workers'))->assertOk();
    $this->get(route('admin.ops.failed'))->assertOk();
    $this->get(route('admin.tenants.index'))->assertForbidden();
});

test('menu sidebar hanya menampilkan bagian yang diizinkan', function () {
    $this->actingAs(operator('finance'));
    $html = $this->get(route('admin.dashboard'))->getContent();
    expect($html)->toContain('Pembayaran')->not->toContain('Workers &amp; scheduler')->not->toContain('Backup database');
});

test('hanya super operator yang boleh mengubah peran dan minimal satu super tersisa', function () {
    $target = operator('support');
    $this->put(route('admin.users.role', $target), ['role' => 'finance'])->assertRedirect();
    expect($target->fresh()->admin_role)->toBe('finance');

    // super terakhir tidak boleh diturunkan
    $this->put(route('admin.users.role', $this->admin), ['role' => 'ops'])->assertSessionHas('error');
    expect($this->admin->fresh()->adminRole())->toBe('super');

    $this->actingAs(operator('ops'));
    $this->put(route('admin.users.role', $target), ['role' => 'super'])->assertForbidden();
    expect(PlatformAuditLog::where('action', 'admin.role_set')->count())->toBe(1);
});

// --- Supervisor, worker, watchdog ---------------------------------------------------

test('notifora:supervise menolak driver sync tanpa --force dan mencetak rencana dengan dry-run', function () {
    $this->artisan('notifora:supervise')->assertFailed();
    $this->artisan('notifora:supervise', ['--dry-run' => true, '--force' => true])
        ->expectsOutputToContain('worker:default#1')
        ->expectsOutputToContain('worker:channels#3')
        ->assertSuccessful();
});

test('monitor melaporkan antrean berisi tanpa worker, lalu sehat setelah ada denyut', function () {
    config(['queue.default' => 'database']);
    DB::table('jobs')->insert(['queue' => 'notifora_default', 'payload' => '{}', 'attempts' => 0, 'available_at' => now()->subMinutes(20)->timestamp, 'created_at' => now()->subMinutes(20)->timestamp]);
    $m = app(WorkerMonitor::class);

    $p = implode(' ', $m->problems());
    expect($p)->toContain('notifora_default')->toContain('Scheduler')->and($m->oldestWaitingSeconds())->toBeGreaterThan(900);

    foreach (config('notifora.queues') as $name) {
        Cache::put("worker:beat:$name", time(), 60);
    }
    Cache::put('scheduler:heartbeat', time(), 60);
    Cache::put('backup:last_ok', time(), 60);
    // job tertua masih menunggu lama, sisanya sehat
    expect(implode(' ', $m->problems()))->toContain('menunggu')->not->toContain('tidak ada worker aktif');
});

test('worker mengirim denyut saat memproses job', function () {
    $job = new class
    {
        public function getQueue()
        {
            return 'notifora_high';
        }
    };
    (new RecordWorkerHeartbeat)->handle((object) ['job' => $job]);
    expect(Cache::get('worker:beat:notifora_high'))->not->toBeNull();
});

test('watchdog mengirim email peringatan ke operator dengan dedupe', function () {
    config(['queue.default' => 'database']);
    DB::table('jobs')->insert(['queue' => 'notifora_high', 'payload' => '{}', 'attempts' => 0, 'available_at' => time(), 'created_at' => time()]);

    $this->artisan('notifora:watchdog')->assertSuccessful();
    expect(EmailLog::where('kind', 'ops-alert')->count())->toBe(1);

    $this->artisan('notifora:watchdog')->assertSuccessful();   // masalah sama dalam 30 menit: tidak dikirim lagi
    expect(EmailLog::where('kind', 'ops-alert')->count())->toBe(1);
});

test('halaman workers menampilkan status dan tindakan restart tercatat', function () {
    config(['queue.default' => 'database']);
    $this->get(route('admin.system.workers'))->assertOk()->assertSee('Antrean')->assertSee('Supervisor');
    $this->post(route('admin.system.workers.restart'))->assertRedirect();
    expect(PlatformAuditLog::where('action', 'system.workers_restarted')->count())->toBe(1);
    $this->post(route('admin.system.workers.schedule'))->assertRedirect();
    $this->post(route('admin.system.tool', 'view-clear'))->assertRedirect();
    $this->post(route('admin.system.tool', 'rm-rf'))->assertNotFound();
});

// --- Kesiapan production ----------------------------------------------------------

test('audit kesiapan tidak membocorkan rahasia dan menandai konfigurasi lemah', function () {
    config(['app.debug' => true, 'services.xendit.internal_secret' => 'qk7wv3', 'services.xendit.secret_key' => 'xnd_production_SUPERSECRET123']);
    $res = $this->get(route('admin.system.readiness'))->assertOk();
    $res->assertDontSee('SUPERSECRET123')->assertDontSee('qk7wv3')->assertSee('APP_DEBUG');

    $groups = app(ProductionAudit::class)->run();
    $sum = app(ProductionAudit::class)->summarize($groups);
    expect($sum['total'])->toBeGreaterThan(15)->and($sum['score'])->toBeBetween(0, 100);
});

// --- Backup ----------------------------------------------------------------------

test('backup sqlite berkas dibuat, dapat diunduh, dihapus, dan nama berkas divalidasi', function () {
    $file = tempnam(sys_get_temp_dir(), 'ntf').'.sqlite';
    file_put_contents($file, str_repeat('x', 4096));
    config(['notifora.backup.path' => sys_get_temp_dir().'/ntf-bk-'.uniqid()]);
    $orig = config('database.default');
    config(['database.connections.bk' => ['driver' => 'sqlite', 'database' => $file], 'database.default' => 'bk']);

    $svc = app(DatabaseBackup::class);
    $name = $svc->run();
    config(['database.default' => $orig]);   // kembalikan sebelum request HTTP (agar tidak membaca berkas palsu)
    expect($name)->toEndWith('.sqlite.gz')->and($svc->path($name))->not->toBeNull()
        ->and($svc->path('../.env'))->toBeNull()
        ->and($svc->path('notifora-../../x.sql.gz'))->toBeNull();
    expect(Cache::get('backup:last_ok'))->not->toBeNull();

    $this->get(route('admin.system.backups'))->assertOk()->assertSee($name);
    $this->get(route('admin.system.backups.download', $name))->assertOk();
    $this->get(route('admin.system.backups.download', 'notifora-nope.sql.gz'))->assertNotFound();
    $this->delete(route('admin.system.backups.destroy', $name))->assertRedirect();
    expect($svc->path($name))->toBeNull();
    @unlink($file);
});

test('backup memory database gagal dengan pesan jelas, bukan error 500', function () {
    $this->post(route('admin.system.backups.run'))->assertSessionHas('error');
    expect(Cache::get('backup:last_error'))->not->toBeNull();
});

// --- Log aplikasi ----------------------------------------------------------------

test('penampil log memfilter level dan pencarian', function () {
    $dir = storage_path('logs');
    @mkdir($dir, 0777, true);
    $path = $dir.'/laravel.log';
    $backup = is_file($path) ? file_get_contents($path) : null;
    file_put_contents($path, "[2026-10-29 10:00:00] testing.ERROR: Gagal kirim pesan zzz-unik\n#0 stack line\n[2026-10-29 10:01:00] testing.INFO: Info biasa\n");

    $this->get(route('admin.system.logs'))->assertOk()->assertSee('zzz-unik')->assertSee('Info biasa');
    $this->get(route('admin.system.logs', ['level' => 'error']))->assertSee('zzz-unik')->assertDontSee('Info biasa');
    $this->get(route('admin.system.logs', ['q' => 'biasa']))->assertSee('Info biasa')->assertDontSee('zzz-unik');
    $this->get(route('admin.system.logs.download'))->assertOk();

    $backup === null ? @unlink($path) : file_put_contents($path, $backup);
});

// --- Branding ---------------------------------------------------------------------

test('branding mengubah nama, warna aksen, footer, dan logo di seluruh situs', function () {
    Storage::fake('public');
    $this->put(route('admin.system.branding.update'), [
        'brand_name' => 'AcmeNotify', 'brand_tagline' => 'Kirim cepat', 'brand_accent' => '#12aa55',
        'brand_footer' => '© {year} Acme', 'support_email' => 'bantuan@acme.test',
        'brand_logo' => UploadedFile::fake()->image('logo.png', 64, 64),
    ])->assertSessionHasNoErrors();

    expect(Brand::name())->toBe('AcmeNotify')
        ->and(Brand::accent())->toBe('#12aa55')
        ->and(Brand::footerRendered())->toBe('© '.date('Y').' Acme')
        ->and(Brand::supportEmail())->toBe('bantuan@acme.test')
        ->and(Brand::logoUrl())->toContain('/storage/branding/');
    Storage::disk('public')->assertExists(PlatformSettings::get('brand_logo_path'));

    $this->get(route('landing'))->assertSee('ACMENOTIFY')->assertSee('Kirim cepat')->assertSee('bantuan@acme.test')->assertSee('--color-cobalt:#12aa55', false);
    $this->get(route('admin.dashboard'))->assertSee('AcmeNotify');

    // hapus logo kustom
    $old = PlatformSettings::get('brand_logo_path');
    $this->put(route('admin.system.branding.update'), ['brand_name' => 'AcmeNotify', 'brand_accent' => '#12aa55', 'remove_logo' => '1'])->assertSessionHasNoErrors();
    Storage::disk('public')->assertMissing($old);
    expect(Brand::logoUrl())->toContain('logo-sm.png');
});

test('branding menolak warna tidak valid, svg, dan email salah', function () {
    $this->put(route('admin.system.branding.update'), [
        'brand_name' => 'X', 'brand_accent' => 'merah', 'support_email' => 'bukan-email',
        'brand_logo' => UploadedFile::fake()->create('logo.svg', 4, 'image/svg+xml'),
    ])->assertSessionHasErrors(['brand_accent', 'support_email', 'brand_logo']);
});

// --- SMTP -----------------------------------------------------------------------

test('smtp disimpan terenkripsi, diterapkan sebagai override, dan dapat diuji tanpa mengirim', function () {
    $this->put(route('admin.emails.smtp-update'), [
        'host' => 'smtp.contoh.test', 'port' => 2525, 'username' => 'user-1', 'password' => 'RAHASIA-123',
        'encryption' => 'smtp', 'from_address' => 'noreply@contoh.test', 'from_name' => 'Contoh', 'override' => '1',
    ])->assertSessionHasNoErrors();

    $stored = PlatformSettings::get('mail_password_enc');
    expect($stored)->not->toContain('RAHASIA-123')->and(MailSettings::password())->toBe('RAHASIA-123');
    expect(config('mail.default'))->toBe('smtp')
        ->and(config('mail.mailers.smtp.host'))->toBe('smtp.contoh.test')
        ->and(config('mail.mailers.smtp.password'))->toBe('RAHASIA-123')
        ->and(config('mail.from.address'))->toBe('noreply@contoh.test');

    // password tidak pernah dirender kembali ke form
    $this->get(route('admin.emails.smtp'))->assertOk()->assertDontSee('RAHASIA-123')->assertDontSee($stored);

    // password kosong = tetap
    $this->put(route('admin.emails.smtp-update'), ['host' => 'smtp.contoh.test', 'port' => 2525, 'encryption' => 'smtp', 'from_address' => 'noreply@contoh.test', 'override' => '1']);
    expect(MailSettings::password())->toBe('RAHASIA-123');

    // uji koneksi ke port tertutup: gagal dengan pesan, tidak melempar
    config(['mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1]);
    $this->post(route('admin.emails.smtp-probe'))->assertSessionHas('error');

    $log = PlatformAuditLog::where('action', 'email.smtp_saved')->latest('created_at')->first();
    expect(json_encode($log->meta))->not->toContain('RAHASIA');
});

test('smtp menolak host tidak valid', function () {
    $this->put(route('admin.emails.smtp-update'), ['host' => 'a b;rm', 'port' => 99999, 'encryption' => 'tls', 'from_address' => 'x'])
        ->assertSessionHasErrors(['host', 'port', 'encryption', 'from_address']);
});

test('operator platform tanpa workspace tetap bisa membuka Console sebagai pengguna biasa', function () {
    expect($this->admin->memberships()->count())->toBe(0);

    $this->get(route('console.dashboard'))->assertOk();

    expect($this->admin->memberships()->count())->toBe(1);
    $this->get(route('admin.dashboard'))->assertOk()->assertSee('Console (tampilan pengguna)');
    $this->get(route('console.dashboard'))->assertOk()->assertSee('Admin panel');
});
