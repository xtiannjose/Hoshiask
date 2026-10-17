<?php

namespace App\Services\TicketImport;

use App\Models\Ticket;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;
use Traversable;

/**
 * The old tracker workbook (.xlsx): Date/Time Received, Requestor Details,
 * Project Name, Issue Description, Resolution, Attended By, ...
 *
 * @phpstan-import-type ImportSettings from TicketRowReader
 * @phpstan-import-type SourceRow from TicketRowReader
 * @phpstan-import-type PreparedRow from TicketRowReader
 */
class TrackerXlsxTicketRowReader implements TicketRowReader
{
    public function __construct(
        private readonly HoshiaskTrackerXlsxParser $parser,
        private readonly HoshiaskTrackerDescriptionFormatter $formatter,
        private readonly ImportedTicketService $importedTickets,
        private readonly ImportEntityLookupCache $lookupCache,
        private readonly TicketImportReferenceResolver $references,
    ) {}

    public function formatLabel(): string
    {
        return 'Tracker XLSX';
    }

    public function requiresDefaultUser(): bool
    {
        // Tracker sheets hold requester details as free text, not user accounts.
        return true;
    }

    public function rows(string $path, array $settings): Traversable
    {
        foreach ($this->parser->parse($path) as $row) {
            yield ['row' => (int) $row['source_row'], 'file' => basename($path), 'values' => $row['values']];
        }
    }

    public function prepare(array $row, array $settings): array
    {
        $values = $row['values'];
        $text = fn (string $field): ?string => $this->references->nullableString($values[$field] ?? null);
        $projectName = $text('project_name');
        $issueDescription = $text('issue_description');

        if ($projectName === null && $issueDescription === null) {
            throw new RuntimeException(sprintf('Spreadsheet row %d in %s is missing both project name and issue description.', $row['row'], $row['file']));
        }

        $timezone = $settings['source_timezone'];
        $createdAt = $this->formatter->combineDateAndTime($values['date_received'] ?? null, $values['time_received'] ?? null, $timezone)
            ?? throw new RuntimeException(sprintf('Spreadsheet row %d in %s is missing a usable Date Received value.', $row['row'], $row['file']));
        $completedAt = $this->formatter->combineDateAndTime($values['date_resolved'] ?? null, $values['time_resolved'] ?? null, $timezone);

        $requester = $this->references->resolveUserReference($settings['default_user'], $row['row'], 'requester', 'Spreadsheet row');
        $category = $this->references->resolveCategoryReference($settings['default_category'], $row['row'], 'Spreadsheet row');
        $attendedBy = $this->references->normalizeLookupKey($values['attended_by'] ?? null);
        $assignedUser = $attendedBy !== null ? $this->lookupCache->supportUserByDisplayName($attendedBy) : null;
        $requesterSnapshot = $this->extractRequesterSnapshot($values['requestor_details'] ?? null, $requester->name, $requester->phone, $requester->email);
        $subject = $this->formatter->buildSubject($projectName, $issueDescription, $values['issue_via'] ?? null);
        $ticketNumber = $text('ticket_number');

        return [
            'ticket_number' => $ticketNumber,
            'match_key' => $this->matchKey($subject, $createdAt),
            'attributes' => $this->importedTickets->applyImportMetadata([
                'ticket_number' => $ticketNumber,
                ...$requesterSnapshot,
                'subject' => $subject,
                'description' => $this->formatter->buildDescription(
                    fields: [
                        'date_received' => $values['date_received'] ?? null,
                        'time_received' => $values['time_received'] ?? null,
                        'date_resolved' => $values['date_resolved'] ?? null,
                        'time_resolved' => $values['time_resolved'] ?? null,
                        'issue_via' => $values['issue_via'] ?? null,
                        'requestor_details' => $values['requestor_details'] ?? null,
                        'project_name' => $projectName,
                        'issue_description' => $issueDescription,
                        'resolution' => $values['resolution'] ?? null,
                        'attended_by' => $values['attended_by'] ?? null,
                    ],
                    createdAt: $createdAt,
                    completedAt: $completedAt,
                    displayTimezone: (string) config('app.timezone', $timezone),
                ),
                'priority' => $settings['default_priority'],
                'status' => $completedAt ? 'closed' : $settings['default_status'],
                'user_id' => $requester->id,
                'assigned_to' => $assignedUser?->id,
                'assigned_at' => $assignedUser ? $createdAt->copy() : null,
                'category_id' => $category->id,
                'created_at' => $createdAt,
                'updated_at' => $completedAt ?? $createdAt->copy(),
                'resolved_at' => $completedAt,
                'closed_at' => $completedAt,
            ], $requester->department),
        ];
    }

