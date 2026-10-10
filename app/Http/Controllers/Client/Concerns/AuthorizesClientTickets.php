<?php

namespace App\Http\Controllers\Client\Concerns;

use App\Http\Controllers\Concerns\HidesForbiddenRecordsFromClients;
use App\Models\Ticket;

trait AuthorizesClientTickets
{
    use HidesForbiddenRecordsFromClients;

    /**
     * A ticket that is not the client's own answers "not found", the same as a ticket that does not exist.
     */
    protected function authorizeOwnedTicket(Ticket $ticket): void
    {
        $this->authorizeHidingFromClients('view', $ticket);
    }
}
