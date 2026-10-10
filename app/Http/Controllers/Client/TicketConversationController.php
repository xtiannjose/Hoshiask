<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Client\Concerns\AuthorizesClientTickets;
use App\Http\Controllers\Concerns\InteractsWithTicketReplies;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\Tickets\StoreTicketReplyRequest;
use App\Http\Requests\Client\Tickets\UpdateTicketReplyRequest;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\TicketUserState;
use App\Services\SystemLogService;
use App\Support\DuplicateSubmissionGuard;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketConversationController extends Controller
{
    use AuthorizesClientTickets;
    use InteractsWithTicketReplies;

    public function __construct(
        private SystemLogService $systemLogs,
    ) {}

    public function show(Ticket $ticket)
    {
        $this->authorizeOwnedTicket($ticket);

        TicketUserState::markSeenAndDismiss($ticket, (int) auth()->id(), $ticket->updated_at ?? now());

        $this->loadTicketWithVisibleReplies($ticket, includeInternal: false);
        $replyFeedCursor = $this->replyFeedCursorForReplies($ticket->replies);

        return view('client.tickets.show', compact('ticket', 'replyFeedCursor'));
    }

    public function replies(Ticket $ticket): JsonResponse
    {
        $this->authorizeOwnedTicket($ticket);

        return $this->replyFeedResponseForTicket(request(), $ticket, includeInternal: false);
    }

    public function reply(StoreTicketReplyRequest $request, Ticket $ticket)
    {
        $this->authorizeOwnedTicket($ticket);

        if ($errorResponse = $this->blockedTicketReplyResponse($request, $ticket)) {
            return $errorResponse;
        }

        if ($errorResponse = $this->invalidReplyTargetResponse($request, $ticket)) {
            return $errorResponse;
        }

        ['reply' => $reply, 'repeat' => $repeat] = DuplicateSubmissionGuard::onceReply(
            DuplicateSubmissionGuard::fingerprint('client-reply:'.$ticket->id, (int) auth()->id(), $request),
            fn (): TicketReply => $this->withAttachmentWriteGuard(function () use ($request, $ticket) {
                return DB::transaction(function () use ($request, $ticket) {
                    // Read the ticket again under a lock: staff may have closed it since this request loaded it,
                    // and a reply must not reopen a closed ticket behind the 7-day rule.
                    $current = Ticket::whereKey($ticket->getKey())->lockForUpdate()->firstOrFail();
                    if ($blocked = $this->blockedTicketReplyResponse($request, $current)) {
                        throw new HttpResponseException($blocked);
                    }

                    $reply = TicketReply::create([
                        'ticket_id' => $current->id,
                        'user_id' => auth()->id(),
                        'reply_to_id' => $request->integer('reply_to_id') ?: null,
                        'message' => trim($request->string('message')->toString()),
                        'is_internal' => false,
                    ]);

                    $this->persistAttachmentsFromRequest($request, $reply);

                    if (in_array($current->status, Ticket::CLOSED_STATUSES, true)) {
                        $current->update([
                            'status' => 'open',
                            ...Ticket::reopenedLifecycleResetAttributes(),
                        ]);
                        TicketUserState::clearAcknowledgmentsForReopenedTicket($current);
                    }

                    return $reply;
                });
            })
        );

        if (! $repeat) {
            $this->systemLogs->record(
                'ticket.reply.created',
                'Added a reply to a ticket.',
                [
                    'category' => 'ticket',
                    'target_type' => Ticket::class,
                    'target_id' => $ticket->id,
                    'metadata' => [
                        'ticket_number' => $ticket->ticket_number,
                        'reply_id' => $reply->id,
                    ],
                    'request' => $request,
                ]
            );
        }

        if ($request->expectsJson()) {
            $reply->load(['user', 'attachments', 'replyTo.user']);

            return response()->json([
                'message' => 'Reply sent',
                'reply' => $this->formatReplyForChat($reply),
            ]);
        }

        return redirect()->back()->with([
            'chat_success' => 'Reply sent',
            'suppress_success_banner' => true,
        ]);
    }

    public function updateReply(UpdateTicketReplyRequest $request, Ticket $ticket, TicketReply $reply): JsonResponse
    {
        $this->authorizeOwnedTicket($ticket);

        return $this->editReplyMessage($request, $ticket, $reply, enforceEditWindow: true);
    }

    public function deleteReply(Request $request, Ticket $ticket, TicketReply $reply): JsonResponse
    {
        $this->authorizeOwnedTicket($ticket);

        return $this->softDeleteReply($request, $ticket, $reply, enforceEditWindow: true);
    }

    private function blockedTicketReplyResponse(Request $request, Ticket $ticket): JsonResponse|RedirectResponse|null
    {
        if ($ticket->acceptsClientReplies()) {
            return null;
        }

        $message = $ticket->isCancelled()
            ? 'This ticket was cancelled and can no longer accept replies.'
            : 'This ticket is closed and can no longer accept replies.';

        if (! $request->expectsJson()) {
            return redirect()->back()->with('error', $message);
        }

        return response()->json(['message' => $message], 422);
    }

    private function invalidReplyTargetResponse(Request $request, Ticket $ticket): JsonResponse|RedirectResponse|null
    {
        if (! $request->filled('reply_to_id')) {
            return null;
        }

        if ($this->replyTargetExistsForTicket($ticket, $request->integer('reply_to_id'), includeInternal: false)) {
            return null;
        }

        if (! $request->expectsJson()) {
            return redirect()->back()->with('error', 'Invalid reply target.');
        }

        return response()->json([
            'message' => 'Invalid reply target.',
        ], 422);
    }
}
