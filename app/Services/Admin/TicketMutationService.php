<?php

namespace App\Services\Admin;

use App\Models\Attachment;
use App\Models\Ticket;
use App\Models\TicketReply;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TicketMutationService
{
    public function deleteTicketWithRelations(Ticket $ticket): void
    {
        $ticket->attachments()->get()->each->delete();

        /** @var \Illuminate\Database\Eloquent\Collection<int, TicketReply> $ticketReplies */
        $ticketReplies = $ticket->replies()->with('attachments')->get();
        $ticketReplies->each(function (TicketReply $reply): void {
            $reply->attachments->each->delete();
            $reply->delete();
        });

        $ticket->delete();
    }

    /**
     * @param  iterable<int, Ticket>  $tickets
     */
    public function deleteManyTicketsWithRelations(iterable $tickets): void
    {
        foreach ($tickets as $ticket) {
            $this->deleteTicketWithRelations($ticket);
        }
    }

    /**
     * @param  Collection<int, Ticket>  $otherTickets
     */
    public function crossOwnerMergeErrorMessage(Ticket $primary, Collection $otherTickets): ?string
    {
        $mismatched = $otherTickets->first(fn (Ticket $ticket) => $ticket->user_id !== $primary->user_id);

        if (! $mismatched) {
            return null;
        }

        return "Ticket {$mismatched->ticket_number} belongs to a different requester than {$primary->ticket_number} and cannot be merged without confirmation.";
    }

    /**
     * @param  Collection<int, Ticket>  $otherTickets
     */
    public function mergeTickets(Ticket $primary, Collection $otherTickets, ?int $actorId): void
    {
        $ticketIds = [$primary->id, ...$otherTickets->pluck('id')->all()];

        DB::transaction(function () use ($ticketIds, $primary, $otherTickets, $actorId): void {
            $lockedTickets = Ticket::query()
                ->whereIn('id', $ticketIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $primary = $lockedTickets->get($primary->id) ?? $primary;
            /** @var Collection<int, Ticket> $otherTickets */
            $otherTickets = $otherTickets->map(fn (Ticket $ticket) => $lockedTickets->get($ticket->id) ?? $ticket);

            $primary->loadMissing('assignedUsers');
            $primaryAssignedIds = $primary->assigned_user_ids;
            $mergedAssignedIds = $otherTickets
                ->flatMap(fn (Ticket $ticket) => $ticket->assigned_user_ids)
                ->map(fn ($userId) => (int) $userId)
                ->filter(fn (int $userId) => $userId > 0)
                ->unique()
                ->values()
                ->all();
            $combinedAssignedIds = Ticket::normalizeAssignedUserIds([
                ...$primaryAssignedIds,
                ...$mergedAssignedIds,
            ], $primary->assigned_to ? (int) $primary->assigned_to : null);

            if ($combinedAssignedIds !== $primaryAssignedIds) {
                $updateData = [];
                $nextPrimaryAssignee = Ticket::primaryAssignedUserId(
                    $combinedAssignedIds,
                    $primary->assigned_to ? (int) $primary->assigned_to : null
                );

                if ((int) ($primary->assigned_to ?? 0) !== (int) ($nextPrimaryAssignee ?? 0)) {
                    $updateData['assigned_to'] = $nextPrimaryAssignee;
                }

                if ($primary->assigned_at === null && $combinedAssignedIds !== []) {
                    $updateData['assigned_at'] = now();
                }

                if ($updateData !== []) {
                    $primary->update($updateData);
                }

                $primary->assignedUsers()->sync($combinedAssignedIds);
                $primary->refresh();
            }

            foreach ($otherTickets as $ticket) {
                TicketReply::create([
                    'ticket_id' => $primary->id,
                    'user_id' => $actorId,
                    'message' => "Merged ticket {$ticket->ticket_number}: {$ticket->subject}",
                    'is_internal' => true,
                ]);

                TicketReply::where('ticket_id', $ticket->id)->update(['ticket_id' => $primary->id]);
                Attachment::where('attachable_type', Ticket::class)
                    ->where('attachable_id', $ticket->id)
                    ->update(['attachable_id' => $primary->id]);

                $ticket->update([
                    'status' => 'closed',
                    'resolved_at' => $ticket->resolved_at ?? now(),
                    'closed_at' => now(),
                    'closed_by' => $actorId,
                ]);
            }
        });
    }
}
