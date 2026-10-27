<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\InAppMessage;
use App\Models\Subscriber;
use App\Services\InAppPublisher;
use App\Support\ApiResponse;
use App\Support\Cursor;
use Illuminate\Http\Request;

class InboxController extends Controller
{
    public function index(Request $request, string $externalId)
    {
        $subscriber = Subscriber::where('external_id', $externalId)->firstOrFail();
        $query = InAppMessage::where('subscriber_id', $subscriber->id)->whereNull('archived_at');
        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }

        [$rows, $next] = Cursor::paginate($query, $request->query('cursor'), (int) $request->query('limit', 50));

        return ApiResponse::ok(
            $rows->map->toFeed()->values(),
            ['next_cursor' => $next, 'unread_count' => self::unread($subscriber)],
        );
    }

    public function read(Request $request, string $externalId, InAppPublisher $publisher)
    {
        $subscriber = Subscriber::where('external_id', $externalId)->firstOrFail();
        $data = $request->validate(['ids' => ['nullable', 'array', 'max:200'], 'ids.*' => ['string'], 'all' => ['nullable', 'boolean']]);

        $query = InAppMessage::where('subscriber_id', $subscriber->id)->whereNull('read_at');
        if (empty($data['all'])) {
            $query->whereIn('id', $data['ids'] ?? []);
        }
        $ids = (clone $query)->pluck('id')->all();
        $query->update(['read_at' => now()]);

        $count = self::unread($subscriber);
        $envId = app('current.environment')->id;
        $publisher->publish($envId, $subscriber->id, 'notification:read', ['ids' => $ids]);
        $publisher->publish($envId, $subscriber->id, 'unread:count', ['unreadCount' => $count]);

        return ApiResponse::ok(['marked' => count($ids), 'unread_count' => $count]);
    }

    public static function unread(Subscriber $s): int
    {
        return InAppMessage::where('subscriber_id', $s->id)->whereNull('read_at')->whereNull('archived_at')->count();
    }
}
