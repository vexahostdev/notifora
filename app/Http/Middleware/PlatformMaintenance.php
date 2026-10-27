<?php

namespace App\Http\Middleware;

use App\Services\PlatformSettings;
use Closure;
use Illuminate\Http\Request;

/**
 * Mode maintenance platform (saklar di Admin → Settings). Operator platform tetap bisa masuk;
 * penerima webhook (pembayaran, status provider, internal) dan health check tetap hidup.
 */
class PlatformMaintenance
{
    private const ALWAYS_OPEN = ['up', 'api/payment/*', 'api/webhooks/*', 'v1/hooks/*', 'v1/internal/*', 'login', 'logout', 'two-factor-challenge', 'admin', 'admin/*', 'security/*', 'build/*'];

    public function handle(Request $request, Closure $next)
    {
        if (! PlatformSettings::bool('maintenance') || $request->is(self::ALWAYS_OPEN) || $request->user()?->isPlatformAdmin()) {
            return $next($request);
        }

        $message = PlatformSettings::get('maintenance_message') ?: 'Notifora sedang dalam pemeliharaan terjadwal. Silakan coba lagi sebentar lagi.';

        if ($request->is('v1/*') || $request->expectsJson()) {
            return response()->json(['error' => ['code' => 'maintenance', 'message' => $message]], 503)->header('Retry-After', '300');
        }

        return response()->view('maintenance', ['message' => $message], 503)->header('Retry-After', '300');
    }
}
