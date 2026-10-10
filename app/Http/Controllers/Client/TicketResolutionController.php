<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Client\Concerns\AuthorizesClientTickets;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\Tickets\CancelTicketRequest;
use App\Http\Requests\Client\Tickets\RateTicketRequest;
use App\Http\Requests\Client\Tickets\ReopenTicketRequest;
use App\Http\Requests\Client\Tickets\ResolveTicketRequest;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\TicketUserState;
use App\Services\Admin\TicketStatusWorkflowService;
use App\Services\SystemLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketResolutionController extends Controller
{
    use AuthorizesClientTickets;

    public function __construct(
        private SystemLogService $systemLogs,
        private TicketStatusWorkflowService $ticketStatusWorkflow,
    ) {}

    public function resolve(ResolveTicketRequest $request, Ticket $ticket)
    {
        $this->authorizeOwnedTicket($ticket);

        return DB::transaction(function () use ($request, $ticket) {
            $ticket = $this->lockedTicket($ticket);

            if ($ticket->status === 'closed') {
                return redirect()->back()->with('error', 'Closed tickets cannot be marked as resolved.');
            }

            // The page hides the button, but a stale tab or a hand-made request would still get here.
            if ($ticket->isCancelled()) {
                return redirect()->back()->with('error', "This ticket was cancelled and can't be marked as resolved.");
            }

            if ($ticket->status !== 'resolved') {
                $previousStatus = $ticket->status;
                TicketReply::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => auth()->id(),
                    'message' => 'Client marked this ticket as resolved.',
                    'is_internal' => false,
                ]);

                $ticket->update([
                    'status' => 'resolved',
                    'resolved_at' => now(),
                    'closed_at' => null,
                    'closed_by' => null,
                    'satisfaction_rating' => $request->integer('rating'),
                    'satisfaction_comment' => $this->satisfactionComment($request),
                ]);
                $this->systemLogs->record(
                    'ticket.resolved_by_client',
                    'Client marked a ticket as resolved.',
                    [
                        'category' => 'ticket',
                        'target_type' => Ticket::class,
                        'target_id' => $ticket->id,
                        'metadata' => [
                            'ticket_number' => $ticket->ticket_number,
                            'previous_status' => $previousStatus,
                            'new_status' => 'resolved',
                            'rating' => $request->integer('rating'),
                        ],
                        'request' => request(),
                    ]
                );
                $this->recordSatisfactionLog(
                    $ticket,
                    $request->integer('rating'),
                    $request->string('comment')->trim()->toString(),
                    $request
                );
            }

            return redirect()->back()->with('success', 'Ticket marked as resolved and your rating has been submitted.');
        });
    }

    public function cancel(CancelTicketRequest $request, Ticket $ticket)
    {
        $this->authorizeOwnedTicket($ticket);

        return DB::transaction(function () use ($request, $ticket) {
            $ticket = $this->lockedTicket($ticket);

            if (! $ticket->isCancellable()) {
                return redirect()->back()->with('error', 'This ticket can only be cancelled while it is Open or Pending.');
            }

            $previousStatus = $ticket->status;
            $reason = $request->string('cancel_reason')->trim()->toString();

            $ticket->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            TicketReply::create([
                'ticket_id' => $ticket->id,
                'user_id' => auth()->id(),
                'message' => "Client cancelled this ticket.\nReason: {$reason}",
                'is_internal' => false,
            ]);

            $this->systemLogs->record(
                'ticket.cancelled_by_client',
                'Client cancelled a ticket.',
                [
                    'category' => 'ticket',
                    'target_type' => Ticket::class,
                    'target_id' => $ticket->id,
                    'metadata' => [
                        'ticket_number' => $ticket->ticket_number,
                        'previous_status' => $previousStatus,
                        'reason' => $reason,
                    ],
                    'request' => $request,
                ]
            );

            return redirect()->back()->with('success', 'Ticket cancelled.');
        });
    }

    public function reopen(ReopenTicketRequest $request, Ticket $ticket)
    {
        $this->authorizeOwnedTicket($ticket);

        return DB::transaction(function () use ($request, $ticket) {
            $ticket = $this->lockedTicket($ticket);

            if (! in_array($ticket->status, Ticket::CLOSED_STATUSES, true)) {
                return redirect()->back()->with('error', 'Only resolved or closed tickets can be reopened.');
            }

            if ($gateError = $this->ticketStatusWorkflow->reopenClosedStatusGateErrorForTicket($ticket, 'open')) {
                return redirect()->back()->with('error', $gateError);
            }

            $previousStatus = $ticket->status;
            $reason = $request->string('reopen_reason')->trim()->toString();

            $ticket->update([
                'status' => 'open',
                ...Ticket::reopenedLifecycleResetAttributes(),
            ]);
            TicketUserState::clearAcknowledgmentsForReopenedTicket($ticket);

            TicketReply::create([
                'ticket_id' => $ticket->id,
                'user_id' => auth()->id(),
                'message' => "Client reopened this ticket.\nReason: {$reason}",
                'is_internal' => false,
            ]);

            $this->systemLogs->record(
                'ticket.reopened_by_client',
                'Client reopened a ticket.',
                [
                    'category' => 'ticket',
                    'target_type' => Ticket::class,
                    'target_id' => $ticket->id,
                    'metadata' => [
                        'ticket_number' => $ticket->ticket_number,
                        'previous_status' => $previousStatus,
                        'reason' => $reason,
                    ],
                    'request' => $request,
                ]
            );

            return redirect()->back()->with('success', 'Ticket reopened.');
        });
    }

    public function rate(RateTicketRequest $request, Ticket $ticket)
    {
        $this->authorizeOwnedTicket($ticket);

        return DB::transaction(function () use ($request, $ticket) {
            $ticket = $this->lockedTicket($ticket);

            if ($ticket->status !== 'resolved' || $ticket->satisfaction_rating !== null) {
                return redirect()->back()->with('error', 'Only resolved tickets awaiting feedback can be rated.');
            }

            $ticket->update([
                'satisfaction_rating' => $request->integer('rating'),
                'satisfaction_comment' => $this->satisfactionComment($request),
            ]);
            $this->recordSatisfactionLog(
                $ticket,
                $request->integer('rating'),
                $request->string('comment')->trim()->toString(),
                $request
            );

            return redirect()->back()->with('success', 'Rating submitted successfully!');
        });
    }

    /**
     * Each action checks and changes the ticket on a locked row, so a staff change saved a moment
     * earlier is seen instead of overwritten, and a double-submitted form acts once.
     */
    private function lockedTicket(Ticket $ticket): Ticket
    {
        return Ticket::whereKey($ticket->getKey())->lockForUpdate()->firstOrFail();
    }

    private function satisfactionComment(Request $request): ?string
    {
        $comment = $request->string('comment')->trim()->toString();

        return $comment === '' ? null : $comment;
    }

    private function recordSatisfactionLog(Ticket $ticket, int $rating, string $comment, Request $request): void
    {
        $this->systemLogs->record(
            'ticket.rating.submitted',
            'Submitted ticket satisfaction rating.',
            [
                'category' => 'ticket',
                'target_type' => Ticket::class,
                'target_id' => $ticket->id,
                'metadata' => [
                    'ticket_number' => $ticket->ticket_number,
                    'rating' => $rating,
                    'has_comment' => $comment !== '',
                ],
                'request' => $request,
            ]
        );
    }
}
