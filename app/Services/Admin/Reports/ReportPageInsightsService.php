<?php

namespace App\Services\Admin\Reports;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

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

    public function buildMixBreakdownData(
        Builder $scopedTickets,
    ): array {
        $mixStatusSummary = $this->queryMetrics->statusBreakdownForAllTime(clone $scopedTickets);
        $mixTotalCreated = $this->queryMetrics->ticketCount(clone $scopedTickets);
        $priorityCounts = $this->queryMetrics->priorityCountsForScope(clone $scopedTickets);
        $categoryCounts = $this->queryMetrics->categoryCountsForScope(clone $scopedTickets);

        return [
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
}
