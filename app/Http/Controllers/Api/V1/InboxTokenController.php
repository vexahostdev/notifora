<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Subscriber;
use App\Services\InboxTokenService;
use App\Support\ApiResponse;

class InboxTokenController extends Controller
{
    /** Dipanggil dari SERVER klien; token diteruskan ke browser subscriber. */
    public function store(string $externalId, InboxTokenService $tokens)
    {
        $subscriber = Subscriber::where('external_id', $externalId)->firstOrFail();

        return ApiResponse::ok([
            'token' => $tokens->issue(app('current.environment'), $subscriber),
            'expires_in' => (int) config('notifora.socket.token_ttl'),
            'socket_url' => config('notifora.socket.public_url'),
        ]);
    }
}
