<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use App\Services\Retention\TicketRetentionService;
use App\Services\SystemLogService;
use Illuminate\Console\Command;

class HoshiaskRetentionHold extends Command
{
    protected $signature = 'hoshiask:retention-hold
        {ticket_number : The ticket number, for example TK-ABCD1234}
        {--release : Lift the hold instead of setting it}
        {--reason= : Why the hold is set or lifted (kept in the audit log)}';

    protected $description = 'Put a ticket on legal hold so the retention clean-up never removes its details, or lift the hold.';

    public function handle(TicketRetentionService $retention, SystemLogService $systemLogs): int
    {
        $number = strtoupper(trim((string) $this->argument('ticket_number')));
        $ticket = Ticket::query()->whereRaw('UPPER(ticket_number) = ?', [$number])->first();

        if ($ticket === null) {
            $this->error("No ticket has the number {$number}.");

            return self::FAILURE;
        }

        $release = (bool) $this->option('release');

        if (! $release && $ticket->anonymized_at !== null) {
            $this->error("{$ticket->ticket_number} was already cleaned on {$ticket->anonymized_at->toDateString()}. A hold cannot bring the removed details back.");

            return self::FAILURE;
        }

        $retention->setHold($ticket, ! $release);

        $systemLogs->record($release ? 'retention.hold_released' : 'retention.hold_set', $release ? 'Lifted a legal hold on a ticket.' : 'Put a ticket on legal hold.', [
            'category' => 'system',
            'target_type' => Ticket::class,
            'target_id' => $ticket->id,
            'metadata' => [
                'ticket_number' => $ticket->ticket_number,
                'reason' => trim((string) $this->option('reason')) ?: null,
            ],
        ]);

        $this->info($release
            ? "{$ticket->ticket_number} is no longer on legal hold."
            : "{$ticket->ticket_number} is on legal hold and will not be cleaned up until you release it.");

        return self::SUCCESS;
    }
}
