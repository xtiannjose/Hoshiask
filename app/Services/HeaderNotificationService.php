<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\TicketUserState;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class HeaderNotificationService
{
    public function __construct(
        private HeaderNotificationQueryService $notificationQueries,
    ) {}

    private const TICKET_WINDOW = 120;

    private const RENDER_LIMIT = 5;

    private const CACHE_SECONDS = 20;

    public function payloadFor(?User $user): array
    {
        if (! $user) {
            return [
                'notifications' => [],
                'unread_count' => 0,
            ];
        }

        $cacheKey = TicketUserState::headerNotificationCacheKeyForUser((int) $user->id);

        if (! $user->canAccessAdminTickets()) {
            return Cache::remember(
                $cacheKey,
                now()->addSeconds(self::CACHE_SECONDS),
                fn (): array => $this->buildPayload($user)
            );
        }

        // Staff entries remember the generation they were built for; a ticket change bumps
        // the shared generation, making every staff entry stale with a single write.
        $generationKey = TicketUserState::STAFF_HEADER_NOTIFICATION_GENERATION_CACHE_KEY;
        $cached = Cache::many([$cacheKey, $generationKey]);
        $generation = (string) ($cached[$generationKey] ?? '');
        $entry = $cached[$cacheKey] ?? null;

        if (is_array($entry) && ($entry['generation'] ?? null) === $generation && is_array($entry['payload'] ?? null)) {
            return $entry['payload'];
        }

        $payload = $this->buildPayload($user);
        Cache::put(
            $cacheKey,
            ['generation' => $generation, 'payload' => $payload],
            now()->addSeconds(self::CACHE_SECONDS)
        );

        return $payload;
    }

    private function buildPayload(User $user): array
    {
        $tickets = $this->notificationQueries->ticketsForUser($user, self::TICKET_WINDOW);
        $notificationItems = $this->buildNotificationsForUser($user, $tickets)
            ->sortByDesc('activity_ts')
            ->values();

        return [
            'notifications' => $notificationItems
                ->take(self::RENDER_LIMIT)
                ->values()
                ->all(),
            'unread_count' => (int) $notificationItems
                ->where('is_viewed', false)
                ->count(),
        ];
    }

    private function buildNotificationsForUser(User $user, Collection $tickets): Collection
    {
        if ($tickets->isEmpty()) {
            return collect();
        }

        $states = TicketUserState::where('user_id', $user->id)
            ->whereIn('ticket_id', $tickets->pluck('id'))
            ->get(['ticket_id', 'last_seen_at', 'dismissed_at'])
            ->keyBy('ticket_id');
        $latestRepliesByTicket = $this->notificationQueries->latestCounterpartRepliesForTickets($user, $tickets);

        return $tickets
            ->map(function (Ticket $ticket) use ($user, $states, $latestRepliesByTicket) {
                /** @var TicketUserState|null $state */
                $state = $states->get($ticket->id);
                $lastSeenAt = optional($state)->last_seen_at;
                $latestCounterpartReply = $latestRepliesByTicket->get((int) $ticket->id);
                $activityAt = $this->resolveNotificationActivityAt($user, $ticket, $latestCounterpartReply);

                if (! $activityAt) {
                    return null;
                }

                if ($state?->dismissed_at && $activityAt->lte($state->dismissed_at)) {
                    return null;
                }

                return $this->buildNotificationItem($user, $ticket, $latestCounterpartReply, $activityAt, $lastSeenAt);
            })
            ->filter()
            ->values();
    }

    private function resolveNotificationActivityAt(
        User $user,
        Ticket $ticket,
        ?TicketReply $latestCounterpartReply
    ): ?Carbon {
        if ($latestCounterpartReply && $latestCounterpartReply->created_at) {
            return $latestCounterpartReply->created_at;
        }

        if ($user->canAccessAdminTickets()) {
            return $ticket->created_at;
        }

        return null;
    }

    private function buildNotificationItem(
        User $user,
        Ticket $ticket,
        ?TicketReply $latestCounterpartReply,
        Carbon $activityAt,
        ?Carbon $lastSeenAt
    ): array {
        $isTicketConsoleUser = $user->canAccessAdminTickets();
        $isViewed = $lastSeenAt && $activityAt->lte($lastSeenAt);
        $senderName = optional($latestCounterpartReply?->user)->name;
        $replyPreview = Str::of((string) optional($latestCounterpartReply)->message)->squish()->toString();
        $meta = $ticket->subject;
        $title = 'Ticket update';

        if ($latestCounterpartReply) {
            $title = $isTicketConsoleUser ? 'New client message' : 'New technical message';
            $meta = $ticket->subject
                .($senderName ? ' - '.$senderName : '')
                .($replyPreview !== '' ? ': '.Str::limit($replyPreview, 70) : '');
        } elseif ($isTicketConsoleUser) {
            $title = 'New ticket received';
            $meta = $ticket->subject.' - '.optional($ticket->user)->name;
        }

        $activityTs = $activityAt->timestamp;

        return [
            'title' => $title,
            'meta' => $meta,
            'time' => $activityAt->diffForHumans(),
            'url' => $this->notificationOpenRoute($user, $ticket, $activityAt),
            'key' => 'ticket-'.$ticket->id.'-'.$activityTs,
            'ticket_id' => $ticket->id,
            'activity_at' => $activityAt->toIso8601String(),
            'activity_ts' => $activityTs,
            'is_viewed' => (bool) $isViewed,
            'can_dismiss' => (bool) $isViewed,
            'dismiss_url' => $isTicketConsoleUser
                ? route('admin.notifications.dismiss')
                : route('client.notifications.dismiss'),
        ];
    }

    private function notificationOpenRoute(User $user, Ticket $ticket, Carbon $activityAt): string
    {
        $routeName = $user->canAccessAdminTickets()
            ? 'admin.notifications.open'
            : 'client.notifications.open';

        return route($routeName, [
            'ticket' => $ticket,
            'ticket_id' => $ticket->id,
            'activity_at' => $activityAt->toIso8601String(),
        ]);
    }
}
