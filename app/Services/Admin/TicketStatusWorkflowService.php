<?php

namespace App\Services\Admin;

use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\TicketUserState;
use App\Services\SystemLogService;
use App\Services\TicketAcknowledgmentService;
use App\Services\TicketEmailAlertService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TicketStatusWorkflowService
{
    private const CLOSED_REVERT_WINDOW_DAYS = 7;

    public function __construct(
        private TicketAcknowledgmentService $ticketAcknowledgments,
        private TicketAssignmentService $ticketAssignments,
        private SystemLogService $systemLogs,
        private TicketEmailAlertService $emailAlerts,
    ) {}

    public function trackTicketHandlingAction(Ticket $ticket): void
    {
        $this->trackTicketAcknowledgment($ticket);
    }

    public function reopenClosedStatusGateErrorForTicket(Ticket $ticket, ?string $nextStatus): ?string
    {
        if ($ticket->status !== 'closed' || $nextStatus === null || $nextStatus === 'closed') {
            return null;
        }

        if (! $ticket->closed_at) {
            return null;
        }

        $reopenDeadline = $ticket->closed_at->copy()->addDays(self::CLOSED_REVERT_WINDOW_DAYS);
        if (now()->gt($reopenDeadline)) {
            return "Ticket {$ticket->ticket_number} can no longer be reverted because it was closed more than "
                .self::CLOSED_REVERT_WINDOW_DAYS
                .' days ago.';
        }

        return null;
    }

    public function cancelTicketGateErrorForTicket(Ticket $ticket, ?string $nextStatus): ?string
    {
        if ($nextStatus !== 'cancelled' || $ticket->isCancelled()) {
            return null;
        }

        if (! $ticket->isCancellable()) {
            return "Ticket {$ticket->ticket_number} can only be cancelled while it is Open or Pending.";
        }

        return null;
    }

    public function updateTicketStatus(Request $request, Ticket $ticket): bool
    {
        return DB::transaction(function () use ($request, $ticket) {
            $seenStatus = (string) $ticket->status;
            $ticket = Ticket::whereKey($ticket->getKey())->lockForUpdate()->firstOrFail();

            $previousStatus = (string) $ticket->status;
            $nextStatus = $request->string('status')->toString();
            if (! in_array($nextStatus, Ticket::STATUSES, true)) {
                throw ValidationException::withMessages(['status' => 'Choose a valid status.']);
            }
            $previousAssignedIds = $ticket->assigned_user_ids;
            $newAssignedIds = $this->ticketAssignments->determineReviewerAssigneeIds($nextStatus, $previousAssignedIds);
            $newAssignedTo = $this->ticketAssignments->primaryAssigneeId($newAssignedIds);

            if ($previousStatus === $nextStatus) {
                return false;
            }
            $this->ensureStatusUnchangedSince($ticket, $seenStatus);

            $updateData = ['status' => $nextStatus];
            if ($previousAssignedIds !== $newAssignedIds) {
                $updateData['assigned_to'] = $newAssignedTo;
                $updateData = array_merge($updateData, $this->ticketAssignments->assignmentMetadataForChange($previousAssignedIds, $newAssignedIds));
            }
            $this->applyLifecycleTimestamps($ticket, $updateData, $request->string('close_reason')->toString());
            $this->applyRootCauseData($request, $nextStatus, $updateData);

            $ticket->update($updateData);
            if ($previousAssignedIds !== $newAssignedIds) {
                $this->ticketAssignments->syncAssignmentState($ticket, $previousAssignedIds, $newAssignedIds);
            }
            $this->resetReopenedTicketReviewState($ticket, $previousStatus, $nextStatus);
            if ($this->shouldTrackAcknowledgmentAfterStatusChange($previousStatus, $nextStatus)) {
                $this->trackTicketAcknowledgment($ticket);
            }
            $this->recordStatusClosureReason($ticket, $previousStatus, $nextStatus, $request->string('close_reason')->toString());
            $this->notifyClientOfStatusChange($ticket, $previousStatus, $nextStatus);
            $this->systemLogs->record(
                'ticket.status.updated',
                'Updated ticket status.',
                [
                    'category' => 'ticket',
                    'target_type' => Ticket::class,
                    'target_id' => $ticket->id,
                    'metadata' => [
                        'ticket_number' => $ticket->ticket_number,
                        'previous_status' => $previousStatus,
                        'new_status' => $nextStatus,
                    ],
                    'request' => $request,
                ]
            );

            return true;
        });
    }

    public function quickUpdateTicket(Request $request, Ticket $ticket): bool
    {
        return DB::transaction(function () use ($request, $ticket) {
            $seenStatus = (string) $ticket->status;
            $ticket = Ticket::whereKey($ticket->getKey())->lockForUpdate()->firstOrFail();

            $previousStatus = (string) $ticket->status;
            $nextStatus = $request->string('status')->toString();
            $nextPriority = $request->filled('priority')
                ? Ticket::normalizePriorityValue($request->string('priority')->toString())
                : null;
            $nextTicketType = $request->filled('ticket_type')
                ? Ticket::normalizeTicketTypeValue($request->string('ticket_type')->toString())
                : $ticket->ticket_type;
            $previousAssignedIds = $ticket->assigned_user_ids;
            $requestedAssignedIds = $this->ticketAssignments->normalizedAssigneeIdsFromRequest($request);
            $newAssignedIds = $this->ticketAssignments->determineReviewerAssigneeIds($nextStatus, $requestedAssignedIds);
            $newAssignedTo = $this->ticketAssignments->primaryAssigneeId($newAssignedIds);
            $updateData = [
                'assigned_to' => $newAssignedTo,
                'status' => $nextStatus,
                'priority' => $nextPriority,
                'ticket_type' => $nextTicketType,
            ];
            $previousPriority = $ticket->priority;
            $previousTicketType = $ticket->ticket_type;

            if (
                $previousAssignedIds === $newAssignedIds
                && $previousStatus === $nextStatus
                && $previousPriority === $nextPriority
                && $previousTicketType === $nextTicketType
            ) {
                return false;
            }
            $this->ensureStatusUnchangedSince($ticket, $seenStatus);

            $this->applyLifecycleTimestamps($ticket, $updateData, $request->string('close_reason')->toString());
            $updateData = array_merge($updateData, $this->ticketAssignments->assignmentMetadataForChange($previousAssignedIds, $newAssignedIds));
            $this->applyRootCauseData($request, $nextStatus, $updateData);

            $ticket->update($updateData);
            $this->ticketAssignments->syncAssignmentState($ticket, $previousAssignedIds, $newAssignedIds, notifyNewAssignees: true);
            $this->resetReopenedTicketReviewState($ticket, $previousStatus, $nextStatus);
            if ($this->shouldTrackAcknowledgmentAfterStatusChange($previousStatus, $nextStatus)) {
                $this->trackTicketAcknowledgment($ticket);
            }
            $this->recordStatusClosureReason($ticket, $previousStatus, $nextStatus, $request->string('close_reason')->toString());
            $this->notifyClientOfStatusChange($ticket, $previousStatus, $nextStatus);
            $this->systemLogs->record(
                'ticket.quick_update',
                'Applied quick ticket update.',
                [
                    'category' => 'ticket',
                    'target_type' => Ticket::class,
                    'target_id' => $ticket->id,
                    'metadata' => [
                        ...$this->ticketAssignments->assignmentLogMetadata($ticket, $previousAssignedIds, $newAssignedIds),
                        'previous_status' => $previousStatus,
                        'new_status' => $nextStatus,
                        'previous_priority' => $previousPriority,
                        'new_priority' => $nextPriority,
                        'previous_ticket_type' => $previousTicketType,
                        'new_ticket_type' => $nextTicketType,
                    ],
                    'request' => $request,
                ]
            );

            return true;
        });
    }

    /**
     * @param  array<int>  $selectedIds
     */
    public function bulkStatusTickets(Request $request, array $selectedIds): void
    {
        $newStatus = $request->string('status')->toString();
        $closeReason = trim($request->string('close_reason')->toString());

        DB::transaction(function () use ($selectedIds, $newStatus, $closeReason): void {
            Ticket::query()
                ->whereIn('id', $selectedIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->each(function (Ticket $ticket) use ($newStatus, $closeReason): void {
                    $previousStatus = (string) $ticket->status;
                    $previousAssignedIds = $ticket->assigned_user_ids;
                    $newAssignedIds = $this->ticketAssignments->determineReviewerAssigneeIds($newStatus, $previousAssignedIds);
                    $newAssignedTo = $this->ticketAssignments->primaryAssigneeId($newAssignedIds);

                    $updateData = ['status' => $newStatus];
                    if ($previousAssignedIds !== $newAssignedIds) {
                        $updateData['assigned_to'] = $newAssignedTo;
                        $updateData = array_merge($updateData, $this->ticketAssignments->assignmentMetadataForChange($previousAssignedIds, $newAssignedIds));
                    }
                    $this->applyLifecycleTimestamps($ticket, $updateData, $closeReason);

                    if ($previousStatus === $newStatus && $previousAssignedIds === $newAssignedIds) {
                        return;
                    }

                    $ticket->update($updateData);
                    $this->resetReopenedTicketReviewState($ticket, $previousStatus, $newStatus);
                    if ($this->shouldTrackAcknowledgmentAfterStatusChange($previousStatus, $newStatus)) {
                        $this->trackTicketAcknowledgment($ticket);
                    }

                    if ($previousAssignedIds !== $newAssignedIds) {
                        $this->ticketAssignments->syncAssignmentState($ticket, $previousAssignedIds, $newAssignedIds);
                    }

                    $this->recordStatusClosureReason($ticket, $previousStatus, $newStatus, $closeReason);

                    $this->notifyClientOfStatusChange($ticket, $previousStatus, $newStatus);
                });
        });

        $this->systemLogs->record(
            'ticket.bulk.status',
            'Updated ticket statuses in bulk.',
            [
                'category' => 'ticket',
                'metadata' => [
                    'ticket_ids' => $selectedIds,
                    'status' => $newStatus,
                ],
                'request' => $request,
            ]
        );
    }

    /**
     * @param  Collection<int, Ticket>  $tickets
     * @param  array<int>  $selectedIds
     */
    public function bulkPriorityTickets(Request $request, Collection $tickets, array $selectedIds): void
    {
        $priority = Ticket::normalizePriorityValue($request->string('priority')->toString());

        Ticket::whereIn('id', $selectedIds)->update(['priority' => $priority]);
        $tickets->each(fn (Ticket $ticket) => $this->trackTicketAcknowledgment($ticket));

        $this->systemLogs->record(
            'ticket.bulk.priority',
            'Updated ticket priorities in bulk.',
            [
                'category' => 'ticket',
                'metadata' => [
                    'ticket_ids' => $selectedIds,
                    'priority' => $priority,
                ],
                'request' => $request,
            ]
        );
    }

    /**
     * Tell the client only about the milestones that need their attention (resolved,
     * and optionally closed/cancelled). Queued after the transaction commits so a
     * rolled-back change never sends an email, and an email problem never undoes the change.
     */
    private function notifyClientOfStatusChange(Ticket $ticket, string $previousStatus, string $nextStatus): void
    {
        if ($previousStatus === $nextStatus || ! in_array($nextStatus, ['resolved', 'closed', 'cancelled'], true)) {
            return;
        }

        DB::afterCommit(fn () => rescue(fn () => $this->emailAlerts->notifyClientOfStatusChange($ticket)));
    }

    /**
     * The caller chose this change from the status it saw. If someone else changed the status since (a client
     * reopened the ticket, another agent resolved it), that choice no longer holds and the gates it passed may not.
     */
    private function ensureStatusUnchangedSince(Ticket $lockedTicket, string $seenStatus): void
    {
        if ((string) $lockedTicket->status !== $seenStatus) {
            throw ValidationException::withMessages([
                'status' => "Ticket {$lockedTicket->ticket_number} was just changed to {$lockedTicket->status_label} by someone else. Check it and try again.",
            ]);
        }
    }

    private function trackTicketAcknowledgment(Ticket $ticket): void
    {
        $actor = auth()->user();
        $this->ticketAcknowledgments->trackHandlingAction($ticket, $actor);
    }

    private function applyLifecycleTimestamps(?Ticket $ticket, array &$updateData, string $cancellationReason = ''): void
    {
        $status = $updateData['status'] ?? null;

        if (in_array($status, Ticket::OPEN_STATUSES, true)) {
            // Only a reopen starts the lifecycle over. Moving between active statuses (or changing just the
            // severity) must keep the SLA reminder marks, or the same reminders are emailed again.
            if (! in_array($ticket?->status, Ticket::OPEN_STATUSES, true)) {
                $updateData = array_merge($updateData, Ticket::reopenedLifecycleResetAttributes());
            }

            return;
        }

        if ($status === 'resolved' && (! $ticket || ! $ticket->resolved_at)) {
            $updateData['resolved_at'] = now();
        }

        if ($status === 'resolved') {
            $updateData['closed_at'] = null;
            $updateData['closed_by'] = null;
        }

        if ($status === 'closed' && (! $ticket || ! $ticket->closed_at)) {
            $updateData['closed_at'] = now();
            $updateData['closed_by'] = auth()->id();
        }

        if ($status === 'closed' && (! $ticket || ! $ticket->resolved_at)) {
            $updateData['resolved_at'] = now();
        }

        if ($status === 'cancelled' && (! $ticket || ! $ticket->cancelled_at)) {
            $updateData['cancelled_at'] = now();
        }

        if ($status === 'cancelled' && trim($cancellationReason) !== '') {
            $updateData['cancellation_reason'] = trim($cancellationReason);
        }

        // Moving out of cancelled to resolved/closed: the cancellation no longer applies.
        if (in_array($status, ['resolved', 'closed'], true) && $ticket?->cancelled_at) {
            $updateData['cancelled_at'] = null;
            $updateData['cancellation_reason'] = null;
        }
    }

    /**
     * Captures the structured root cause when a ticket is resolved or closed. Optional —
     * left blank, a ticket simply won't appear in root-cause breakdown reports.
     */
    private function applyRootCauseData(Request $request, string $nextStatus, array &$updateData): void
    {
        if (! in_array($nextStatus, ['resolved', 'closed'], true)) {
            return;
        }

        if ($request->filled('root_cause_category')) {
            $updateData['root_cause_category'] = $request->string('root_cause_category')->toString();
        }

        if ($request->filled('root_cause_notes')) {
            $updateData['root_cause_notes'] = $request->string('root_cause_notes')->trim()->toString();
        }
    }

    private function recordStatusClosureReason(Ticket $ticket, string $previousStatus, string $nextStatus, string $closeReason): void
    {
        if (! in_array($nextStatus, ['closed', 'cancelled'], true) || $previousStatus === $nextStatus) {
            return;
        }

        $reason = trim($closeReason);
        if ($reason === '') {
            return;
        }

        $actor = auth()->user();
        $actorName = $actor->name ?? 'System';
        $verb = $nextStatus === 'cancelled' ? 'cancelled' : 'closed';

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => auth()->id() ?? $ticket->assigned_to ?? $ticket->created_by_user_id ?? $ticket->user_id,
            'message' => "Ticket was {$verb} by {$actorName}.\nReason: {$reason}",
            'is_internal' => true,
        ]);
    }

    private function resetReopenedTicketReviewState(Ticket $ticket, string $previousStatus, string $nextStatus): void
    {
        if (! in_array($previousStatus, Ticket::TERMINAL_STATUSES, true)) {
            return;
        }

        if (! in_array($nextStatus, Ticket::OPEN_STATUSES, true)) {
            return;
        }

        TicketUserState::clearAcknowledgmentsForReopenedTicket($ticket);
    }

    private function shouldTrackAcknowledgmentAfterStatusChange(string $previousStatus, string $nextStatus): bool
    {
        return ! (
            in_array($previousStatus, Ticket::TERMINAL_STATUSES, true)
            && in_array($nextStatus, Ticket::OPEN_STATUSES, true)
        );
    }
}
