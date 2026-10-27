<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class BlockedEntity extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = ['expires_at' => 'datetime'];

    protected static function booted(): void
    {
        $flush = fn () => Cache::forget('blocklist.v1');
        static::saved($flush);
        static::deleted($flush);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Daftar aktif {ip: [...], email_domain: [...]} (cache 60 dtk). */
    public static function active(): array
    {
        return Cache::remember('blocklist.v1', 60, function () {
            $rows = static::query()->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->get();

            return [
                'ip' => $rows->where('type', 'ip')->pluck('value')->all(),
                'email_domain' => $rows->where('type', 'email_domain')->pluck('value')->map(fn ($v) => strtolower($v))->all(),
            ];
        });
    }

    public static function ipBlocked(?string $ip): bool
    {
        return $ip !== null && in_array($ip, static::active()['ip'], true);
    }

    public static function emailBlocked(string $email): bool
    {
        $domain = strtolower(substr(strrchr($email, '@') ?: '', 1));

        return $domain !== '' && in_array($domain, static::active()['email_domain'], true);
    }
}
