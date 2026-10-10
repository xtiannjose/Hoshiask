<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Auth\Access\AuthorizationException;

/**
 * Same permission check as $this->authorize(), but a client who is refused sees "not found"
 * instead of "no access". Otherwise the difference between the two answers would let a client
 * find out which ticket numbers or file ids exist by trying them. Staff keep the plain 403,
 * which is more useful to them ("this ticket is assigned to someone else").
 */
trait HidesForbiddenRecordsFromClients
{
    protected function authorizeHidingFromClients(string $ability, mixed $record): void
    {
        try {
            $this->authorize($ability, $record);
        } catch (AuthorizationException $exception) {
            if (auth()->user()?->isClient()) {
                abort(404);
            }

            throw $exception;
        }
    }
}
