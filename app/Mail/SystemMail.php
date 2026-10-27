<?php

namespace App\Mail;

use App\Models\EmailLog;
use App\Models\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Email sistem Notifora (pengirim: noreply@, balasan: support@).
 * Satu mailable generik; isi didefinisikan di katalog App\Mail\Mails.
 *
 * $payload: title, paragraphs[], details[label=>value], cta{label,url}, note, tone(info|success|warn|danger), preheader
 */
class SystemMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120, 600];

    /** ID baris email_logs (diisi SystemNotifier) agar status terkirim/gagal tercatat. */
    public ?string $logId = null;

    public function __construct(public string $mailSubject, public array $payload, public string $kind = 'generic')
    {
        $this->onQueue(config('notifora.queues.default'));
    }

    /** Terapkan penyesuaian admin: prefiks subject dan catatan tambahan. */
    public function applyTemplate(EmailTemplate $t): void
    {
        if ($t->subject_prefix) {
            $this->mailSubject = trim($t->subject_prefix.' '.$this->mailSubject);
        }
        if ($t->extra_note) {
            $this->payload['note'] = trim(($this->payload['note'] ?? '')."\n\n".$t->extra_note);
        }
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->mailSubject,
            replyTo: [new Address(config('notifora.support_email'), 'Notifora Support')],
            tags: [$this->kind],
        );
    }

    public function headers(): Headers
    {
        return new Headers(text: $this->logId ? ['X-Notifora-Email-Log' => $this->logId] : []);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.message',
            text: 'emails.message-text',
            with: ['p' => $this->payload + ['tone' => 'info'], 'supportEmail' => config('notifora.support_email')],
        );
    }

    /** Dipanggil queue saat pengiriman gagal permanen. */
    public function failed(Throwable $e): void
    {
        if ($this->logId) {
            EmailLog::whereKey($this->logId)->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500)]);
        }
    }
}
