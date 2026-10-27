<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Idempotency atomik: slot diklaim dengan Cache::add (SET NX), sehingga request
 * paralel dengan key yang sama tidak bisa lolos dua kali.
 */
class IdempotencyGuard
{
    private const TTL = 86400;

    public function handle(Request $request, Closure $next)
    {
        $key = $request->header('X-Idempotency-Key');
        if (! $key) {
            return $next($request);
        }
        if (strlen($key) > 191) {
            return ApiResponse::error('validation_failed', 'X-Idempotency-Key terlalu panjang (maks 191).', 422);
        }

        $envId = app('current.environment')->id;
        $slot = "idem:{$envId}:{$key}";
        $hash = hash('sha256', $request->method().'|'.$request->path().'|'.$request->getContent());

        if (! Cache::add($slot, ['state' => 'processing', 'hash' => $hash], self::TTL)) {
            $stored = Cache::get($slot);
            if (($stored['hash'] ?? null) !== $hash) {
                return ApiResponse::error('idempotency_conflict', 'Idempotency key dipakai dengan body berbeda.', 409);
            }
            if (($stored['state'] ?? '') !== 'done') {
                return ApiResponse::error('idempotency_conflict', 'Request dengan key ini sedang diproses.', 409)
                    ->header('Retry-After', '1');
            }

            return response()->json($stored['body'], $stored['status'])->header('Idempotent-Replay', 'true');
        }

        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            Cache::forget($slot); // lepas slot supaya klien boleh retry
            throw $e;
        }

        if ($response->isSuccessful()) {
            Cache::put($slot, [
                'state' => 'done',
                'hash' => $hash,
                'status' => $response->getStatusCode(),
                'body' => json_decode($response->getContent(), true),
            ], self::TTL);
        } else {
            Cache::forget($slot);
        }

        return $response;
    }
}
