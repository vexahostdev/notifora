<?php

namespace App\Listeners;

use App\Models\EmailLog;
use Illuminate\Mail\Events\MessageSent;

/** Menandai baris email_logs sebagai terkirim berdasarkan header X-Notifora-Email-Log. */
class LogEmailSent
{
    public function onSent(MessageSent $event): void
    {
        $header = $event->message->getHeaders()->get('X-Notifora-Email-Log');
        if ($header) {
            EmailLog::whereKey($header->getBodyAsString())->update(['status' => 'sent', 'sent_at' => now(), 'error' => null]);
        }
    }
}
