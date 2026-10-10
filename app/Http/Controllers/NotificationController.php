<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HidesForbiddenRecordsFromClients;
use App\Http\Requests\Notifications\DismissNotificationRequest;
use App\Models\Ticket;
use App\Models\TicketUserState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class NotificationController extends Controller
{
    use HidesForbiddenRecordsFromClients;

    private const CLEAR_NOTIFICATION_CHUNK_SIZE = 250;

    public function clientDismiss(DismissNotificationRequest $request): RedirectResponse
    {
        $ticket = Ticket::findOrFail($request->integer('ticket_id'));
        $this->authorizeHidingFromClients('view', $ticket);

        return $this->dismissNotificationForTicket(
            $ticket,
            Carbon::parse($request->string('activity_at')->toString())
        );
    }

    public function clientClear(Request $request): JsonResponse|RedirectResponse
    {
        $userId = (int) auth()->id();

        $this->clearNotificationsForTickets(
            Ticket::query()->where('user_id', $userId),
            $userId
        );

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back();
    }

    public function clientSeen(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorizeHidingFromClients('view', $ticket);

        return $this->markTicketNotificationSeen($ticket, $request->input('activity_at'));
    }

    public function clientOpen(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorizeHidingFromClients('view', $ticket);

        return $this->openTicketFromNotification($ticket, $request->query('activity_at'), 'client.tickets.show');
    }

    public function adminDismiss(DismissNotificationRequest $request): RedirectResponse
    {
        $ticket = Ticket::findOrFail($request->integer('ticket_id'));
        $this->authorizeHidingFromClients('view', $ticket);

        return $this->dismissNotificationForTicket(
            $ticket,
            Carbon::parse($request->string('activity_at')->toString())
        );
    }

    public function adminClear(Request $request): JsonResponse|RedirectResponse
    {
        $authUser = auth()->user();
        $userId = (int) auth()->id();
        $ticketsQuery = Ticket::query()->open();

        if ($authUser && $authUser->isTechnician()) {
            Ticket::applyAssignedToConstraint($ticketsQuery, (int) $authUser->id);
        }

        $this->clearNotificationsForTickets($ticketsQuery, $userId);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back();
    }

    public function adminSeen(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorizeHidingFromClients('view', $ticket);

        return $this->markTicketNotificationSeen($ticket, $request->input('activity_at'));
    }

    public function adminOpen(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorizeHidingFromClients('view', $ticket);

        return $this->openTicketFromNotification($ticket, $request->query('activity_at'), 'admin.tickets.show');
    }

    private function dismissNotificationForTicket(Ticket $ticket, Carbon $activityAt): RedirectResponse
    {
        $userId = (int) auth()->id();
        $state = TicketUserState::query()->firstOrNew([
            'ticket_id' => $ticket->id,
            'user_id' => $userId,
        ]);

        if (! $state->exists || ! $state->hasViewedActivity($activityAt)) {
            return back()->with('error', 'You can dismiss notifications only after viewing them.');
        }

        $state->dismissed_at = $activityAt;
        $state->save();
        TicketUserState::forgetHeaderNotificationCacheForUser($userId);

        return back();
    }

    private function markTicketNotificationSeen(Ticket $ticket, mixed $activityAtInput): JsonResponse
    {
        $seenAt = TicketUserState::resolveSeenAt($ticket, $activityAtInput);
        TicketUserState::markSeenAndDismiss($ticket, (int) auth()->id(), $seenAt);

        return response()->json([
            'ok' => true,
            'seen_at' => $seenAt->toIso8601String(),
        ]);
    }

    private function openTicketFromNotification(Ticket $ticket, mixed $activityAtInput, string $routeName): RedirectResponse
    {
        $seenAt = TicketUserState::resolveSeenAt($ticket, $activityAtInput);
        TicketUserState::markSeenAndDismiss($ticket, (int) auth()->id(), $seenAt);

        return redirect()->route($routeName, $ticket);
    }

    /**
     * @param  Builder<Ticket>  $ticketsQuery
     */
    private function clearNotificationsForTickets(Builder $ticketsQuery, int $userId): void
    {
        $now = now();

        (clone $ticketsQuery)
            ->select('id')
            ->orderBy('id')
            ->chunkById(self::CLEAR_NOTIFICATION_CHUNK_SIZE, function ($tickets) use ($now, $userId): void {
                /** @var Collection<int, Ticket> $tickets */
                $rows = $tickets->map(fn (Ticket $ticket) => [
                    'ticket_id' => (int) $ticket->id,
                    'user_id' => $userId,
                    'last_seen_at' => $now,
                    'dismissed_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                if ($rows === []) {
                    return;
                }

                TicketUserState::upsert(
                    $rows,
                    ['ticket_id', 'user_id'],
                    ['last_seen_at', 'dismissed_at', 'updated_at']
                );
            });

        TicketUserState::forgetHeaderNotificationCacheForUser($userId);
    }
}
