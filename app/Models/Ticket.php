<?php

namespace App\Models;

use App\Enums\RootCauseCategory;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Support\SlaSettings;
use App\Support\TicketSla;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $created_by_user_id
 * @property int|null $assigned_to
 * @property int|null $category_id
 * @property string $ticket_number
 * @property string $ticket_type
 * @property string|null $creation_source
 * @property string|null $department
 * @property string $subject
 * @property string|null $priority
 * @property string $status
 * @property bool $is_imported
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $resolved_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $client_notified_received_at
 * @property Carbon|null $client_notified_resolved_at
 * @property Carbon|null $client_notified_auto_close_warning_at
 * @property Carbon|null $client_notified_closed_at
 * @property Carbon|null $anonymized_at
 * @property bool $retention_hold
 * @property-read User $user
 * @property-read User|null $createdByUser
 * @property-read User|null $assignedUser
 * @property-read Collection<int, User> $assignedUsers
 * @property-read User|null $closedBy
 * @property-read Category|null $category
 * @property-read Collection<int, TicketReply> $replies
 * @property-read Collection<int, Attachment> $attachments
 * @property-read Collection<int, TicketUserState> $userStates
 *
 * @method static Builder<static> open()
 * @method static Builder<static> closed()
 * @method static Builder<static> byPriority(string $priority)
 */
class Ticket extends Model
{
    public const PRIORITIES = [
        TicketPriority::Severity1->value,
        TicketPriority::Severity2->value,
        TicketPriority::Severity3->value,
    ];

    public const TYPE_INTERNAL = 'internal';

    public const TYPE_EXTERNAL = 'external';

    public const TYPES = [
        self::TYPE_INTERNAL,
        self::TYPE_EXTERNAL,
    ];

    public const CREATION_SOURCE_CLIENT_SELF_SERVICE = 'client_self_service';

    public const CREATION_SOURCE_STAFF_FOR_CLIENT = 'staff_for_client';

    public const CREATION_SOURCE_STAFF_FOR_STAFF = 'staff_for_staff';

    public const CREATION_SOURCE_IMPORTED = 'imported';

    public const CREATION_SOURCES = [
        self::CREATION_SOURCE_CLIENT_SELF_SERVICE,
        self::CREATION_SOURCE_STAFF_FOR_CLIENT,
        self::CREATION_SOURCE_STAFF_FOR_STAFF,
        self::CREATION_SOURCE_IMPORTED,
    ];

    public const PRIORITY_ALIASES = [
        'severity_1' => 'severity_1',
        'severity 1' => 'severity_1',
        'severity-1' => 'severity_1',
        'urgent' => 'severity_1',
        'high' => 'severity_1',
        'severity_2' => 'severity_2',
        'severity 2' => 'severity_2',
        'severity-2' => 'severity_2',
        'medium' => 'severity_2',
        'severity_3' => 'severity_3',
        'severity 3' => 'severity_3',
        'severity-3' => 'severity_3',
        'low' => 'severity_3',
    ];

    public const OPEN_STATUSES = [
        TicketStatus::Open->value,
        TicketStatus::InProgress->value,
        TicketStatus::Pending->value,
    ];

    public const CLOSED_STATUSES = [
        TicketStatus::Resolved->value,
        TicketStatus::Closed->value,
    ];

    public const CANCELLED_STATUSES = [
        TicketStatus::Cancelled->value,
    ];

    /** Statuses a ticket may be cancelled from — a terminal state reached only from early, unworked statuses. */
    public const CANCELLABLE_FROM_STATUSES = [
        TicketStatus::Open->value,
        TicketStatus::Pending->value,
    ];

    /** An active ticket this old shows under "Needs Attention" (the tab and the dashboard count). */
    public const NEEDS_ATTENTION_AFTER_HOURS = 16;

    /** A Resolved ticket is closed automatically once the client has been silent this long. */
    public const AUTO_CLOSE_RESOLVED_AFTER_DAYS = 7;

