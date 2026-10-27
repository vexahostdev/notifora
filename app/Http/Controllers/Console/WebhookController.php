<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Jobs\DeliverCustomerWebhookJob;
use App\Models\AuditLog;
use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;
use App\Services\SafeUrl;
use App\Services\WebhookDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WebhookController extends Controller
{
    public function index()
    {
        $subs = WebhookSubscription::latest()->get();
        $deliveries = WebhookDelivery::whereIn('webhook_subscription_id', $subs->pluck('id'))->latest('id')->limit(30)->get();

        return view('console.settings.webhooks', [
            'subs' => $subs,
            'deliveries' => $deliveries,
            'events' => WebhookDispatcher::EVENTS,
            'newSecret' => session('new_secret'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'url' => ['required', 'url', 'max:500'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['in:'.implode(',', WebhookDispatcher::EVENTS)],
        ]);

        $check = SafeUrl::inspect($data['url']);
        if (! $check['ok']) {
            return back()->withInput()->withErrors(['url' => 'URL ditolak: '.$check['reason']]);
        }

        $secret = 'whsec_'.Str::random(40);
        $sub = WebhookSubscription::create(['url' => $data['url'], 'events' => $data['events'], 'secret' => $secret]);
        AuditLog::record(app('current.workspace')->id, 'webhook.created', 'webhook', $sub->id, ['url' => $data['url']]);

        return redirect()->route('console.webhooks.index')->with('new_secret', $secret);
    }

    public function toggle(WebhookSubscription $webhook)
    {
        $active = $webhook->status === 'active';
        $webhook->update(['status' => $active ? 'disabled' : 'active', 'disabled_at' => $active ? now() : null, 'consecutive_failures' => 0]);

        return back()->with('success', 'Status webhook diperbarui.');
    }

    public function destroy(WebhookSubscription $webhook)
    {
        $webhook->delete();

        return back()->with('success', 'Webhook dihapus.');
    }

    public function replay(string $delivery)
    {
        $d = WebhookDelivery::whereIn('webhook_subscription_id', WebhookSubscription::pluck('id'))->findOrFail($delivery);
        $d->update(['status' => 'pending', 'attempt' => 0, 'next_retry_at' => null]);
        DeliverCustomerWebhookJob::dispatch($d->id)->onQueue(config('notifora.queues.webhooks'));

        return back()->with('success', 'Pengiriman ulang dijadwalkan.');
    }
}
