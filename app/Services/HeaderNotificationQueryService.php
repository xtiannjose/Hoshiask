<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

class HeaderNotificationQueryService
{
    public function ticketsForUser(User $user, int $ticketWindow): Collection
    {
        if (! $user->canAccessAdminTickets()) {
            return Ticket::where('user_id', $user->id)
                ->select(['id', 'user_id', 'subject', 'created_at', 'updated_at'])
                ->with('user:id,name')
                ->latest('updated_at')
                ->limit($ticketWindow)
                ->get();
        }

        $query = Ticket::query()
            ->select(['id', 'user_id', 'subject', 'created_at', 'updated_at', 'assigned_to'])
            ->with('user:id,name')
            ->open()
            ->latest('updated_at');

        if ($user->isTechnician()) {
            Ticket::applyAssignedToConstraint($query, (int) $user->id);
        }

        return $query
            ->limit($ticketWindow)
            ->get();
    }

    public function latestCounterpartRepliesForTickets(User $user, Collection $tickets): Collection
    {
        if ($tickets->isEmpty()) {
            return collect();
        }

        $ticketIds = $tickets
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        $query = TicketReply::query()
            ->select('ticket_replies.*')
            ->with('user:id,name')
            ->join('tickets as notification_tickets', 'notification_tickets.id', '=', 'ticket_replies.ticket_id')
            ->whereIn('ticket_replies.ticket_id', $ticketIds)
            ->where('ticket_replies.is_internal', false);

        $this->applyCounterpartUserScope(
            $query,
            $user,
            'ticket_replies',
            'notification_tickets',
            'reply_users'
        );

        $query->whereNotExists(function ($subquery) use ($user) {
            $subquery->selectRaw('1')
                ->from('ticket_replies as newer_replies')
                ->join('tickets as newer_tickets', 'newer_tickets.id', '=', 'newer_replies.ticket_id')
                ->whereColumn('newer_replies.ticket_id', 'ticket_replies.ticket_id')
                ->where('newer_replies.is_internal', false)
                ->where(function ($comparisonQuery) {
                    $comparisonQuery->whereColumn('newer_replies.created_at', '>', 'ticket_replies.created_at')
                        ->orWhere(function ($tieBreakerQuery) {
                            $tieBreakerQuery->whereColumn('newer_replies.created_at', 'ticket_replies.created_at')
                                ->whereColumn('newer_replies.id', '>', 'ticket_replies.id');
                        });
                });

            $this->applyCounterpartUserScope(
                $subquery,
                $user,
                'newer_replies',
                'newer_tickets',
                'newer_reply_users'
            );
        });

        return $query
            ->orderByDesc('ticket_replies.created_at')
            ->orderByDesc('ticket_replies.id')
            ->get()
            ->keyBy(fn (TicketReply $reply) => (int) $reply->ticket_id);
    }

    private function applyCounterpartUserScope(
        Builder|QueryBuilder $query,
        User $user,
        string $replyAlias,
        string $ticketAlias,
        string $userAlias
    ): void {
        if (! $user->isShadow()) {
            $query->join("users as {$userAlias}", "{$userAlias}.id", '=', "{$replyAlias}.user_id")
                ->where("{$userAlias}.role", '!=', User::ROLE_SHADOW);
        }

        if ($user->canAccessAdminTickets()) {
            $query->whereColumn("{$replyAlias}.user_id", "{$ticketAlias}.user_id");

            return;
        }

        $query->where("{$replyAlias}.user_id", '!=', $user->id);
    }
}
