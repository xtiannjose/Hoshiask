<?php

namespace App\Services\Client;

use App\Models\Ticket;
use App\Models\User;
use App\Services\DashboardSnapshotService;
use App\Support\LikePattern;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ClientTicketIndexService
{
    public function __construct(
        private DashboardSnapshotService $dashboardSnapshot,
    ) {}

    /**
     * @return Builder<Ticket>
     */
    public function scopedTicketQueryFor(User $user): Builder
    {
        return Ticket::query()
            ->whereBelongsTo($user, 'user')
            ->with(['category', 'assignedUser', 'assignedUsers']);
    }

    public function resolveActiveTab(string $requestedTab): string
    {
        return in_array($requestedTab, ['tickets', 'history'], true)
            ? $requestedTab
            : 'tickets';
    }

    public function resolveSelectedStatus(string $requestedStatus, string $activeTab): string
    {
        $selectedStatus = trim($requestedStatus);
        if ($selectedStatus === '') {
            $selectedStatus = 'all';
        }

        return in_array($selectedStatus, $this->allowedStatusesForTab($activeTab), true)
            ? $selectedStatus
            : 'all';
    }

    /**
     * @param  Builder<Ticket>  $query
     */
    public function applyTabScope(Builder $query, string $activeTab): void
    {
        $query->whereIn(
            'status',
            $activeTab === 'history'
                ? [...Ticket::CLOSED_STATUSES, ...Ticket::CANCELLED_STATUSES]
                : Ticket::OPEN_STATUSES
        );
    }

    /**
     * @param  Builder<Ticket>  $query
     */
    public function applyFilters(Builder $query, Request $request, string $selectedStatus): void
    {
        $query
            ->when($selectedStatus !== 'all', function (Builder $builder) use ($selectedStatus) {
                if (in_array($selectedStatus, ['open', 'open_group'], true)) {
                    $builder->whereIn('status', Ticket::OPEN_STATUSES);

                    return;
                }

                $builder->where('status', $selectedStatus);
            })
            ->when($request->filled('search'), function (Builder $builder) use ($request) {
                $pattern = LikePattern::contains(mb_strtolower($request->string('search')->toString()));
                $like = 'LIKE ? '.LikePattern::LIKE_ESCAPE;

                $builder->where(function (Builder $nested) use ($pattern, $like) {
                    $nested->whereRaw("LOWER(subject) {$like}", [$pattern])
                        ->orWhereRaw("LOWER(ticket_number) {$like}", [$pattern])
                        ->orWhereRaw("LOWER(description) {$like}", [$pattern]);
                });
            });
    }

    /**
     * @param  Builder<Ticket>  $ticketQuery
     */
    public function buildSnapshotToken(Builder $ticketQuery): string
    {
        return $this->buildSnapshotTokenFromSummary($this->buildSummarySnapshot($ticketQuery));
    }

    public function buildSnapshotTokenFromSummary(array $summary): string
    {
        return $this->dashboardSnapshot->buildToken(
            ! empty($summary['latest_updated_at']) ? (string) $summary['latest_updated_at'] : null,
            [
                'open_tickets' => (int) ($summary['open_tickets'] ?? 0),
                'total_tickets' => (int) ($summary['total_tickets'] ?? 0),
            ]
        );
    }

    /**
     * @param  Builder<Ticket>  $ticketQuery
     * @return array<string, mixed>
     */
    public function buildSummarySnapshot(Builder $ticketQuery): array
    {
        $openStatusesSqlList = "'".implode("','", Ticket::OPEN_STATUSES)."'";
        $summary = (clone $ticketQuery)
            ->toBase()
            ->selectRaw('COUNT(*) as total_tickets')
            ->selectRaw("SUM(CASE WHEN status IN ({$openStatusesSqlList}) THEN 1 ELSE 0 END) as open_tickets")
            ->selectRaw("SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tickets")
            ->selectRaw('MAX(updated_at) as latest_updated_at')
            ->first();

        return is_object($summary) ? (array) $summary : [];
    }

    private function allowedStatusesForTab(string $activeTab): array
    {
        return $activeTab === 'history'
            ? array_merge(['all'], Ticket::CLOSED_STATUSES, Ticket::CANCELLED_STATUSES)
            : array_merge(['all', 'open_group'], Ticket::OPEN_STATUSES);
    }
}
