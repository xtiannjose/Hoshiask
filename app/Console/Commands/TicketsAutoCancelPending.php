<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use App\Services\Admin\TicketStatusWorkflowService;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class TicketsAutoCancelPending extends Command
{
    protected $signature = 'tickets:auto-cancel-pending {--dry-run : List the tickets that would be cancelled and change nothing}';

    protected $description = 'Cancel Pending tickets that have had no status change and no reply for HELPDESK_PENDING_AUTO_CANCEL_DAYS days.';

    public function __construct(private TicketStatusWorkflowService $ticketStatusWorkflow)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $days = max(0, (int) config('helpdesk.pending_auto_cancel_days', 14));
        if ($days < 1) {
            $this->info('Automatic cancelling of Pending tickets is switched off (HELPDESK_PENDING_AUTO_CANCEL_DAYS=0).');

            return self::SUCCESS;
        }

        $cutoff = now()->subDays($days);
        $dryRun = (bool) $this->option('dry-run');
        $cancelled = 0;

        Ticket::applyQuietPendingConstraint(Ticket::query(), $cutoff)
            ->orderBy('id')
            ->chunkById(200, function (Collection $tickets) use ($cutoff, $days, $dryRun, &$cancelled): void {
                foreach ($tickets as $ticket) {
                    if ($dryRun) {
                        $this->line('Would cancel '.$ticket->ticket_number.' ('.$ticket->subject.')');
                        $cancelled++;

                        continue;
                    }

                    // A client may have written back since the batch was read.
                    if (! Ticket::applyQuietPendingConstraint(Ticket::query()->whereKey($ticket->id), $cutoff)->exists()) {
                        continue;
                    }

                    $request = Request::create('/', 'POST', [
                        'status' => 'cancelled',
                        'close_reason' => 'Automatically cancelled after '.$days.' days with no activity while Pending.',
                    ]);

                    try {
                        $cancelled += (int) $this->ticketStatusWorkflow->updateTicketStatus($request, $ticket);
                    } catch (ValidationException) {
                        // Changed by someone else since it was selected: leave it as it now is.
                    }
                }
            });

        $this->info(sprintf(
            '%s %d Pending ticket(s) with no activity for %d+ days.',
            $dryRun ? 'Would cancel' : 'Cancelled',
            $cancelled,
            $days
        ));

        return self::SUCCESS;
    }
}
