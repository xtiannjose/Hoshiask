<?php

namespace App\Mail;

use App\Models\Ticket;
use App\Support\TicketEmailThread;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

class TicketAlertMail extends Mailable implements ShouldQueue
{
    use Queueable;

    /**
     * Seconds to wait before each retry after a failed send (e.g. transient SMTP outage).
     *
     * @var array<int, int>
     */
    public $backoff = [30, 60, 120];

    public function __construct(
        public Ticket $ticket,
        public string $subjectLine,
        public string $headline,
        public string $messageLine,
        public array $details = [],
        public ?string $actionUrl = null,
        public ?string $actionLabel = null,
        public bool $clientThread = false,
        public bool $clientThreadRoot = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
        );
    }

    /**
     * Client emails about one ticket share a conversation: the first ("received") sets a
     * fixed Message-ID and the rest reference it.
     */
    public function headers(): Headers
    {
        if (! $this->clientThread) {
            return new Headers;
        }

        $rootId = TicketEmailThread::rootMessageId($this->ticket);

        if ($this->clientThreadRoot) {
            return new Headers(messageId: $rootId);
        }

        return new Headers(
            references: [$rootId],
            text: ['In-Reply-To' => '<'.$rootId.'>'],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ticket-alert',
            text: 'emails.ticket-alert-text',
        );
    }
}
