<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = ['last_used_at' => 'datetime', 'revoked_at' => 'datetime'];

    public function environment()
    {
        return $this->belongsTo(Environment::class);
    }

    public static function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }

    /** @return array{0: ApiKey, 1: string} model dan key lengkap (hanya tampil sekali) */
    public static function issue(Environment $env, string $name, ?int $userId = null): array
    {
        $plain = $env->type->keyPrefix().Str::random(32);

        $key = static::create([
            'environment_id' => $env->id,
            'name' => $name,
            'key_prefix' => substr($plain, 0, 12),
            'key_hash' => static::hash($plain),
            'last4' => substr($plain, -4),
            'created_by' => $userId,
        ]);

        return [$key, $plain];
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function masked(): string
    {
        return $this->key_prefix.'••••••••'.$this->last4;
    }
}