    /** The client is warned this many days before the automatic close. */
    public const AUTO_CLOSE_WARNING_DAYS_BEFORE = 2;

    /** A warned ticket is never closed sooner than this many hours after the warning went out. */
    public const AUTO_CLOSE_MIN_HOURS_AFTER_WARNING = 24;

    public const STATUSES = [...self::OPEN_STATUSES, ...self::CLOSED_STATUSES, ...self::CANCELLED_STATUSES];

    /** Statuses where a ticket no longer needs active handling — closed/resolved and cancelled alike. */
    public const TERMINAL_STATUSES = [...self::CLOSED_STATUSES, ...self::CANCELLED_STATUSES];

    /** Statuses that no longer accept client replies. Resolved is deliberately absent: a client can still reply to, rate or reopen a resolved ticket. */
    public const CLIENT_REPLY_BLOCKED_STATUSES = [TicketStatus::Closed->value, ...self::CANCELLED_STATUSES];

    protected $fillable = [
        'ticket_number',
        'name',
        'contact_number',
        'email',
        'province',
        'municipality',
        'subject',
        'description',
        'priority',
        'status',
        'user_id',
        'created_by_user_id',
        'assigned_to',
        'assigned_at',
        'is_imported',
        'category_id',
        'department',
        'ticket_type',
        'creation_source',
        'resolved_at',
        'closed_at',
        'closed_by',
        'cancelled_at',
        'cancellation_reason',
        'satisfaction_rating',
        'satisfaction_comment',
        'root_cause_category',
        'root_cause_notes',
        'escalated_to',
        'escalated_by',
        'escalated_at',
        'escalation_reason',
        'escalation_resolved_at',
        'consent_accepted_at',
        'consent_version',
        'consent_ip_address',
        'consent_user_agent',
        'technical_user_notified_assignment_at',
        'technical_user_notified_sla_at',
        'super_users_notified_new_at',
        'super_users_notified_unchecked_at',
        'super_users_notified_unassigned_sla_at',
        'client_notified_received_at',
        'client_notified_resolved_at',
        'client_notified_auto_close_warning_at',
        'client_notified_closed_at',
        'anonymized_at',
        'retention_hold',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'is_imported' => 'boolean',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'escalated_at' => 'datetime',
            'escalation_resolved_at' => 'datetime',
            'satisfaction_rating' => 'integer',
            'consent_accepted_at' => 'datetime',
            'technical_user_notified_assignment_at' => 'datetime',
            'technical_user_notified_sla_at' => 'datetime',
            'super_users_notified_new_at' => 'datetime',
            'super_users_notified_unchecked_at' => 'datetime',
            'super_users_notified_unassigned_sla_at' => 'datetime',
            'client_notified_received_at' => 'datetime',
            'client_notified_resolved_at' => 'datetime',
            'client_notified_auto_close_warning_at' => 'datetime',
            'client_notified_closed_at' => 'datetime',
            'anonymized_at' => 'datetime',
            'retention_hold' => 'boolean',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function (Ticket $ticket) {
            if (empty($ticket->ticket_number)) {
                $ticket->ticket_number = self::generateUniqueTicketNumber();
            }

            // A ticket remembers the department it came from, so reports stay right when its owner
            // is later renamed, moved to another department or deleted.
            if (blank($ticket->department) && $ticket->user_id) {
                $ticket->department = User::query()->whereKey($ticket->user_id)->value('department');
            }
        });

        static::created(function (Ticket $ticket) {
            if ($ticket->assigned_to) {
                $ticket->assignedUsers()->syncWithoutDetaching([(int) $ticket->assigned_to]);
            }
        });

        static::saved(function (Ticket $ticket) {
            TicketUserState::forgetHeaderNotificationCachesForTicket($ticket);
        });

