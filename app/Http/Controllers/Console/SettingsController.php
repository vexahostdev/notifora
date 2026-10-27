<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\DashboardMetrics;
use App\Services\SystemNotifier;
use App\Services\UsageMeter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class SettingsController extends Controller
{
    public function usage(UsageMeter $usage, DashboardMetrics $metrics)
    {
        $env = app('current.environment');
        $limit = $usage->limitFor($env);
        $used = $usage->monthlyEvents($env);

        return view('console.settings.usage', [
            'plan' => app('current.workspace')->planConfig(),
            'planKey' => app('current.workspace')->plan,
            'used' => $used,
            'limit' => $limit,
            'pct' => $limit ? min(100, round($used / $limit * 100)) : 0,
            'daily' => $metrics->usageDaily($env->id),
            'plans' => config('notifora.plans'),
        ]);
    }

    public function audit(Request $request)
    {
        $q = AuditLog::with('user:id,name')->where('workspace_id', app('current.workspace')->id)->latest('id');
        if ($a = $request->query('action')) {
            $q->where('action', 'like', "$a%");
        }

        return view('console.settings.audit', ['logs' => $q->paginate(40)->withQueryString()]);
    }

    public function account()
    {
        return view('console.settings.account');
    }

    public function updateAccount(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
        ]);
        $user->update($data);

        return back()->with('success', 'Profil diperbarui.');
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);
        $request->user()->update(['password' => Hash::make($data['password'])]);
        AuditLog::record(app('current.workspace')->id, 'user.password_changed', 'user', (string) $request->user()->id);
        app(SystemNotifier::class)->passwordChanged($request->user());

        return back()->with('success', 'Password diubah.');
    }
}
