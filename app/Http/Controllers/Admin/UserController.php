<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\User;
use App\Services\AdminAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $q = User::query()->withCount('memberships');
        if ($s = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%"));
        }
        match ($request->query('filter')) {
            'admins' => $q->where('is_platform_admin', true),
            'suspended' => $q->whereNotNull('suspended_at'),
            'no2fa' => $q->whereNull('two_factor_confirmed_at'),
            'unverified' => $q->whereNull('email_verified_at'),
            default => null,
        };

        return view('admin.users.index', ['users' => $q->latest()->paginate(30)->withQueryString()]);
    }

    public function show(User $user)
    {
        return view('admin.users.show', [
            'u' => $user,
            'memberships' => Member::with('workspace:id,name,slug,plan,suspended_at')->where('user_id', $user->id)->get(),
        ]);
    }

    public function suspend(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'Anda tidak dapat menangguhkan akun sendiri.');
        }
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $user->forceFill(['suspended_at' => now(), 'suspended_reason' => $data['reason']])->save();
        AdminAudit::record('user.suspended', 'user', (string) $user->id, null, ['email' => $user->email, 'reason' => $data['reason']]);

        return back()->with('success', 'Akun ditangguhkan; tidak dapat masuk.');
    }

    public function unsuspend(User $user)
    {
        $user->forceFill(['suspended_at' => null, 'suspended_reason' => null])->save();
        AdminAudit::record('user.unsuspended', 'user', (string) $user->id, null, ['email' => $user->email]);

        return back()->with('success', 'Penangguhan akun dicabut.');
    }

    public function verifyEmail(User $user)
    {
        $user->forceFill(['email_verified_at' => now()])->save();
        AdminAudit::record('user.email_verified', 'user', (string) $user->id, null, ['email' => $user->email]);

        return back()->with('success', 'Email ditandai terverifikasi.');
    }

    public function reset2fa(User $user)
    {
        $user->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();
        AdminAudit::record('user.2fa_reset', 'user', (string) $user->id, null, ['email' => $user->email]);

        return back()->with('success', '2FA pengguna direset. Pengguna perlu mengaktifkannya lagi.');
    }

    public function sendReset(User $user)
    {
        Password::sendResetLink(['email' => $user->email]);
        AdminAudit::record('user.reset_link_sent', 'user', (string) $user->id, null, ['email' => $user->email]);

        return back()->with('success', 'Tautan reset password dikirim ke '.$user->email.'.');
    }

    /** Beri / cabut akses operator platform. Selalu menyisakan minimal satu operator, dan tidak bisa mencabut diri sendiri. */
    /** Atur peran operator (super/finance/support/ops). Hanya super operator. */
    public function role(Request $request, User $user)
    {
        abort_unless($request->user()->adminRole() === 'super', 403, 'Hanya super operator yang dapat mengubah peran.');
        abort_unless($user->is_platform_admin, 422, 'Pengguna bukan operator.');
        $role = $request->validate(['role' => ['required', 'in:'.implode(',', array_keys(config('admin.roles')))]])['role'];

        if ($user->adminRole() === 'super' && $role !== 'super'
            && User::where('is_platform_admin', true)->where(fn ($q) => $q->whereNull('admin_role')->orWhere('admin_role', 'super'))->count() <= 1) {
            return back()->with('error', 'Minimal satu super operator harus tersisa.');
        }
        $user->forceFill(['admin_role' => $role === 'super' ? null : $role])->save();
        AdminAudit::record('admin.role_set', 'user', (string) $user->id, null, ['email' => $user->email, 'role' => $role]);

        return back()->with('success', "Peran operator diubah menjadi {$role}.");
    }

    public function toggleAdmin(Request $request, User $user)
    {
        abort_unless($request->user()->adminRole() === 'super', 403, 'Hanya super operator yang dapat memberi atau mencabut akses operator.');
        if ($user->is_platform_admin) {
            if ($user->id === $request->user()->id) {
                return back()->with('error', 'Anda tidak dapat mencabut akses operator Anda sendiri.');
            }
            if (User::where('is_platform_admin', true)->count() <= 1) {
                return back()->with('error', 'Minimal satu operator platform harus tersisa.');
            }
            $user->forceFill(['is_platform_admin' => false])->save();
            AdminAudit::record('admin.revoked', 'user', (string) $user->id, null, ['email' => $user->email]);

            return back()->with('success', 'Akses operator dicabut.');
        }

        if (config('admin.require_2fa') && ! $user->hasTwoFactor()) {
            return back()->with('error', 'Pengguna harus mengaktifkan 2FA lebih dulu sebelum dijadikan operator.');
        }
        $user->forceFill(['is_platform_admin' => true])->save();
        AdminAudit::record('admin.granted', 'user', (string) $user->id, null, ['email' => $user->email]);

        return back()->with('success', 'Akses operator diberikan.');
    }
}
