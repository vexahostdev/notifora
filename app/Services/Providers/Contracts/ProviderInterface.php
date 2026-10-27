<?php

namespace App\Services\Providers\Contracts;

use App\Services\Providers\DeliveryUpdate;
use App\Services\Providers\ProviderResult;
use App\Services\Providers\SendRequest;
use Illuminate\Http\Request;

interface ProviderInterface
{
    public function send(SendRequest $request): ProviderResult;

    /** Parse webhook status pengiriman dari provider; null bila tidak didukung/tidak dikenali. */
    public function parseWebhook(Request $request): ?DeliveryUpdate;
}
