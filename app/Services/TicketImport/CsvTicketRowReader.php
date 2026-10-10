<?php

namespace App\Services\TicketImport;

use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Carbon;
use RuntimeException;
use SplFileObject;
use Traversable;

/**
 * Generic CSV exports: one ticket per row, columns recognised by the names below.
 *
 * @phpstan-import-type ImportSettings from TicketRowReader
 * @phpstan-import-type SourceRow from TicketRowReader
 * @phpstan-import-type PreparedRow from TicketRowReader
 */
class CsvTicketRowReader implements TicketRowReader
{
    /**
     * Accepted column names per field (matched case-insensitively, punctuation ignored).
     *
     * @var array<string, list<string>>
     */
    private const HEADER_ALIASES = [
        'ticket_number' => ['ticket_number', 'ticket number', 'ticket_no', 'ticket no', 'ticket no.', 'ticketnum'],
        'subject' => ['subject', 'title', 'summary'],
        'description' => ['description', 'details', 'body', 'message', 'issue', 'concern'],
        'priority' => ['priority', 'severity'],
        'status' => ['status', 'state'],
        'category_id' => ['category_id'],
        'category' => ['category', 'category_name', 'category name', 'type'],
        'user_id' => ['user_id', 'requester_user_id', 'client_user_id'],
        'user_email' => ['user_email', 'requester_user_email', 'client_user_email', 'account_email'],
        'user_username' => ['user_username', 'requester_username', 'client_username', 'account_username'],
        'assigned_to_id' => ['assigned_to_id', 'assignee_id', 'technician_id'],
        'assigned_to_email' => ['assigned_to_email', 'assignee_email', 'technician_email'],
        'assigned_to_username' => ['assigned_to_username', 'assignee_username', 'technician_username'],
        'name' => ['name', 'requester_name', 'client_name'],
        'contact_number' => ['contact_number', 'contact number', 'contact no', 'contact no.', 'contact', 'phone', 'phone_number', 'mobile'],
        'email' => ['email', 'requester_email', 'client_email'],
        'province' => ['province', 'state_province'],
        'municipality' => ['municipality', 'city', 'municipal_city'],
        'created_at' => ['created_at', 'created', 'original_created_at', 'date_created', 'opened_at', 'reported_at', 'requested_at'],
        'updated_at' => ['updated_at', 'updated', 'modified_at', 'last_updated_at'],
        'assigned_at' => ['assigned_at'],
        'resolved_at' => ['resolved_at', 'date_resolved'],
        'closed_at' => ['closed_at', 'date_closed'],
    ];

    public function __construct(
        private readonly ImportedTicketService $importedTickets,
        private readonly ImportEntityLookupCache $lookupCache,
        private readonly TicketImportReferenceResolver $references,
    ) {}

    public function formatLabel(): string
    {
        return 'CSV';
    }

    public function requiresDefaultUser(): bool
    {
        return false;
    }

    public function existingTicketMatcher(): ?callable
    {
        return null;
    }

    public function rows(string $path, array $settings): Traversable
    {
        $file = new SplFileObject($path, 'r');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::DROP_NEW_LINE);
        $file->setCsvControl($settings['delimiter']);

        $headers = $this->mapHeaders($file->fgetcsv());
        $lineNumber = 1;

