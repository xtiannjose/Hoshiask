<?php

namespace App\Console\Commands;

use App\Services\SystemLogService;
use App\Services\TicketImport\TicketImporter;
use Illuminate\Console\Command;

class ImportTickets extends Command
{
    protected $signature = 'tickets:import
        {paths* : CSV or tracker .xlsx files. Relative paths resolve from storage/app/private/imports.}
        {--default-user= : Requester (user id, email or username) for rows without one. Required for .xlsx files}
        {--default-category=Other : Category id or name for rows without one}
        {--default-priority=medium : Severity for rows without one}
        {--default-status=open : Status for rows without one (.xlsx rows with a resolved date are closed)}
        {--source-timezone= : Timezone of source dates that have no offset}
        {--delimiter=, : CSV column delimiter}
        {--dry-run : Check every row and list the problems without saving anything}
        {--skip-invalid : Save the valid rows even when some rows have problems}
        {--update-existing : Update tickets that already exist (by ticket number; .xlsx rows without one by subject + date received)}';

    /**
     * The former per-format commands keep working.
     *
     * @var list<string>
     */
    protected $aliases = ['tickets:import-csv', 'tickets:import-xlsx'];

    protected $description = 'Import tickets from CSV or tracker XLSX files, keeping their original dates.';

    public function handle(TicketImporter $importer, SystemLogService $systemLogs): int
    {
        $progress = $this->output->isDecorated() ? $this->output->createProgressBar() : null;

        try {
            $summary = $importer->import(array_values((array) $this->argument('paths')), [
                'default_user' => $this->option('default-user'),
                'default_category' => $this->option('default-category'),
                'default_priority' => $this->option('default-priority'),
                'default_status' => $this->option('default-status'),
                'source_timezone' => $this->option('source-timezone'),
                'delimiter' => $this->option('delimiter'),
                'dry_run' => (bool) $this->option('dry-run'),
                'update_existing' => (bool) $this->option('update-existing'),
                'skip_invalid' => (bool) $this->option('skip-invalid'),
            ], $progress !== null ? fn () => $progress->advance() : null);
        } catch (\Throwable $exception) {
            $progress?->clear();
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($progress !== null) {
            $progress->finish();
            $this->newLine();
        }

        foreach ($summary['paths'] as $path) {
            $this->line('Source: '.$path);
        }

        foreach ($summary['errors'] as $error) {
            $this->error($error);
        }

        $unlistedErrors = $summary['invalid'] - count($summary['errors']);
        if ($unlistedErrors > 0) {
            $this->error(sprintf('... and %d more invalid rows.', $unlistedErrors));
        }

        if ($summary['dry_run']) {
            $this->info(sprintf('Dry run complete. rows=%d valid=%d invalid=%d', $summary['rows'], $summary['valid'], $summary['invalid']));

            return $summary['invalid'] > 0 ? self::FAILURE : self::SUCCESS;
        }

        if (! $summary['saved']) {
            $this->error(sprintf(
                'Found %d invalid row(s); nothing was imported. Fix them, or re-run with --skip-invalid to import the valid rows.',
                $summary['invalid']
            ));

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Import complete. created=%d updated=%d skipped=%d rows=%d',
            $summary['imported'],
            $summary['updated'],
            $summary['skipped'],
            $summary['rows'],
        ).($summary['invalid'] > 0 ? sprintf(' invalid=%d', $summary['invalid']) : ''));

        $systemLogs->record('ticket.imported_batch', 'Imported tickets from '.implode(' and ', $summary['formats']).' files.', [
            'category' => 'ticket',
            'metadata' => [
                'paths' => $summary['paths'],
                'formats' => $summary['formats'],
                'rows' => $summary['rows'],
                'imported' => $summary['imported'],
                'updated' => $summary['updated'],
                'skipped' => $summary['skipped'],
                'invalid' => $summary['invalid'],
            ],
        ]);

        return self::SUCCESS;
    }
}
