<?php

namespace App\Services\Providers;

use App\Models\InAppMessage;
use App\Services\InAppPublisher;
use App\Services\Providers\Contracts\ProviderInterface;
use Illuminate\Http\Request;

class InAppProvider implements ProviderInterface
{
    public function __construct(private InAppPublisher $publisher) {}

    public function send(SendRequest $r): ProviderResult
    {
        if (! $r->subscriber) {
            return new ProviderResult(false, false, error: 'Subscriber tidak ditemukan', errorCode: 'invalid_destination');
        }

        $message = InAppMessage::create([
            'environment_id' => $r->environmentId,
            'subscriber_id' => $r->subscriber->id,
            'title' => mb_substr($r->subject ?: 'Notification', 0, 200),
            'body' => $r->content,
            'action_url' => $r->meta['action_url'] ?? null,
            'data' => $r->meta['data'] ?? null,
        ]);

        $this->publisher->publish($r->environmentId, $r->subscriber->id, 'notification:received', $message->toFeed());
        $unread = InAppMessage::where('subscriber_id', $r->subscriber->id)->whereNull('read_at')->whereNull('archived_at')->count();
        $this->publisher->publish($r->environmentId, $r->subscriber->id, 'unread:count', ['unreadCount' => $unread]);

        return new ProviderResult(true, messageId: $message->id, destination: $r->subscriber->external_id, delivered: true);
    }

    public function parseWebhook(Request $request): ?DeliveryUpdate
    {
        return null;
    }
}
