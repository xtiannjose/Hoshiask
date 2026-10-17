<?php

namespace App\Console\Commands;

use App\Services\Retention\TicketRetentionService;
use App\Services\SystemLogService;
use Illuminate\Console\Command;

class HoshiaskEnforceRetention extends Command
{
    protected $signature = 'hoshiask:enforce-retention
        {--dry-run : List the tickets that would be cleaned and change nothing}
        {--years= : Use this number of years instead of HOSHIASK_RETENTION_YEARS}';

    protected $description = 'Remove the personal details from tickets that were closed or cancelled longer ago than the retention period.';

    private const LISTED_TICKETS = 50;

    private const LOGGED_TICKETS = 100;

    public function handle(TicketRetentionService $retention, SystemLogService $systemLogs): int
    {
        $years = $this->option('years') !== null ? max(0, (int) $this->option('years')) : $retention->years();
        $eligible = $retention->countEligible($years);

        $this->line("Retention period: {$years} year(s). Tickets past it that still hold personal details: {$eligible}.");

        if ($this->option('dry-run')) {
            $numbers = $retention->eligible($years)->orderBy('id')->limit(self::LISTED_TICKETS)->pluck('ticket_number');
            foreach ($numbers as $number) {
                $this->line("  would clean {$number}");
            }
            if ($eligible > $numbers->count()) {
                $this->line('  ... and '.($eligible - $numbers->count()).' more');
            }
            $this->info('Dry run: nothing was changed.');

            return self::SUCCESS;
        }

        if (! $retention->isEnabled()) {
            $this->warn('The clean-up is switched off (HOSHIASK_RETENTION_ENABLED=false), so nothing was changed.');
            $this->line('Review the list with --dry-run, then set HOSHIASK_RETENTION_ENABLED=true to switch it on.');

            return self::SUCCESS;
        }

        $cleaned = [];
        $failed = [];
        $batchSize = max(1, (int) config('retention.batch_size', 200));

        do {
            $batch = $retention->enforce($years, $batchSize, array_keys($failed));
            array_push($cleaned, ...$batch['cleaned']);
            $failed += $batch['failed'];
        } while ($batch['cleaned'] !== []);

        if ($cleaned !== [] || $failed !== []) {
            $systemLogs->record('retention.enforced', 'Removed personal details from tickets past the retention period.', [
                'category' => 'system',
                'metadata' => [
                    'years' => $years,
                    'tickets_cleaned' => count($cleaned),
                    'ticket_numbers' => array_slice($cleaned, 0, self::LOGGED_TICKETS),
                    'tickets_failed' => count($failed),
                    'failed_ticket_numbers' => array_slice(array_values($failed), 0, self::LOGGED_TICKETS),
                ],
            ]);
        }

        $this->info('Removed the personal details from '.count($cleaned).' ticket(s).');

        if ($failed !== []) {
            $this->error(count($failed).' ticket(s) could not be cleaned and will be tried again on the next run: '.implode(', ', array_slice(array_values($failed), 0, 10)));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
