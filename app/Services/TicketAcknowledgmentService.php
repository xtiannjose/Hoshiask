<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketUserState;
use App\Models\User;
use Illuminate\Support\Carbon;

class TicketAcknowledgmentService
{
    public function canAcknowledge(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return in_array($user->normalizedRole(), [
            User::ROLE_SUPER_USER,
            User::ROLE_ADMIN,
            User::ROLE_SHADOW,
        ], true);
    }

    public function acknowledge(Ticket $ticket, User $user, ?Carbon $acknowledgedAt = null): ?TicketUserState
    {
        if (! $this->canAcknowledge($user)) {
            return null;
        }

        return TicketUserState::markAcknowledged(
            $ticket,
            (int) $user->id,
            $acknowledgedAt ?? now()
        );
    }

    public function trackHandlingAction(Ticket $ticket, ?User $user, ?Carbon $acknowledgedAt = null): ?TicketUserState
    {
        if (! $user || ! $this->canAcknowledge($user)) {
            return null;
        }

        return $this->acknowledge($ticket, $user, $acknowledgedAt);
    }
}
