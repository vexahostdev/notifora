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
        PlatformSettings::set('maintenance_message', trim((string) $request->validate(['maintenance_message' => ['nullable', 'string', 'max:500']])['maintenance_message']));

        $changed = collect(PlatformSettings::all())->filter(fn ($v, $k) => ($before[$k] ?? null) !== $v)->keys()->all();
        AdminAudit::record('settings.updated', 'settings', null, null, ['changed' => $changed, 'maintenance' => $request->boolean('maintenance')]);

        return back()->with('success', 'Pengaturan platform disimpan.');
    }
}
