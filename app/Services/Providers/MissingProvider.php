<?php

namespace App\Services\Providers;

use App\Services\Providers\Contracts\ProviderInterface;
use Illuminate\Http\Request;

class MissingProvider implements ProviderInterface
{
    public function send(SendRequest $r): ProviderResult
    {
        return new ProviderResult(false, false, error: 'Belum ada provider aktif untuk channel ini', errorCode: 'no_provider_configured');
    }

    public function parseWebhook(Request $request): ?DeliveryUpdate
    {
        return null;
    }
}
