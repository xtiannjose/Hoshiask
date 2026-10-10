<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesAdminReturnToRedirects;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Tickets\AssignTicketRequest;
use App\Http\Requests\Admin\Tickets\EscalateTicketRequest;
use App\Http\Requests\Admin\Tickets\QuickUpdateTicketRequest;
use App\Http\Requests\Admin\Tickets\UpdateTicketDatesRequest;
use App\Http\Requests\Admin\Tickets\UpdateTicketDepartmentRequest;
use App\Http\Requests\Admin\Tickets\UpdateTicketSeverityRequest;
use App\Http\Requests\Admin\Tickets\UpdateTicketStatusRequest;
use App\Http\Requests\Admin\Tickets\UpdateTicketTypeRequest;
use App\Models\Ticket;
use App\Models\TicketUserState;
use App\Services\Admin\TicketAssignmentService;
use App\Services\Admin\TicketEscalationService;
use App\Services\Admin\TicketMutationService;
use App\Services\Admin\TicketStatusWorkflowService;
use App\Services\SystemLogService;
use App\Services\TicketAcknowledgmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TicketActionController extends Controller
{
    use HandlesAdminReturnToRedirects;

    public function __construct(
        private TicketAcknowledgmentService $ticketAcknowledgments,
        private SystemLogService $systemLogs,
        private TicketMutationService $ticketMutations,
        private TicketAssignmentService $ticketAssignments,
        private TicketStatusWorkflowService $ticketStatusWorkflow,
        private TicketEscalationService $ticketEscalations,
    ) {}

    public function acknowledge(Request $request, Ticket $ticket)
    {
        $this->authorizeTicketManagement($ticket);

        $actor = auth()->user();
        if (! $this->ticketAcknowledgments->canAcknowledge($actor)) {
            abort(403);
        }

        // Acknowledgment feeds SLA tracking, which does not apply to a ticket the client withdrew.
        if ($ticket->isCancelled()) {
            return $this->redirectBackOrReturnTo($request)->with('error', 'Cancelled tickets do not need to be acknowledged.');
        }

        $existingState = TicketUserState::query()
            ->where('ticket_id', $ticket->id)
            ->where('user_id', (int) auth()->id())
            ->first();

        if (! optional($existingState)->acknowledged_at) {
            $state = $this->ticketAcknowledgments->acknowledge($ticket, $actor);
            $this->systemLogs->record(
                'ticket.acknowledged',
                'Acknowledged a ticket for SLA tracking.',
                [
                    'category' => 'ticket',
                    'target_type' => Ticket::class,
                    'target_id' => $ticket->id,
                    'metadata' => [
                        'ticket_number' => $ticket->ticket_number,
                        'acknowledged_at' => optional($state)->acknowledged_at?->toIso8601String(),
                    ],
                    'request' => $request,
                ]
            );
        }

        return $this->redirectBackOrReturnTo($request)->with('success', 'Ticket acknowledged.');
    }

    public function assign(AssignTicketRequest $request, Ticket $ticket)
    {
        $this->authorizeTicketManagement($ticket);

        if (! $this->ticketAssignments->assignTicket($request, $ticket)) {
            return $this->redirectBackOrReturnTo($request)->with('success', 'No changes were detected.');
        }

        return $this->redirectBackOrReturnTo($request)->with('success', 'Ticket assignment updated successfully!');
    }

    public function escalate(EscalateTicketRequest $request, Ticket $ticket)
    {
        $this->authorizeTicketManagement($ticket);

        $this->ticketEscalations->escalate($request, $ticket);

        return $this->redirectBackOrReturnTo($request)->with('success', 'Ticket escalated.');
    }

    public function clearEscalation(Request $request, Ticket $ticket)
    {
        $this->authorizeTicketManagement($ticket);

        if (! $ticket->isEscalated()) {
            return $this->redirectBackOrReturnTo($request)->with('success', 'No changes were detected.');
        }

        $this->ticketEscalations->clearEscalation($request, $ticket);

        return $this->redirectBackOrReturnTo($request)->with('success', 'Escalation cleared.');
    }

    public function updateStatus(UpdateTicketStatusRequest $request, Ticket $ticket)
    {
        $this->authorizeTicketManagement($ticket);

        $nextStatus = $request->string('status')->toString();
        if ((string) $ticket->status === $nextStatus) {
            return $this->redirectBackOrReturnTo($request)->with('success', 'No changes were detected.');
        }

        $reopenGateError = $this->ticketStatusWorkflow->reopenClosedStatusGateErrorForTicket($ticket, $nextStatus);
        if ($reopenGateError !== null) {
            return $this->redirectBackOrReturnTo($request)->with('error', $reopenGateError);
        }

        $cancelGateError = $this->ticketStatusWorkflow->cancelTicketGateErrorForTicket($ticket, $nextStatus);
        if ($cancelGateError !== null) {
            return $this->redirectBackOrReturnTo($request)->with('error', $cancelGateError);
        }

        try {
            $this->ticketStatusWorkflow->updateTicketStatus($request, $ticket);
        } catch (ValidationException $exception) {
            return $this->redirectBackOrReturnTo($request)->with('error', $exception->getMessage());
        }

        return $this->redirectBackOrReturnTo($request)->with('success', 'Ticket status updated successfully!');
    }

    public function updateSeverity(UpdateTicketSeverityRequest $request, Ticket $ticket)
    {
        $this->authorizeTicketManagement($ticket);

        if ($ticket->priority === $request->severity) {
            return $this->redirectBackOrReturnTo($request)->with('success', 'No changes were detected.');
        }

        $previousSeverity = $ticket->priority;
        $ticket->update(['priority' => $request->severity]);
        $this->ticketStatusWorkflow->trackTicketHandlingAction($ticket);
        $this->systemLogs->record(
            'ticket.severity.updated',
            'Updated ticket severity.',
            [
                'category' => 'ticket',
                'target_type' => Ticket::class,
                'target_id' => $ticket->id,
                'metadata' => [
                    'ticket_number' => $ticket->ticket_number,
                    'previous_severity' => $previousSeverity,
                    'new_severity' => $request->string('severity')->toString(),
                ],
                'request' => $request,
            ]
        );

        return $this->redirectBackOrReturnTo($request)->with('success', 'Ticket severity updated successfully!');
    }

    public function updateType(UpdateTicketTypeRequest $request, Ticket $ticket)
    {
        $this->authorizeTicketManagement($ticket);

        $nextType = $request->string('ticket_type')->toString();
        if ($ticket->ticket_type === $nextType) {
            return $this->redirectBackOrReturnTo($request)->with('success', 'No changes were detected.');
        }

        $previousType = $ticket->ticket_type;
        $ticket->update([
            'ticket_type' => $nextType,
        ]);
        $this->ticketStatusWorkflow->trackTicketHandlingAction($ticket);
        $this->systemLogs->record(
            'ticket.type.updated',
            'Updated ticket type.',
            [
                'category' => 'ticket',
                'target_type' => Ticket::class,
                'target_id' => $ticket->id,
                'metadata' => [
                    'ticket_number' => $ticket->ticket_number,
                    'previous_ticket_type' => $previousType,
                    'new_ticket_type' => $nextType,
                ],
                'request' => $request,
            ]
        );

        return $this->redirectBackOrReturnTo($request)->with('success', 'Ticket type updated successfully!');
    }

    public function updateDepartment(UpdateTicketDepartmentRequest $request, Ticket $ticket)
    {
        $this->authorizeTicketManagement($ticket);

        $nextDepartment = $request->string('department')->toString();
        if ($ticket->department === $nextDepartment) {
            return $this->redirectBackOrReturnTo($request)->with('success', 'No changes were detected.');
        }

        $previousDepartment = $ticket->department;
        $ticket->update(['department' => $nextDepartment]);
        $this->ticketStatusWorkflow->trackTicketHandlingAction($ticket);
        $this->systemLogs->record(
            'ticket.department.updated',
            'Updated ticket department.',
            [
                'category' => 'ticket',
                'target_type' => Ticket::class,
                'target_id' => $ticket->id,
                'metadata' => [
                    'ticket_number' => $ticket->ticket_number,
                    'previous_department' => $previousDepartment,
                    'new_department' => $nextDepartment,
                ],
                'request' => $request,
            ]
        );

        return $this->redirectBackOrReturnTo($request)->with('success', 'Ticket department updated successfully!');
    }

    public function updateDates(UpdateTicketDatesRequest $request, Ticket $ticket)
    {
        $this->authorizeTicketManagement($ticket);

        $loggedAt = Carbon::parse($request->input('created_at'));
        $resolvedAt = $request->filled('resolved_at') ? Carbon::parse($request->input('resolved_at')) : null;
        $closedAt = $request->filled('closed_at') ? Carbon::parse($request->input('closed_at')) : null;

        $unchanged = $this->sameNullableTimestamp($ticket->created_at, $loggedAt)
            && $this->sameNullableTimestamp($ticket->resolved_at, $resolvedAt)
            && $this->sameNullableTimestamp($ticket->closed_at, $closedAt);

        if ($unchanged) {
            return $this->redirectBackOrReturnTo($request)->with('success', 'No changes were detected.');
        }

        $previousValues = [
            'created_at' => optional($ticket->created_at)->toIso8601String(),
            'resolved_at' => optional($ticket->resolved_at)->toIso8601String(),
            'closed_at' => optional($ticket->closed_at)->toIso8601String(),
        ];

        // The form only carries minutes. A date the staff member left alone keeps its stored seconds.
        if ($this->sameNullableTimestamp($ticket->created_at, $loggedAt)) {
            $loggedAt = $ticket->created_at;
        }
        if ($this->sameNullableTimestamp($ticket->resolved_at, $resolvedAt)) {
            $resolvedAt = $ticket->resolved_at;
        }
        if ($this->sameNullableTimestamp($ticket->closed_at, $closedAt)) {
            $closedAt = $ticket->closed_at;
        }

        $ticket->update([
            'resolved_at' => $resolvedAt,
            'closed_at' => $closedAt,
        ]);
        Ticket::query()->whereKey($ticket->id)->update(['created_at' => $loggedAt]);
        $ticket->setAttribute('created_at', $loggedAt);
        $this->ticketStatusWorkflow->trackTicketHandlingAction($ticket);

        $this->systemLogs->record(
            'ticket.dates.updated',
            'Updated ticket logging dates.',
            [
                'category' => 'ticket',
                'target_type' => Ticket::class,
                'target_id' => $ticket->id,
                'metadata' => [
                    'ticket_number' => $ticket->ticket_number,
                    'previous' => $previousValues,
                    'new' => [
                        'created_at' => $loggedAt->toIso8601String(),
                        'resolved_at' => optional($resolvedAt)->toIso8601String(),
                        'closed_at' => optional($closedAt)->toIso8601String(),
                    ],
                ],
                'request' => $request,
            ]
        );

        return $this->redirectBackOrReturnTo($request)->with('success', 'Ticket dates updated successfully!');
    }

    public function quickUpdate(QuickUpdateTicketRequest $request, Ticket $ticket)
    {
        $this->authorizeTicketManagement($ticket);

        $nextStatus = $request->string('status')->toString();

        $reopenGateError = $this->ticketStatusWorkflow->reopenClosedStatusGateErrorForTicket($ticket, $nextStatus);
        if ($reopenGateError !== null) {
            return $this->redirectBackOrReturnTo($request)->with('error', $reopenGateError);
        }

        $cancelGateError = $this->ticketStatusWorkflow->cancelTicketGateErrorForTicket($ticket, $nextStatus);
        if ($cancelGateError !== null) {
            return $this->redirectBackOrReturnTo($request)->with('error', $cancelGateError);
        }

        try {
            if (! $this->ticketStatusWorkflow->quickUpdateTicket($request, $ticket)) {
                return $this->redirectBackOrReturnTo($request)->with('success', 'No changes were detected.');
            }
        } catch (ValidationException $exception) {
            return $this->redirectBackOrReturnTo($request)->with('error', $exception->getMessage());
        }

        return $this->redirectBackOrReturnTo($request)->with('success', 'Ticket updated successfully.');
    }

    public function destroy(Request $request, Ticket $ticket)
    {
        $this->authorizeTicketManagement($ticket);

        if (! $this->canDeleteTickets()) {
            abort(403, 'Only admins can delete tickets.');
        }

        $ticketNumber = $ticket->ticket_number;
        $ticketId = $ticket->id;

        DB::transaction(function () use ($ticket) {
            $this->ticketMutations->deleteTicketWithRelations($ticket);
        });
        $this->systemLogs->record(
            'ticket.deleted',
            'Deleted a ticket.',
            [
                'category' => 'ticket',
                'target_type' => Ticket::class,
                'target_id' => $ticketId,
                'metadata' => [
                    'ticket_number' => $ticketNumber,
                ],
            ]
        );

        $returnPath = $this->returnPathFromRequest($request);
        if ($returnPath !== null) {
            return redirect()->to($returnPath)->with('success', 'Ticket deleted successfully.');
        }

        return redirect()->route('admin.tickets.index')->with('success', 'Ticket deleted successfully.');
    }

    private function canDeleteTickets(): bool
    {
        return auth()->user()?->canDeleteTickets() ?? false;
    }

    /**
     * Compares at minute precision, since the datetime-local form inputs this feeds
     * can't carry seconds — without this, resubmitting an untouched form would be
     * treated as a change and silently truncate the stored timestamp's seconds.
     */
    private function sameNullableTimestamp(?Carbon $a, ?Carbon $b): bool
    {
        if ($a === null || $b === null) {
            return $a === $b;
        }

        return $a->copy()->second(0)->microsecond(0)->equalTo($b->copy()->second(0)->microsecond(0));
    }

    private function authorizeTicketManagement(Ticket $ticket): void
    {
        $this->authorize('manage', $ticket);
    }
}
