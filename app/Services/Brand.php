<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Identitas merek yang dapat diubah operator (Admin → Branding) tanpa deploy.
 * Nilai tersimpan di platform_settings; bila kosong dipakai bawaan aplikasi.
 */
class Brand
{
    public const DEFAULT_ACCENT = '#0033ff';

    public static function get(string $key, ?string $default = null): ?string
    {
        $v = PlatformSettings::get($key);

        return ($v === null || $v === '') ? $default : $v;
    }

    public static function name(): string
    {
        return self::get('brand_name', 'Notifora');
    }

    public static function tagline(): string
    {
        return self::get('brand_tagline', 'Satu API untuk semua notifikasi');
    }

    public static function footer(): string
    {
        return self::get('brand_footer', '© {year} notifora. All rights reserved. Created by notifora');
    }

    public static function footerRendered(): string
    {
        return str_replace('{year}', date('Y'), self::footer());
    }

    public static function accent(): string
    {
        $c = self::get('brand_accent', self::DEFAULT_ACCENT);

        return preg_match('/^#[0-9a-fA-F]{6}$/', $c) ? strtolower($c) : self::DEFAULT_ACCENT;
    }

    /** Versi lebih gelap ±15% untuk status hover. */
    public static function accentHover(): string
    {
        $c = ltrim(self::accent(), '#');
        $rgb = array_map(fn ($h) => max(0, (int) round(hexdec($h) * 0.85)), str_split($c, 2));

        return sprintf('#%02x%02x%02x', ...$rgb);
    }

    public static function hasCustomAccent(): bool
    {
        return self::accent() !== self::DEFAULT_ACCENT;
    }

    public static function logoUrl(): string
    {
        return self::fileUrl('brand_logo_path') ?? asset('logo-sm.png');
    }

    public static function faviconUrl(): string
    {
        return self::fileUrl('brand_favicon_path') ?? asset('favicon.ico');
    }

    public static function supportEmail(): string
    {
        return self::get('support_email', config('notifora.support_email'));
    }

    private static function fileUrl(string $key): ?string
    {
        $path = self::get($key);
        if (! $path) {
            return null;
        }
        try {
            return Storage::disk('public')->exists($path) ? asset('storage/'.$path).'?v='.(self::get($key.'_v') ?? '1') : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** Terapkan ke config runtime (nama aplikasi, email dukungan, nama pengirim). Aman jika tabel belum ada. */
    public static function apply(): void
    {
        try {
            $name = self::name();
            config([
                'app.name' => $name,
                'notifora.support_email' => self::supportEmail(),
                'mail.from.name' => self::get('mail_from_name', $name),
            ]);
        } catch (Throwable) {
            // belum migrasi / DB belum siap
        }
    }
}
