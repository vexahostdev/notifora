<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;

/** Endpoint internal (socket-engine -> Laravel). */
class VerifyInternalSecret
{
    public function handle(Request $request, Closure $next)
    {
        $secret = (string) config('notifora.internal_secret');
        $given = (string) $request->header('X-Internal-Secret');

        if ($secret === '' || ! hash_equals($secret, $given)) {
            return ApiResponse::error('forbidden', 'Forbidden.', 403);
        }

        return $next($request);
    }
}
