<?php

namespace App\Services\Providers;

use App\Services\Providers\Contracts\ProviderInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Dipakai simulator console: render saja, tidak ada panggilan eksternal. */
class DryRunProvider implements ProviderInterface
{
    public function send(SendRequest $r): ProviderResult
    {
        return new ProviderResult(true, messageId: 'dryrun_'.Str::lower(Str::random(10)), destination: $r->to, rawResponse: ['dry_run' => true]);
    }

    public function parseWebhook(Request $request): ?DeliveryUpdate
    {
        return null;
    }
}
