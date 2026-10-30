<?php

namespace App\Services\Providers;

use App\Services\Providers\Contracts\ProviderInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PostivoEmailProvider implements ProviderInterface
{
    public function __construct(private array $credentials) {}

    public function send(SendRequest $r): ProviderResult
    {
        if (! $r->to || ! filter_var($r->to, FILTER_VALIDATE_EMAIL)) {
            return new ProviderResult(false, false, error: 'Alamat email tidak valid', errorCode: 'invalid_destination');
        }

        $from = $this->credentials['from_email'] ?? null;
        $fromName = $this->credentials['from_name'] ?? null;

        try {
            $endpoint = rtrim((string) ($this->credentials['endpoint_url'] ?? ''), '/');
            if (str_ends_with($endpoint, '/emails') || str_ends_with($endpoint, '/emails/send')) {
                $url = $endpoint;
            } elseif (str_ends_with($endpoint, '/v1')) {
                $url = $endpoint.'/emails';
            } else {
                $url = $endpoint.'/v1/emails';
            }

            $res = Http::withToken((string) ($this->credentials['api_key'] ?? ''))
                ->acceptJson()->timeout(15)->connectTimeout(3)
                ->post($url, array_filter([
                    'from' => $fromName ? "$fromName <$from>" : $from,
                    'to' => is_array($r->to) ? $r->to : [$r->to],
                    'subject' => $r->subject ?? '(no subject)',
                    'text' => $r->content,
                    'html' => $r->html,
                ]));
        } catch (ConnectionException $e) {
            return new ProviderResult(false, true, destination: $r->to, error: $e->getMessage(), errorCode: 'connection_error');
        }

        if ($res->successful()) {
            return new ProviderResult(
                true,
                messageId: $res->json('messageId') ?? $res->json('id') ?? $res->json('data.id'),
                destination: $r->to,
                httpStatus: $res->status(),
                rawResponse: $res->json() ?? [],
            );
        }

        $retryable = $res->serverError() || in_array($res->status(), [408, 429], true);

        return new ProviderResult(false, $retryable, destination: $r->to, error: $res->body(), errorCode: 'http_'.$res->status(), httpStatus: $res->status());
    }

    public function parseWebhook(Request $request): ?DeliveryUpdate
    {
        $id = $request->input('messageId', $request->input('id'));
        $status = DeliveryUpdate::mapStatus($request->input('event', $request->input('status')));

        return $id && $status ? new DeliveryUpdate((string) $id, $status, $request->input('reason')) : null;
    }
}
