<?php

namespace App\Console\Commands;

use App\Services\PlatformSettings;
use Illuminate\Console\Command;

class ToggleSignup extends Command
{
    protected $signature = 'notifora:signup {action? : status | lock | unlock} {--message= : Pesan saat pendaftaran dikunci}';

    protected $description = 'Kunci, buka, atau periksa status pendaftaran akun baru platform Notifora.';

    public function handle(): int
    {
        $action = strtolower((string) ($this->argument('action') ?: 'status'));

        if ($action === 'lock') {
            PlatformSettings::setBool('signup_enabled', false);
            if ($msg = $this->option('message')) {
                PlatformSettings::set('signup_lock_message', $msg);
            }
            $this->warn('🔒 Pendaftaran akun baru berhasil DIKUNCI.');
            $this->line('Pesan: '.(PlatformSettings::get('signup_lock_message') ?: '(default)'));

            return self::SUCCESS;
        }

        if ($action === 'unlock') {
            PlatformSettings::setBool('signup_enabled', true);
            $this->info('🔓 Pendaftaran akun baru berhasil DIBUKA.');

            return self::SUCCESS;
        }

        if ($action === 'status') {
            $open = PlatformSettings::bool('signup_enabled');
            $this->line('Status pendaftaran: '.($open ? '<info>TERBUKA</info>' : '<comment>TERKUNCI</comment>'));
            if (! $open) {
                $this->line('Pesan lock: '.(PlatformSettings::get('signup_lock_message') ?: '(default)'));
            }

            return self::SUCCESS;
        }

        $this->error("Aksi tidak valid: {$action}. Gunakan 'lock', 'unlock', atau 'status'.");

        return self::FAILURE;
    }
}
