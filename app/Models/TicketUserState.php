<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $ticket_id
 * @property int $user_id
 * @property Carbon|null $last_seen_at
 * @property Carbon|null $acknowledged_at
 * @property Carbon|null $dismissed_at
 * @property-read Ticket $ticket
 * @property-read User $user
 */
class TicketUserState extends Model
{
    /**
     * Shared version token for every staff member's cached header payload. Bumping it
     * (one write) marks all staff caches stale, instead of deleting one key per staff user.
     */
    public const STAFF_HEADER_NOTIFICATION_GENERATION_CACHE_KEY = 'header_notifications_staff_generation_v1'; // gitleaks:allow (cache key name, not a secret)

    protected $fillable = [
        'ticket_id',
        'user_id',
        'last_seen_at',
        'acknowledged_at',
        'dismissed_at',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'dismissed_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function markSeen(
        Ticket $ticket,
        int $userId,
        Carbon $seenAt,
        bool $clearDismissedAt = false
    ): self {
        /** @var self $state */
        $state = static::query()->firstOrNew([
            'ticket_id' => $ticket->id,
            'user_id' => $userId,
        ]);

        $currentSeenAt = $state->last_seen_at;
        $nextSeenAt = $currentSeenAt && $currentSeenAt->gt($seenAt)
            ? $currentSeenAt
            : $seenAt;

        $state->last_seen_at = $nextSeenAt;
        if ($clearDismissedAt) {
            $state->dismissed_at = null;
        }
        $state->save();
        static::forgetHeaderNotificationCacheForUser($userId);

        return $state;
    }

    public static function markAcknowledged(
        Ticket $ticket,
        int $userId,
        Carbon $acknowledgedAt,
        bool $clearDismissedAt = false
    ): self {
        $state = static::markSeen($ticket, $userId, $acknowledgedAt, $clearDismissedAt);
        $currentAcknowledgedAt = $state->acknowledged_at;
        $nextAcknowledgedAt = $currentAcknowledgedAt && $currentAcknowledgedAt->lt($acknowledgedAt)
            ? $currentAcknowledgedAt
            : $acknowledgedAt;

        if (! $currentAcknowledgedAt || ! $currentAcknowledgedAt->equalTo($nextAcknowledgedAt)) {
            $state->acknowledged_at = $nextAcknowledgedAt;
            $state->save();
        }

        static::forgetHeaderNotificationCacheForUser($userId);

        return $state;
    }

    public static function markSeenAndDismiss(
        Ticket $ticket,
        int $userId,
        Carbon $seenAt
    ): self {
        $state = static::markSeen($ticket, $userId, $seenAt);
        if (! $state->dismissed_at || $state->dismissed_at->lt($state->last_seen_at)) {
            $state->dismissed_at = $state->last_seen_at;
            $state->save();
        }
        static::forgetHeaderNotificationCacheForUser($userId);

        return $state;
    }

    public static function clearAcknowledgmentsForReopenedTicket(Ticket $ticket): void
    {
        static::query()
            ->where('ticket_id', $ticket->id)
            ->whereHas('user', function ($query) {
                $query->whereIn('role', [
                    User::ROLE_SUPER_USER,
                    User::ROLE_ADMIN,
                    User::ROLE_SHADOW,
                ]);
            })
            ->whereNotNull('acknowledged_at')
            ->update([
                'acknowledged_at' => null,
            ]);

        static::forgetHeaderNotificationCachesForTicket($ticket);
    }

    public function hasViewedActivity(Carbon $activityAt): bool
    {
        return $this->last_seen_at !== null && $this->last_seen_at->gte($activityAt);
    }

    public static function resolveSeenAt(Ticket $ticket, mixed $rawActivityAt): Carbon
    {
        if (is_string($rawActivityAt) && trim($rawActivityAt) !== '') {
            try {
                return Carbon::parse($rawActivityAt);
            } catch (\Throwable) {
                // Fall through to a reliable fallback.
            }
        }

        return $ticket->updated_at ?? now();
    }

    public static function headerNotificationCacheKeyForUser(int $userId): string
    {
        return 'header_notifications_payload_user_'.$userId.'_v1';
    }

    public static function forgetHeaderNotificationCacheForUser(int $userId): void
    {
        Cache::forget(static::headerNotificationCacheKeyForUser($userId));
    }

    public static function forgetHeaderNotificationCachesForTicket(Ticket $ticket): void
    {
        // Staff (including any assignee) all go stale through the shared generation token.
        Cache::forever(self::STAFF_HEADER_NOTIFICATION_GENERATION_CACHE_KEY, (string) Str::ulid());

        // Requesters cache per user, so only the ticket's own requester needs clearing.
        static::forgetHeaderNotificationCacheForUser((int) $ticket->user_id);
    }
}
