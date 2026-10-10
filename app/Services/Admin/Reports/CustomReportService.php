<?php

namespace App\Services\Admin\Reports;

use App\Enums\RootCauseCategory;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Admin\TicketIndexFilterService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CustomReportService
{
    public const GROUP_BY_OPTIONS = ['status', 'priority', 'category', 'root_cause', 'assignee', 'department'];

    /** A mistyped "from" date must not ask for the whole history: the detailed list and the exports read every matching ticket. */
    private const MAX_RANGE_DAYS = 730;

    /**
     * The detailed PDF lists at most this many tickets. DomPDF needs roughly 0.35 MB per table row,
     * so 250 rows stay inside PHP's default 128 MB where the old 500-row cap ran out of memory and
     * returned an error page. The CSV export has no such limit.
     */
    public const PDF_MAX_ROWS = 250;

    public function __construct(
        private TicketIndexFilterService $ticketFilters,
    ) {}

    /**
     * `notices` lists anything changed about the dates the user typed, so the report never
     * quietly answers a different question than the one asked.
     *
     * @return array{start: Carbon, end: Carbon, from: string, to: string, label: string, notices: array<int, string>}
     */
    public function resolveDateRange(Request $request): array
    {
        $range = $this->ticketFilters->resolveCreatedDateRange($request);
        if ($range !== null) {
            $notices = [];
            if ($range['swapped'] ?? false) {
                $notices[] = 'The From date was later than the To date, so the two dates were swapped.';
            }

            $range = $this->capRangeSpan($range, $notices);
            $range['notices'] = $notices;

            return $range;
        }

        $start = now()->startOfMonth();
        $end = now()->endOfMonth();

        return [
            'start' => $start,
            'end' => $end,
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
            'label' => $start->format('F Y'),
            'month' => $start->format('Y-m'),
            'notices' => [],
        ];
    }

    /**
     * Limits an overly wide custom range to MAX_RANGE_DAYS, so a mistyped or very old "from"
     * date can't pull the entire ticket history into memory. The most recent stretch is kept
     * (that is what people are almost always after) and the user is told.
     *
     * @param  array<int, string>  $notices
     */
    private function capRangeSpan(array $range, array &$notices): array
    {
        $minStart = $range['end']->copy()->subDays(self::MAX_RANGE_DAYS)->startOfDay();
        if ($range['start']->gte($minStart)) {
            return $range;
        }

        $range['start'] = $minStart;
        $range['from'] = $minStart->toDateString();
        $range['label'] = trim(($range['label'] ?? '').' (capped to '.self::MAX_RANGE_DAYS.' days)');
        $notices[] = 'That range is longer than '.self::MAX_RANGE_DAYS.' days, so this report shows the most recent stretch ('
            .$range['start']->format('M j, Y').' to '.$range['end']->format('M j, Y').'). Choose a shorter range to see an earlier period.';

        return $range;
    }

    /**
     * @return Builder<Ticket>
     */
    public function buildFilteredQuery(Request $request, array $dateRange): Builder
    {
        $query = Ticket::query()->with(['category:id,name', 'assignedUser:id,name,role']);
        $selectedStatus = trim((string) $request->input('status', 'all'));
        if ($selectedStatus === '') {
            $selectedStatus = 'all';
        }

        $this->ticketFilters->applyFilters($query, $request, $selectedStatus, $dateRange);
        // Same scope as the Reports page, so both give the same totals for the same period.
        Ticket::applyReportableConstraint($query);

        return $query;
    }

    /**
     * Counted by the database, so the cost does not grow with the number of tickets: a report over
     * 15,000 tickets used to load all of them into memory (11 s and 116 MB) to count them.
     *
     * @param  Builder<Ticket>  $query
     */
    public function buildSummaryRows(Builder $query, string $groupBy): Collection
    {
        $groupBy = in_array($groupBy, self::GROUP_BY_OPTIONS, true) ? $groupBy : 'status';
        $counts = $this->countsByGroup($query, $groupBy);
        $total = max(1, $counts->sum());

        // Rows with the same count are ordered by label, so the report reads the same on every
        // database and every reload instead of in whatever order the rows came back.
        return $counts->map(fn (int $count, string|int $key) => [
            'label' => $this->labelForGroupKey($groupBy, (string) $key),
            'count' => $count,
            'share' => round(($count / $total) * 100, 1),
        ])->sortBy([['count', 'desc'], ['label', 'asc']])->values();
    }

    /**
     * Ticket counts keyed by the group a ticket falls in, using the same keys the labels are built from.
     *
     * @param  Builder<Ticket>  $query
     * @return Collection<string|int, int>
     */
    private function countsByGroup(Builder $query, string $groupBy): Collection
    {
        $column = match ($groupBy) {
            'priority' => 'priority',
            'category' => 'category_id',
            'root_cause' => 'root_cause_category',
            'assignee' => 'assigned_to',
            'department' => 'department',
            default => 'status',
        };

        // A plain query: no eager loads, and the filters (including sub-queries) stay as they were.
        $rows = (clone $query)->reorder()->toBase()
            ->select($column)
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy($column)
            ->get();

        $names = match ($groupBy) {
            'category' => Category::query()->whereIn('id', $rows->pluck('category_id'))->pluck('name', 'id'),
            'assignee' => User::query()->whereIn('id', $rows->pluck('assigned_to')->filter())->get()
                ->mapWithKeys(fn (User $user) => [$user->id => $user->publicDisplayName()]),
            default => collect(),
        };

        $counts = [];
        foreach ($rows as $row) {
            $key = match ($groupBy) {
                'priority' => $row->priority ?? '__pending__',
                'category' => $names[$row->category_id] ?? 'Uncategorized',
                'root_cause' => $row->root_cause_category ?? '__none__',
                'assignee' => $names[$row->assigned_to] ?? 'Unassigned',
                'department' => ($row->department ?? '') !== '' ? $row->department : 'Unknown',
                default => $row->status,
            };
            $counts[$key] = ($counts[$key] ?? 0) + (int) $row->aggregate;
        }

        return collect($counts);
    }

    private function labelForGroupKey(string $groupBy, string $key): string
    {
        return match ($groupBy) {
            'status' => TicketStatus::tryFrom($key)?->label() ?? ucfirst($key),
            'priority' => $key === '__pending__' ? 'Severity not set' : ucfirst(str_replace('_', ' ', $key)),
            'root_cause' => $key === '__none__' ? 'Not specified' : (RootCauseCategory::tryFrom($key)?->label() ?? ucfirst($key)),
            default => $key,
        };
    }

    /**
     * @param  Builder<Ticket>  $query
     * @return LengthAwarePaginator<int, Ticket>
     */
    public function buildDetailedRows(Builder $query): LengthAwarePaginator
    {
        return (clone $query)->latest('created_at')->paginate(50)->withQueryString();
    }

    /**
     * @return array<int, string>
     */
    public function departmentOptions(): array
    {
        return User::allowedDepartments();
    }
}
