<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AdminAudit;
use App\Services\PlatformSettings;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        return view('admin.settings', [
            'flags' => config('admin.flags'),
            'values' => PlatformSettings::all(),
            'info' => [
                'Aplikasi' => config('app.name').' · '.app()->environment().' · Laravel '.app()->version().' · PHP '.PHP_VERSION,
                'URL' => config('app.url').' · API '.config('notifora.api_url'),
                'Database' => config('database.default'),
                'Queue / Cache / Session' => config('queue.default').' / '.config('cache.default').' / '.config('session.driver'),
                'Email' => config('mail.default').' · dari '.config('mail.from.address').' · support '.config('notifora.support_email'),
                'Pembayaran' => config('services.xendit.secret_key') ? 'Xendit terkonfigurasi' : 'Xendit BELUM dikonfigurasi',
                'Operator wajib 2FA' => config('admin.require_2fa') ? 'ya' : 'TIDAK (hanya untuk development)',
                'Jumlah operator' => (string) User::where('is_platform_admin', true)->count(),
            ],
        ]);
    }

    public function update(Request $request)
    {
        $before = PlatformSettings::all();
        foreach (array_keys(config('admin.flags')) as $flag) {
            PlatformSettings::setBool($flag, $request->boolean($flag));
        }
        PlatformSettings::setBool('maintenance', $request->boolean('maintenance'));
        $data = $request->validate([
            'maintenance_message' => ['nullable', 'string', 'max:500'],
            'signup_lock_message' => ['nullable', 'string', 'max:500'],
        ]);
        PlatformSettings::set('maintenance_message', trim((string) ($data['maintenance_message'] ?? '')));
        PlatformSettings::set('signup_lock_message', trim((string) ($data['signup_lock_message'] ?? '')));

        $changed = collect(PlatformSettings::all())->filter(fn ($v, $k) => ($before[$k] ?? null) !== $v)->keys()->all();
        AdminAudit::record('settings.updated', 'settings', null, null, ['changed' => $changed, 'maintenance' => $request->boolean('maintenance')]);

        return back()->with('success', 'Pengaturan platform disimpan.');
    }

    public function toggleSignup(Request $request)
    {
        $new = ! PlatformSettings::bool('signup_enabled');
        PlatformSettings::setBool('signup_enabled', $new);

        AdminAudit::record('settings.signup_toggled', 'settings', null, null, [
            'signup_enabled' => $new,
        ]);

        $msg = $new
            ? 'Pendaftaran akun baru berhasil DIBUKA.'
            : 'Pendaftaran akun baru berhasil DIKUNCI. User umum tidak dapat mendaftar.';

        return back()->with('success', $msg);
    }
}
