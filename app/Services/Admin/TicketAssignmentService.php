<?php

namespace App\Services\Admin;

use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use App\Services\SystemLogService;
use App\Services\TicketAcknowledgmentService;
use App\Services\TicketEmailAlertService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketAssignmentService
{
    /**
     * @var array<int, string|null>
     */
    private array $assigneeDisplayNameCache = [];

    public function __construct(
        private TicketAcknowledgmentService $ticketAcknowledgments,
        private TicketEmailAlertService $ticketEmailAlerts,
        private SystemLogService $systemLogs,
    ) {}

    public function normalizedAssigneeIdsFromRequest(Request $request): array
    {
        return Ticket::normalizeAssignedUserIds((array) $request->input('assigned_to', []));
    }

    public function assignTicket(Request $request, Ticket $ticket): bool
    {
        return DB::transaction(function () use ($request, $ticket) {
            $ticket = Ticket::whereKey($ticket->getKey())->lockForUpdate()->firstOrFail();

            $previousAssignedIds = $ticket->assigned_user_ids;
            $newAssignedIds = $this->normalizedAssigneeIdsFromRequest($request);
            $newAssignedTo = $this->primaryAssigneeId($newAssignedIds);

            if ($previousAssignedIds === $newAssignedIds) {
                return false;
            }

            $ticket->update([
                'assigned_to' => $newAssignedTo,
                ...$this->assignmentMetadataForChange($previousAssignedIds, $newAssignedIds),
            ]);
            $this->trackTicketAcknowledgment($ticket);
            $this->systemLogs->record(
                'ticket.assignment.updated',
                'Updated ticket assignment.',
                [
                    'category' => 'ticket',
                    'target_type' => Ticket::class,
                    'target_id' => $ticket->id,
                    'metadata' => $this->assignmentLogMetadata($ticket, $previousAssignedIds, $newAssignedIds),
                    'request' => $request,
                ]
            );

            $this->syncAssignmentState($ticket, $previousAssignedIds, $newAssignedIds, notifyNewAssignees: true);

            return true;
        });
    }

    /**
     * @param  array<int>  $selectedIds
     */
    public function bulkAssignTickets(Request $request, array $selectedIds): void
    {
        $newAssignedIds = $this->normalizedAssigneeIdsFromRequest($request);
        $newAssignedTo = $this->primaryAssigneeId($newAssignedIds);

        DB::transaction(function () use ($selectedIds, $newAssignedIds, $newAssignedTo): void {
            Ticket::query()
                ->whereIn('id', $selectedIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->each(function (Ticket $ticket) use ($newAssignedIds, $newAssignedTo): void {
                    $previousAssignedIds = $ticket->assigned_user_ids;
                    if ($previousAssignedIds === $newAssignedIds) {
                        return;
                    }

                    $ticket->update([
                        'assigned_to' => $newAssignedTo,
                        ...$this->assignmentMetadataForChange($previousAssignedIds, $newAssignedIds),
                    ]);
                    $this->trackTicketAcknowledgment($ticket);
                    $this->syncAssignmentState($ticket, $previousAssignedIds, $newAssignedIds, notifyNewAssignees: true);
                });
        });

        $this->systemLogs->record(
            'ticket.bulk.assign',
            'Assigned tickets in bulk.',
            [
                'category' => 'ticket',
                'metadata' => [
                    'ticket_ids' => $selectedIds,
                    'assigned_to' => $newAssignedTo,
                    'assigned_user_ids' => $newAssignedIds,
                ],
                'request' => $request,
            ]
        );
    }

    public function determineReviewerAssigneeIds(string $nextStatus, array $requestedAssignedIds): array
    {
        if ($requestedAssignedIds !== []) {
            return $requestedAssignedIds;
        }

        if (! in_array($nextStatus, Ticket::CLOSED_STATUSES, true)) {
            return [];
        }

        $actor = auth()->user();
        if (! $actor || ! $actor->isAdminLevel() || $actor->isShadow()) {
            return [];
        }

        return [(int) $actor->id];
    }

    public function assignmentMetadataForChange(array $previousAssignedIds, array $newAssignedIds): array
    {
        if ($previousAssignedIds === $newAssignedIds) {
            return [];
        }

        return [
            'assigned_at' => $newAssignedIds !== [] ? now() : null,
            'technical_user_notified_assignment_at' => null,
            'technical_user_notified_sla_at' => null,
        ];
    }

    public function assignmentLogMetadata(Ticket $ticket, array $previousAssignedIds, array $newAssignedIds): array
    {
        return [
            'ticket_number' => $ticket->ticket_number,
            'previous_assigned_to' => $this->primaryAssigneeId($previousAssignedIds),
            'assigned_to' => $this->primaryAssigneeId($newAssignedIds),
            'previous_assigned_user_ids' => $previousAssignedIds,
            'assigned_user_ids' => $newAssignedIds,
        ];
    }

    public function syncAssignmentState(
        Ticket $ticket,
        array $previousAssignedIds,
        array $newAssignedIds,
        bool $notifyNewAssignees = false
    ): void {
        if ($previousAssignedIds === $newAssignedIds) {
            return;
        }

        $ticket->assignedUsers()->sync($newAssignedIds);
        $this->recordAssignmentHandoff($ticket, $previousAssignedIds, $newAssignedIds);

        if ($notifyNewAssignees) {
            $this->notifyNewlyAssignedUsers($ticket, $previousAssignedIds, $newAssignedIds);
        }
    }

    public function primaryAssigneeId(array $assigneeIds): ?int
    {
        return Ticket::primaryAssignedUserId($assigneeIds);
    }

    private function trackTicketAcknowledgment(Ticket $ticket): void
    {
        $actor = auth()->user();
        $this->ticketAcknowledgments->trackHandlingAction($ticket, $actor);
    }

    private function notifyNewlyAssignedUsers(Ticket $ticket, array $previousAssignedIds, array $newAssignedIds): void
    {
        $newlyAssignedIds = array_values(array_diff($newAssignedIds, $previousAssignedIds));
        if ($newlyAssignedIds === []) {
            return;
        }

        $this->ticketEmailAlerts->notifyAssignedTechnicalUsersAboutAssignment(
            $ticket->fresh(['assignedUser', 'assignedUsers']),
            $newlyAssignedIds
        );
    }

    private function recordAssignmentHandoff(Ticket $ticket, array $previousAssignedIds, array $newAssignedIds): void
    {
        if ($previousAssignedIds === $newAssignedIds) {
            return;
        }

        $this->primeAssigneeDisplayNames(array_values(array_unique([
            ...$previousAssignedIds,
            ...$newAssignedIds,
        ])));

        $actor = auth()->user();
        $actorName = $actor->name ?? 'System';
        $previousAssigneeName = $this->assigneeDisplayNames($previousAssignedIds);
        $newAssigneeName = $this->assigneeDisplayNames($newAssignedIds);

        $message = match (true) {
            $previousAssignedIds === [] && $newAssigneeName !== null => "Ticket was assigned to {$newAssigneeName} by {$actorName}.",
            $previousAssigneeName !== null && $newAssigneeName !== null => "Ticket assignment updated by {$actorName}. Previous: {$previousAssigneeName}. New: {$newAssigneeName}.",
            $previousAssigneeName !== null && $newAssignedIds === [] => "Ticket was unassigned from {$previousAssigneeName} by {$actorName}.",
            default => null,
        };

        if (! $message) {
            return;
        }

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => auth()->id() ?? $ticket->assigned_to ?? $ticket->created_by_user_id ?? $ticket->user_id,
            'message' => $message.' Previous conversation remains available for continuity.',
            'is_internal' => true,
        ]);
    }

    private function assigneeDisplayNames(array $userIds): ?string
    {
        if ($userIds === []) {
            return null;
        }

        $this->primeAssigneeDisplayNames($userIds);
        $displayNames = [];

        foreach ($userIds as $userId) {
            $displayName = $this->assigneeDisplayNameCache[$userId] ?? null;
            if ($displayName) {
                $displayNames[] = $displayName;
            }
        }

        $displayNames = array_values(array_unique($displayNames));

        return $displayNames !== [] ? implode(', ', $displayNames) : null;
    }

    private function primeAssigneeDisplayNames(array $userIds): void
    {
        $missingUserIds = array_values(array_filter(
            $userIds,
            fn (int $userId): bool => ! array_key_exists($userId, $this->assigneeDisplayNameCache)
        ));

        if ($missingUserIds === []) {
            return;
        }

        $usersById = User::query()
            ->whereIn('id', $missingUserIds)
            ->get()
            ->keyBy('id');

        foreach ($missingUserIds as $userId) {
            /** @var User|null $user */
            $user = $usersById->get($userId);
            $this->assigneeDisplayNameCache[$userId] = $user && ! $user->isShadow()
                ? $user->publicDisplayName()
                : null;
        }
    }
}
