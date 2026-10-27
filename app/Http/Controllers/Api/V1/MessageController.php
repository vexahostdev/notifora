<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MessageLog;
use App\Models\Subscriber;
use App\Support\ApiResponse;
use App\Support\Cursor;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function index(Request $request)
    {
        $query = MessageLog::query();
        if ($s = $request->query('status')) {
            $query->where('status', strtoupper($s));
        }
        if ($c = $request->query('channel')) {
            $query->where('channel_type', strtoupper($c));
        }
        if ($sub = $request->query('subscriberId')) {
            $query->where('subscriber_id', Subscriber::where('external_id', $sub)->value('id') ?? '-');
        }

        [$rows, $next] = Cursor::paginate($query, $request->query('cursor'), (int) $request->query('limit', 50));

        return ApiResponse::ok($rows->map(fn ($m) => self::present($m))->values(), ['next_cursor' => $next]);
    }

    public function show(string $id)
    {
        $m = MessageLog::with('attemptsLog')->findOrFail($id);

        return ApiResponse::ok([
            ...self::present($m),
            'attempts' => $m->attemptsLog->map(fn ($a) => [
                'attempt' => $a->attempt, 'success' => $a->success, 'http_status' => $a->http_status,
                'duration_ms' => $a->duration_ms, 'at' => $a->created_at->toIso8601String(),
            ]),
        ]);
    }

    public static function present(MessageLog $m): array
    {
        return [
            'id' => $m->id,
            'channel' => $m->channel_type,
            'destination' => $m->destination_masked,
            'status' => $m->status->value,
            'external_message_id' => $m->external_message_id,
            'error_code' => $m->error_code,
            'attempts' => $m->attempts,
            'sent_at' => $m->sent_at?->toIso8601String(),
            'delivered_at' => $m->delivered_at?->toIso8601String(),
            'created_at' => $m->created_at->toIso8601String(),
        ];
    }
}
