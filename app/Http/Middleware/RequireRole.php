<?php

namespace App\Http\Middleware;

use App\Enums\MemberRole;
use Closure;
use Illuminate\Http\Request;

/** Pemakaian: ->middleware('role:DEVELOPER') = DEVELOPER ke atas. */
class RequireRole
{
    public function handle(Request $request, Closure $next, string $min)
    {
        $role = app('current.role');
        abort_unless($role->atLeast(MemberRole::from($min)), 403, 'Anda tidak memiliki akses.');

        return $next($request);
    }
}
