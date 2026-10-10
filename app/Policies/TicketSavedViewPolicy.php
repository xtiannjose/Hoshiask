<?php

namespace App\Policies;

use App\Models\TicketSavedView;
use App\Models\User;

class TicketSavedViewPolicy
{
    public function delete(User $actor, TicketSavedView $ticketSavedView): bool
    {
        return (int) $ticketSavedView->user_id === (int) $actor->id;
    }
}
