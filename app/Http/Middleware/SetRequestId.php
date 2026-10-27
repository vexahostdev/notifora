<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SetRequestId
{
    public function handle(Request $request, Closure $next)
    {
        $id = 'req_'.Str::lower(Str::random(16));
        $request->attributes->set('request_id', $id);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $id);

        return $response;
    }
}
