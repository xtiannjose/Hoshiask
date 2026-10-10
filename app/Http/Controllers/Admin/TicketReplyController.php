<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesAdminReturnToRedirects;
use App\Http\Controllers\Concerns\InteractsWithTicketReplies;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Tickets\StoreTicketReplyRequest;
use App\Http\Requests\Admin\Tickets\UpdateTicketReplyRequest;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Services\Admin\TicketStatusWorkflowService;
use App\Services\SystemLogService;
use App\Services\TicketEmailAlertService;
use App\Support\DuplicateSubmissionGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TicketReplyController extends Controller
{
    use HandlesAdminReturnToRedirects;
    use InteractsWithTicketReplies;

    public function __construct(
        private TicketStatusWorkflowService $ticketStatusWorkflow,
        private TicketEmailAlertService $emailAlerts,
        private SystemLogService $systemLogs,
    ) {}

    public function replies(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorizeTicketAccess($ticket);

        return $this->replyFeedResponseForTicket($request, $ticket);
    }

    public function reply(StoreTicketReplyRequest $request, Ticket $ticket)
    {
        $this->authorizeTicketManagement($ticket);

        $replyToId = $request->integer('reply_to_id') ?: null;

        if (! $this->replyTargetExistsForTicket($ticket, $replyToId)) {
            return $this->redirectBackOrReturnTo($request)->with('error', 'Invalid reply target.');
        }

        $replyTarget = null;
        if ($replyToId) {
            $replyTarget = $this->visibleRepliesQueryForTicket($ticket)
                ->select(['id', 'is_internal'])
                ->whereKey($replyToId)
                ->first();
        }

        // On a closed or cancelled ticket staff can still add notes, but only internal ones: the client
        // can no longer reply, so nothing new should appear in (or be emailed to) their conversation.
        $isInternal = ! $ticket->acceptsClientReplies()
            || $request->boolean('is_internal')
            || (bool) optional($replyTarget)->is_internal;

        ['reply' => $reply, 'repeat' => $repeat] = DuplicateSubmissionGuard::onceReply(
            DuplicateSubmissionGuard::fingerprint('staff-reply:'.$ticket->id, (int) auth()->id(), $request),
            fn (): TicketReply => $this->createStaffReply($request, $ticket, $replyToId, $isInternal)
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
                        'is_internal' => $isInternal,
                    ],
                    'request' => $request,
                ]
            );

            $this->emailAlerts->notifyClientOfAgentReply($ticket, $reply);
        }

        if ($request->expectsJson()) {
            $reply->loadMissing(['user', 'attachments', 'replyTo.user']);

            return response()->json([
                'message' => 'Reply added successfully.',
                'reply' => $this->formatReplyForChat($reply),
            ]);
        }

        return $this->redirectBackOrReturnTo($request)->with('success', 'Reply added successfully!');
    }

    private function createStaffReply(StoreTicketReplyRequest $request, Ticket $ticket, ?int $replyToId, bool $isInternal): TicketReply
    {
        return $this->withAttachmentWriteGuard(function () use ($request, $ticket, $replyToId, $isInternal) {
            return DB::transaction(function () use ($request, $ticket, $replyToId, $isInternal) {
                $reply = TicketReply::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => auth()->id(),
                    'reply_to_id' => $replyToId,
                    'message' => trim($request->string('message')->toString()),
                    'is_internal' => $isInternal,
                ]);

                $this->persistAttachmentsFromRequest($request, $reply);

                // A public reply on an Open ticket means work has started. Going through the workflow locks and
                // logs it like any staff status change; a ticket someone changed meanwhile keeps their change.
                if ($ticket->status === 'open' && ! $isInternal) {
                    try {
                        // A copy of the request that carries the new status. It must be read as form data: for a JSON
                        // request the copy would still read the original JSON body and find no status.
                        $statusRequest = $request->duplicate([], ['status' => 'in_progress']);
                        $statusRequest->headers->set('Content-Type', 'application/x-www-form-urlencoded');
                        $this->ticketStatusWorkflow->updateTicketStatus($statusRequest, $ticket);
                    } catch (ValidationException) {
                        // Changed meanwhile, for example cancelled: leave it as it now is.
                    }
                    $ticket->refresh();
                }
                $this->ticketStatusWorkflow->trackTicketHandlingAction($ticket);

                return $reply;
            });
        });
    }

    public function updateReply(UpdateTicketReplyRequest $request, Ticket $ticket, TicketReply $reply): JsonResponse
    {
        $this->authorizeTicketManagement($ticket);

        return $this->editReplyMessage($request, $ticket, $reply, enforceEditWindow: false);
    }

    public function deleteReply(Request $request, Ticket $ticket, TicketReply $reply): JsonResponse
    {
        $this->authorizeTicketManagement($ticket);

        return $this->softDeleteReply($request, $ticket, $reply, enforceEditWindow: false);
    }

    private function authorizeTicketAccess(Ticket $ticket): void
    {
        $this->authorize('view', $ticket);
    }

    private function authorizeTicketManagement(Ticket $ticket): void
    {
        $this->authorize('manage', $ticket);
    }
}
