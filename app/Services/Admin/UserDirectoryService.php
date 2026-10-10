<?php

namespace App\Services\Admin;

use App\Models\Ticket;
use App\Models\User;
use App\Support\LikePattern;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class UserDirectoryService
{
    /** How many accounts the new-ticket requester picker lists at once; the rest is reached by searching. */
    public const REQUESTER_ACCOUNT_LIMIT = 50;

    /**
     * The accounts a staff member may pick as the requester of a new ticket: active clients for a client ticket,
     * active staff who can be assigned tickets for a staff ticket. Returns the first accounts by name, or the
     * matches for $search, and how many accounts match in all. $alsoIncludeIds are listed even when they fall
     * outside the limit (the account chosen before a validation error, or the signed-in staff member).
     *
     * @param  'client'|'support'  $group
     * @param  list<int>  $alsoIncludeIds
     * @return array{accounts: list<array{value: string, label: string, name: string, email: ?string, phone: ?string, group: string, role: ?string}>, total: int}
     */
    public function requesterAccounts(string $group, string $search = '', array $alsoIncludeIds = []): array
    {
        $columns = ['id', 'name', 'email', 'phone', 'role'];
        $matching = $this->requesterAccountScope($group);

        if (trim($search) !== '') {
            $this->applySearchFilter($matching, trim($search));
        }

        $accounts = (clone $matching)
            ->orderBy('name')
            ->orderBy('id')
            ->limit(self::REQUESTER_ACCOUNT_LIMIT)
            ->get($columns);

        $missingIds = array_values(array_diff(
            array_filter(array_map('intval', $alsoIncludeIds)),
            $accounts->pluck('id')->map(fn ($id): int => (int) $id)->all()
        ));

        if ($missingIds !== []) {
            $accounts = $accounts->concat(
                $this->requesterAccountScope($group)->whereKey($missingIds)->orderBy('name')->orderBy('id')->get($columns)
            );
        }

        return [
            'accounts' => $accounts->map(fn (User $account): array => [
                'value' => (string) $account->id,
                'label' => (string) $account->name,
                'name' => (string) $account->name,
                'email' => $account->email,
                'phone' => $account->phone,
                'group' => $group,
                'role' => $group === 'support' ? $account->role : null,
            ])->values()->all(),
            'total' => (clone $matching)->count(),
        ];
    }

    /**
     * @return Builder<User>
     */
    private function requesterAccountScope(string $group): Builder
    {
        return User::query()
            ->where('is_active', true)
            ->when(
                $group === 'support',
                fn (Builder $query) => $query->whereIn('role', User::TICKET_ASSIGNABLE_ROLES),
                fn (Builder $query) => $query->where('role', User::ROLE_CLIENT),
            );
    }

    /**
     * Case-insensitive "contains" over the fields staff know an account by.
     *
     * @param  Builder<User>  $query
     */
    private function applySearchFilter(Builder $query, string $search): void
    {
        $pattern = LikePattern::contains(mb_strtolower($search));
        $like = 'LIKE ? '.LikePattern::LIKE_ESCAPE;

        $query->where(function (Builder $builder) use ($pattern, $like) {
            $builder->whereRaw("LOWER(COALESCE(username, '')) {$like}", [$pattern])
                ->orWhereRaw("LOWER(COALESCE(name, '')) {$like}", [$pattern])
                ->orWhereRaw("LOWER(COALESCE(email, '')) {$like}", [$pattern])
                ->orWhereRaw("LOWER(COALESCE(phone, '')) {$like}", [$pattern])
                ->orWhereRaw("LOWER(COALESCE(department, '')) {$like}", [$pattern]);
        });
    }

    public function buildIndexViewData(Request $request, User $currentUser, string $segment): array
    {
        if (! $this->canManageStaffAccounts($currentUser)) {
            $segment = 'clients';
        }

        $query = User::query();
        $departmentsQuery = User::query();

        $this->excludeSystemReservedEmails($query);
        $this->excludeSystemReservedEmails($departmentsQuery);

        $this->applyVisibilityScope($query, $currentUser);
        $this->applyVisibilityScope($departmentsQuery, $currentUser);
        $this->applySegmentScope($query, $segment, $currentUser);
        $this->applySegmentScope($departmentsQuery, $segment, $currentUser);

        if ($request->filled('role') && $request->role !== 'all') {
            $requestedRole = User::normalizeRole($request->string('role')->toString());

            if ($requestedRole === User::ROLE_ADMIN) {
                $query->whereIn('role', [
                    User::ROLE_SHADOW,
                    User::ROLE_ADMIN,
                ]);
            } elseif ($requestedRole === User::ROLE_TECHNICAL) {
                $query->where('role', User::ROLE_TECHNICAL);
            } else {
                $query->where('role', $requestedRole);
            }
        }

        if ($request->filled('search')) {
            $this->applySearchFilter($query, trim((string) $request->search));
        }

        if ($request->filled('department') && $request->department !== 'all') {
            $query->where('department', $request->department);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('is_active', $request->status === 'active');
        }

        $roleRankCases = implode(' ', array_map(
            fn (int $rank) => 'WHEN ? THEN '.($rank + 1),
            array_keys(User::ROLE_HIERARCHY)
        ));

        $users = $query
            ->orderByRaw(
                "CASE role {$roleRankCases} ELSE ".(count(User::ROLE_HIERARCHY) + 1).' END',
                User::ROLE_HIERARCHY
            )
            ->orderByRaw("LOWER(COALESCE(name, ''))")
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        $departments = $departmentsQuery
            ->whereNotNull('department')
            ->where('department', '!=', '')
            ->distinct()
            ->pluck('department')
            ->sort(fn (string $left, string $right) => strnatcasecmp($left, $right))
            ->values();

        return [
            'users' => $users,
            'departments' => $departments,
            'availableRolesFilter' => $this->availableRolesFilterForSegment($segment, $currentUser),
            'segment' => $segment,
            'segmentTitle' => $segment === 'clients' ? 'Client Accounts' : 'Staff Accounts',
        ];
    }

    public function departmentForRole(string $role, string $department): string
    {
        return User::managedDepartmentForRole($role, $department);
    }

    public function normalizeRoleForPersistence(string $role): string
    {
        return User::normalizeRole($role);
    }

    public function buildUserTicketStatistics(User $user): array
    {
        $isClient = $user->normalizedRole() === User::ROLE_CLIENT;
        $relatedTickets = $isClient
            ? Ticket::query()->where('user_id', $user->id)
            : Ticket::query()->where(function (Builder $query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere(function (Builder $assignmentQuery) use ($user) {
                        Ticket::applyAssignedToConstraint($assignmentQuery, (int) $user->id);
                    });
            });
        $assignedTickets = $isClient
            ? null
            : Ticket::applyAssignedToConstraint(Ticket::query(), (int) $user->id);

        $openPlaceholders = implode(',', array_fill(0, count(Ticket::OPEN_STATUSES), '?'));
        $closedPlaceholders = implode(',', array_fill(0, count(Ticket::CLOSED_STATUSES), '?'));
        $cancelledPlaceholders = implode(',', array_fill(0, count(Ticket::CANCELLED_STATUSES), '?'));
        $counts = (clone $relatedTickets)
            ->selectRaw(
                'COUNT(*) as total_tickets, '.
                "SUM(CASE WHEN status IN ({$openPlaceholders}) THEN 1 ELSE 0 END) as open_tickets, ".
                "SUM(CASE WHEN status IN ({$closedPlaceholders}) THEN 1 ELSE 0 END) as closed_tickets, ".
                "SUM(CASE WHEN status IN ({$cancelledPlaceholders}) THEN 1 ELSE 0 END) as cancelled_tickets",
                [...Ticket::OPEN_STATUSES, ...Ticket::CLOSED_STATUSES, ...Ticket::CANCELLED_STATUSES]
            )
            ->first();

        return [
            'total_tickets' => (int) $counts->getAttribute('total_tickets'),
            'open_tickets' => (int) $counts->getAttribute('open_tickets'),
            'closed_tickets' => (int) $counts->getAttribute('closed_tickets'),
            'cancelled_tickets' => (int) $counts->getAttribute('cancelled_tickets'),
            'assigned_tickets' => $assignedTickets ? (clone $assignedTickets)->count() : null,
            'show_assigned' => ! $isClient,
        ];
    }

    public function buildUserStatisticsLinks(User $user, bool $showAssigned): array
    {
        $ticketFilter = $user->normalizedRole() === User::ROLE_CLIENT
            ? ['account_id' => $user->id]
            : ['related_user_id' => $user->id];

        return [
            'total_tickets' => route('admin.tickets.index', array_merge($ticketFilter, ['tab' => 'all'])),
            'open_tickets' => route('admin.tickets.index', array_merge($ticketFilter, ['tab' => 'tickets'])),
            'closed_tickets' => route('admin.tickets.index', array_merge($ticketFilter, ['tab' => 'history'])),
            'cancelled_tickets' => route('admin.tickets.index', array_merge($ticketFilter, ['tab' => 'history', 'status' => 'cancelled'])),
            'assigned_tickets' => $showAssigned
                ? route('admin.tickets.index', ['tab' => 'tickets', 'assigned_to' => $user->id])
                : null,
        ];
    }

    public function recentTicketsForUser(User $user): Collection
    {
        $normalizedRole = $user->normalizedRole();

        return Ticket::query()
            ->where(function (Builder $query) use ($user, $normalizedRole) {
                $query->where('user_id', $user->id);

                if ($normalizedRole !== User::ROLE_CLIENT) {
                    $query->orWhere(function (Builder $assignmentQuery) use ($user) {
                        Ticket::applyAssignedToConstraint($assignmentQuery, (int) $user->id);
                    });
                }
            })
            ->latest()
            ->take(5)
            ->get();
    }

    public function canManageStaffAccounts(User $currentUser): bool
    {
        return $currentUser->canManageStaffAccounts();
    }

    public function cannotManageTarget(User $currentUser, User $targetUser): bool
    {
        return Gate::forUser($currentUser)->denies('view', $targetUser);
    }

    private function manageableRolesForAdmin(): array
    {
        return User::USER_MANAGEMENT_CLIENT_ONLY_ROLES;
    }

    private function excludeSystemReservedEmails(Builder $query): void
    {
        $query->where(function (Builder $builder) {
            $builder->whereNull('email')
                ->orWhere('email', 'not like', '%'.User::SYSTEM_RESERVED_EMAIL_DOMAIN);
        });
    }

    private function applyVisibilityScope(Builder $query, User $currentUser): void
    {
        if ($currentUser->isShadow()) {
            return;
        }

        if ($currentUser->normalizedRole() === User::ROLE_ADMIN) {
            $query->where('role', '!=', User::ROLE_SHADOW);

            return;
        }

        if (! $currentUser->isSuperAdmin()) {
            $query->where('id', '!=', $currentUser->id)
                ->whereIn('role', $this->manageableRolesForAdmin());
        }
    }

    private function applySegmentScope(Builder $query, string $segment, User $currentUser): void
    {
        if ($segment === 'clients' || ! $this->canManageStaffAccounts($currentUser)) {
            $query->where('role', User::ROLE_CLIENT);

            return;
        }

        $query->whereIn('role', [
            User::ROLE_SHADOW,
            User::ROLE_ADMIN,
            User::ROLE_SUPER_USER,
            User::ROLE_TECHNICAL,
        ]);

        if ($currentUser->normalizedRole() === User::ROLE_ADMIN) {
            $query->where('role', '!=', User::ROLE_SHADOW);
        }
    }

    private function availableRolesFilterForSegment(string $segment, User $currentUser): array
    {
        if ($segment === 'clients') {
            return [User::ROLE_CLIENT];
        }

        if ($this->canManageStaffAccounts($currentUser)) {
            return [
                User::ROLE_ADMIN,
                User::ROLE_SUPER_USER,
                User::ROLE_TECHNICAL,
            ];
        }

        return [User::ROLE_CLIENT];
    }
}
