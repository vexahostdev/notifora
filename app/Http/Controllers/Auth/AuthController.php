<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\BlockedEntity;
use App\Models\User;
use App\Services\InvitationService;
use App\Services\PlatformSettings;
use App\Services\SystemNotifier;
use App\Services\WorkspaceProvisioner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request, InvitationService $invites)
    {
        $cred = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);

        $user = User::where('email', $cred['email'])->first();
        if (! $user || ! Hash::check($cred['password'], $user->password)) {
            return back()->withInput($request->only('email'))->withErrors(['email' => 'Email atau password salah.']);
        }
        if ($user->isSuspended()) {
            return back()->withInput($request->only('email'))->withErrors(['email' => 'Akun ditangguhkan. Hubungi '.config('notifora.support_email').'.']);
        }

        // 2FA: login baru selesai setelah kode TOTP / kode pemulihan diverifikasi
        if ($user->hasTwoFactor()) {
            $request->session()->put('2fa', ['user_id' => $user->id, 'remember' => $request->boolean('remember'), 'at' => time()]);

            return redirect()->route('two-factor.challenge');
        }

        return $this->finishLogin($request, $user, $request->boolean('remember'));
    }

    /** Menyelesaikan login (setelah password, dan 2FA bila aktif). */
    public function finishLogin(Request $request, User $user, bool $remember = false)
    {
        Auth::login($user, $remember);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();
        $this->acceptPendingInvite($request, app(InvitationService::class));

        $home = $user->isPlatformAdmin() && ! $user->currentMembership() ? route('admin.dashboard') : route('console.dashboard');

        return redirect()->intended($home);
    }

    public function showRegister(Request $request)
    {
        $hasInvite = (bool) $request->session()->get('invite_token');
        $locked = ! $hasInvite && ! PlatformSettings::bool('signup_enabled');
        $lockMessage = PlatformSettings::get('signup_lock_message')
            ?: 'Pendaftaran akun baru sedang ditutup sementara untuk pengujian dan pemeliharaan sistem. Hubungi '.config('notifora.support_email').'.';

        return view('auth.register', [
            'invite' => $hasInvite,
            'locked' => $locked,
            'lockMessage' => $lockMessage,
        ]);
    }

    public function register(Request $request, WorkspaceProvisioner $provisioner, InvitationService $invites, SystemNotifier $notifier)
    {
        $inviteToken = $request->session()->get('invite_token');
        $invite = $inviteToken ? $invites->find($inviteToken) : null;

        if (! $invite && ! PlatformSettings::bool('signup_enabled')) {
            $lockMessage = PlatformSettings::get('signup_lock_message')
                ?: 'Pendaftaran akun baru sedang ditutup sementara. Hubungi '.config('notifora.support_email').'.';

            return back()->withInput()->withErrors(['email' => $lockMessage]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'team' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', PasswordRule::min(10)->letters()->numbers()],
            'terms' => ['accepted'],
        ]);

        if (BlockedEntity::emailBlocked($data['email'])) {
            return back()->withInput()->withErrors(['email' => 'Domain email ini tidak dapat digunakan untuk mendaftar.']);
        }

        // Email pengundang harus sama dengan email undangan
        if ($invite && strcasecmp($invite->email, $data['email']) !== 0) {
            return back()->withInput()->withErrors(['email' => 'Gunakan email yang diundang ('.$invite->email.').']);
        }

        $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);

        if ($invite) {
            $invites->accept($user, $inviteToken);
            $request->session()->forget('invite_token');
        } else {
            $provisioner->create($user, ($data['team'] ?? null) ?: $data['name']."'s workspace");
        }

        Auth::login($user);
        $request->session()->regenerate();
        $notifier->welcome($user);

        return redirect()->route('console.dashboard');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('landing');
    }

    // --- Lupa / reset password ---------------------------------------

    public function showForgot()
    {
        return view('auth.forgot-password');
    }

    public function sendReset(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);
        Password::sendResetLink($request->only('email'));

        // Respons sama untuk email terdaftar/tidak (cegah enumerasi akun)
        return back()->with('status', 'Jika email terdaftar, tautan reset password telah dikirim.');
    }

    public function showReset(Request $request, string $token)
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function reset(Request $request)
    {
        $data = $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(10)->letters()->numbers()],
        ]);

        $status = Password::reset($data, function (User $user, string $password) {
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            app(SystemNotifier::class)->passwordChanged($user);
        });

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', 'Password berhasil diubah. Silakan masuk.')
            : back()->withErrors(['email' => 'Token reset tidak valid atau sudah kedaluwarsa.']);
    }

    // --- Undangan tim -------------------------------------------------

    public function acceptInvitation(Request $request, string $token, InvitationService $invites)
    {
        $invite = $invites->find($token);
        if (! $invite) {
            return redirect()->route('login')->withErrors(['email' => 'Undangan tidak valid atau sudah kedaluwarsa.']);
        }
        $request->session()->put('invite_token', $token);

        if (Auth::check()) {
            $this->acceptPendingInvite($request, $invites);

            return redirect()->route('console.dashboard');
        }

        $exists = User::where('email', $invite->email)->exists();

        return redirect()->route($exists ? 'login' : 'register')->with('status', 'Undangan untuk '.$invite->email.'. Lanjutkan untuk bergabung.');
    }

    private function acceptPendingInvite(Request $request, InvitationService $invites): void
    {
        if ($token = $request->session()->get('invite_token')) {
            $invites->accept($request->user(), $token);
            $request->session()->forget('invite_token');
        }
    }
}
