<?php

namespace App\Services\Admin\Reports;

use App\Models\Ticket;
use App\Services\Admin\ReportBreakdownService;
use App\Services\Admin\Reports\Concerns\BuildsReportQueryCacheKey;
use App\Support\TicketCategoryBuckets;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ReportStatisticsService
{
    use BuildsReportQueryCacheKey;

    private array $statusBreakdownCache = [];

    private array $priorityBreakdownCache = [];

    private array $categoryBreakdownCache = [];

    private array $majorIssueSummaryCache = [];

    private array $dailyTicketStatisticsCache = [];

    public function __construct(
        private ReportBreakdownService $reportBreakdowns,
    ) {}

    public function buildKpiSummary(Builder $scopedTickets): array
    {
        $openStatusesSqlList = "'".implode("','", Ticket::OPEN_STATUSES)."'";
        $closedStatusesSqlList = "'".implode("','", Ticket::CLOSED_STATUSES)."'";
        $cancelledStatusesSqlList = "'".implode("','", Ticket::CANCELLED_STATUSES)."'";

        $summary = (clone $scopedTickets)
            ->toBase()
            ->selectRaw('COUNT(*) as total_tickets')
            ->selectRaw("SUM(CASE WHEN status IN ({$openStatusesSqlList}) THEN 1 ELSE 0 END) as open_tickets")
            ->selectRaw("SUM(CASE WHEN status IN ({$closedStatusesSqlList}) THEN 1 ELSE 0 END) as closed_tickets")
            ->selectRaw("SUM(CASE WHEN status IN ({$cancelledStatusesSqlList}) THEN 1 ELSE 0 END) as cancelled_tickets")
            ->selectRaw("SUM(CASE WHEN status IN ({$openStatusesSqlList}) AND assigned_to IS NULL AND NOT EXISTS (SELECT 1 FROM ticket_assignments WHERE ticket_assignments.ticket_id = tickets.id) THEN 1 ELSE 0 END) as unassigned_open_tickets")
            ->selectRaw("SUM(CASE WHEN status IN ({$openStatusesSqlList}) AND priority = 'severity_1' THEN 1 ELSE 0 END) as severity_one_open_tickets")
            ->first();
        $summaryRow = is_object($summary) ? (array) $summary : [];

        return [
            'total_tickets' => (int) ($summaryRow['total_tickets'] ?? 0),
            'open_tickets' => (int) ($summaryRow['open_tickets'] ?? 0),
            'closed_tickets' => (int) ($summaryRow['closed_tickets'] ?? 0),
            'cancelled_tickets' => (int) ($summaryRow['cancelled_tickets'] ?? 0),
            'unassigned_open_tickets' => (int) ($summaryRow['unassigned_open_tickets'] ?? 0),
            'severity_one_open_tickets' => (int) ($summaryRow['severity_one_open_tickets'] ?? 0),
        ];
    }

    public function buildReportStatusBreakdownForPeriod(Builder $scopedTickets, Carbon $start, Carbon $end): array
    {
        $breakdown = $this->buildStatusBreakdownForPeriod($scopedTickets, $start, $end);
        $resolvedOnlyCount = (int) $breakdown['resolved'];
        $breakdown['resolved_only'] = $resolvedOnlyCount;
        $breakdown['resolved'] = $resolvedOnlyCount + (int) ($breakdown['closed'] ?? 0);

        return $breakdown;
    }

    public function buildReportStatusBreakdownForAllTime(Builder $scopedTickets): array
    {
        $cacheKey = $this->queryScopeSignature($scopedTickets).'|all_time';
        if (array_key_exists($cacheKey, $this->statusBreakdownCache)) {
            return $this->statusBreakdownCache[$cacheKey];
        }

        $statusCounts = (clone $scopedTickets)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $breakdown = collect(Ticket::STATUSES)->mapWithKeys(fn (string $status) => [
            $status => (int) ($statusCounts[$status] ?? 0),
        ])->all();

        $resolvedOnlyCount = (int) $breakdown['resolved'];
        $breakdown['resolved_only'] = $resolvedOnlyCount;
        $breakdown['resolved'] = $resolvedOnlyCount + (int) ($breakdown['closed'] ?? 0);

        $this->statusBreakdownCache[$cacheKey] = $breakdown;

        return $breakdown;
    }

    public function buildPriorityBreakdownForPeriod(Builder $scopedTickets, Carbon $start, Carbon $end): array
    {
        $cacheKey = $this->queryScopeSignature($scopedTickets)
            .'|'.$start->toIso8601String()
            .'|'.$end->toIso8601String();
        if (array_key_exists($cacheKey, $this->priorityBreakdownCache)) {
            return $this->priorityBreakdownCache[$cacheKey];
        }

        $priorityCounts = (clone $scopedTickets)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('priority, COUNT(*) as count')
            ->groupBy('priority')
            ->pluck('count', 'priority');

        $breakdown = collect(Ticket::PRIORITIES)->mapWithKeys(fn (string $priority) => [
            $priority => (int) ($priorityCounts[$priority] ?? 0),
        ])->all();
        $breakdown = array_merge([
            'pending_review' => (int) ($priorityCounts[''] ?? $priorityCounts[null] ?? 0),
        ], $breakdown);

        $this->priorityBreakdownCache[$cacheKey] = $breakdown;

        return $breakdown;
    }

    public function buildCategoryBreakdownForPeriod(Builder $scopedTickets, Carbon $start, Carbon $end): Collection
    {
        $cacheKey = $this->queryScopeSignature($scopedTickets)
            .'|'.$start->toIso8601String()
            .'|'.$end->toIso8601String();
        if (array_key_exists($cacheKey, $this->categoryBreakdownCache)) {
            return $this->categoryBreakdownCache[$cacheKey];
        }

        $breakdown = $this->reportBreakdowns->buildCategoryBreakdownForScope(
            clone $scopedTickets,
            $start,
            $end
        );

        $this->categoryBreakdownCache[$cacheKey] = $breakdown;

        return $breakdown;
    }

    public function buildCategoryBucketsFromCounts(Collection $categoryCounts): array
    {
        return $this->reportBreakdowns->bucketCategoryCounts($categoryCounts);
    }

    public function buildPriorityBucketsFromCounts(Collection $priorityCounts): array
    {
        return $this->reportBreakdowns->bucketPriorityCounts($priorityCounts);
    }

    public function buildMajorIssueSummary(Builder $scopedTickets, Carbon $start, Carbon $end): array
    {
        $cacheKey = $this->queryScopeSignature($scopedTickets)
            .'|'.$start->toIso8601String()
            .'|'.$end->toIso8601String();
        if (array_key_exists($cacheKey, $this->majorIssueSummaryCache)) {
            return $this->majorIssueSummaryCache[$cacheKey];
        }

        $incidentScopedTickets = (clone $scopedTickets)
            ->whereBetween('tickets.created_at', [$start, $end])
            ->where('priority', 'severity_1');

        $summary = $this->buildMajorIssueAggregate($incidentScopedTickets);
        $this->majorIssueSummaryCache[$cacheKey] = $summary;

        return $summary;
    }

    public function buildMajorIssueSummaryForAllTime(Builder $scopedTickets): array
    {
        $cacheKey = $this->queryScopeSignature($scopedTickets).'|all_time_major_issues';
        if (array_key_exists($cacheKey, $this->majorIssueSummaryCache)) {
            return $this->majorIssueSummaryCache[$cacheKey];
        }

        return $this->majorIssueSummaryCache[$cacheKey] = Cache::remember(
            'report:'.$cacheKey,
            600,
            fn () => $this->buildMajorIssueAggregate((clone $scopedTickets)->where('priority', 'severity_1'))
        );
    }

    public function buildDailyTicketStatisticsForDate(Builder $scopedTickets, Carbon $date): array
    {
        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();
        $statistics = $this->dailyTicketStatisticsForRange(clone $scopedTickets, $start, $end);

        return [
            'mode' => 'day',
            'date' => $start->toDateString(),
            'label' => $start->format('M j, Y'),
            'received' => $statistics['received'],
            'in_progress' => $statistics['in_progress'],
            'resolved' => $statistics['resolved'],
            'closed' => $statistics['closed'],
        ];
    }

    public function buildDailyTicketStatisticsForRange(
        Builder $scopedTickets,
        Carbon $start,
        Carbon $end,
        string $rangeLabel
    ): array {
        $statistics = $this->dailyTicketStatisticsForRange(clone $scopedTickets, $start, $end);

        return [
            'mode' => 'month',
            'date' => null,
            'label' => 'All days in '.$rangeLabel,
            'received' => $statistics['received'],
            'in_progress' => $statistics['in_progress'],
            'resolved' => $statistics['resolved'],
            'closed' => $statistics['closed'],
        ];
    }

    private function buildStatusBreakdownForPeriod(Builder $scopedTickets, Carbon $start, Carbon $end): array
    {
        $cacheKey = $this->cacheKeyForRange($scopedTickets, $start, $end, 'status_breakdown');
        if (array_key_exists($cacheKey, $this->statusBreakdownCache)) {
            return $this->statusBreakdownCache[$cacheKey];
        }

        $statusCounts = (clone $scopedTickets)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $breakdown = collect(Ticket::STATUSES)->mapWithKeys(fn (string $status) => [
            $status => (int) ($statusCounts[$status] ?? 0),
        ])->all();

        $this->statusBreakdownCache[$cacheKey] = $breakdown;

        return $breakdown;
    }

    private function dailyTicketStatisticsForRange(Builder $scopedTickets, Carbon $start, Carbon $end): array
    {
        $cacheKey = $this->cacheKeyForRange($scopedTickets, $start, $end, 'daily_ticket_statistics');
        if (array_key_exists($cacheKey, $this->dailyTicketStatisticsCache)) {
            return $this->dailyTicketStatisticsCache[$cacheKey];
        }

        $closedStatusesSqlList = "'".implode("','", Ticket::CLOSED_STATUSES)."'";
        $summary = (clone $scopedTickets)
            ->toBase()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('COUNT(*) as received')
            ->selectRaw("SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress")
            ->selectRaw("SUM(CASE WHEN status IN ({$closedStatusesSqlList}) THEN 1 ELSE 0 END) as resolved")
            ->selectRaw("SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed")
            ->first();
        $summaryRow = is_object($summary) ? (array) $summary : [];

        $statistics = [
            'received' => (int) ($summaryRow['received'] ?? 0),
            'in_progress' => (int) ($summaryRow['in_progress'] ?? 0),
            'resolved' => (int) ($summaryRow['resolved'] ?? 0),
            'closed' => (int) ($summaryRow['closed'] ?? 0),
        ];

        $this->dailyTicketStatisticsCache[$cacheKey] = $statistics;

        return $statistics;
    }

    private function cacheKeyForRange(Builder $scopedTickets, Carbon $start, Carbon $end, string $suffix): string
    {
        return $this->queryScopeSignature($scopedTickets)
            .'|'.$suffix
            .'|'.$start->toIso8601String()
            .'|'.$end->toIso8601String();
    }

    private function buildMajorIssueAggregate(Builder $incidentScopedTickets): array
    {
        $openStatusesSqlList = "'".implode("','", Ticket::OPEN_STATUSES)."'";
        $summary = (clone $incidentScopedTickets)
            ->toBase()
            ->selectRaw('COUNT(*) as major_count')
            ->selectRaw("SUM(CASE WHEN priority = 'severity_1' THEN 1 ELSE 0 END) as urgent_total")
            ->selectRaw("SUM(CASE WHEN status IN ({$openStatusesSqlList}) THEN 1 ELSE 0 END) as open_major_count")
            ->first();
        $summaryRow = is_object($summary) ? (array) $summary : [];

        $incidentByCategory = $this->reportBreakdowns
            ->categoryCountsForScope(clone $incidentScopedTickets)
            ->map(function (object $row): array {
                $count = (int) ($row->count ?? 0);

                return [
                    'name' => TicketCategoryBuckets::forCategoryName((string) ($row->category_name ?? '')),
                    'count' => $count,
                ];
            })
            ->groupBy('name')
            ->map(fn (Collection $group, string $name) => [
                'name' => $name,
                'count' => (int) $group->sum('count'),
            ])
            ->sortByDesc('count')
            ->values()
            ->take(3);

        return [
            'major_count' => (int) ($summaryRow['major_count'] ?? 0),
            'open_major_count' => (int) ($summaryRow['open_major_count'] ?? 0),
            'urgent_total' => (int) ($summaryRow['urgent_total'] ?? 0),
            'top_categories' => $incidentByCategory,
        ];
    }
}
