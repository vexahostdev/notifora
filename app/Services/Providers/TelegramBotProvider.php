<?php

namespace App\Services\Providers;

use App\Services\Providers\Contracts\ProviderInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TelegramBotProvider implements ProviderInterface
{
    public function __construct(private array $credentials) {}

    public function send(SendRequest $r): ProviderResult
    {
        if (! $r->to) {
            return new ProviderResult(false, false, error: 'chat_id kosong', errorCode: 'invalid_destination');
        }

        try {
            $res = Http::acceptJson()->timeout(8)->connectTimeout(3)
                ->post('https://api.telegram.org/bot'.($this->credentials['bot_token'] ?? '').'/sendMessage', [
                    'chat_id' => $r->to,
                    'text' => e($r->content),
                    'parse_mode' => 'HTML',
                ]);
        } catch (ConnectionException $e) {
            return new ProviderResult(false, true, destination: $r->to, error: 'connection error', errorCode: 'connection_error');
        }

        if ($res->successful() && $res->json('ok')) {
            return new ProviderResult(true, messageId: (string) $res->json('result.message_id'), destination: $r->to, httpStatus: $res->status(), rawResponse: $res->json() ?? []);
        }

        $status = $res->status();
        $code = $status === 403 ? 'blocked_by_user' : 'http_'.$status;
        $retryable = $res->serverError() || $status === 429;

        // Jangan simpan body mentah: URL/token tidak ada di body, tapi tetap batasi panjang.
        return new ProviderResult(false, $retryable, destination: $r->to, error: mb_substr($res->body(), 0, 300), errorCode: $code, httpStatus: $status);
    }

    public function parseWebhook(Request $request): ?DeliveryUpdate
    {
        return null; // Telegram Bot API tidak mengirim status delivery.
    }
}
