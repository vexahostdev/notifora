<?php

namespace App\Services\Providers;

use App\Services\Providers\Contracts\ProviderInterface;
use App\Support\Phone;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class VexaHostWhatsAppProvider implements ProviderInterface
{
    public function __construct(private array $credentials) {}

    public function send(SendRequest $r): ProviderResult
    {
        $phone = Phone::digits($r->to);
        if (! $phone) {
            return new ProviderResult(false, false, error: 'Nomor tujuan tidak valid', errorCode: 'invalid_destination');
        }

        try {
            $res = Http::withToken((string) ($this->credentials['api_key'] ?? ''))
                ->acceptJson()->timeout(8)->connectTimeout(3)
                ->post(rtrim((string) ($this->credentials['endpoint_url'] ?? ''), '/').'/api/send-message', [
                    'sessionId' => $this->credentials['session_id'] ?? null,
                    'phoneNumber' => $phone,
                    'message' => $r->content,
                ]);
        } catch (ConnectionException $e) {
            return new ProviderResult(false, true, destination: $phone, error: $e->getMessage(), errorCode: 'connection_error');
        }

        if ($res->successful()) {
            return new ProviderResult(
                true,
                messageId: $res->json('messageId') ?? $res->json('id'),
                destination: $phone,
                httpStatus: $res->status(),
                rawResponse: $res->json() ?? [],
            );
        }

        $retryable = $res->serverError() || in_array($res->status(), [408, 429], true);

        return new ProviderResult(false, $retryable, destination: $phone, error: $res->body(), errorCode: 'http_'.$res->status(), httpStatus: $res->status());
    }

    /** Payload yang diasumsikan: {messageId|id, status}. Verifikasi terhadap gateway asli sebelum rilis. */
    public function parseWebhook(Request $request): ?DeliveryUpdate
    {
        $id = $request->input('messageId', $request->input('id'));
        $status = DeliveryUpdate::mapStatus($request->input('status'));

        return $id && $status ? new DeliveryUpdate((string) $id, $status, $request->input('error')) : null;
    }
}
