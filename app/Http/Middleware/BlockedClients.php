<?php

namespace App\Http\Middleware;

use App\Models\BlockedEntity;
use Closure;
use Illuminate\Http\Request;

/** Menolak IP yang masuk blocklist (Admin → Abuse). Webhook penerima dikecualikan agar pembayaran tidak terputus. */
class BlockedClients
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->is('up', 'api/payment/*', 'api/webhooks/*') || ! BlockedEntity::ipBlocked($request->ip())) {
            return $next($request);
        }

        if ($request->is('v1/*') || $request->expectsJson()) {
            return response()->json(['error' => ['code' => 'forbidden', 'message' => 'Access blocked.']], 403);
        }

        abort(403, 'Akses dari alamat ini diblokir. Hubungi support bila ini keliru.');
    }
}
