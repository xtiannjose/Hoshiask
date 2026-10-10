<?php

namespace App\Services\Admin;

use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use App\Services\SystemLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketEscalationService
{
    public function __construct(
        private SystemLogService $systemLogs,
    ) {}

    public function escalate(Request $request, Ticket $ticket): void
    {
        DB::transaction(function () use ($request, $ticket) {
            $ticket = Ticket::whereKey($ticket->getKey())->lockForUpdate()->firstOrFail();

            $targetUserId = $request->integer('escalated_to');
            $reason = trim($request->string('reason')->toString());
            $actor = auth()->user();

            $ticket->update([
                'escalated_to' => $targetUserId,
                'escalated_by' => $actor?->id,
                'escalated_at' => now(),
                'escalation_reason' => $reason,
                'escalation_resolved_at' => null,
            ]);

            $targetName = User::query()->find($targetUserId)?->publicDisplayName() ?? 'the selected staff member';
            $actorName = $actor->name ?? 'System';

            TicketReply::create([
                'ticket_id' => $ticket->id,
                'user_id' => $actor->id ?? $ticket->assigned_to ?? $ticket->created_by_user_id ?? $ticket->user_id,
                'message' => "Ticket escalated to {$targetName} by {$actorName}.\nReason: {$reason}",
                'is_internal' => true,
            ]);

            $this->systemLogs->record(
                'ticket.escalated',
                'Escalated a ticket.',
                [
                    'category' => 'ticket',
                    'target_type' => Ticket::class,
                    'target_id' => $ticket->id,
                    'metadata' => [
                        'ticket_number' => $ticket->ticket_number,
                        'escalated_to' => $targetUserId,
                        'reason' => $reason,
                    ],
                    'request' => $request,
                ]
            );
        });
    }

    public function clearEscalation(Request $request, Ticket $ticket): void
    {
        DB::transaction(function () use ($request, $ticket) {
            $ticket = Ticket::whereKey($ticket->getKey())->lockForUpdate()->firstOrFail();

            if (! $ticket->isEscalated()) {
                return;
            }

            $ticket->update(['escalation_resolved_at' => now()]);

            $actor = auth()->user();
            $actorName = $actor->name ?? 'System';

            TicketReply::create([
                'ticket_id' => $ticket->id,
                'user_id' => $actor->id ?? $ticket->assigned_to ?? $ticket->created_by_user_id ?? $ticket->user_id,
                'message' => "Escalation cleared by {$actorName}.",
                'is_internal' => true,
            ]);

            $this->systemLogs->record(
                'ticket.escalation.cleared',
                'Cleared a ticket escalation.',
                [
                    'category' => 'ticket',
                    'target_type' => Ticket::class,
                    'target_id' => $ticket->id,
                    'metadata' => [
                        'ticket_number' => $ticket->ticket_number,
                    ],
                    'request' => $request,
                ]
            );
        });
    }
}
