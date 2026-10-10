<?php

namespace App\Services\TicketImport;

use App\Models\Ticket;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TicketImportPersistenceService
{
    public function __construct(
        private readonly ImportedTicketService $importedTickets,
    ) {}

    /**
     * @param  list<array{ticket_number: string|null, attributes: array<string, mixed>, match_key?: string}>  $preparedRows
     * @param  null|callable(array): array<string, Collection<int, Ticket>>  $loadExistingByMatchKey
     * @return array{imported: int, updated: int, skipped: int}
     */
    public function persist(array $preparedRows, bool $updateExisting, ?callable $loadExistingByMatchKey = null): array
    {
        $summary = [
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
        ];
        $existingTicketsByNumber = $this->loadExistingTicketsByNumber($preparedRows);

        $existingTicketsByKey = $updateExisting && $loadExistingByMatchKey
            ? $loadExistingByMatchKey($preparedRows)
            : [];

        DB::transaction(function () use (
            $preparedRows,
            $updateExisting,
            &$summary,
            &$existingTicketsByNumber,
            &$existingTicketsByKey
        ): void {
            foreach ($preparedRows as $preparedRow) {
                $ticketNumber = $preparedRow['ticket_number'];

                if ($ticketNumber !== null) {
                    $existingTicket = $existingTicketsByNumber[$ticketNumber] ?? null;
                    if ($existingTicket instanceof Ticket) {
                        if (! $updateExisting) {
                            $summary['skipped']++;

                            continue;
                        }

                        $existingTicket = $this->updateTicket($existingTicket, $preparedRow['attributes']);
                        $this->registerPersistedTicketLookups($preparedRow, $existingTicket, $existingTicketsByNumber, $existingTicketsByKey);
                        $summary['updated']++;

                        continue;
                    }
                }

                if ($updateExisting && isset($preparedRow['match_key'])) {
                    $matchKey = $preparedRow['match_key'];
                    /** @var Collection<int, Ticket> $matches */
                    $matches = $existingTicketsByKey[$matchKey] ?? collect();
                    /** @var Ticket|null $existingTicket */
                    $existingTicket = $matches->shift();
                    $existingTicketsByKey[$matchKey] = $matches;

                    if ($existingTicket instanceof Ticket) {
                        $existingTicket = $this->updateTicket($existingTicket, $preparedRow['attributes']);
                        $this->registerPersistedTicketLookups($preparedRow, $existingTicket, $existingTicketsByNumber, $existingTicketsByKey);
                        $summary['updated']++;

                        continue;
                    }
                }

                $ticket = $this->createTicket($preparedRow['attributes']);
                $this->registerPersistedTicketLookups($preparedRow, $ticket, $existingTicketsByNumber, $existingTicketsByKey);
                $summary['imported']++;
            }
        });

        return $summary;
    }

    /**
     * @param  list<array{ticket_number: string|null, attributes: array<string, mixed>, match_key?: string}>  $preparedRows
     * @return array<string, Ticket>
     */
    private function loadExistingTicketsByNumber(array $preparedRows): array
    {
        $ticketNumbers = collect($preparedRows)
            ->pluck('ticket_number')
            ->filter(fn (?string $ticketNumber): bool => $ticketNumber !== null && $ticketNumber !== '')
            ->unique()
            ->values();

        if ($ticketNumbers->isEmpty()) {
            return [];
        }

        return Ticket::query()
            ->whereIn('ticket_number', $ticketNumbers->all())
            ->get()
            ->keyBy('ticket_number')
            ->all();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createTicket(array $attributes): Ticket
    {
        $ticket = new Ticket;
        $ticket->timestamps = false;
        $ticket->forceFill($attributes);
        $ticket->save();
        $this->syncImportedAssignmentState($ticket, $attributes);
        $this->importedTickets->syncImportedReviewState($ticket);

        return $ticket;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function updateTicket(Ticket $ticket, array $attributes): Ticket
    {
        $ticket->timestamps = false;
        $ticket->forceFill($attributes);
        $ticket->save();
        $this->syncImportedAssignmentState($ticket, $attributes);
        $this->importedTickets->syncImportedReviewState($ticket);

        return $ticket;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function syncImportedAssignmentState(Ticket $ticket, array $attributes): void
    {
        $assignedUserIds = Ticket::normalizeAssignedUserIds([
            isset($attributes['assigned_to']) ? (int) $attributes['assigned_to'] : 0,
        ]);
        $assignedTo = Ticket::primaryAssignedUserId($assignedUserIds);

        if ((int) ($ticket->assigned_to ?? 0) !== (int) ($assignedTo ?? 0)) {
            $ticket->forceFill([
                'assigned_to' => $assignedTo,
            ])->save();
        }

        $ticket->assignedUsers()->sync($assignedUserIds);
    }

    /**
     * @param  array{ticket_number: string|null, attributes: array<string, mixed>, match_key?: string}  $preparedRow
     * @param  array<string, Ticket>  $existingTicketsByNumber
     * @param  array<string, Collection<int, Ticket>>  $existingTicketsByKey
     */
    private function registerPersistedTicketLookups(
        array $preparedRow,
        Ticket $ticket,
        array &$existingTicketsByNumber,
        array &$existingTicketsByKey
    ): void {
        $ticketNumber = $preparedRow['ticket_number'] ?? null;
        if (is_string($ticketNumber) && $ticketNumber !== '') {
            $existingTicketsByNumber[$ticketNumber] = $ticket;
        }

        if (! isset($preparedRow['match_key'])) {
            return;
        }

        $matchKey = $preparedRow['match_key'];
        $matches = isset($existingTicketsByKey[$matchKey]) ? $existingTicketsByKey[$matchKey]->all() : [];
        $matches[] = $ticket;
        $existingTicketsByKey[$matchKey] = collect($matches)
            ->unique(fn (Ticket $existingTicket) => $existingTicket->id)
            ->values();
    }
}