    public function existingTicketMatcher(): ?callable
    {
        return function (array $preparedRows): array {
            $existingTicketsByKey = [];
            $subjects = [];
            $createdAts = [];

            foreach ($preparedRows as $preparedRow) {
                if ($preparedRow['ticket_number'] !== null || array_key_exists($preparedRow['match_key'], $existingTicketsByKey)) {
                    continue;
                }

                $existingTicketsByKey[$preparedRow['match_key']] = collect();
                $subjects[] = $preparedRow['attributes']['subject'];
                $createdAts[] = $this->utcTimestamp($preparedRow['attributes']['created_at']);
            }

            if ($existingTicketsByKey === []) {
                return [];
            }

            $existingTickets = Ticket::query()
                ->whereIn('subject', array_values(array_unique($subjects)))
                ->whereIn('created_at', array_values(array_unique($createdAts)))
                ->orderBy('id')
                ->get();

            foreach ($existingTickets as $ticket) {
                $matchKey = $this->matchKey((string) $ticket->subject, $ticket->created_at);
                if (array_key_exists($matchKey, $existingTicketsByKey)) {
                    $existingTicketsByKey[$matchKey]->push($ticket);
                }
            }

            /** @var array<string, Collection<int, Ticket>> $existingTicketsByKey */
            return $existingTicketsByKey;
        };
    }

    private function matchKey(string $subject, Carbon $createdAt): string
    {
        return $subject.'|'.$this->utcTimestamp($createdAt);
    }

    private function utcTimestamp(Carbon $timestamp): string
    {
        return $timestamp->copy()->setTimezone('UTC')->format('Y-m-d H:i:s');
    }

    /**
     * Pull "Name: ...", "Contact Number: ...", "Email: ...", "Province: ...", "Municipality: ..."
     * out of the free-text Requestor Details cell, falling back to the default requester.
     *
     * @return array{name: string, contact_number: string, email: string, province: string, municipality: string}
     */
    private function extractRequesterSnapshot(?string $requestorDetails, ?string $fallbackName, ?string $fallbackPhone, ?string $fallbackEmail): array
    {
        $details = $this->references->nullableString($requestorDetails);
        $patterns = [
            'name' => '/^(?:name|requester|requestor)\s*[:\-]\s*(.+)$/i',
            'contact_number' => '/^(?:contact(?:\s+number)?|contact\s+no\.?|phone|mobile)\s*[:\-]\s*(.+)$/i',
            'email' => '/^email\s*[:\-]\s*(.+)$/i',
            'province' => '/^province\s*[:\-]\s*(.+)$/i',
            'municipality' => '/^(?:municipality|city)\s*[:\-]\s*(.+)$/i',
        ];
        $snapshot = array_fill_keys(array_keys($patterns), null);

        foreach ($details !== null ? (preg_split('/[\r\n;]+/', $details) ?: []) : [] as $segment) {
            $segment = trim($segment);
            foreach ($patterns as $field => $pattern) {
                if (preg_match($pattern, $segment, $matches)) {
                    $snapshot[$field] = $this->references->nullableString($matches[1]);

                    break;
                }
            }
        }

        if ($details !== null && $snapshot['email'] === null && preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $details, $matches)) {
            $snapshot['email'] = $matches[0];
        }

        if ($details !== null && $snapshot['contact_number'] === null && preg_match('/(?:\+?\d[\d\s\-]{8,}\d)/', $details, $matches)) {
            $snapshot['contact_number'] = $matches[0];
        }

        return [
            'name' => $snapshot['name'] ?? ($fallbackName ?: 'Unknown Requester'),
            'contact_number' => $snapshot['contact_number'] ?? ($fallbackPhone ?: 'N/A'),
            'email' => $snapshot['email'] ?? ($fallbackEmail ?: 'unknown@local.invalid'),
            'province' => $snapshot['province'] ?? 'Unspecified',
            'municipality' => $snapshot['municipality'] ?? 'Unspecified',
        ];
    }
}
