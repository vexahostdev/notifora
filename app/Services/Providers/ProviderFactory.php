<?php

namespace App\Services\Providers;

use App\Enums\ChannelType;
use App\Models\ChannelProvider;
use App\Models\NotificationEvent;
use App\Services\InAppPublisher;
use App\Services\Providers\Contracts\ProviderInterface;
use App\Services\SystemNotifier;

class ProviderFactory
{
    public function __construct(private InAppPublisher $publisher) {}

    /** Resolve provider untuk satu step (dari snapshot). Harus dipanggil dalam konteks environment. */
    public function forStep(array $stepDef, NotificationEvent $event): ProviderInterface
    {
        if ($event->dry_run) {
            return new DryRunProvider;
        }

        $channel = ChannelType::from($stepDef['channel']);
        if ($channel === ChannelType::IN_APP) {
            return new InAppProvider($this->publisher);
        }

        $query = ChannelProvider::where('channel', $channel->value)->where('status', '!=', 'disabled');
        $provider = ! empty($stepDef['provider_id'])
            ? (clone $query)->find($stepDef['provider_id'])
            : null;
        $provider ??= (clone $query)->orderByDesc('is_default')->first();

        return $provider ? $this->make($provider) : new MissingProvider;
    }

    public function make(ChannelProvider $provider): ProviderInterface
    {
        $creds = $provider->credentials ?? [];

        $adapter = match ($provider->provider) {
            'vexahost_wa' => new VexaHostWhatsAppProvider($creds),
            'postivo' => new PostivoEmailProvider($creds),
            'telegram' => new TelegramBotProvider($creds),
            default => new MissingProvider,
        };

        return new CircuitBreakerProvider($adapter, new CircuitBreaker(
            $provider->id,
            onOpen: fn () => app(SystemNotifier::class)->providerDegraded($provider),
        ));
    }
}
