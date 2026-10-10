<?php

namespace App\Services\Admin\Reports;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReportPageInsightsService
{
    public function __construct(
        private ReportPageQueryMetricsService $queryMetrics,
        private ReportStatisticsService $reportStatistics,
    ) {}

    public function buildStats(array $kpiSummary): array
    {
        $totalTickets = (int) ($kpiSummary['total_tickets'] ?? 0);
        $resolvedTicketsCount = (int) ($kpiSummary['closed_tickets'] ?? 0);
        // Cancelled tickets were withdrawn, not left unresolved, so they are not part of the rate's base.
        $resolvableTickets = max($totalTickets - (int) ($kpiSummary['cancelled_tickets'] ?? 0), 0);

        return [
            'total_tickets' => $totalTickets,
            'open_tickets' => (int) ($kpiSummary['open_tickets'] ?? 0),
            'closed_tickets' => $resolvedTicketsCount,
            'unassigned_open_tickets' => (int) ($kpiSummary['unassigned_open_tickets'] ?? 0),
            'severity_one_open_tickets' => (int) ($kpiSummary['severity_one_open_tickets'] ?? 0),
            'resolution_rate' => $resolvableTickets > 0 ? round(($resolvedTicketsCount / $resolvableTickets) * 100, 1) : 0,
        ];
    }

    public function buildPeriodOverview(
        array $selectedMonthRange,
        Carbon $selectedPeriodStart,
        Carbon $selectedPeriodEnd,
        int $totalTicketsThisPeriod,
        array $majorIssueSummary,
        array $periodStatusSummary,
        int $backlogThisPeriod,
    ): array {
        return [
            'label' => (string) $selectedMonthRange['label'],
            'start' => $selectedPeriodStart->toDateString(),
            'end' => $selectedPeriodEnd->toDateString(),
            'total_tickets' => $totalTicketsThisPeriod,
            'major_issues_count' => (int) ($majorIssueSummary['major_count'] ?? 0),
            'total_created' => $totalTicketsThisPeriod,
            'open' => (int) ($periodStatusSummary['open'] ?? 0),
            'in_progress' => (int) ($periodStatusSummary['in_progress'] ?? 0),
            'pending' => (int) ($periodStatusSummary['pending'] ?? 0),
            'resolved' => (int) ($periodStatusSummary['resolved'] ?? 0),
            'closed' => (int) ($periodStatusSummary['closed'] ?? 0),
            'backlog_end' => $backlogThisPeriod,
        ];
    }

    /**
     * @return array{selectedMonthStatuses: array<string, int>, selectedMonthPriorities: array<string, int>, selectedMonthCategories: Collection<int, array<string, int|string>>}
     */
    public function buildSelectedMonthBreakdowns(
        Builder $scopedTickets,
        bool $selectedMonthIsAllTime,
        array $selectedMonthRange,
    ): array {
        return [
            'selectedMonthStatuses' => $this->queryMetrics->statusBreakdownForScope(
                clone $scopedTickets,
                $selectedMonthIsAllTime,
                $selectedMonthRange['start'],
                $selectedMonthRange['end']
            ),
            'selectedMonthPriorities' => $selectedMonthIsAllTime
                ? $this->reportStatistics->buildPriorityBreakdownForAllTime(clone $scopedTickets)
                : $this->reportStatistics->buildPriorityBreakdownForPeriod(
                    clone $scopedTickets,
                    $selectedMonthRange['start'],
                    $selectedMonthRange['end']
                ),
            'selectedMonthCategories' => $selectedMonthIsAllTime
                ? $this->reportStatistics->buildCategoryBreakdownForAllTime(clone $scopedTickets)
                : $this->reportStatistics->buildCategoryBreakdownForPeriod(
                    clone $scopedTickets,
                    $selectedMonthRange['start'],
                    $selectedMonthRange['end']
                ),
        ];
    }

    public function buildTicketsByCategory(Collection $categoryCounts, int $totalTickets): Collection
    {
        return $categoryCounts
            ->map(fn (object $row) => [
                'name' => (string) $row->category_name,
                'count' => (int) $row->count,
                'share' => $totalTickets > 0 ? round(((int) $row->count / $totalTickets) * 100, 1) : 0.0,
            ])
            ->values();
    }

    public function buildMixBreakdownData(
        Builder $scopedTickets,
    ): array {
        $mixStatusSummary = $this->queryMetrics->statusBreakdownForAllTime(clone $scopedTickets);
        $mixTotalCreated = $this->queryMetrics->ticketCount(clone $scopedTickets);
        $priorityCounts = $this->queryMetrics->priorityCountsForScope(clone $scopedTickets);
        $categoryCounts = $this->queryMetrics->categoryCountsForScope(clone $scopedTickets);

        return [
            'mixScopeLabel' => 'All Time',
            'ticketHistoryScope' => [],
            'ticketsBreakdownOverview' => [
                'label' => 'All Time',
                'start' => null,
                'end' => null,
                'total_created' => $mixTotalCreated,
                'open' => (int) ($mixStatusSummary['open'] ?? 0),
                'in_progress' => (int) ($mixStatusSummary['in_progress'] ?? 0),
                'pending' => (int) ($mixStatusSummary['pending'] ?? 0),
                'resolved' => (int) ($mixStatusSummary['resolved'] ?? 0),
                'closed' => (int) ($mixStatusSummary['closed'] ?? 0),
                'cancelled' => (int) ($mixStatusSummary['cancelled'] ?? 0),
            ],
            'categoryBreakdownBuckets' => $this->reportStatistics->buildCategoryBucketsFromCounts($categoryCounts),
            'priorityBreakdownBuckets' => $this->reportStatistics->buildPriorityBucketsFromCounts($priorityCounts),
        ];
    }

    public function buildTicketTrend(Builder $scopedTickets): Collection
    {
        $trendStart = Carbon::now()->startOfDay()->subDays(29);
        $trendEnd = Carbon::now()->startOfDay();
        $trendCounts = (clone $scopedTickets)
            ->selectRaw('DATE(created_at) as ticket_date, COUNT(*) as count')
            ->whereBetween('created_at', [$trendStart, $trendEnd->copy()->endOfDay()])
            ->groupBy('ticket_date')
            ->orderBy('ticket_date')
            ->pluck('count', 'ticket_date');
        $ticketTrend = collect();
        for ($cursor = $trendStart->copy(); $cursor->lte($trendEnd); $cursor->addDay()) {
            $dateLabel = $cursor->toDateString();
            $ticketTrend->push(['date' => $dateLabel, 'count' => (int) ($trendCounts[$dateLabel] ?? 0)]);
        }

        return $ticketTrend;
    }
}
