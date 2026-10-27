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

        $request->attributes->set('admin', $user);
        view()->share(['admin' => $user]);

        return $next($request);
    }
}
