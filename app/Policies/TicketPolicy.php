<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    public function view(User $user, Ticket $ticket): bool
    {
        if ($user->isClient()) {
            return (int) $ticket->user_id === (int) $user->id;
        }

        if ($user->isTechnician()) {
            // Technicians can open (read-only) any client-facing ticket, for example from a link a
            // colleague shared while consulting. Changing a ticket still needs "manage" below, and the
            // ticket lists, counts and notifications still show only their own tickets. Staff-to-staff
            // (internal) requests stay limited to the people involved.
            return $ticket->hasAssignedUser((int) $user->id)
                || $ticket->ticket_type !== Ticket::TYPE_INTERNAL
                || ($ticket->isInternalRequesterTicketFor((int) $user->id) && $ticket->isClosed());
        }

        return $user->canAccessAdminTickets();
    }

    public function manage(User $user, Ticket $ticket): bool
    {
        if ($user->isClient()) {
            return false;
        }

        if ($user->isTechnician()) {
            return $ticket->hasAssignedUser((int) $user->id);
        }

        return $user->canAccessAdminTickets();
    }
}