        while (! $file->eof()) {
            $lineNumber++;
            $rawRow = $file->fgetcsv();

            if (! is_array($rawRow) || implode('', array_map(fn ($value) => trim((string) $value), $rawRow)) === '') {
                continue;
            }

            $values = [];
            foreach ($headers as $index => $header) {
                $values[$header] = isset($rawRow[$index]) ? trim((string) $rawRow[$index]) : null;
            }

            yield ['row' => $lineNumber, 'file' => basename($path), 'values' => $values];
        }
    }

    public function prepare(array $row, array $settings): array
    {
        $line = $row['row'];
        $values = $row['values'];
        $timezone = $settings['source_timezone'];

        $subject = $this->requiredString($line, $values, 'subject');
        $createdAt = $this->parseDateTime($line, $this->requiredString($line, $values, 'created_at'), 'created_at', $timezone);
        $updatedAt = $this->optionalDateTime($line, $values['updated_at'] ?? null, 'updated_at', $timezone) ?? $createdAt->copy();
        if ($updatedAt->lt($createdAt)) {
            $updatedAt = $createdAt->copy();
        }

        $requester = $this->resolveRequester($line, $values, $settings['default_user']);
        $category = $this->resolveCategory($line, $values, $settings['default_category']);
        $assignedUser = $this->references->resolveOptionalUser(
            $line,
            $values['assigned_to_id'] ?? null,
            $values['assigned_to_email'] ?? null,
            $values['assigned_to_username'] ?? null,
            'assignee'
        );

        $rawPriority = $this->references->nullableString($values['priority'] ?? null) ?? $settings['default_priority'];
        $priority = $this->references->priorityValue($rawPriority)
            ?? throw new RuntimeException(sprintf('Row %d has an unsupported priority: %s', $line, $rawPriority));
        $rawStatus = $this->references->nullableString($values['status'] ?? null) ?? $settings['default_status'];
        $status = $this->references->statusValue($rawStatus)
            ?? throw new RuntimeException(sprintf('Row %d has an unsupported status: %s', $line, $rawStatus));

        $ticketNumber = $this->references->nullableString($values['ticket_number'] ?? null);
        $text = fn (string $field): ?string => $this->references->nullableString($values[$field] ?? null);

        return [
            'ticket_number' => $ticketNumber,
            'attributes' => $this->importedTickets->applyImportMetadata([
                'ticket_number' => $ticketNumber,
                'name' => $text('name') ?? $requester->name ?? 'Unknown Requester',
                'contact_number' => $text('contact_number') ?? $requester->phone ?? 'N/A',
                'email' => $text('email') ?? $requester->email ?? 'unknown@local.invalid',
                'province' => $text('province') ?? 'Unspecified',
                'municipality' => $text('municipality') ?? 'Unspecified',
                'subject' => $subject,
                'description' => $text('description') ?? $subject,
                'priority' => $priority,
                'status' => $status,
                'user_id' => $requester->id,
                'assigned_to' => $assignedUser?->id,
                'assigned_at' => $this->optionalDateTime($line, $values['assigned_at'] ?? null, 'assigned_at', $timezone)
                    ?? ($assignedUser ? $createdAt->copy() : null),
                'category_id' => $category->id,
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
                'resolved_at' => $this->optionalDateTime($line, $values['resolved_at'] ?? null, 'resolved_at', $timezone),
                'closed_at' => $this->optionalDateTime($line, $values['closed_at'] ?? null, 'closed_at', $timezone),
            ], $requester->department),
        ];
    }

    /**
     * @param  array<int, string|null>|false  $rawHeaders
     * @return array<int, string> column index => field
     */
    private function mapHeaders(array|false $rawHeaders): array
    {
        if (! is_array($rawHeaders) || $rawHeaders === [null] || $rawHeaders === []) {
            throw new RuntimeException('Import file is missing a header row.');
        }

        $headers = [];
        foreach ($rawHeaders as $index => $rawHeader) {
            $field = $this->fieldForHeader((string) $rawHeader);
            if ($field === null) {
                continue;
            }

            if (in_array($field, $headers, true)) {
                throw new RuntimeException(sprintf('Duplicate import column detected: %s', $field));
            }

            $headers[$index] = $field;
        }

        if (! in_array('subject', $headers, true)) {
            throw new RuntimeException('Import file must include a subject column.');
        }

        if (! in_array('created_at', $headers, true)) {
            throw new RuntimeException('Import file must include a created_at column so historical ticket dates are preserved.');
        }

        return $headers;
    }

    private function fieldForHeader(string $header): ?string
    {
        $normalize = fn (string $value): string => trim((string) preg_replace('/[^a-z0-9]+/i', '_', strtolower(trim($value))), '_');
        $normalizedHeader = $normalize($header);
        if ($normalizedHeader === '') {
            return null;
        }

        foreach (self::HEADER_ALIASES as $field => $aliases) {
            foreach ($aliases as $alias) {
                if ($normalizedHeader === $normalize($alias)) {
                    return $field;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, string|null>  $values
     */
    private function requiredString(int $line, array $values, string $field): string
    {
        return $this->references->nullableString($values[$field] ?? null)
            ?? throw new RuntimeException(sprintf('Row %d is missing a %s value.', $line, $field));
    }

    private function parseDateTime(int $line, string $value, string $field, string $sourceTimezone): Carbon
    {
        try {
            return Carbon::parse($value, $sourceTimezone)->setTimezone((string) config('app.timezone', 'UTC'));
        } catch (\Throwable $exception) {
            throw new RuntimeException(sprintf('Row %d has an invalid %s value: %s', $line, $field, $value), 0, $exception);
        }
    }

    private function optionalDateTime(int $line, mixed $value, string $field, string $sourceTimezone): ?Carbon
    {
        $normalizedValue = $this->references->nullableString($value);

        return $normalizedValue === null ? null : $this->parseDateTime($line, $normalizedValue, $field, $sourceTimezone);
    }

    /**
     * @param  array<string, string|null>  $values
     */
    private function resolveRequester(int $line, array $values, ?string $defaultReference): User
    {
        $requester = $this->references->resolveOptionalUser(
            $line,
            $values['user_id'] ?? null,
            $values['user_email'] ?? null,
            $values['user_username'] ?? null,
            'requester'
        );

        if ($requester instanceof User) {
            return $requester;
        }

        if ($defaultReference !== null) {
            return $this->references->resolveUserReference($defaultReference, $line, 'requester');
        }

        throw new RuntimeException(sprintf(
            'Row %d could not resolve a requester. Provide user_id, user_email, or user_username columns, or pass --default-user.',
            $line
        ));
    }

    /**
     * @param  array<string, string|null>  $values
     */
    private function resolveCategory(int $line, array $values, string $defaultReference): Category
    {
        $categoryId = $this->references->nullableString($values['category_id'] ?? null);
        if ($categoryId !== null && ($category = $this->lookupCache->categoryById((int) $categoryId))) {
            return $category;
        }

        $categoryName = $this->references->nullableString($values['category'] ?? null) ?? $defaultReference;

        $category = (ctype_digit($categoryName) ? $this->lookupCache->categoryById((int) $categoryName) : null)
            ?? $this->lookupCache->categoryByName($categoryName);

        return $category ?? throw new RuntimeException(sprintf('Row %d references an unknown category: %s', $line, $categoryName));
    }
}
