<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $ticket_id
 * @property int $user_id
 * @property int|null $reply_to_id
 * @property string|null $message
 * @property bool $is_internal
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $edited_at
 * @property Carbon|null $deleted_at
 * @property-read Ticket $ticket
 * @property-read User $user
 * @property-read TicketReply|null $replyTo
 * @property-read Collection<int, Attachment> $attachments
 */
class TicketReply extends Model
{
    protected $fillable = [
        'ticket_id',
        'user_id',
        'reply_to_id',
        'message',
        'is_internal',
        'edited_at',
        'deleted_at',
        'client_notified_reply_at',
    ];

    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
            'edited_at' => 'datetime',
            'deleted_at' => 'datetime',
            'client_notified_reply_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (TicketReply $reply) {
            $reply->loadMissing('ticket:id,user_id,assigned_to');
            if ($reply->ticket) {
                TicketUserState::forgetHeaderNotificationCachesForTicket($reply->ticket);
            }
        });

        static::deleted(function (TicketReply $reply) {
            $reply->loadMissing('ticket:id,user_id,assigned_to');
            if ($reply->ticket) {
                TicketUserState::forgetHeaderNotificationCachesForTicket($reply->ticket);
            }
        });
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(TicketReply::class, 'reply_to_id');
    }

    public static function editWindowMinutes(): int
    {
        $minutes = (int) config('hoshiask.reply_edit_window_minutes', 180);

        return $minutes > 0 ? $minutes : 180;
    }

    /**
     * Human wording of the edit window, e.g. "3 hours" or "90 minutes".
     */
    public static function editWindowLabel(): string
    {
        $minutes = self::editWindowMinutes();

        if ($minutes % 60 === 0) {
            $hours = intdiv($minutes, 60);

            return $hours.' '.($hours === 1 ? 'hour' : 'hours');
        }

        return $minutes.' minutes';
    }

    public function isWithinEditWindow(): bool
    {
        return $this->created_at === null
            || $this->created_at->greaterThanOrEqualTo(now()->subMinutes(self::editWindowMinutes()));
    }

    public function scopeInternal($query)
    {
        return $query->where('is_internal', true);
    }

    public function scopeVisibleToViewer(Builder $query, ?User $viewer = null): Builder
    {
        if ($viewer?->isShadow()) {
            return $query;
        }

        return $query->whereHas('user', function (Builder $userQuery) {
            $userQuery->where('role', '!=', User::ROLE_SHADOW);
        });
    }

    public function isVisibleTo(?User $viewer = null): bool
    {
        return $viewer?->isShadow() || ! $this->user->isShadow();
    }
}
