<?php

namespace App\Models;

use App\Enums\ChannelType;
use App\Models\Concerns\BelongsToEnvironment;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ChannelProvider extends Model
{
    use BelongsToEnvironment, HasUuids;

    protected $guarded = [];

    protected $hidden = ['credentials'];

    protected $casts = [
        'channel' => ChannelType::class,
        'credentials' => 'encrypted:array',
        'is_default' => 'boolean',
        'last_tested_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $p) {
            $p->webhook_token ??= Str::random(40);
        });
    }

    /** Kredensial dengan nilai rahasia disamarkan untuk UI. */
    public function maskedCredentials(): array
    {
        return collect($this->credentials ?? [])->map(function ($v, $k) {
            $secret = preg_match('/key|token|secret|password/i', (string) $k);

            return $secret && is_string($v) && $v !== '' ? '••••'.substr($v, -4) : $v;
        })->all();
    }

    /** Field kredensial per tipe provider. */
    public static function schema(string $provider): array
    {
        return match ($provider) {
            'vexahost_wa' => ['endpoint_url' => 'Endpoint URL', 'api_key' => 'API Key', 'session_id' => 'Session ID'],
            'postivo' => ['endpoint_url' => 'Endpoint URL', 'api_key' => 'API Key', 'from_email' => 'From Email', 'from_name' => 'From Name'],
            'telegram' => ['bot_token' => 'Bot Token'],
            default => [],
        };
    }

    public static function providerFor(ChannelType $channel): string
    {
        return match ($channel) {
            ChannelType::WHATSAPP => 'vexahost_wa',
            ChannelType::EMAIL => 'postivo',
            ChannelType::TELEGRAM => 'telegram',
            ChannelType::IN_APP => 'internal',
        };
    }
}
