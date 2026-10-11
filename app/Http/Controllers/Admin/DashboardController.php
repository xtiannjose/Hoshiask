<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\User;
use App\Services\DashboardSnapshotService;
use App\Support\BusinessHours;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardSnapshotService $dashboardSnapshot,
    ) {}

    public function index(Request $request)
    {
        $user = auth()->user();
        $scopedTickets = $this->scopedTicketQueryForUser($user);
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();
        $liveSnapshotToken = $this->buildDashboardSnapshotToken(clone $scopedTickets);

        if ($request->boolean('heartbeat')) {
            return response()->json([
                'token' => $liveSnapshotToken,
            ]);
        }

        $stats = [
            'total_tickets' => (clone $scopedTickets)->count(),
            'open_tickets' => (clone $scopedTickets)->open()->count(),
            'severity_one_tickets' => (clone $scopedTickets)->byPriority('severity_1')->open()->count(),
        ];

        if (! $user->isTechnician()) {
            $stats['attention_tickets'] = (clone $scopedTickets)->whereIn('status', Ticket::SLA_CLOCK_STATUSES)
                ->where('created_at', '<=', BusinessHours::cutoff(Ticket::NEEDS_ATTENTION_AFTER_HOURS * 60))
                ->count();
        }

        [$recentStart, $recentEnd] = $this->dashboardSnapshot->recentWindow();

        $recentTickets = (clone $scopedTickets)
            ->whereBetween('created_at', [$recentStart, $recentEnd])
            ->with(['user', 'category', 'assignedUser', 'assignedUsers'])
            ->latest()
            ->take(20)
            ->get();

        $ticketsByStatus = (clone $scopedTickets)
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status');
        $ticketsByStatusDisplay = collect([
            'open' => (int) ($ticketsByStatus->get('open', 0)),
            'in_progress' => (int) ($ticketsByStatus->get('in_progress', 0)),
            'pending' => (int) ($ticketsByStatus->get('pending', 0)),
            'resolved' => (int) ($ticketsByStatus->get('resolved', 0)) + (int) ($ticketsByStatus->get('closed', 0)),
            'closed' => (int) ($ticketsByStatus->get('closed', 0)),
            'cancelled' => (int) ($ticketsByStatus->get('cancelled', 0)),
        ]);

        $ticketsByPriority = (clone $scopedTickets)
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->selectRaw('priority, COUNT(*) as count')
            ->groupBy('priority')
            ->get()
            ->pluck('count', 'priority');
        $ticketsByPriority = collect([
            '__pending__' => (int) ($ticketsByPriority[''] ?? $ticketsByPriority[null] ?? 0),
            'severity_1' => (int) ($ticketsByPriority['severity_1'] ?? 0),
            'severity_2' => (int) ($ticketsByPriority['severity_2'] ?? 0),
            'severity_3' => (int) ($ticketsByPriority['severity_3'] ?? 0),
        ]);

        $ticketsTrend = $this->buildTicketsTrend($scopedTickets);
        $topIssues = $this->buildTopIssues($scopedTickets, $monthStart, $monthEnd);

        $isTechnical = $user->isTechnician();
        $dashboardTitle = $isTechnical ? 'Technical Dashboard' : 'Support Dashboard';
        $dashboardSubtitle = $isTechnical
            ? 'Monitor assigned work, aging tickets, and severities from one place.'
            : 'Operational overview, ticket health, and quick actions in one place.';

        return view('admin.dashboard', compact(
            'stats',
            'recentTickets',
            'ticketsByStatus',
            'ticketsByStatusDisplay',
            'ticketsByPriority',
            'ticketsTrend',
            'topIssues',
            'dashboardTitle',
            'dashboardSubtitle',
            'isTechnical',
            'liveSnapshotToken'
        ));
    }

    /**
     * Ticket volume for each of the last 30 days, zero-filled so the trend chart
     * doesn't misrepresent quiet days as missing data points.
     *
     * @param  Builder<Ticket>  $scopedTickets
     * @return array<int, array{date: string, count: int}>
     */
    private function buildTicketsTrend(Builder $scopedTickets): array
    {
        $rangeStart = Carbon::now()->subDays(29)->startOfDay();
        $countsByDate = (clone $scopedTickets)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', $rangeStart)
            ->groupBy('date')
            ->pluck('count', 'date');

        return collect(range(0, 29))
            ->map(function (int $offset) use ($countsByDate) {
                $date = Carbon::now()->subDays(29 - $offset)->toDateString();

                return ['date' => $date, 'count' => (int) ($countsByDate[$date] ?? 0)];
            })
            ->all();
    }

    /**
     * Top 5 recurring ticket categories this month, for the dashboard's "Top Issues" panel.
     *
     * @param  Builder<Ticket>  $scopedTickets
     */
    private function buildTopIssues(Builder $scopedTickets, Carbon $monthStart, Carbon $monthEnd): Collection
    {
        return (clone $scopedTickets)
            ->join('categories', 'categories.id', '=', 'tickets.category_id')
            ->whereBetween('tickets.created_at', [$monthStart, $monthEnd])
            ->toBase()
            ->selectRaw('categories.id as category_id, categories.name as category_name, COUNT(*) as ticket_count')
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('ticket_count')
            ->take(5)
            ->get();
    }

    /**
     * @return Builder<Ticket>
     */
    private function scopedTicketQueryForUser(User $user): Builder
    {
        $query = Ticket::query();

        if ($user->isTechnician()) {
            $query->where(function (Builder $builder) use ($user) {
                Ticket::applyAssignedToConstraint($builder, (int) $user->id)
                    ->orWhere(function (Builder $requesterQuery) use ($user) {
                        Ticket::applyInternalRequesterConstraint($requesterQuery, (int) $user->id);
                    });
            });
        }

        return $query;
    }

    /**
     * @param  Builder<Ticket>  $scopedTickets
     */
    private function buildDashboardSnapshotToken(Builder $scopedTickets): string
    {
        $latestUpdatedAt = (clone $scopedTickets)->max('updated_at');
        $totalTickets = (clone $scopedTickets)->count();

        return $this->dashboardSnapshot->buildToken(
            $latestUpdatedAt ? (string) $latestUpdatedAt : null,
            ['total' => $totalTickets]
        );
    }
}
