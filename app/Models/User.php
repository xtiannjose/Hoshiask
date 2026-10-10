<?php

namespace App\Models;

use App\Services\SupportBrandingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @property Carbon|null $last_seen_at
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected static array $departmentBrandAssetCache = [];

    protected static ?array $allowedDepartmentCache = null;

    public const ROLE_CLIENT = 'client';

    public const ROLE_SHADOW = 'shadow';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_TECHNICAL = 'technical';

    public const ROLE_SUPER_USER = 'super_user';

    public const TICKET_CONSOLE_ROLES = [
        self::ROLE_SHADOW,
        self::ROLE_ADMIN,
        self::ROLE_SUPER_USER,
        self::ROLE_TECHNICAL,
    ];

    public const ADMIN_LEVEL_ROLES = [
        self::ROLE_SHADOW,
        self::ROLE_ADMIN,
        self::ROLE_SUPER_USER,
    ];

    public const TICKET_ASSIGNABLE_ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_SUPER_USER,
        self::ROLE_TECHNICAL,
    ];

    /** Staff who can log tickets on a client's behalf. */
    public const TICKET_CREATOR_ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_SUPER_USER,
        self::ROLE_TECHNICAL,
    ];

    /** Top-level administrators (departments, staff accounts, ticket deletion). */
    public const SYSTEM_ADMIN_ROLES = [
        self::ROLE_SHADOW,
        self::ROLE_ADMIN,
    ];

    /** Highest to lowest; used to sort user lists. */
    public const ROLE_HIERARCHY = [
        self::ROLE_SHADOW,
        self::ROLE_ADMIN,
        self::ROLE_SUPER_USER,
        self::ROLE_TECHNICAL,
        self::ROLE_CLIENT,
    ];

    public const USER_MANAGEMENT_CLIENT_ONLY_ROLES = [
        self::ROLE_CLIENT,
    ];

    public const SYSTEM_RESERVED_EMAIL_DOMAIN = '@system.local';

    protected $fillable = [
        'name',
        'username',
        'email',
        'phone',
        'department',
        'client_notes',
        'role',
        'password',
        'is_active',
        'is_profile_locked',
        'must_change_password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_profile_locked' => 'boolean',
            'must_change_password' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if (trim((string) $user->username) !== '') {
                return;
            }

            $user->username = self::generateAvailableUsername((string) $user->name);
        });

        static::updating(function (User $user): void {
            if (trim((string) $user->username) !== '') {
                return;
            }

            $user->username = self::generateAvailableUsername((string) $user->name, (int) $user->id);
        });
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function assignedTickets(): BelongsToMany
    {
        return $this->belongsToMany(Ticket::class, 'ticket_assignments')
            ->withTimestamps();
    }

    public function ticketReplies(): HasMany
    {
        return $this->hasMany(TicketReply::class);
    }

    /**
     * True for admin/shadow roles only — does NOT include the separate `super_user` role.
     */
    public function isSuperAdmin(): bool
    {
        return in_array($this->normalizedRole(), self::SYSTEM_ADMIN_ROLES, true);
    }

    public function isShadow(): bool
    {
        return $this->normalizedRole() === self::ROLE_SHADOW;
    }

    public function isClient(): bool
    {
        return $this->normalizedRole() === self::ROLE_CLIENT;
    }

    public function isTechnician(): bool
    {
        return $this->normalizedRole() === self::ROLE_TECHNICAL;
    }

    public function mustChangePassword(): bool
    {
        return (bool) $this->must_change_password;
    }

    /**
     * Invalidate this user's server-side session records, e.g. after a password change,
     * so a stolen session cookie stops working once the account is secured.
     */
    public function invalidateSessions(?string $exceptSessionId = null): int
    {
        return DB::table('sessions')
            ->where('user_id', $this->id)
            ->when($exceptSessionId, fn ($query) => $query->where('id', '!=', $exceptSessionId))
            ->delete();
    }

    /**
     * Sign this user out on every device. Their session records are dropped and the
     * remember-me token is rotated, since a saved "remember me" cookie would otherwise
     * sign them straight back in. Returns how many sessions were ended.
     */
    public function forceSignOut(): int
    {
        $endedSessions = $this->invalidateSessions();

        // Written directly so the account's updated_at is not touched.
        $rememberToken = Str::random(60);
        DB::table($this->getTable())
            ->where('id', $this->id)
            ->update(['remember_token' => $rememberToken]);
        $this->setRememberToken($rememberToken);
        $this->syncOriginalAttribute('remember_token');

        return $endedSessions;
    }

    public function canAccessAdminTickets(): bool
    {
        return in_array($this->normalizedRole(), self::TICKET_CONSOLE_ROLES, true);
    }

    public function canManageTickets(): bool
    {
        return in_array($this->normalizedRole(), self::ADMIN_LEVEL_ROLES, true);
    }

    public function canManageUsers(): bool
    {
        return $this->canManageTickets();
    }

    public function canDeleteTickets(): bool
    {
        return $this->isSuperAdmin();
    }

    public function canCreateClientTickets(): bool
    {
        return in_array($this->normalizedRole(), self::TICKET_CREATOR_ROLES, true);
    }

    public function canSetManualTicketDates(): bool
    {
        return in_array($this->normalizedRole(), [
            self::ROLE_SUPER_USER,
            self::ROLE_ADMIN,
            self::ROLE_SHADOW,
        ], true);
    }

    public function canManageTicketType(): bool
    {
        return $this->normalizedRole() === self::ROLE_SUPER_USER;
    }

    public function canManageStaffAccounts(): bool
    {
        return in_array($this->normalizedRole(), self::SYSTEM_ADMIN_ROLES, true);
    }

    public function canManageUserTarget(User $targetUser): bool
    {
        if ($this->isShadow()) {
            return true;
        }

        $targetRole = $targetUser->normalizedRole();
        if ($this->normalizedRole() === self::ROLE_ADMIN) {
            return ! in_array($targetRole, [self::ROLE_SHADOW, self::ROLE_ADMIN], true);
        }

        return in_array($targetRole, self::USER_MANAGEMENT_CLIENT_ONLY_ROLES, true);
    }

    public function canEditManagedUserPassword(User $targetUser): bool
    {
        if ($this->isShadow()) {
            return true;
        }

        if ($this->isSuperAdmin()) {
            return ! $targetUser->isShadow();
        }

        return ! (
            $this->normalizedRole() === self::ROLE_SUPER_USER
            && $targetUser->normalizedRole() === self::ROLE_CLIENT
        );
    }

    public function manageableUserCreationRoles(): array
    {
        $roles = self::USER_MANAGEMENT_CLIENT_ONLY_ROLES;

        if ($this->isShadow()) {
            return array_merge($roles, [
                self::ROLE_ADMIN,
                self::ROLE_TECHNICAL,
                self::ROLE_SUPER_USER,
            ]);
        }

        if ($this->normalizedRole() === self::ROLE_ADMIN) {
            return array_merge($roles, [
                self::ROLE_TECHNICAL,
                self::ROLE_SUPER_USER,
            ]);
        }

        return $roles;
    }

    public function manageableUserRoleOptions(?User $targetUser = null): array
    {
        $roles = $this->manageableUserCreationRoles();

        if (
            $targetUser instanceof User
            && $this->canManageUserTarget($targetUser)
        ) {
            $roles[] = $targetUser->normalizedRole();
        }

        return array_values(array_unique($roles));
    }

    public function isAdminLevel(): bool
    {
        return $this->canManageTickets();
    }

    public function normalizedRole(): string
    {
        return self::normalizeRole($this->role);
    }

    public function publicRole(): string
    {
        return self::publicRoleValue($this->role);
    }

    public function publicDisplayName(): string
    {
        return $this->isShadow()
            ? self::supportTeamName()
            : (string) $this->name;
    }

    public static function supportDepartment(): string
    {
        return app(SupportBrandingService::class)->supportDepartment();
    }

    public static function supportBrandName(): string
    {
        return app(SupportBrandingService::class)->supportBrandName();
    }

    public static function supportOrganizationName(): string
    {
        return app(SupportBrandingService::class)->supportOrganizationName();
    }

    public static function supportTeamName(): string
    {
        return app(SupportBrandingService::class)->supportTeamName();
    }

    public static function supportLogoPath(): string
    {
        return app(SupportBrandingService::class)->supportLogoPath();
    }

    public static function supportLogoUrl(): string
    {
        return app(SupportBrandingService::class)->supportLogoUrl();
    }

    public static function normalizeRole(?string $role): string
    {
        return strtolower(trim((string) $role));
    }

    public static function publicRoleValue(?string $role): string
    {
        $normalizedRole = self::normalizeRole($role);

        return $normalizedRole === self::ROLE_SHADOW
            ? self::ROLE_ADMIN
            : $normalizedRole;
    }

    public static function publicRoleLabel(?string $role): string
    {
        return ucfirst(str_replace('_', ' ', self::publicRoleValue($role)));
    }

    public function isSystemReplacementAccount(): bool
    {
        return self::emailUsesReservedSystemDomain($this->email);
    }

    public static function managedDepartmentForRole(string $role, string $department): string
    {
        $trimmedDepartment = trim($department);

        return $trimmedDepartment !== ''
            ? $trimmedDepartment
            : self::supportDepartment();
    }

    public static function emailUsesReservedSystemDomain(?string $email): bool
    {
        return str_ends_with(strtolower(trim((string) $email)), self::SYSTEM_RESERVED_EMAIL_DOMAIN);
    }

    public static function departmentBrandKey(?string $department, ?string $role = null): string
    {
        return app(SupportBrandingService::class)->departmentBrandKey($department, $role);
    }

    public static function departmentBrandAssets(?string $department, ?string $role = null): array
    {
        $cacheKey = mb_strtolower(trim((string) $department)).'|'.self::normalizeRole($role);

        if (isset(self::$departmentBrandAssetCache[$cacheKey])) {
            return self::$departmentBrandAssetCache[$cacheKey];
        }
        $assets = app(SupportBrandingService::class)->departmentBrandAssets($department, $role);

        self::$departmentBrandAssetCache[$cacheKey] = $assets;

        return $assets;
    }

    public static function allowedDepartments(): array
    {
        if (self::$allowedDepartmentCache !== null) {
            return self::$allowedDepartmentCache;
        }

        // Departments are managed in Admin > Departments (the departments table).
        return self::$allowedDepartmentCache = Department::query()
            ->orderByRaw('LOWER(name)')
            ->pluck('name')
            ->all();
    }

    public static function flushDepartmentCaches(): void
    {
        self::$allowedDepartmentCache = null;
        self::$departmentBrandAssetCache = [];
    }

    public static function generateAvailableUsername(string $name, ?int $ignoreUserId = null): string
    {
        $baseUsername = Str::of($name)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '.')
            ->trim('.')
            ->value();

        if ($baseUsername === '') {
            $baseUsername = 'user';
        }

        $baseUsername = mb_substr($baseUsername, 0, 45);
        $candidate = $baseUsername;
        $suffix = 1;

        while (self::query()
            ->when($ignoreUserId !== null, fn (Builder $query) => $query->where('id', '!=', $ignoreUserId))
            ->whereRaw('LOWER(username) = ?', [strtolower($candidate)])
            ->exists()
        ) {
            $suffix++;
            $candidate = mb_substr($baseUsername, 0, 40).'.'.$suffix;
        }

        return $candidate;
    }
}
