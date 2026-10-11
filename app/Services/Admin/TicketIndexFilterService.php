<?php

namespace App\Services\Admin;

use App\Models\Ticket;
use App\Support\LikePattern;
use App\Support\TicketCategoryBuckets;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TicketIndexFilterService
{
    public function resolveCreatedDateRange(Request $request): ?array
    {
        $selectedMonth = $this->parseMonthKey($request->query('month'));
        if ($selectedMonth !== null) {
            $monthStart = $selectedMonth->copy()->startOfMonth();
            $monthEnd = $selectedMonth->copy()->endOfMonth();

            return [
                'start' => $monthStart,
                'end' => $monthEnd,
                'from' => $monthStart->toDateString(),
                'to' => $monthEnd->toDateString(),
                'label' => $monthStart->format('F Y'),
                'month' => $monthStart->format('Y-m'),
            ];
        }

        $fromDate = $this->parseCreatedDate($request->query('created_from'));
        $toDate = $this->parseCreatedDate($request->query('created_to'));
        if (! $fromDate && ! $toDate) {
            return null;
        }

        $startDate = $fromDate ?? $toDate;
        $endDate = $toDate ?? $fromDate;
        if (! $startDate || ! $endDate) {
            return null;
        }

        $swapped = false;
        if ($startDate->gt($endDate)) {
            [$startDate, $endDate] = [$endDate, $startDate];
            $swapped = true;
        }

        return [
            'start' => $startDate->copy()->startOfDay(),
            'end' => $endDate->copy()->endOfDay(),
            'from' => $startDate->toDateString(),
            'to' => $endDate->toDateString(),
            'label' => trim((string) $request->query('report_scope')),
            'month' => null,
            'swapped' => $swapped,
        ];
    }

    public function applyFilters(
        Builder $query,
        Request $request,
        string $selectedStatus,
        ?array $createdDateRange = null,
    ): void {
        $this->applyFiltersExcept($query, $request, $selectedStatus, $createdDateRange);
    }

    public function applyFiltersExcept(
        Builder $query,
        Request $request,
        string $selectedStatus,
        ?array $createdDateRange = null,
        array $excludedFilters = [],
    ): void {
        $excluded = collect($excludedFilters)
            ->map(fn (mixed $value) => strtolower(trim((string) $value)))
            ->filter()
            ->values()
            ->all();

        if (! in_array('status', $excluded, true)) {
            $this->applyStatusFilter($query, $selectedStatus);
        }

        if (! in_array('priority', $excluded, true)) {
            $this->applyPriorityFilter($query, $request);
        }

        if (! in_array('ticket_type', $excluded, true)) {
            $this->applyTicketTypeFilter($query, $request);
        }

        if (! in_array('category', $excluded, true)) {
            $this->applyCategoryFilters($query, $request);
        }

        if (! in_array('province', $excluded, true) || ! in_array('municipality', $excluded, true)) {
            $this->applyLocationFilters($query, $request, $excluded);
        }

        if (! in_array('account', $excluded, true)) {
            $this->applyAccountFilters($query, $request);
        }

        if (! in_array('department', $excluded, true)) {
            $this->applyDepartmentFilter($query, $request);
        }

        if (! in_array('assigned_to', $excluded, true)) {
            $this->applyAssignmentFilter($query, $request);
        }

        if (! in_array('search', $excluded, true)) {
            $this->applySearchFilter($query, $request);
        }

        if (! in_array('created_date', $excluded, true) && ! in_array('month', $excluded, true)) {
            $this->applyCreatedDateRangeFilter($query, $createdDateRange);
        }
    }

    private function applyStatusFilter(Builder $query, string $selectedStatus): void
    {
        if ($selectedStatus === 'all') {
            return;
        }

        $query->where('status', $selectedStatus);
    }

    private function applyPriorityFilter(Builder $query, Request $request): void
    {
        if (! $request->filled('priority') || $request->priority === 'all') {
            return;
        }

        $priority = $request->string('priority')->toString();

        if ($priority === 'unassigned') {
            $query->whereNull('priority');

            return;
        }

        $normalizedPriority = Ticket::normalizePriorityValue($priority);
        if ($normalizedPriority === null) {
            return;
        }

        $query->where('priority', $normalizedPriority);
    }

    private function applyTicketTypeFilter(Builder $query, Request $request): void
    {
        if (! $request->filled('ticket_type') || $request->ticket_type === 'all') {
            return;
        }

        $ticketType = trim($request->string('ticket_type')->toString());

        if ($request->string('tab')->toString() === 'history') {
            if ($ticketType === Ticket::TYPE_INTERNAL) {
                Ticket::applyHistoryStaffRequesterConstraint($query);

                return;
            }

            if ($ticketType === Ticket::TYPE_EXTERNAL) {
                Ticket::applyHistoryClientRequesterConstraint($query);

                return;
            }

            return;
        }

        if ($ticketType === Ticket::TYPE_INTERNAL) {
            $query->where('ticket_type', Ticket::TYPE_INTERNAL);

            return;
        }

        if ($ticketType !== Ticket::TYPE_EXTERNAL) {
            return;
        }

        $query->where(function (Builder $builder) {
            $builder->whereNull('ticket_type')
                ->orWhere('ticket_type', Ticket::TYPE_EXTERNAL);
        });
    }

    private function applyCategoryFilters(Builder $query, Request $request): void
    {
        if ($request->filled('category') && $request->category !== 'all') {
            $query->where('category_id', $request->integer('category'));
        }

        if (! $request->filled('category_bucket') || $request->category_bucket === 'all') {
            return;
        }

        $bucket = TicketCategoryBuckets::labelForFilterKey($request->string('category_bucket')->toString());
        if ($bucket === null) {
            return;
        }

        $constrainToBucket = fn (Builder $categoryQuery) => TicketCategoryBuckets::constrainCategoryQuery($categoryQuery, $bucket);

        if ($bucket === TicketCategoryBuckets::OTHER) {
            $query->where(fn (Builder $builder) => $builder
                ->whereNull('category_id')
                ->orWhereHas('category', $constrainToBucket));

            return;
        }

        $query->whereHas('category', $constrainToBucket);
    }

    private function applyLocationFilters(Builder $query, Request $request, array $excluded = []): void
    {
        if (! in_array('province', $excluded, true) && $request->filled('province') && $request->province !== 'all') {
            $this->applyCaseInsensitiveExactMatch($query, 'province', (string) $request->province);
        }

        if (! in_array('municipality', $excluded, true) && $request->filled('municipality') && $request->municipality !== 'all') {
            $this->applyCaseInsensitiveExactMatch($query, 'municipality', (string) $request->municipality);
        }
    }

    private function applyDepartmentFilter(Builder $query, Request $request): void
    {
        if ($request->filled('department') && $request->string('department')->toString() !== 'all') {
            $query->where('department', $request->string('department')->toString());
        }
    }

    private function applyAccountFilters(Builder $query, Request $request): void
    {
        if ($request->filled('account_id') && $request->account_id !== 'all') {
            $query->where('user_id', $request->integer('account_id'));
        }

        if (! $request->filled('related_user_id') || $request->related_user_id === 'all') {
            return;
        }

        $relatedUserId = $request->integer('related_user_id');

        $query->where(function (Builder $builder) use ($relatedUserId) {
            $builder->where('user_id', $relatedUserId)
                ->orWhere(function (Builder $assignmentQuery) use ($relatedUserId) {
                    Ticket::applyAssignedToConstraint($assignmentQuery, $relatedUserId);
                });
        });
    }

    private function applyAssignmentFilter(Builder $query, Request $request): void
    {
        if (! $request->filled('assigned_to') || $request->assigned_to === 'all') {
            return;
        }

        if ((string) $request->assigned_to === '0') {
            Ticket::applyUnassignedConstraint($query);

            return;
        }

        Ticket::applyAssignedToConstraint($query, $request->integer('assigned_to'));
    }

    private function applySearchFilter(Builder $query, Request $request): void
    {
        if (! $request->filled('search')) {
            return;
        }

        $search = trim($request->string('search')->toString());
        if ($search === '') {
            return;
        }

        $pattern = LikePattern::contains(mb_strtolower($search));
        $like = 'LIKE ? '.LikePattern::LIKE_ESCAPE;
        $ticketsTable = $query->getModel()->getTable();

        // One id set per searched column, joined with UNION, instead of five OR-ed tests on the ticket row. On
        // PostgreSQL every branch can then use its trigram index (see the search index migrations); the OR form
        // cannot, and read the whole table ten times per page (12.7 s for 60,000 tickets).
        // LOWER(column) LIKE is what those indexes are built on, and it behaves the same on every database.
        $idsOf = static fn (string $column) => DB::table($ticketsTable)
            ->select('id')
            ->whereRaw("LOWER({$column}) {$like}", [$pattern]);

        $matchingIds = $idsOf('subject')
            ->union($idsOf('ticket_number'))
            ->union($idsOf('description'))
            ->union($idsOf('department'))
            ->union(
                DB::table($ticketsTable)
                    ->join('users', 'users.id', '=', "{$ticketsTable}.user_id")
                    ->select("{$ticketsTable}.id")
                    ->where(function ($userQuery) use ($like, $pattern) {
                        $userQuery->whereRaw("LOWER(users.name) {$like}", [$pattern])
                            ->orWhereRaw("LOWER(users.email) {$like}", [$pattern]);
                    })
            );

        $query->whereIn("{$ticketsTable}.id", $matchingIds);
    }

    private function applyCreatedDateRangeFilter(Builder $query, ?array $createdDateRange): void
    {
        if ($createdDateRange === null) {
            return;
        }

        $query->whereBetween('created_at', [
            $createdDateRange['start'],
            $createdDateRange['end'],
        ]);
    }

    private function parseCreatedDate(mixed $rawDate): ?Carbon
    {
        if (! is_string($rawDate)) {
            return null;
        }

        $normalized = trim($rawDate);
        if ($normalized === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $normalized)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseMonthKey(mixed $rawMonth): ?Carbon
    {
        if (! is_string($rawMonth)) {
            return null;
        }

        $normalized = trim($rawMonth);
        if ($normalized === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('!Y-m', $normalized)->startOfMonth();
        } catch (\Throwable) {
            return null;
        }
    }

    private function applyCaseInsensitiveExactMatch(Builder $query, string $column, string $value): void
    {
        $this->assertSupportedLocationColumn($column);

        $normalizedValue = trim($value);
        if ($normalizedValue === '') {
            return;
        }

        // Equality, not LIKE: a % or _ in the value must match itself. $column is whitelisted above.
        $query->whereRaw("LOWER({$column}) = LOWER(?)", [$normalizedValue]);
    }

    private function assertSupportedLocationColumn(string $column): void
    {
        if (! in_array($column, ['province', 'municipality'], true)) {
            throw new \InvalidArgumentException('Unsupported ticket location column.');
        }
    }
}
