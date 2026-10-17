<?php

namespace App\Services\TicketImport;

use InvalidArgumentException;
use RuntimeException;

/**
 * Imports tickets from legacy files: check every row first, then save in batches.
 *
 * Every row of every file is checked before anything is written. Invalid rows are
 * collected (not fatal) so one run reports them all; nothing is saved while any row is
 * invalid unless skip_invalid is set. Problems with a whole file stop the run at once.
 *
 * @phpstan-import-type ImportSettings from TicketRowReader
 */
class TicketImporter
{
    private const PERSIST_BATCH_SIZE = 250;

    /** Invalid rows reported one by one; any beyond this are only counted. */
    public const MAX_REPORTED_ERRORS = 50;

    public function __construct(
        private readonly TicketImportPersistenceService $persistence,
        private readonly ImportPathResolver $pathResolver,
        private readonly TicketImportReferenceResolver $references,
        private readonly CsvTicketRowReader $csvReader,
        private readonly TrackerXlsxTicketRowReader $xlsxReader,
    ) {}

    /**
     * @param  list<string>  $paths  CSV and/or tracker XLSX files
     * @param  array<string, mixed>  $options  default_user, default_category, default_priority, default_status,
     *                                         source_timezone, delimiter, dry_run, update_existing, skip_invalid
     * @param  (callable(): void)|null  $onRowChecked  called once per checked row (progress display)
     * @return array{paths: list<string>, formats: list<string>, rows: int, valid: int, invalid: int, errors: list<string>, imported: int, updated: int, skipped: int, dry_run: bool, saved: bool}
     */
    public function import(array $paths, array $options = [], ?callable $onRowChecked = null): array
    {
        if ($paths === []) {
            throw new InvalidArgumentException('At least one import file is required.');
        }

        $settings = $this->normalizeOptions($options);
        $files = [];
        foreach ($paths as $path) {
            $resolvedPath = $this->pathResolver->resolve($path);
            $files[] = ['path' => $resolvedPath, 'reader' => $this->readerFor($resolvedPath)];
        }

        $summary = [
            'paths' => array_column($files, 'path'),
            'formats' => array_values(array_unique(array_map(fn (array $file) => $file['reader']->formatLabel(), $files))),
            'rows' => 0,
            'valid' => 0,
            'invalid' => 0,
            'errors' => [],
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'dry_run' => $settings['dry_run'],
            'saved' => false,
        ];

        // Pass 1: check every row, spooling the prepared rows to a temp file.
        foreach ($files as &$file) {
            /** @var TicketRowReader $reader */
            $reader = $file['reader'];
            if ($reader->requiresDefaultUser() && $settings['default_user'] === null) {
                throw new InvalidArgumentException(sprintf(
                    '%s files need --default-user because they do not include requester accounts.',
                    $reader->formatLabel()
                ));
            }

            $file['spool'] = new PreparedImportRowSpool;
            foreach ($reader->rows($file['path'], $settings) as $row) {
                $summary['rows']++;
                if ($onRowChecked !== null) {
                    $onRowChecked();
                }

                try {
                    $preparedRow = $reader->prepare($row, $settings);
                } catch (RuntimeException $exception) {
                    $summary['invalid']++;
                    if (count($summary['errors']) < self::MAX_REPORTED_ERRORS) {
                        $summary['errors'][] = $exception->getMessage();
                    }

                    continue;
                }

                $file['spool']->append($preparedRow);
                $summary['valid']++;
            }
        }
        unset($file);

        if ($settings['dry_run'] || ($summary['invalid'] > 0 && ! $settings['skip_invalid'])) {
            return $summary;
        }

        // Pass 2: save the valid rows in batches.
        foreach ($files as $file) {
            $batch = [];
            foreach ($file['spool']->rows() as $preparedRow) {
                $batch[] = $preparedRow;
                if (count($batch) >= self::PERSIST_BATCH_SIZE) {
                    $this->persistBatch($batch, $file['reader'], $settings, $summary);
                    $batch = [];
                }
            }

            if ($batch !== []) {
                $this->persistBatch($batch, $file['reader'], $settings, $summary);
            }
        }

        $summary['saved'] = true;

        return $summary;
    }

    /**
     * @param  list<array<string, mixed>>  $batch
     * @param  ImportSettings  $settings
     * @param  array<string, mixed>  $summary
     */
    private function persistBatch(array $batch, TicketRowReader $reader, array $settings, array &$summary): void
    {
        $result = $this->persistence->persist($batch, $settings['update_existing'], $reader->existingTicketMatcher());

        $summary['imported'] += $result['imported'];
        $summary['updated'] += $result['updated'];
        $summary['skipped'] += $result['skipped'];
    }

    private function readerFor(string $path): TicketRowReader
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'csv', 'txt' => $this->csvReader,
            'xlsx' => $this->xlsxReader,
            default => throw new InvalidArgumentException('Unsupported import file type (use .csv or .xlsx): '.basename($path)),
        };
    }

    /**
     * @param  array<string, mixed>  $options
     * @return ImportSettings
     */
    private function normalizeOptions(array $options): array
    {
        $rawPriority = $this->references->nullableString($options['default_priority'] ?? null) ?? 'medium';
        $rawStatus = $this->references->nullableString($options['default_status'] ?? null) ?? 'open';
        $delimiter = (string) ($options['delimiter'] ?? ',');

        if ($delimiter === '') {
            throw new InvalidArgumentException('CSV delimiter cannot be empty.');
        }

        return [
            'default_user' => $this->references->nullableString($options['default_user'] ?? null),
            'default_category' => $this->references->nullableString($options['default_category'] ?? null) ?? 'Other',
            'default_priority' => $this->references->priorityValue($rawPriority)
                ?? throw new InvalidArgumentException('Unsupported default priority: '.$rawPriority),
            'default_status' => $this->references->statusValue($rawStatus)
                ?? throw new InvalidArgumentException('Unsupported default status: '.$rawStatus),
            'source_timezone' => $this->references->nullableString($options['source_timezone'] ?? null)
                ?? (string) config('hoshiask.ticket_import_timezone', config('app.timezone', 'UTC')),
            'delimiter' => mb_substr($delimiter, 0, 1),
            'dry_run' => (bool) ($options['dry_run'] ?? false),
            'update_existing' => (bool) ($options['update_existing'] ?? false),
            'skip_invalid' => (bool) ($options['skip_invalid'] ?? false),
        ];
    }
}
