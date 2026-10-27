<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Pengaturan platform (saklar & pesan) yang dapat diubah admin tanpa deploy. */
class PlatformSettings
{
    private const DEFAULTS = [
        'signup_enabled' => '1',
        'checkout_enabled' => '1',
        'maintenance' => '0',
        'maintenance_message' => '',
    ];

    /** @return array<string,string> */
    public static function all(): array
    {
        try {
            $stored = Cache::remember('platform.settings', 60, fn () => DB::table('platform_settings')->pluck('value', 'key')->all());
        } catch (Throwable) {
            $stored = [];
        }

        return $stored + self::DEFAULTS;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        return self::all()[$key] ?? $default;
    }

    public static function bool(string $key): bool
    {
        return in_array(strtolower((string) self::get($key, '0')), ['1', 'true', 'on', 'yes'], true);
    }

    public static function set(string $key, ?string $value): void
    {
        DB::table('platform_settings')->updateOrInsert(['key' => $key], ['value' => $value, 'updated_at' => now(), 'created_at' => now()]);
        Cache::forget('platform.settings');
    }

    public static function setBool(string $key, bool $value): void
    {
        self::set($key, $value ? '1' : '0');
    }
}
