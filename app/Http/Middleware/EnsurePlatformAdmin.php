<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Gerbang panel operator /admin.
 *  - Bukan platform admin -> 404 (keberadaan panel disembunyikan).
 *  - Akun ditangguhkan -> logout.
 *  - 2FA wajib (config admin.require_2fa) -> arahkan ke pengaturan 2FA sampai aktif.
 */
class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user || ! $user->isPlatformAdmin()) {
            abort(404);
        }
        if ($user->isSuspended()) {
            auth()->logout();

            return redirect()->route('login')->withErrors(['email' => 'Akun ditangguhkan.']);
        }
        if (config('admin.require_2fa') && ! $user->hasTwoFactor()) {
            return redirect()->route('security.2fa')->with('error', 'Operator platform wajib mengaktifkan 2FA sebelum membuka panel admin.');
        }

        $section = self::section((string) $request->route()?->getName());
        abort_unless($user->canAdmin($section), 403, 'Peran operator Anda tidak memiliki akses ke bagian ini.');

        $request->attributes->set('admin', $user);
        view()->share(['admin' => $user]);

        return $next($request);
    }

    /** Petakan nama rute admin.* ke bagian panel untuk pengecekan peran. */
    public static function section(string $routeName): string
    {
        $seg = explode('.', $routeName)[1] ?? 'dashboard';

        return match (true) {
            in_array($seg, ['coupons'], true) => 'plans',
            in_array($seg, ['settings', 'system'], true) => 'system',
            $seg === 'emails' && str_starts_with($routeName, 'admin.emails.smtp') => 'system',
            default => $seg,
        };
    }
}
