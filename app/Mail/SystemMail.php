<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

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

    public function __construct(public string $mailSubject, public array $payload, public string $kind = 'generic')
    {
        $this->onQueue(config('notifora.queues.default'));
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->mailSubject,
            replyTo: [new Address(config('notifora.support_email'), 'Notifora Support')],
            tags: [$this->kind],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.message',
            text: 'emails.message-text',
            with: ['p' => $this->payload + ['tone' => 'info'], 'supportEmail' => config('notifora.support_email')],
        );
    }
}
