<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MessageLog;
use App\Models\Subscriber;
use App\Services\SubscriberService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class SubscriberController extends Controller
{
    public function upsert(Request $request, string $externalId, SubscriberService $service)
    {
        $data = $request->validate([
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'telegramChatId' => ['nullable', 'string', 'max:64'],
            'firstName' => ['nullable', 'string', 'max:100'],
            'lastName' => ['nullable', 'string', 'max:100'],
            'locale' => ['nullable', 'string', 'max:10'],
            'timezone' => ['nullable', 'timezone'],
            'data' => ['nullable', 'array'],
        ]);

        $subscriber = $service->upsert([...$data, 'subscriberId' => $externalId]);

        return ApiResponse::ok(self::present($subscriber));
    }

    public function show(string $externalId)
    {
        return ApiResponse::ok(self::present(Subscriber::where('external_id', $externalId)->firstOrFail()));
    }

    /** Erasure: hapus subscriber beserta pesan inbox & preferensi. */
    public function destroy(string $externalId)
    {
        $subscriber = Subscriber::where('external_id', $externalId)->firstOrFail();
        MessageLog::where('subscriber_id', $subscriber->id)->update(['subscriber_id' => null]);
        $subscriber->delete();

        return ApiResponse::ok(['deleted' => true]);
    }

    public static function present(Subscriber $s): array
    {
        return [
            'subscriberId' => $s->external_id,
            'email' => $s->email,
            'phone' => $s->phone,
            'telegramChatId' => $s->telegram_chat_id,
            'firstName' => $s->first_name,
            'lastName' => $s->last_name,
            'locale' => $s->locale,
            'timezone' => $s->timezone,
            'data' => $s->data ?? (object) [],
            'created_at' => $s->created_at->toIso8601String(),
        ];
    }
}
