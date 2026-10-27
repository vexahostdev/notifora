<?php

namespace App\Http\Middleware;

use App\Enums\EnvironmentType;
use App\Models\Environment;
use Closure;
use Illuminate\Http\Request;

/** Console: tentukan workspace, role, dan environment aktif dari session. */
class SetCurrentEnvironment
{
    public function handle(Request $request, Closure $next)
    {
        $membership = $request->user()?->currentMembership();
        if (! $membership) {
            auth()->logout();

            return redirect()->route('login')->withErrors(['email' => 'Akun belum terhubung ke workspace.']);
        }

        $envs = Environment::where('workspace_id', $membership->workspace_id)->get();
        $env = $envs->firstWhere('id', session('environment_id'))
            ?? $envs->firstWhere('type', EnvironmentType::DEVELOPMENT)
            ?? $envs->first();
        session(['environment_id' => $env->id]);
        $env->setRelation('workspace', $membership->workspace);

        app()->instance('current.environment', $env);
        app()->instance('current.workspace', $membership->workspace);
        app()->instance('current.role', $membership->role);

        view()->share([
            'currentEnv' => $env,
            'currentWorkspace' => $membership->workspace,
            'currentRole' => $membership->role,
            'allEnvs' => $envs,
        ]);

        return $next($request);
    }
}