        static::deleted(function (Ticket $ticket) {
            TicketUserState::forgetHeaderNotificationCachesForTicket($ticket);
        });
    }

    private static function generateUniqueTicketNumber(int $maxAttempts = 20): string
    {
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $candidate = 'TK-'.strtoupper(Str::random(8));
            if (! self::query()->where('ticket_number', $candidate)->exists()) {
                return $candidate;
            }
        }

        return 'TK-'.strtoupper(Str::random(12));
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return BelongsToMany<User, $this> */
    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'ticket_assignments')
            ->withTimestamps();
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<User, $this> */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /** @return BelongsTo<User, $this> */
    public function escalatedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalated_to');
    }

    /** @return BelongsTo<User, $this> */
    public function escalatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalated_by');
    }

    /** @return HasMany<TicketReply, $this> */
    public function replies(): HasMany
    {
        return $this->hasMany(TicketReply::class);
    }

    /** @return MorphMany<Attachment, $this> */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /** @return HasMany<TicketUserState, $this> */
    public function userStates(): HasMany
    {
        return $this->hasMany(TicketUserState::class);
    }

    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public static function applyAssignedToConstraint(Builder $query, int $userId): Builder
    {
        return $query->where(function (Builder $builder) use ($userId) {
            $builder->where('assigned_to', $userId)
                ->orWhereExists(function ($assignmentQuery) use ($userId) {
                    self::configureAssignmentExistsQuery($assignmentQuery, $userId);
                });
        });
    }

    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public static function applyClosedInternalRequesterConstraint(Builder $query, int $userId): Builder
    {
        return $query->where('ticket_type', self::TYPE_INTERNAL)
            ->where('user_id', $userId)
            ->whereIn('status', self::CLOSED_STATUSES);
    }

    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public static function applyInternalRequesterConstraint(Builder $query, int $userId): Builder
    {
        return $query->where('ticket_type', self::TYPE_INTERNAL)
            ->where('user_id', $userId);
    }

    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public static function applyHistoryClientRequesterConstraint(Builder $query): Builder
    {
        return $query->whereHas('user', function (Builder $userQuery) {
            $userQuery->where('role', User::ROLE_CLIENT);
        });
    }

    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public static function applyHistoryStaffRequesterConstraint(Builder $query): Builder
    {
        return $query->whereHas('user', function (Builder $userQuery) {
            $userQuery->where('role', '!=', User::ROLE_CLIENT);
        });
    }

    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public static function applyReportableConstraint(Builder $query): Builder
    {
        return $query->where(function (Builder $builder) {
            $builder->where(function (Builder $creationSourceQuery) {
                $creationSourceQuery->whereNull('creation_source')
                    ->orWhere('creation_source', '!=', self::CREATION_SOURCE_STAFF_FOR_STAFF);
            })->where(function (Builder $ticketTypeQuery) {
                $ticketTypeQuery->whereNull('ticket_type')
                    ->orWhere('ticket_type', '!=', self::TYPE_INTERNAL)
                    ->orWhereHas('user', function (Builder $userQuery) {
                        $userQuery->where('role', User::ROLE_CLIENT);
                    });
            });
        });
    }

    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public static function applyAssignedConstraint(Builder $query): Builder
    {
        return $query->where(function (Builder $builder) {
            $builder->whereNotNull('assigned_to')
                ->orWhereExists(function ($assignmentQuery) {
                    self::configureAssignmentExistsQuery($assignmentQuery);
                });
        });
    }

    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public static function applyUnassignedConstraint(Builder $query): Builder
    {
        return $query->whereNull('assigned_to')
            ->whereNotExists(function ($assignmentQuery) {
                self::configureAssignmentExistsQuery($assignmentQuery);
            });
    }

    public static function normalizeAssignedUserIds(array $userIds, ?int $primaryAssignedTo = null): array
    {
        $normalizedIds = collect($userIds)
            ->map(fn ($userId) => (int) $userId)
            ->filter(fn (int $userId) => $userId > 0)
            ->unique()
            ->values()
            ->all();

        if ($primaryAssignedTo === null || $primaryAssignedTo <= 0) {
            return $normalizedIds;
        }

        return array_values(array_unique([
            $primaryAssignedTo,
            ...$normalizedIds,
        ]));
    }

    public static function primaryAssignedUserId(array $userIds, ?int $primaryAssignedTo = null): ?int
    {
        return self::normalizeAssignedUserIds($userIds, $primaryAssignedTo)[0] ?? null;
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->whereIn('status', self::CLOSED_STATUSES);
    }

    public function scopeByPriority(Builder $query, string $priority): Builder
    {
        return $query->where('priority', self::normalizePriorityValue($priority));
    }

    public function isClosed(): bool
    {
        return in_array($this->status, self::CLOSED_STATUSES, true);
    }

    public function isCancelled(): bool
    {
        return in_array($this->status, self::CANCELLED_STATUSES, true);
    }

    public function acceptsClientReplies(): bool
    {
        return ! in_array($this->status, self::CLIENT_REPLY_BLOCKED_STATUSES, true);
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, self::CANCELLABLE_FROM_STATUSES, true);
    }

    public function isEscalated(): bool
    {
        return $this->escalated_at !== null && $this->escalation_resolved_at === null;
    }

    public function isImported(): bool
    {
        return (bool) $this->is_imported;
    }

    public static function normalizePriorityValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = strtolower(trim(str_replace('-', '_', $value)));
        if ($normalized === '') {
            return null;
        }

        return self::PRIORITY_ALIASES[$normalized] ?? null;
    }

    public function setPriorityAttribute(mixed $value): void
    {
        $this->attributes['priority'] = self::normalizePriorityValue($value);
    }

    public static function normalizeTicketTypeValue(mixed $value): string
    {
        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, self::TYPES, true)
            ? $normalized
            : self::TYPE_EXTERNAL;
    }

    public function setTicketTypeAttribute(mixed $value): void
    {
        $this->attributes['ticket_type'] = self::normalizeTicketTypeValue($value);
    }

    public static function normalizeCreationSourceValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = strtolower(trim($value));

        return in_array($normalized, self::CREATION_SOURCES, true)
            ? $normalized
            : null;
    }

    public function setCreationSourceAttribute(mixed $value): void
    {
        $this->attributes['creation_source'] = self::normalizeCreationSourceValue($value);
    }

    public function hasAssignedUser(int $userId): bool
    {
        if ((int) ($this->assigned_to ?? 0) === $userId) {
            return true;
        }

        if ($this->relationLoaded('assignedUsers')) {
            return $this->assignedUsers->contains(fn (User $user) => (int) $user->id === $userId);
        }

        return $this->assignedUsers()->where('users.id', $userId)->exists();
    }

    public function isInternalRequesterTicketFor(int $userId): bool
    {
        return $this->ticket_type === self::TYPE_INTERNAL
            && (int) $this->user_id === $userId;
    }

    public function getAssignedUserIdsAttribute(): array
    {
        $assignedUserIds = $this->relationLoaded('assignedUsers')
            ? $this->assignedUsers->pluck('id')->map(fn ($id) => (int) $id)->values()->all()
            : $this->assignedUsers()->pluck('users.id')->map(fn ($id) => (int) $id)->values()->all();

        return self::normalizeAssignedUserIds(
            $assignedUserIds,
            $this->assigned_to ? (int) $this->assigned_to : null
        );
    }

    /**
     * Requires 'assignedUser' and 'assignedUsers' to be eager-loaded before calling.
     * Use ->with(['assignedUser', 'assignedUsers']) on the query to avoid N+1.
     */
    public function getAssignedUsersLabelAttribute(): string
    {
        $names = collect();

        /** @var User|null $primaryAssignedUser */
        $primaryAssignedUser = $this->relationLoaded('assignedUser')
            ? $this->assignedUser
            : null;

        if ($primaryAssignedUser && ! $primaryAssignedUser->isShadow()) {
            $names->push($primaryAssignedUser->publicDisplayName());
        }

        /** @var Collection<int, User> $assignedUsers */
        $assignedUsers = $this->relationLoaded('assignedUsers')
            ? $this->assignedUsers
            : collect();

        foreach ($assignedUsers as $assignedUser) {
            if ($primaryAssignedUser && (int) $primaryAssignedUser->id === (int) $assignedUser->id) {
                continue;
            }

            if ($assignedUser->isShadow()) {
                continue;
            }

            $names->push($assignedUser->publicDisplayName());
        }

        $label = $names
            ->filter(fn (?string $name) => $name !== null && trim($name) !== '')
            ->unique()
            ->implode(', ');

        return $label !== '' ? $label : 'Unassigned';
    }

    public function getRequesterDisplayLabelAttribute(): string
    {
        if ($this->relationLoaded('user') && $this->user) {
            return $this->user->publicDisplayName();
        }

        if ($this->user_id) {
            $requester = $this->user()->first();
            if ($requester instanceof User) {
                return $requester->publicDisplayName();
            }
        }

        return (string) ($this->name ?: 'Unknown requester');
    }

    public function getCreationSourceSummaryAttribute(): string
    {
        $creatorName = $this->creatorDisplayName();

        return match ($this->resolvedCreationSource()) {
            self::CREATION_SOURCE_CLIENT_SELF_SERVICE => 'Submitted directly by client',
            self::CREATION_SOURCE_STAFF_FOR_CLIENT => $creatorName
                ? "Logged by {$creatorName} for client"
                : 'Logged by staff for client',
            self::CREATION_SOURCE_STAFF_FOR_STAFF => $creatorName
                ? "Logged by {$creatorName} for staff"
                : 'Logged by staff for staff',
            self::CREATION_SOURCE_IMPORTED => 'Imported from legacy records',
            default => $this->resolvedRequesterSourceSummary(),
        };
    }

    public function getPriorityColorAttribute(): string
    {
        return TicketPriority::tryFromValue($this->priority)?->badgeClass() ?? 'bg-gray-100 text-gray-800';
    }

    public function getPriorityBadgeClassAttribute(): string
    {
        return TicketPriority::tryFromValue($this->priority)?->badgeClass() ?? 'bg-slate-100 text-slate-700';
    }

    public function getPriorityLabelAttribute(): string
    {
        return TicketPriority::labelFor($this->priority);
    }

    public function getTicketTypeBadgeClassAttribute(): string
    {
        return match ($this->ticket_type) {
            self::TYPE_INTERNAL => 'bg-slate-100 text-slate-700',
            self::TYPE_EXTERNAL => 'bg-cyan-100 text-cyan-700',
            default => 'bg-slate-100 text-slate-700',
        };
    }

    public function getTicketTypeLabelAttribute(): string
    {
        return ucfirst(self::normalizeTicketTypeValue($this->ticket_type));
    }

    public function getStatusColorAttribute(): string
    {
        return TicketStatus::tryFrom((string) $this->status)?->softBadgeClass() ?? 'bg-gray-100 text-gray-800';
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return TicketStatus::tryFrom((string) $this->status)?->badgeClass() ?? 'bg-slate-400 text-white';
    }

    public function getStatusLabelAttribute(): string
    {
        return TicketStatus::tryFrom((string) $this->status)?->label() ?? str_replace('_', ' ', (string) $this->status);
    }

    public function getRootCauseCategoryLabelAttribute(): ?string
    {
        return RootCauseCategory::tryFrom((string) $this->root_cause_category)?->label();
    }

    public function getActivityDotClassAttribute(): string
    {
        $status = TicketStatus::tryFrom((string) $this->status);

        return match ($status) {
            TicketStatus::Pending, TicketStatus::InProgress => $status->dotClass(),
            TicketStatus::Resolved, TicketStatus::Closed, TicketStatus::Cancelled => TicketStatus::Closed->dotClass(),
            default => TicketStatus::Open->dotClass(),
        };
    }

    public function getActivityLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'Awaiting customer response',
            'in_progress' => 'In Progress',
            'resolved', 'closed' => 'Read',
            'cancelled' => 'Cancelled',
            default => 'Unread',
        };
    }

    public function getSlaBadgeLabelAttribute(): ?string
    {
        return $this->sla()->badgeLabel();
    }

    public function getSlaBadgeClassAttribute(): string
    {
        return $this->sla()->badgeClass();
    }

    /** The SLA state of this ticket; the rules live in App\Support\TicketSla. */
    public function sla(): TicketSla
    {
        return new TicketSla($this);
    }

    public static function reopenedLifecycleResetAttributes(): array
    {
        return [
            'resolved_at' => null,
            'closed_at' => null,
            'closed_by' => null,
            'cancelled_at' => null,
            'cancellation_reason' => null,
            'satisfaction_rating' => null,
            'satisfaction_comment' => null,
            'technical_user_notified_sla_at' => null,
            'super_users_notified_unchecked_at' => null,
            'super_users_notified_unassigned_sla_at' => null,
            'client_notified_resolved_at' => null,
            'client_notified_auto_close_warning_at' => null,
            'client_notified_closed_at' => null,
        ];
    }

    public function hasBreachedResolutionTarget(?CarbonInterface $asOf = null): bool
    {
        return $this->sla()->hasBreachedResolutionTarget($asOf);
    }

    /**
     * Minutes to resolution/closure, or null while the ticket is still open. Shared by the live SLA badge and the
     * batch SLA reports, so both agree on what "resolved" means.
     */
    public function slaResolutionMinutes(): ?int
    {
        return $this->sla()->resolutionMinutes();
    }

    public static function resolutionTargetMinutes(): int
    {
        return SlaSettings::resolutionTargetMinutes();
    }

    private static function configureAssignmentExistsQuery(mixed $assignmentQuery, ?int $userId = null): void
    {
        $assignmentQuery->selectRaw('1')
            ->from('ticket_assignments')
            ->whereColumn('ticket_assignments.ticket_id', 'tickets.id');

        if ($userId !== null) {
            $assignmentQuery->where('ticket_assignments.user_id', $userId);
        }
    }

    private function resolvedCreationSource(): ?string
    {
        if ($this->is_imported) {
            return self::CREATION_SOURCE_IMPORTED;
        }

        $normalizedSource = self::normalizeCreationSourceValue($this->creation_source);
        if ($normalizedSource !== null) {
            return $normalizedSource;
        }

        /** @var User|null $requester */
        $requester = $this->relationLoaded('user')
            ? $this->user
            : ($this->user_id ? $this->user()->first() : null);

        if (! $requester instanceof User) {
            return null;
        }

        if ($requester->isClient() && (int) ($this->created_by_user_id ?? 0) === (int) $requester->id) {
            return self::CREATION_SOURCE_CLIENT_SELF_SERVICE;
        }

        if ($requester->isClient() && $this->ticket_type === self::TYPE_INTERNAL) {
            return self::CREATION_SOURCE_STAFF_FOR_CLIENT;
        }

        if ($requester->isClient() && (int) ($this->created_by_user_id ?? 0) > 0) {
            return self::CREATION_SOURCE_STAFF_FOR_CLIENT;
        }

        if (! $requester->isClient() && $this->ticket_type === self::TYPE_INTERNAL) {
            return self::CREATION_SOURCE_STAFF_FOR_STAFF;
        }

        return null;
    }

    private function creatorDisplayName(): ?string
    {
        /** @var User|null $creator */
        $creator = $this->relationLoaded('createdByUser')
            ? $this->createdByUser
            : ($this->created_by_user_id ? $this->createdByUser()->first() : null);

        return $creator?->publicDisplayName();
    }

    private function resolvedRequesterSourceSummary(): string
    {
        /** @var User|null $requester */
        $requester = $this->relationLoaded('user')
            ? $this->user
            : ($this->user_id ? $this->user()->first() : null);

        if ($requester?->isClient()) {
            return 'Requester account: client';
        }

        if ($requester instanceof User) {
            return 'Requester account: staff';
        }

        return 'Requester account linked';
    }
}
