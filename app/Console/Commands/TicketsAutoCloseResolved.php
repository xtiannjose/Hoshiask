<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use App\Services\Admin\TicketStatusWorkflowService;
use App\Services\TicketEmailAlertService;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TicketsAutoCloseResolved extends Command
{
    /**
     * If a warning email keeps failing to go out, close the ticket anyway once it is this
     * many days past the normal close date, so a mail problem cannot leave tickets open forever.
     */
    private const WARNING_GRACE_DAYS = 3;

    protected $signature = 'tickets:auto-close-resolved';

    protected $description = 'Warn clients, then auto-close tickets that have sat in Resolved status for 7+ days without the client confirming or reopening them.';

    public function __construct(private TicketStatusWorkflowService $ticketStatusWorkflow)
    {
        parent::__construct();
    }

    public function handle(TicketEmailAlertService $alerts): int
    {
        $closeAfterDays = Ticket::AUTO_CLOSE_RESOLVED_AFTER_DAYS;

        $warned = $this->sendWarnings($alerts);

        $tickets = Ticket::query()
            ->where('status', 'resolved')
            ->where('resolved_at', '<=', now()->subDays($closeAfterDays))
            ->with('user:id,name,email,role,is_active')
            ->get()
            ->filter(fn (Ticket $ticket) => $this->isReadyToClose($ticket, $alerts));

        $closed = 0;
        foreach ($tickets as $ticket) {
            $request = Request::create('/', 'POST', [
                'status' => 'closed',
                'close_reason' => 'Automatically closed after '.$closeAfterDays.' days with no client response following resolution.',
            ]);

            try {
                $closed += (int) $this->ticketStatusWorkflow->updateTicketStatus($request, $ticket);
            } catch (ValidationException) {
                // Reopened (or otherwise changed) since it was selected above: leave it as it now is.
            }
        }

        $this->info(sprintf(
            'Auto-closed %d resolved ticket(s) inactive for %d+ days. Warned %d client(s) of an upcoming close.',
            $closed,
            $closeAfterDays,
            $warned
        ));

        return self::SUCCESS;
    }

    /**
     * One reminder per resolved ticket, a couple of days before the automatic close.
     */
    private function sendWarnings(TicketEmailAlertService $alerts): int
    {
        $warnAfterDays = Ticket::AUTO_CLOSE_RESOLVED_AFTER_DAYS - Ticket::AUTO_CLOSE_WARNING_DAYS_BEFORE;

        $tickets = Ticket::query()
            ->where('status', 'resolved')
            ->where('resolved_at', '<=', now()->subDays($warnAfterDays))
            ->whereNull('client_notified_auto_close_warning_at')
            ->with('user:id,name,email,role,is_active')
            ->get();

        $warned = 0;

        foreach ($tickets as $ticket) {
            $closesAt = $ticket->resolved_at->copy()->addDays(Ticket::AUTO_CLOSE_RESOLVED_AFTER_DAYS);
            $earliestAllowed = now()->addHours(Ticket::AUTO_CLOSE_MIN_HOURS_AFTER_WARNING);

            // A ticket that is already past its close date (first run after this feature
            // shipped, or a long scheduler outage) still gets its full notice period.
            if ($closesAt->lt($earliestAllowed)) {
                $closesAt = $earliestAllowed;
            }

            if ($alerts->notifyClientOfAutoCloseWarning($ticket, $closesAt)) {
                $warned++;
            }
        }

        return $warned;
    }

    /**
     * A client who can be emailed is closed only after the warning has had time to be read.
     */
    private function isReadyToClose(Ticket $ticket, TicketEmailAlertService $alerts): bool
    {
        if (! $alerts->shouldWarnClientBeforeAutoClose($ticket)) {
            return true;
        }

        $warnedAt = $ticket->client_notified_auto_close_warning_at;
        if ($warnedAt !== null && $warnedAt->lte(now()->subHours(Ticket::AUTO_CLOSE_MIN_HOURS_AFTER_WARNING))) {
            return true;
        }

        return $ticket->resolved_at->lte(now()->subDays(Ticket::AUTO_CLOSE_RESOLVED_AFTER_DAYS + self::WARNING_GRACE_DAYS));
    }
}
