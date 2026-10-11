<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * One email to a staff member that lists their Pending tickets with no activity for a few days. The client is not
 * emailed; staff decide whether to follow up, resolve or cancel.
 */
class PendingFollowUpMail extends Mailable implements ShouldQueue
{
    use Queueable;

    /**
     * Seconds to wait before each retry after a failed send (for example a short SMTP outage).
     *
     * @var array<int, int>
     */
    public $backoff = [30, 60, 120];

    /**
     * @param  list<array{ticket_number: string, subject: string, waiting: string, url: string}>  $tickets  The tickets shown.
     * @param  int  $total  How many tickets are waiting, which can be more than are listed.
     */
    public function __construct(
        public string $recipientName,
        public array $tickets,
        public int $total,
        public int $days,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->total === 1
                ? 'Follow up: 1 pending ticket has had no activity for '.$this->days.' days'
                : 'Follow up: '.$this->total.' pending tickets have had no activity for '.$this->days.' days',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.pending-follow-up',
            text: 'emails.pending-follow-up-text',
        );
    }
}
