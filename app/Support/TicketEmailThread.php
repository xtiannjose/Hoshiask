<?php

namespace App\Support;

use App\Models\Ticket;
use Illuminate\Support\Str;

/**
 * Keeps every client-facing email about one ticket in a single conversation in the
 * client's inbox: the "ticket received" email carries a fixed Message-ID and every later
 * email references it and reuses the same subject.
 */
class TicketEmailThread
{
    public static function subject(Ticket $ticket, bool $isRoot): string
    {
        $topic = trim((string) preg_replace('/\s+/u', ' ', (string) preg_replace('/[[:cntrl:]]+/u', ' ', (string) $ticket->subject)));
        $subject = '['.$ticket->ticket_number.']'.($topic !== '' ? ' '.Str::limit($topic, 70, '...') : '');

        return $isRoot ? $subject : 'Re: '.$subject;
    }

    public static function rootMessageId(Ticket $ticket): string
    {
        return 'ticket-'.$ticket->getKey().'@'.self::domain();
    }

    private static function domain(): string
    {
        $fromAddress = (string) config('mail.from.address');
        $domain = str_contains($fromAddress, '@') ? Str::after($fromAddress, '@') : '';

        if ($domain === '' || ! preg_match('/^[A-Za-z0-9]([A-Za-z0-9.-]*[A-Za-z0-9])?$/', $domain)) {
            $domain = (string) parse_url((string) config('app.url'), PHP_URL_HOST);
        }

        return preg_match('/^[A-Za-z0-9]([A-Za-z0-9.-]*[A-Za-z0-9])?$/', $domain) ? $domain : 'hoshiask.invalid';
    }
}
