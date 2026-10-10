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
            $search = trim((string) $request->search);
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
