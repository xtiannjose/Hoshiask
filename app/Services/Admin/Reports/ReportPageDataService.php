<?php

namespace App\Services\Admin\Reports;

use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ReportPageDataService
{
    private const CACHE_SECONDS = 60;

    public function __construct(
        private ReportPageInsightsService $insights,
        private ReportPageQueryMetricsService $queryMetrics,
        private ReportPageVisualService $visuals,
        private ReportScopeService $reportScopes,
        private ReportStatisticsService $reportStatistics,
        private SlaReportService $slaReports,
    ) {}

    public function buildIndexViewData(Request $request, User $user): array
    {
        return Cache::remember(
            $this->indexViewCacheKey($request, $user),
            now()->addSeconds(self::CACHE_SECONDS),
            fn (): array => $this->buildIndexViewDataUncached($request, $user)
        );
    }

    private function indexViewCacheKey(Request $request, User $user): string
    {
        $scope = $user->isTechnician() ? 'technician:'.$user->id : 'shared';

        return implode(':', [
            'reports.index',
            $scope,
            (string) $request->query('month'),
            (string) $request->query('daily_month'),
            (string) $request->query('daily_date'),
        ]);
    }

    private function buildIndexViewDataUncached(Request $request, User $user): array
    {
        $scopedTickets = $this->scopedTicketQueryForUser($user);
        [$monthlyReportRows, $monthlyGraphPoints] = $this->queryMetrics->monthlyDataset(clone $scopedTickets);
        $selectedMonthContext = $this->reportScopes->resolveSelectedMonthContext($request, $monthlyReportRows, clone $scopedTickets);
        $selectedMonthKey = $selectedMonthContext['selectedMonthKey'];
        $selectedMonthIsAllTime = $selectedMonthContext['selectedMonthIsAllTime'];
        $selectedMonthRange = $selectedMonthContext['selectedMonthRange'];
        $selectedMonthRow = $selectedMonthContext['selectedMonthRow'];
        $selectedPeriodStart = $selectedMonthContext['selectedPeriodStart'];
        $selectedPeriodEnd = $selectedMonthContext['selectedPeriodEnd'];

        $dailyScope = $this->reportScopes->resolveDailyScope(
            $request,
            $monthlyReportRows,
        );
        $dailyMonthKey = $dailyScope['dailyMonthKey'];
        $dailyMonthRange = $dailyScope['dailyMonthRange'];
        $dailyDateOptions = $dailyScope['dailyDateOptions'];
        $dailyAllDaysSelected = $dailyScope['dailyAllDaysSelected'];
        $dailySelectedDate = $dailyScope['dailySelectedDate'];
        $dailySelectedDateValue = $dailyScope['dailySelectedDateValue'];

        $kpiSummary = $this->reportStatistics->buildKpiSummary(clone $scopedTickets);
        $totalTickets = (int) ($kpiSummary['total_tickets'] ?? 0);
        $stats = $this->insights->buildStats($kpiSummary);

        $periodStatusSummary = $this->queryMetrics->statusBreakdownForScope(
            clone $scopedTickets,
            $selectedMonthIsAllTime,
            $selectedPeriodStart,
            $selectedPeriodEnd
        );
        $totalTicketsThisPeriod = $selectedMonthIsAllTime
            ? $this->queryMetrics->ticketCount(clone $scopedTickets)
            : (int) ($selectedMonthRow['received'] ?? 0);
        $backlogThisPeriod = $this->queryMetrics->openTicketCountAtCutoff(clone $scopedTickets, $selectedPeriodEnd);
        $majorIssueSummary = $selectedMonthIsAllTime
            ? $this->reportStatistics->buildMajorIssueSummaryForAllTime(clone $scopedTickets)
            : $this->reportStatistics->buildMajorIssueSummary(clone $scopedTickets, $selectedPeriodStart, $selectedPeriodEnd);
        $periodOverview = $this->insights->buildPeriodOverview(
            $selectedMonthRange,
            $selectedPeriodStart,
            $selectedPeriodEnd,
            $totalTicketsThisPeriod,
            $majorIssueSummary,
            $periodStatusSummary,
            $backlogThisPeriod
        );

        $mixBreakdownData = $this->insights->buildMixBreakdownData(clone $scopedTickets);
        $ticketHistoryScope = $mixBreakdownData['ticketHistoryScope'];
        $ticketsBreakdownOverview = $mixBreakdownData['ticketsBreakdownOverview'];
        $categoryBreakdownBuckets = $mixBreakdownData['categoryBreakdownBuckets'];
        $priorityBreakdownBuckets = $mixBreakdownData['priorityBreakdownBuckets'];

        $dailyVolumeStart = $selectedMonthIsAllTime ? now()->copy()->startOfDay()->subDays(29) : $selectedPeriodStart;
        $dailyVolumeEnd = $selectedMonthIsAllTime ? now()->copy()->endOfDay() : $selectedPeriodEnd;
        $volumeSeries = [
            'daily' => $this->reportStatistics->buildDailyVolumeSeries(clone $scopedTickets, $dailyVolumeStart, $dailyVolumeEnd),
            'weekly' => $this->reportStatistics->buildWeeklyVolumeSeries(clone $scopedTickets, 12),
            'monthly' => $monthlyReportRows->map(fn (array $row) => [
                'label' => Carbon::createFromFormat('!Y-m', $row['month_key'])->format('M'),
                'count' => (int) $row['received'],
                'key' => $row['month_key'],
            ])->values(),
        ];
        $dailySelectedStats = $dailyAllDaysSelected
            ? $this->reportStatistics->buildDailyTicketStatisticsForRange(
                clone $scopedTickets,
                $dailyMonthRange['start']->copy()->startOfDay(),
                $dailyMonthRange['end']->copy()->endOfDay(),
                (string) $dailyMonthRange['label']
            )
            : $this->reportStatistics->buildDailyTicketStatisticsForDate(clone $scopedTickets, $dailySelectedDate);

        $slaReport = $this->slaReports->build(
            clone $scopedTickets,
            $selectedPeriodStart->copy(),
            $selectedPeriodEnd->copy(),
            (string) $selectedMonthRange['label']
        );

        $monthlyPerformanceGraphPoints = $monthlyGraphPoints;
        $monthlyPerformanceFocusMonthKey = $selectedMonthIsAllTime
            ? $this->reportScopes->latestAvailableMonthKey($monthlyReportRows)
            : $selectedMonthKey;
        $reportVisuals = $this->visuals->build(
            ($monthlyPerformanceGraphPoints ?? $monthlyGraphPoints)->values()->all(),
            (string) $monthlyPerformanceFocusMonthKey,
            $ticketsBreakdownOverview,
            $categoryBreakdownBuckets,
            $priorityBreakdownBuckets,
            $ticketHistoryScope
        );

        $ticketsByStatus = $this->queryMetrics->statusCountsForScope(clone $scopedTickets);
        $ticketsByPriority = $this->queryMetrics->priorityCountsForScope(clone $scopedTickets);
        $categoryCounts = $this->queryMetrics->categoryCountsForScope(clone $scopedTickets);
        $ticketsByCategory = $this->insights->buildTicketsByCategory($categoryCounts, $totalTickets);
        [
            'selectedMonthStatuses' => $selectedMonthStatuses,
            'selectedMonthPriorities' => $selectedMonthPriorities,
            'selectedMonthCategories' => $selectedMonthCategories,
        ] = $this->insights->buildSelectedMonthBreakdowns(
            clone $scopedTickets,
            $selectedMonthIsAllTime,
            $selectedMonthRange
        );

        $monthOptions = $monthlyReportRows
            ->map(fn (array $row) => ['key' => $row['month_key'], 'label' => $row['month_label']])
            ->reverse()
            ->prepend(['key' => ReportScopeService::ALL_TIME_MONTH_KEY, 'label' => 'All Time'])
            ->values();

        $ticketTrend = $this->insights->buildTicketTrend(clone $scopedTickets);

        return compact(
            'stats',
            'ticketsByStatus',
            'ticketsByPriority',
            'ticketsByCategory',
            'ticketTrend',
            'monthlyReportRows',
            'monthlyGraphPoints',
            'monthlyPerformanceGraphPoints',
            'monthlyPerformanceFocusMonthKey',
            'monthOptions',
            'selectedMonthKey',
            'selectedMonthIsAllTime',
            'selectedMonthRow',
            'selectedMonthStatuses',
            'selectedMonthPriorities',
            'selectedMonthCategories',
            'periodOverview',
            'majorIssueSummary',
            'categoryBreakdownBuckets',
            'priorityBreakdownBuckets',
            'volumeSeries',
            'ticketsBreakdownOverview',
            'dailyMonthKey',
            'dailySelectedDateValue',
            'dailyDateOptions',
            'dailySelectedStats',
            'ticketHistoryScope',
            'reportVisuals',
            'slaReport'
        );
    }

    public function buildMonthlyPdfViewData(Request $request, User $user): array
    {
        $scopedTickets = $this->scopedTicketQueryForUser($user);
        [$monthlyReportRows] = $this->queryMetrics->monthlyDataset(clone $scopedTickets);
        $selectedMonthKey = $this->reportScopes->resolveSelectedMonthKey($request->query('month'), $monthlyReportRows);
        $selectedMonthRange = $this->reportScopes->monthRangeFromKey($selectedMonthKey);
        $selectedMonthRow = $monthlyReportRows->firstWhere('month_key', $selectedMonthKey)
            ?? $this->reportScopes->emptyMonthlyReportRow($selectedMonthKey, $selectedMonthRange);

        return [
            'generatedAt' => now(),
            'selectedMonthKey' => $selectedMonthKey,
            'selectedMonthRow' => $selectedMonthRow,
            'selectedMonthRange' => $selectedMonthRange,
            'statusBreakdown' => $this->reportStatistics->buildReportStatusBreakdownForPeriod(
                clone $scopedTickets,
                $selectedMonthRange['start'],
                $selectedMonthRange['end']
            ),
            'priorityBreakdown' => $this->reportStatistics->buildPriorityBreakdownForPeriod(
                clone $scopedTickets,
                $selectedMonthRange['start'],
                $selectedMonthRange['end']
            ),
            'categoryBreakdown' => $this->reportStatistics->buildCategoryBreakdownForPeriod(
                clone $scopedTickets,
                $selectedMonthRange['start'],
                $selectedMonthRange['end']
            ),
            'monthlyReportRows' => $monthlyReportRows->reverse()->values(),
        ];
    }

    /**
     * @return Builder<Ticket>
     */
    private function scopedTicketQueryForUser(User $user): Builder
    {
        $query = Ticket::query();
        Ticket::applyReportableConstraint($query);

        if ($user->isTechnician()) {
            Ticket::applyAssignedToConstraint($query, (int) $user->id);
        }

        return $query;
    }
}
