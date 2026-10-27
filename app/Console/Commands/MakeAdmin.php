<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeAdmin extends Command
{
    protected $signature = 'notifora:make-admin {email? : Kosong = pakai ADMIN_EMAIL/ADMIN_PASSWORD dari .env} {--create : Buat akun bila belum ada (password acak ditampilkan sekali)} {--name= : Nama untuk akun baru} {--revoke : Cabut akses operator}';

    protected $description = 'Beri atau cabut akses operator platform (/admin). Tanpa argumen: sinkronkan akun operator dari .env.';

    public function handle(): int
    {
        $boot = config('admin.bootstrap');
        $fromEnv = ! $this->argument('email');
        $email = Str::lower(trim((string) ($this->argument('email') ?: $boot['email'])));

        if ($email === '') {
            $this->error('Isi argumen email, atau set ADMIN_EMAIL di .env.');

            return self::FAILURE;
        }

        $envMatch = $email === Str::lower((string) $boot['email']) && ! empty($boot['password']);
        $user = User::where('email', $email)->first();

        if (! $user && $this->option('revoke')) {
            $this->error("Pengguna {$email} tidak ditemukan.");

            return self::FAILURE;
        }

        if (! $user) {
            if (! $fromEnv && ! $this->option('create')) {
                $this->error("Pengguna {$email} tidak ditemukan. Tambahkan --create untuk membuatnya.");

                return self::FAILURE;
            }
            $password = $envMatch ? $boot['password'] : Str::password(20, symbols: false);
            $user = User::create(['name' => $this->option('name') ?: $boot['name'], 'email' => $email, 'password' => $password]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $this->info($envMatch ? 'Akun dibuat dengan password dari ADMIN_PASSWORD.' : "Akun dibuat. Password sementara (hanya tampil sekali): {$password}");
        } elseif ($envMatch && ! $this->option('revoke')) {
            // Sinkronkan password dari .env (idempoten; password di-hash oleh cast model)
            $user->forceFill(['password' => $boot['password'], 'email_verified_at' => $user->email_verified_at ?? now()])->save();
            $this->info('Password disinkronkan dari ADMIN_PASSWORD.');
        }

        $user->forceFill(['is_platform_admin' => ! $this->option('revoke')])->save();
        $this->info($this->option('revoke')
            ? "Akses operator dicabut dari {$email}."
            : "{$email} sekarang operator platform. Login lalu aktifkan 2FA di /security/2fa.");

        return self::SUCCESS;
    }
}
