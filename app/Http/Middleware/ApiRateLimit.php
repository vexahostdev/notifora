<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;

class ApiRateLimit
{
    public function __construct(private RateLimiter $limiter) {}

    public function handle(Request $request, Closure $next)
    {
        $key = $request->attributes->get('api_key');
        $env = app('current.environment');
        $max = (int) ($env->workspace->planConfig()['rpm'] ?? 600);
        $bucket = 'api:'.$key->id;

        if ($this->limiter->tooManyAttempts($bucket, $max)) {
            $retry = $this->limiter->availableIn($bucket);

            return ApiResponse::error('rate_limited', 'Too many requests.', 429)
                ->withHeaders([
                    'Retry-After' => $retry,
                    'X-RateLimit-Limit' => $max,
                    'X-RateLimit-Remaining' => 0,
                    'X-RateLimit-Reset' => time() + $retry,
                ]);
        }

        $this->limiter->hit($bucket, 60);
        $response = $next($request);

        return $response->withHeaders([
            'X-RateLimit-Limit' => $max,
            'X-RateLimit-Remaining' => $this->limiter->remaining($bucket, $max),
            'X-RateLimit-Reset' => time() + $this->limiter->availableIn($bucket),
        ]);
    }
}
