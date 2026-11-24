<?php

namespace App\Services\Admin\Reports;

use App\Models\Ticket;
use App\Models\User;
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

        // The three values come straight from the address bar. Hashed, a long or odd value cannot overflow the
        // cache table's 255-character key column.
        return implode(':', [
            'reports.index',
            $scope,
            sha1(implode('|', [
                (string) $request->query('month'),
                (string) $request->query('daily_month'),
                (string) $request->query('daily_date'),
            ])),
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

        $stats = $this->insights->buildStats($this->reportStatistics->buildKpiSummary(clone $scopedTickets));

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
        $ticketsBreakdownOverview = $mixBreakdownData['ticketsBreakdownOverview'];

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

        $monthlyPerformanceFocusMonthKey = $selectedMonthIsAllTime
            ? $this->reportScopes->latestAvailableMonthKey($monthlyReportRows)
            : $selectedMonthKey;
        $reportVisuals = $this->visuals->build(
            $monthlyGraphPoints->values()->all(),
            (string) $monthlyPerformanceFocusMonthKey,
            $ticketsBreakdownOverview,
            $mixBreakdownData['categoryBreakdownBuckets'],
            $mixBreakdownData['priorityBreakdownBuckets'],
        );

        $monthOptions = $monthlyReportRows
            ->map(fn (array $row) => ['key' => $row['month_key'], 'label' => $row['month_label']])
            ->reverse()
            ->prepend(['key' => ReportScopeService::ALL_TIME_MONTH_KEY, 'label' => 'All Time'])
            ->values();

        return compact(
            'stats',
            'monthlyPerformanceFocusMonthKey',
            'monthOptions',
            'selectedMonthKey',
            'selectedMonthIsAllTime',
            'selectedMonthRow',
            'periodOverview',
            'majorIssueSummary',
            'ticketsBreakdownOverview',
            'dailyMonthKey',
            'dailySelectedDateValue',
            'dailyDateOptions',
            'dailySelectedStats',
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
