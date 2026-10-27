<?php

namespace App\Jobs;

use App\Mail\Mails;
use App\Models\Broadcast;
use App\Services\SystemNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Mengirim broadcast email ke Owner/Admin workspace, satu email per penerima (tercatat di email_logs). */
class SendBroadcastJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public string $broadcastId)
    {
        $this->onQueue(config('notifora.queues.default'));
    }

    public function handle(SystemNotifier $notifier): void
    {
        $b = Broadcast::find($this->broadcastId);
        if (! $b || $b->status === 'sent') {
            return;
        }

        $delivered = 0;
        foreach (Broadcast::recipientsFor($b->audience) as $email) {
            $notifier->deliver($email, Mails::broadcast($b->subject, $b->body));
            $delivered++;
            if ($delivered % 25 === 0) {
                $b->update(['delivered' => $delivered]);
            }
        }

        $b->update(['delivered' => $delivered, 'status' => 'sent', 'sent_at' => now()]);
    }
}
