<?php

namespace App\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Throwable;

/**
 * Konfigurasi SMTP yang dapat diubah dari Admin → Email → SMTP.
 * Bila mail_override=1 nilai di DB menggantikan .env (password dienkripsi dengan APP_KEY).
 */
class MailSettings
{
    public const KEYS = ['mail_host', 'mail_port', 'mail_username', 'mail_encryption', 'mail_from_address', 'mail_from_name'];

    public static function overridden(): bool
    {
        return PlatformSettings::bool('mail_override');
    }

    public static function password(): ?string
    {
        $raw = PlatformSettings::get('mail_password_enc');
        if (! $raw) {
            return null;
        }
        try {
            return Crypt::decryptString($raw);
        } catch (DecryptException) {
            return null; // APP_KEY berubah: operator harus mengisi ulang
        }
    }

    public static function savePassword(string $plain): void
    {
        PlatformSettings::set('mail_password_enc', Crypt::encryptString($plain));
    }

    /** Konfigurasi efektif (setelah override) untuk ditampilkan di form. */
    public static function effective(): array
    {
        $m = config('mail.mailers.smtp');

        return [
            'host' => $m['host'] ?? '',
            'port' => $m['port'] ?? 587,
            'username' => $m['username'] ?? '',
            'has_password' => ! empty($m['password']),
            'encryption' => $m['scheme'] ?? (($m['port'] ?? 587) == 465 ? 'smtps' : 'smtp'),
            'from_address' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
        ];
    }

    public static function apply(): void
    {
        try {
            if (! self::overridden()) {
                return;
            }
            $port = (int) PlatformSettings::get('mail_port', '587');
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => PlatformSettings::get('mail_host') ?: config('mail.mailers.smtp.host'),
                'mail.mailers.smtp.port' => $port,
                'mail.mailers.smtp.username' => PlatformSettings::get('mail_username') ?: config('mail.mailers.smtp.username'),
                'mail.mailers.smtp.password' => self::password() ?: config('mail.mailers.smtp.password'),
                'mail.mailers.smtp.scheme' => PlatformSettings::get('mail_encryption') === 'smtps' ? 'smtps' : 'smtp',
                'mail.from.address' => PlatformSettings::get('mail_from_address') ?: config('mail.from.address'),
            ]);
            Mail::purge('smtp');
        } catch (Throwable) {
            // DB belum siap
        }
    }

    /**
     * Uji koneksi + autentikasi SMTP tanpa mengirim email.
     *
     * @return array{ok:bool, message:string, ms:int}
     */
    public static function probe(): array
    {
        $m = config('mail.mailers.smtp');
        $t0 = microtime(true);
        try {
            $scheme = ($m['scheme'] ?? null) === 'smtps' || (int) $m['port'] === 465;
            $transport = new EsmtpTransport($m['host'], (int) $m['port'], $scheme);
            $transport->setUsername((string) ($m['username'] ?? ''));
            $transport->setPassword((string) ($m['password'] ?? ''));
            $transport->getStream()->setTimeout(8);
            $transport->start();
            $transport->stop();

            return ['ok' => true, 'message' => "Terhubung & terautentikasi ke {$m['host']}:{$m['port']}", 'ms' => (int) ((microtime(true) - $t0) * 1000)];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => mb_substr($e->getMessage(), 0, 300), 'ms' => (int) ((microtime(true) - $t0) * 1000)];
        }
    }
}
