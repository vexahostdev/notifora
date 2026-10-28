<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PlatformAuditLog;
use App\Models\User;
use App\Services\Totp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TwoFactorController extends Controller
{
    private const CHALLENGE_TTL = 600; // detik

    public function __construct(private Totp $totp) {}

    // --- Tantangan saat login ---------------------------------------------

    public function challenge(Request $request)
    {
        abort_unless($this->pendingUser($request), 404);

        return view('auth.two-factor-challenge');
    }

    public function verify(Request $request, AuthController $auth)
    {
        $user = $this->pendingUser($request);
        if (! $user) {
            return redirect()->route('login')->withErrors(['email' => 'Sesi verifikasi berakhir. Silakan masuk lagi.']);
        }
        $request->validate(['code' => ['nullable', 'string', 'max:32'], 'recovery_code' => ['nullable', 'string', 'max:32']]);

        if (! $this->codeAccepted($user, (string) $request->input('code'), (string) $request->input('recovery_code'))) {
            return back()->withErrors(['code' => 'Kode tidak valid atau sudah dipakai.']);
        }

        $remember = (bool) ($request->session()->get('2fa')['remember'] ?? false);
        $request->session()->forget('2fa');

        return $auth->finishLogin($request, $user, $remember);
    }

    // --- Pengaturan 2FA ---------------------------------------------------

    public function show(Request $request)
    {
        $user = $request->user();
        $secret = null;
        if (! $user->hasTwoFactor()) {
            $secret = $request->session()->get('2fa_setup_secret');
        }

        return view('security.two-factor', [
            'user' => $user,
            'secret' => $secret,
            'uri' => $secret ? $this->totp->uri($secret, $user->email) : null,
            'recoveryCodes' => $request->session()->get('recovery_codes'),
            'mustKeep' => config('admin.require_2fa') && $user->isPlatformAdmin(),
        ]);
    }

    public function start(Request $request)
    {
        abort_if($request->user()->hasTwoFactor(), 404);
        $request->session()->put('2fa_setup_secret', $this->totp->generateSecret());

        return redirect()->route('security.2fa');
    }

    public function confirm(Request $request)
    {
        $user = $request->user();
        $secret = $request->session()->get('2fa_setup_secret');
        $request->validate(['code' => ['required', 'string', 'max:12']]);

        if (! $secret || ! $this->totp->verify($secret, $request->input('code'))) {
            return back()->withErrors(['code' => 'Kode salah. Pastikan jam perangkat Anda akurat dan coba lagi.']);
        }

        $codes = $this->totp->recoveryCodes();
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $codes,
            'two_factor_confirmed_at' => now(),
        ])->save();
        $request->session()->forget('2fa_setup_secret');
        $request->session()->flash('recovery_codes', $codes);

        return redirect()->route('security.2fa')->with('success', '2FA aktif. Simpan kode pemulihan di tempat aman.');
    }

    public function disable(Request $request)
    {
        $user = $request->user();
        abort_if(config('admin.require_2fa') && $user->isPlatformAdmin(), 403, 'Operator platform tidak boleh menonaktifkan 2FA.');
        abort_unless($user->hasTwoFactor(), 404);
        $request->validateWithBag('disable', ['password' => ['required', 'current_password'], 'code' => ['required', 'string', 'max:32']]);

        if (! $this->codeAccepted($user, (string) $request->input('code'), (string) $request->input('code'))) {
            return back()->withErrors(['code' => 'Kode tidak valid.'], 'disable');
        }
        $user->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();

        return redirect()->route('security.2fa')->with('success', '2FA dinonaktifkan.');
    }

    public function regenerate(Request $request)
    {
        $user = $request->user();
        abort_unless($user->hasTwoFactor(), 404);
        $request->validateWithBag('recovery', ['password' => ['required', 'current_password']]);

        $codes = $this->totp->recoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => $codes])->save();
        $request->session()->flash('recovery_codes', $codes);

        return redirect()->route('security.2fa')->with('success', 'Kode pemulihan baru dibuat; kode lama tidak berlaku lagi.');
    }

    // ---------------------------------------------------------------------

    private function pendingUser(Request $request): ?User
    {
        $p = $request->session()->get('2fa');
        if (! $p || (time() - ($p['at'] ?? 0)) > self::CHALLENGE_TTL) {
            return null;
        }

        return User::find($p['user_id']);
    }

    /** Terima kode TOTP (sekali pakai dalam jendela waktunya) atau satu kode pemulihan. */
    private function codeAccepted(User $user, string $code, string $recovery): bool
    {
        $code = trim($code);
        if ($code !== '' && preg_match('/^\d{6}$/', preg_replace('/\s+/', '', $code))) {
            $clean = preg_replace('/\s+/', '', $code);
            // 2FA bisa saja direset operator saat tantangan masih berjalan: jangan panggil verify(null).
            if ($user->two_factor_secret !== null
                && $this->totp->verify($user->two_factor_secret, $clean)
                && Cache::add("totp:used:{$user->id}:$clean", 1, 120)) {
                return true;
            }
        }

        $candidate = strtolower(trim($recovery !== '' ? $recovery : $code));
        $codes = $user->two_factor_recovery_codes ?? [];
        foreach ($codes as $i => $c) {
            if ($candidate !== '' && hash_equals($c, $candidate)) {
                unset($codes[$i]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();
                if ($user->isPlatformAdmin()) {
                    PlatformAuditLog::create(['admin_id' => $user->id, 'action' => 'admin.recovery_code_used', 'target_type' => 'user', 'target_id' => (string) $user->id, 'ip' => request()->ip(), 'created_at' => now()]);
                }

                return true;
            }
        }

        return false;
    }
}
