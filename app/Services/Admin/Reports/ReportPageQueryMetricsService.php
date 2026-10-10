<?php

namespace App\Services\Admin\Reports;

use App\Services\Admin\ReportBreakdownService;
use App\Services\Admin\Reports\Concerns\BuildsReportQueryCacheKey;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReportPageQueryMetricsService
{
    use BuildsReportQueryCacheKey;

    private array $monthlyDatasetCache = [];

    private array $ticketCountCache = [];

    private array $openTicketCutoffCache = [];

    private array $statusCountCache = [];

    private array $priorityCountCache = [];

    private array $categoryCountsCache = [];

    public function __construct(
        private ReportBreakdownService $reportBreakdowns,
        private MonthlyReportDatasetService $monthlyReportDatasets,
        private ReportStatisticsService $reportStatistics,
    ) {}

    public function monthlyDataset(Builder $scopedTickets): array
    {
        $cacheKey = $this->queryScopeSignature($scopedTickets);
        if (array_key_exists($cacheKey, $this->monthlyDatasetCache)) {
            return $this->monthlyDatasetCache[$cacheKey];
        }

        return $this->monthlyDatasetCache[$cacheKey] = $this->monthlyReportDatasets->build(clone $scopedTickets);
    }

    public function ticketCount(Builder $scopedTickets): int
    {
        $cacheKey = $this->queryScopeSignature($scopedTickets);
        if (array_key_exists($cacheKey, $this->ticketCountCache)) {
            return $this->ticketCountCache[$cacheKey];
        }

        return $this->ticketCountCache[$cacheKey] = (clone $scopedTickets)->count();
    }

    public function openTicketCountAtCutoff(Builder $scopedTickets, Carbon $cutoff): int
    {
        $cacheKey = $this->queryScopeSignature($scopedTickets).'|'.$cutoff->toIso8601String();
        if (array_key_exists($cacheKey, $this->openTicketCutoffCache)) {
            return $this->openTicketCutoffCache[$cacheKey];
        }

        return $this->openTicketCutoffCache[$cacheKey] = $this->monthlyReportDatasets->countOpenTicketsAtCutoff(
            clone $scopedTickets,
            $cutoff
        );
    }

    public function statusCountsForScope(Builder $scopedTickets): Collection
    {
        $cacheKey = $this->queryScopeSignature($scopedTickets);
        if (array_key_exists($cacheKey, $this->statusCountCache)) {
            return $this->statusCountCache[$cacheKey];
        }

        return $this->statusCountCache[$cacheKey] = (clone $scopedTickets)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');
    }

    public function priorityCountsForScope(Builder $scopedTickets): Collection
    {
        $cacheKey = $this->queryScopeSignature($scopedTickets);
        if (array_key_exists($cacheKey, $this->priorityCountCache)) {
            return $this->priorityCountCache[$cacheKey];
        }

        return $this->priorityCountCache[$cacheKey] = (clone $scopedTickets)
            ->selectRaw('priority, COUNT(*) as count')
            ->groupBy('priority')
            ->pluck('count', 'priority');
    }

    public function categoryCountsForScope(Builder $scopedTickets): Collection
    {
        $cacheKey = $this->queryScopeSignature($scopedTickets);
        if (array_key_exists($cacheKey, $this->categoryCountsCache)) {
            return $this->categoryCountsCache[$cacheKey];
        }

        return $this->categoryCountsCache[$cacheKey] = $this->reportBreakdowns->categoryCountsForScope(clone $scopedTickets);
    }

    /**
     * @return array<string, int>
     */
    public function statusBreakdownForScope(Builder $query, bool $allTime, Carbon $start, Carbon $end): array
    {
        return $allTime
            ? $this->statusBreakdownForAllTime($query)
            : $this->statusBreakdownForPeriod($query, $start, $end);
    }

    /**
     * @return array<string, int>
     */
    public function statusBreakdownForAllTime(Builder $query): array
    {
        return $this->reportStatistics->buildReportStatusBreakdownForAllTime($query);
    }

    /**
     * @return array<string, int>
     */
    public function statusBreakdownForPeriod(Builder $query, Carbon $start, Carbon $end): array
    {
        return $this->reportStatistics->buildReportStatusBreakdownForPeriod($query, $start, $end);
    }
}
