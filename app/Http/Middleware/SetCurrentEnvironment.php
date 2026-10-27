<?php

namespace App\Http\Middleware;

use App\Enums\EnvironmentType;
use App\Models\Announcement;
use App\Models\Environment;
use App\Services\WorkspaceProvisioner;
use Closure;
use Illuminate\Http\Request;

/** Console: tentukan workspace, role, dan environment aktif dari session. */
class SetCurrentEnvironment
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user?->isSuspended() && ! session('impersonator_id')) {
            auth()->logout();

            return redirect()->route('login')->withErrors(['email' => 'Akun ditangguhkan. Hubungi '.config('notifora.support_email').'.']);
        }

        $membership = $user?->currentMembership();
        if (! $membership && $user?->isPlatformAdmin()) {
            // Operator platform juga pengguna biasa: siapkan workspace pribadi saat pertama membuka Console
            app(WorkspaceProvisioner::class)->create($user, $user->name.' Workspace');
            $membership = $user->fresh()->currentMembership();
        }
        if (! $membership) {
            auth()->logout();

            return redirect()->route('login')->withErrors(['email' => 'Akun belum terhubung ke workspace.']);
        }

        if ($membership->workspace->isSuspended() && ! session('impersonator_id')) {
            return response()->view('console.suspended', ['workspace' => $membership->workspace], 403);
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
            'liveAnnouncements' => Announcement::live()->latest()->limit(3)->get(),
        ]);

        return $next($request);
    }
}
