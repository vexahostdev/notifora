<?php

namespace App\Services\Providers;

use App\Services\Providers\Contracts\ProviderInterface;
use Illuminate\Http\Request;

class CircuitBreakerProvider implements ProviderInterface
{
    public function __construct(private ProviderInterface $inner, private CircuitBreaker $breaker) {}

    public function send(SendRequest $request): ProviderResult
    {
        if ($this->breaker->isOpen()) {
            return new ProviderResult(false, true, error: 'Circuit open', errorCode: 'circuit_open', deferred: true);
        }

        $result = $this->inner->send($request);

        // Hanya kegagalan infrastruktur (retryable) yang menghitung; error 4xx = masalah data, bukan provider.
        if ($result->success) {
            $this->breaker->recordSuccess();
        } elseif ($result->retryable) {
            $this->breaker->recordFailure();
        }

        return $result;
    }

    public function parseWebhook(Request $request): ?DeliveryUpdate
    {
        return $this->inner->parseWebhook($request);
    }
}
