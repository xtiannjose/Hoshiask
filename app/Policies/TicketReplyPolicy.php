<?php

namespace App\Policies;

use App\Models\TicketReply;
use App\Models\User;

class TicketReplyPolicy
{
    public function update(User $user, TicketReply $reply): bool
    {
        return (int) $reply->user_id === (int) $user->id;
    }

    public function delete(User $user, TicketReply $reply): bool
    {
        return $this->update($user, $reply);
    }
}
