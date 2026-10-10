<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;

class AttachmentPolicy
{
    public function __construct(
        private TicketPolicy $tickets,
    ) {}

    public function download(User $user, Attachment $attachment): bool
    {
        $attachable = $attachment->attachable;

        if ($attachable instanceof Ticket) {
            return $this->tickets->view($user, $attachable);
        }

        if ($attachable instanceof TicketReply) {
            if ($attachable->deleted_at) {
                return false;
            }

            $ticket = $attachable->ticket;
            if (! $ticket || ! $this->tickets->view($user, $ticket)) {
                return false;
            }

            // Shadow-account files stay hidden from everyone else.
            if ($attachable->user->isShadow() && ! $user->isShadow()) {
                return false;
            }

            // Clients never see files on internal notes.
            return ! ($user->isClient() && $attachable->is_internal);
        }

        return false;
    }
}
