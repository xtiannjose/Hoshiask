<?php

namespace App\Services\TicketImport;

use App\Models\Ticket;
use Illuminate\Support\Collection;
use Traversable;

/**
 * One import file format. TicketImporter reads rows through it, asks it to turn each
 * row into ticket attributes, and saves the results.
 *
 * Throw from rows() for problems with the whole file (missing column, unreadable file);
 * throw a RuntimeException from prepare() for problems with one row, so the importer
 * can report every bad row in one pass.
 *
 * @phpstan-type ImportSettings array{
 *     default_user: string|null,
 *     default_category: string,
 *     default_priority: string,
 *     default_status: string,
 *     source_timezone: string,
 *     delimiter: string,
 *     dry_run: bool,
 *     update_existing: bool,
 *     skip_invalid: bool
 * }
 * @phpstan-type SourceRow array{row: int, file: string, values: array<string, string|null>}
 * @phpstan-type PreparedRow array{ticket_number: string|null, attributes: array<string, mixed>, match_key?: string}
 */
interface TicketRowReader
{
    /**
     * Short name used in messages, e.g. "CSV".
     */
    public function formatLabel(): string;

    public function requiresDefaultUser(): bool;

    /**
     * @param  ImportSettings  $settings
     * @return Traversable<int, SourceRow>
     */
    public function rows(string $path, array $settings): Traversable;

    /**
     * @param  SourceRow  $row
     * @param  ImportSettings  $settings
     * @return PreparedRow
     */
    public function prepare(array $row, array $settings): array;

    /**
     * For --update-existing: finds existing tickets for rows without a ticket number,
     * keyed by the rows' match_key. Null when the format only matches by ticket number.
     *
     * @return null|callable(list<PreparedRow>): array<string, Collection<int, Ticket>>
     */
    public function existingTicketMatcher(): ?callable;
}
