<?php

namespace App\Services;

use App\Mail\TicketAlertMail;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use App\Support\SlaSettings;
use App\Support\TicketEmailThread;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TicketEmailAlertService
{
    /**
     * A ticket event (logged, resolved, closed, cancelled) dated further back than this was
     * recorded after the fact, e.g. backlog entered from paper: the client is not emailed about it.
     */
    private const AFTER_THE_FACT_HOURS = 24;

    public function notifyAssignedTechnicalUsersAboutAssignment(Ticket $ticket, ?array $assigneeIds = null): bool
    {
        if ($ticket->isClosed()) {
            return false;
        }

        $assignees = $this->activeTechnicalRecipientsForTicket($ticket, $assigneeIds);

        if ($assignees->isEmpty()) {
            return false;
        }

        $sentCount = $this->sendAlertToUsers(
            $assignees,
            $ticket,
            'Ticket Assigned: '.$ticket->ticket_number,
            'A ticket was assigned to you.',
            'Please review and start progress before the '.$this->resolutionTargetLabel().' resolution target.',
            [
                'Ticket Number' => $ticket->ticket_number,
                'Subject' => $ticket->subject,
                'Severity' => $ticket->priority_label,
                'Assigned By' => optional(auth()->user())->name ?? 'System',
            ]
        );

        if ($sentCount > 0) {
            $this->recordNotifiedAt($ticket, 'technical_user_notified_assignment_at');

            return true;
        }

        return false;
    }

    /**
     * With batching on (the default) this sends nothing: the scheduled sweep in
     * sendClientReplyDigests() merges the replies of a busy ticket into one email.
     * With batching off every public agent reply is emailed straight away.
     */
    public function notifyClientOfAgentReply(Ticket $ticket, TicketReply $reply): bool
    {
        if ($reply->is_internal || $reply->client_notified_reply_at !== null) {
            return false;
        }

        if ($this->clientReplyBatchMinutes() > 0) {
            return false;
        }

        return $this->sendClientReplyEmail($ticket, collect([$reply]));
    }

    /**
     * "We received your ticket": the first email of the ticket's conversation. It gives
     * the client the ticket number in writing and tells them what to expect.
     */
    public function notifyClientOfTicketReceived(Ticket $ticket): bool
    {
        if (! (bool) config('helpdesk.client_emails.received', true) || $ticket->client_notified_received_at !== null) {
            return false;
        }

        // Staff logging a past ticket after the fact (backdated, or already resolved/closed)
        // must not tell the client "we received your ticket and will review it shortly".
        if (in_array($ticket->status, Ticket::TERMINAL_STATUSES, true) || $this->isAfterTheFact($ticket->created_at)) {
            return false;
        }

        $client = $this->clientRecipientForTicket($ticket);
        if (! $client) {
            return false;
        }

        $sentCount = $this->sendClientEmail(
            $client,
            $ticket,
            'We received your ticket.',
            'Thank you for contacting support. Your ticket is logged and our team will review it shortly. Keep this email for your records: we will only email you again when something needs your attention, such as your ticket being resolved.',
            [
                'Ticket Number' => $ticket->ticket_number,
                'Subject' => $ticket->subject,
                'Submitted' => optional($ticket->created_at)->format('M j, Y g:i A'),
            ],
            isRoot: true
        );

        return $this->stampWhenSent($sentCount, $ticket, 'client_notified_received_at');
    }

    /**
     * Sent when staff move a ticket to Resolved: the moment the client has to act
     * (confirm it is fixed, or reopen it).
     */
    public function notifyClientOfStatusChange(Ticket $ticket): bool
    {
        return match ($ticket->status) {
            'resolved' => $this->notifyClientOfResolution($ticket),
            'closed', 'cancelled' => $this->notifyClientOfClosure($ticket),
            default => false,
        };
    }

    /**
     * One reminder before a Resolved ticket is closed automatically.
     */
    public function notifyClientOfAutoCloseWarning(Ticket $ticket, Carbon $closesAt): bool
    {
        if (! (bool) config('helpdesk.client_emails.auto_close_warning', true)
            || $ticket->status !== 'resolved'
            || $ticket->client_notified_auto_close_warning_at !== null
            || $this->resolutionWasNotAnnounced($ticket)
        ) {
            return false;
        }

        $client = $this->clientRecipientForTicket($ticket);
        if (! $client) {
            return false;
        }

        $sentCount = $this->sendClientEmail(
            $client,
            $ticket,
            'Your resolved ticket will close soon.',
            'We have not heard back since your ticket was resolved. If the issue is fixed you do not need to do anything. If it is not, open the ticket and choose Reopen before it closes.',
            [
                'Ticket Number' => $ticket->ticket_number,
                'Subject' => $ticket->subject,
                'Closes automatically on' => $closesAt->format('M j, Y'),
            ]
        );

        return $this->stampWhenSent($sentCount, $ticket, 'client_notified_auto_close_warning_at');
    }

    /**
     * Whether the automatic close should wait for a warning email to go out first: the
     * warning is switched on and this client can actually receive it.
     */
    public function shouldWarnClientBeforeAutoClose(Ticket $ticket): bool
    {
        return (bool) config('helpdesk.client_emails.auto_close_warning', true)
            && ! $this->resolutionWasNotAnnounced($ticket)
            && $this->clientRecipientForTicket($ticket) !== null;
    }

    private function notifyClientOfResolution(Ticket $ticket): bool
    {
        if (! (bool) config('helpdesk.client_emails.resolved', true)
            || $ticket->client_notified_resolved_at !== null
            || $this->isAfterTheFact($ticket->resolved_at)
        ) {
            return false;
        }

        $client = $this->clientRecipientForTicket($ticket);
        if (! $client) {
            return false;
        }

        $closesOn = ($ticket->resolved_at ?? now())->copy()->addDays(Ticket::AUTO_CLOSE_RESOLVED_AFTER_DAYS);

        $sentCount = $this->sendClientEmail(
            $client,
            $ticket,
            'Your ticket has been resolved.',
            'Please check that the issue is fixed. If it is, confirm it in the ticket and tell us how we did. If it is not, open the ticket and choose Reopen. If we do not hear from you the ticket will close automatically on '.$closesOn->format('M j, Y').'.',
            [
                'Ticket Number' => $ticket->ticket_number,
                'Subject' => $ticket->subject,
                'Resolved' => optional($ticket->resolved_at)->format('M j, Y g:i A'),
            ]
        );

        return $this->stampWhenSent($sentCount, $ticket, 'client_notified_resolved_at');
    }

    private function notifyClientOfClosure(Ticket $ticket): bool
    {
        if (! (bool) config('helpdesk.client_emails.closed', false) || $ticket->client_notified_closed_at !== null) {
            return false;
        }

        $cancelled = $ticket->status === 'cancelled';
        $byStaff = auth()->check();

        // Closed/cancelled on a past date, or auto-closed after a resolution the client never heard about.
        if ($this->isAfterTheFact($cancelled ? $ticket->cancelled_at : $ticket->closed_at)
            || (! $byStaff && $this->resolutionWasNotAnnounced($ticket))
        ) {
            return false;
        }

        $client = $this->clientRecipientForTicket($ticket);
        if (! $client) {
            return false;
        }

        $details = [
            'Ticket Number' => $ticket->ticket_number,
            'Subject' => $ticket->subject,
        ];
        if ($cancelled && trim((string) $ticket->cancellation_reason) !== '') {
            $details['Reason'] = trim((string) $ticket->cancellation_reason);
        }

        $sentCount = $this->sendClientEmail(
            $client,
            $ticket,
            $cancelled ? 'Your ticket was cancelled.' : 'Your ticket has been closed.',
            $cancelled
                ? 'Our team cancelled this ticket. If you still need help, please open a new ticket.'
                : ($byStaff
                    ? 'Our team closed this ticket. If you still need help, please open a new ticket.'
                    : 'This ticket was closed automatically because we did not hear back after it was resolved. If you still need help, please open a new ticket.'),
            $details
        );

        return $this->stampWhenSent($sentCount, $ticket, 'client_notified_closed_at');
    }

    /**
     * Emails each ticket's un-notified public agent replies as ONE message once the oldest
     * of them is at least the batching window old. Runs from the five-minute scheduler.
     */
    private function sendClientReplyDigests(): int
    {
        $batchMinutes = $this->clientReplyBatchMinutes();
        if ($batchMinutes < 1) {
            return 0;
        }

        // The one-day floor stops a first run on an old database from mailing ancient replies.
        $pending = TicketReply::query()
            ->where('is_internal', false)
            ->whereNull('client_notified_reply_at')
            ->whereNull('deleted_at')
            ->where('created_at', '>=', now()->subDay())
            ->whereHas('user', fn ($query) => $query->where('role', '!=', User::ROLE_CLIENT))
            ->with('user:id,name')
            ->orderBy('id')
            ->get()
            ->groupBy('ticket_id');

        $cutoff = now()->subMinutes($batchMinutes);
        $sentTickets = 0;

        foreach ($pending as $ticketId => $replies) {
            if ($replies->first()->created_at->gt($cutoff)) {
                continue;
            }

            $ticket = Ticket::query()->find($ticketId);
            if ($ticket && $this->sendClientReplyEmail($ticket, $replies)) {
                $sentTickets++;
            }
        }

        return $sentTickets;
    }

    /**
     * @param  Collection<int, TicketReply>  $replies  public agent replies on one ticket
     */
    private function sendClientReplyEmail(Ticket $ticket, Collection $replies): bool
    {
        $client = $this->clientRecipientForTicket($ticket);
        if (! $client || $replies->isEmpty()) {
            return false;
        }

        $replies->each(fn (TicketReply $reply) => $reply->loadMissing('user:id,name'));
        $agentNames = $replies
            ->map(fn (TicketReply $reply) => $reply->user?->name ?: 'Support Team')
            ->unique()
            ->values();
        $count = $replies->count();

        $sentCount = $this->sendClientEmail(
            $client,
            $ticket,
            $count === 1 ? 'A support agent has replied to your ticket.' : 'There are new replies on your ticket.',
            $count === 1
                ? $agentNames->first().' has posted a new response. Please log in to view the full message and continue the conversation.'
                : $count.' new responses have been posted on your ticket. Please log in to read them and continue the conversation.',
            [
                'Ticket Number' => $ticket->ticket_number,
                'Subject' => $ticket->subject,
                'Replied By' => $agentNames->implode(', '),
            ]
        );

        if ($sentCount < 1) {
            return false;
        }

        $replies->each(fn (TicketReply $reply) => $this->recordNotifiedAt($reply, 'client_notified_reply_at'));

        return true;
    }

    private function clientReplyBatchMinutes(): int
    {
        return max(0, (int) config('helpdesk.client_emails.reply_batch_minutes', 5));
    }

    private function clientRecipientForTicket(Ticket $ticket): ?User
    {
        $ticket->loadMissing('user:id,name,email,role,is_active');
        $client = $ticket->user;

        // These emails send the reader to the client's own ticket page, which staff cannot open. The
        // requester of an internal (staff-to-staff) ticket is staff, so it has no recipient at all.
        if (! $client || ! $client->isClient() || ! $client->is_active || ! is_string($client->email) || trim($client->email) === '') {
            return null;
        }

        return $client;
    }

    private function sendClientEmail(User $client, Ticket $ticket, string $headline, string $messageLine, array $details, bool $isRoot = false): int
    {
        return $this->sendAlertToUsers(
            collect([$client]),
            $ticket,
            TicketEmailThread::subject($ticket, $isRoot),
            $headline,
            $messageLine,
            $details,
            route('client.tickets.show', $ticket),
            clientThread: true,
            clientThreadRoot: $isRoot
        );
    }

    private function isAfterTheFact(?Carbon $at): bool
    {
        return $at !== null && $at->lt(now()->subHours(self::AFTER_THE_FACT_HOURS));
    }

    /**
     * The auto-close warning and notice follow up the "resolved" email. When that email was
     * never sent for this resolution (it was logged after the fact), they are skipped too.
     */
    private function resolutionWasNotAnnounced(Ticket $ticket): bool
    {
        return (bool) config('helpdesk.client_emails.resolved', true) && $ticket->client_notified_resolved_at === null;
    }

    private function stampWhenSent(int $sentCount, Ticket $ticket, string $column): bool
    {
        if ($sentCount < 1) {
            return false;
        }

        $this->recordNotifiedAt($ticket, $column);

        return true;
    }

    public function notifySuperUsersAboutNewTicket(Ticket $ticket): bool
    {
        if (! (bool) config('helpdesk.sla.super_user_new_ticket_notify', true)) {
            return false;
        }

        if ($ticket->super_users_notified_new_at !== null) {
            return false;
        }

        $recipients = $this->activeSuperUserRecipients();

        if ($recipients->isEmpty()) {
            return false;
        }

        $sentCount = $this->sendAlertToUsers(
            $recipients,
            $ticket,
            'New Ticket Submitted: '.$ticket->ticket_number,
            'A new support ticket has been submitted.',
            'Please review and assign the ticket to the appropriate technical staff.',
            [
                'Ticket Number' => $ticket->ticket_number,
                'Subject' => $ticket->subject,
                'Severity' => $ticket->priority_label,
                'Submitted By' => optional($ticket->user)->name ?? 'Unknown',
            ]
        );

        if ($sentCount > 0) {
            $this->recordNotifiedAt($ticket, 'super_users_notified_new_at');

            return true;
        }

        return false;
    }

    public function sendScheduledReminders(): array
    {
        return [
            'assigned_sla_for_technical_users' => $this->sendAssignedTechnicalSlaReminders(),
            'unchecked_for_super_users' => $this->sendUnassignedSuperUserReminders(
                SlaSettings::superUserUncheckedMinutes(),
                'super_users_notified_unchecked_at',
                'Unchecked Ticket Alert',
                'A ticket has been open and unassigned for over %s.',
                'This ticket needs to be reviewed and assigned to technical staff.',
            ),
            'unassigned_sla_for_super_users' => $this->sendUnassignedSuperUserReminders(
                SlaSettings::superUserUnassignedMinutes(),
                'super_users_notified_unassigned_sla_at',
                'Unassigned SLA Warning',
                'An unassigned ticket is approaching the SLA resolution target.',
                'This ticket has been unassigned for %s and risks breaching the SLA.',
            ),
            'client_reply_digests' => $this->sendClientReplyDigests(),
        ];
    }

    private function sendAssignedTechnicalSlaReminders(): int
    {
        $cutoff = now()->subMinutes(SlaSettings::assignmentReminderMinutes());

        // Imported tickets are old records: reminding about them would mail every open one on the first run.
        $query = Ticket::query()
            ->where('is_imported', false)
            ->whereIn('status', Ticket::SLA_CLOCK_STATUSES)
            ->whereNotNull('assigned_at')
            ->where('assigned_at', '<=', $cutoff)
            ->whereNull('technical_user_notified_sla_at');
        Ticket::applyAssignedConstraint($query);

        $tickets = $query
            ->with('assignedUsers:id,name,email,role,is_active')
            ->get();

        $notifiedTickets = 0;

        foreach ($tickets as $ticket) {
            $assignees = $this->activeTechnicalRecipientsForTicket($ticket);

            if ($assignees->isEmpty()) {
                continue;
            }

            $sentCount = $this->sendAlertToUsers(
                $assignees,
                $ticket,
                $this->resolutionTargetTitle().' SLA Reminder (Assigned): '.$ticket->ticket_number,
                'This assigned ticket is nearing the '.$this->resolutionTargetLabel().' resolution target.',
                $this->assignmentReminderLabel().' have passed since assignment and the ticket is still unresolved.',
                [
                    'Ticket Number' => $ticket->ticket_number,
                    'Subject' => $ticket->subject,
                    'Severity' => $ticket->priority_label,
                    'Assigned At' => optional($ticket->assigned_at)->format('Y-m-d H:i:s'),
                ]
            );

            if ($sentCount > 0) {
                $this->recordNotifiedAt($ticket, 'technical_user_notified_sla_at');
                $notifiedTickets++;
            }
        }

        return $notifiedTickets;
    }

    /**
     * Tell the super users about Active tickets nobody has taken, once per ticket. Both reminders of this
     * kind (unchecked, unassigned SLA) differ only in the threshold, the "already told" column and the text.
     * `%s` in the two text lines becomes the threshold written out, for example "2 hours".
     */
    private function sendUnassignedSuperUserReminders(
        int $thresholdMinutes,
        string $notifiedColumn,
        string $subjectPrefix,
        string $headline,
        string $messageLine,
    ): int {
        $thresholdLabel = $this->formatMinutes($thresholdMinutes);

        $query = Ticket::query()
            ->where('is_imported', false)
            ->whereIn('status', Ticket::SLA_CLOCK_STATUSES)
            ->where('created_at', '<=', now()->subMinutes($thresholdMinutes))
            ->whereNull($notifiedColumn);
        $tickets = Ticket::applyUnassignedConstraint($query)
            ->get(['id', 'ticket_number', 'subject', 'priority', 'created_at']);

        $recipients = $this->activeSuperUserRecipients();
        if ($recipients->isEmpty()) {
            return 0;
        }

        $notifiedCount = 0;
        foreach ($tickets as $ticket) {
            $sentCount = $this->sendAlertToUsers(
                $recipients,
                $ticket,
                $subjectPrefix.': '.$ticket->ticket_number,
                sprintf($headline, $thresholdLabel),
                sprintf($messageLine, $thresholdLabel),
                [
                    'Ticket Number' => $ticket->ticket_number,
                    'Subject' => $ticket->subject,
                    'Severity' => $ticket->priority_label,
                    'Submitted' => optional($ticket->created_at)->format('Y-m-d H:i:s'),
                ]
            );

            if ($sentCount > 0) {
                $this->recordNotifiedAt($ticket, $notifiedColumn);
                $notifiedCount++;
            }
        }

        return $notifiedCount;
    }

    private function activeSuperUserRecipients(): Collection
    {
        return User::query()
            ->whereIn('role', [User::ROLE_SHADOW, User::ROLE_ADMIN, User::ROLE_SUPER_USER])
            ->where('is_active', true)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->get(['id', 'name', 'email', 'role', 'is_active'])
            ->values();
    }

    private function activeTechnicalRecipientsForTicket(Ticket $ticket, ?array $assigneeIds = null): Collection
    {
        $normalizedAssigneeIds = $assigneeIds !== null
            ? Ticket::normalizeAssignedUserIds($assigneeIds)
            : null;

        if ($ticket->relationLoaded('assignedUsers')) {
            $assignedUsers = $ticket->assignedUsers
                ->filter(function (User $user) use ($normalizedAssigneeIds): bool {
                    if ($user->normalizedRole() !== User::ROLE_TECHNICAL) {
                        return false;
                    }

                    if (! $user->is_active || ! is_string($user->email) || trim($user->email) === '') {
                        return false;
                    }

                    if ($normalizedAssigneeIds === null) {
                        return true;
                    }

                    return in_array((int) $user->id, $normalizedAssigneeIds, true);
                })
                ->values();

            if ($ticket->assigned_to) {
                $primaryAssignedUser = $assignedUsers->first(fn (User $user) => (int) $user->id === (int) $ticket->assigned_to);
                if ($primaryAssignedUser) {
                    return $assignedUsers
                        ->sortBy(fn (User $user) => (int) $user->id === (int) $primaryAssignedUser->id ? 0 : 1)
                        ->values();
                }
            }

            return $assignedUsers;
        }

        $assignedUserIds = collect($ticket->assigned_user_ids)
            ->map(fn ($userId) => (int) $userId)
            ->filter(fn (int $userId) => $userId > 0);

        if ($normalizedAssigneeIds !== null) {
            $assignedUserIds = $assignedUserIds->intersect($normalizedAssigneeIds);
        }

        $assignedUserIds = $assignedUserIds->unique()->values();
        if ($assignedUserIds->isEmpty()) {
            return collect();
        }

        return User::query()
            ->whereIn('id', $assignedUserIds->all())
            ->where('role', User::ROLE_TECHNICAL)
            ->where('is_active', true)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->get(['id', 'name', 'email', 'role', 'is_active'])
            ->values();
    }

    private function resolutionTargetLabel(): string
    {
        return $this->formatMinutes(SlaSettings::resolutionTargetMinutes());
    }

    private function assignmentReminderLabel(): string
    {
        return $this->formatMinutes(SlaSettings::assignmentReminderMinutes());
    }

    private function resolutionTargetTitle(): string
    {
        $minutes = SlaSettings::resolutionTargetMinutes();

        if ($minutes % 60 === 0) {
            $hours = intdiv($minutes, 60);

            return $hours.'-'.($hours === 1 ? 'Hour' : 'Hours');
        }

        return $minutes.'-Minute';
    }

    /**
     * Stamp an alert-bookkeeping column without bumping updated_at or firing model
     * events. These columns are never shown to users, so recording them must not
     * look like ticket/reply activity (list refreshes, reply-feed re-sends, cache flushes).
     */
    private function recordNotifiedAt(Model $model, string $column): void
    {
        $usesTimestamps = $model->timestamps;
        $model->timestamps = false;

        try {
            $model->forceFill([$column => now()])->saveQuietly();
        } finally {
            $model->timestamps = $usesTimestamps;
        }
    }

    private function formatMinutes(int $minutes): string
    {
        $minutes = $minutes > 0 ? $minutes : 1;
        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;
        $parts = [];

        if ($hours > 0) {
            $parts[] = $hours.' '.($hours === 1 ? 'hour' : 'hours');
        }

        if ($remainingMinutes > 0) {
            $parts[] = $remainingMinutes.' '.($remainingMinutes === 1 ? 'minute' : 'minutes');
        }

        return implode(' and ', $parts);
    }

    private function sendAlertToUsers(
        Collection $recipients,
        Ticket $ticket,
        string $subject,
        string $headline,
        string $messageLine,
        array $details = [],
        ?string $actionUrl = null,
        bool $clientThread = false,
        bool $clientThreadRoot = false
    ): int {
        $resolvedActionUrl = $actionUrl ?? route('admin.tickets.show', $ticket);
        $sentCount = 0;

        foreach ($recipients as $recipient) {
            try {
                Mail::to($recipient->email)->queue(new TicketAlertMail(
                    ticket: $ticket,
                    subjectLine: $subject,
                    headline: $headline,
                    messageLine: $messageLine,
                    details: $details,
                    actionUrl: $resolvedActionUrl,
                    actionLabel: 'Open Ticket',
                    clientThread: $clientThread,
                    clientThreadRoot: $clientThreadRoot
                ));
                $sentCount++;
            } catch (\Throwable $exception) {
                Log::warning('Ticket alert email failed.', [
                    'ticket_id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'recipient_user_id' => $recipient->id ?? null,
                    'recipient_email' => $recipient->email ?? null,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $sentCount;
    }
}
