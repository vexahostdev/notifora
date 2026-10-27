<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ValidateApiKey
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        if (! $token || ! str_starts_with($token, 'ntf_')) {
            return ApiResponse::error('unauthenticated', 'Missing or invalid API key.', 401);
        }

        $hash = ApiKey::hash($token);
        $key = Cache::remember("apikey:$hash", 5, fn () => ApiKey::with('environment.workspace')->where('key_hash', $hash)->first());

        if (! $key || $key->isRevoked() || ! $key->environment) {
            return ApiResponse::error('unauthenticated', 'Invalid or revoked API key.', 401);
        }

        app()->instance('current.environment', $key->environment);
        $request->attributes->set('api_key', $key);

        if (Cache::add("apikey:touch:{$key->id}", 1, 60)) {
            ApiKey::whereKey($key->id)->update(['last_used_at' => now()]);
        }

        return $next($request);
    }
}
