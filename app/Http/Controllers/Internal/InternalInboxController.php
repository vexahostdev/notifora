<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\Environment;
use App\Models\InAppMessage;
use App\Services\InAppPublisher;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

/** Dipanggil socket-engine (header X-Internal-Secret). */
class InternalInboxController extends Controller
{
    public function unread(Request $request)
    {
        $data = $request->validate(['env' => ['required', 'string'], 'subscriber_id' => ['required', 'string']]);

        return Environment::run($data['env'], fn () => ApiResponse::ok([
            'unreadCount' => $this->count($data['subscriber_id']),
        ]));
    }

    public function read(Request $request, InAppPublisher $publisher)
    {
        $data = $request->validate([
            'env' => ['required', 'string'],
            'subscriber_id' => ['required', 'string'],
            'ids' => ['nullable', 'array', 'max:200'],
            'all' => ['nullable', 'boolean'],
        ]);

        return Environment::run($data['env'], function () use ($data, $publisher) {
            $q = InAppMessage::where('subscriber_id', $data['subscriber_id'])->whereNull('read_at');
            if (empty($data['all'])) {
                $q->whereIn('id', $data['ids'] ?? []);
            }
            $ids = (clone $q)->pluck('id')->all();
            $q->update(['read_at' => now()]);

            $count = $this->count($data['subscriber_id']);
            $publisher->publish($data['env'], $data['subscriber_id'], 'notification:read', ['ids' => $ids]);
            $publisher->publish($data['env'], $data['subscriber_id'], 'unread:count', ['unreadCount' => $count]);

            return ApiResponse::ok(['marked' => count($ids), 'unreadCount' => $count]);
        });
    }

    private function count(string $subscriberId): int
    {
        return InAppMessage::where('subscriber_id', $subscriberId)->whereNull('read_at')->whereNull('archived_at')->count();
    }
}
