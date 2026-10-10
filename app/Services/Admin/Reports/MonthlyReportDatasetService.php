<?php

namespace App\Services\Admin\Reports;

use App\Models\Ticket;
use App\Services\Admin\Reports\Concerns\BuildsReportQueryCacheKey;
use App\Support\DatabaseDateExpressions;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class MonthlyReportDatasetService
{
    use BuildsReportQueryCacheKey;

    private array $openTicketCountCache = [];

    public function build(Builder $scopedTickets): array
    {
        $startMonth = Carbon::now()->startOfMonth()->subMonths(11);
        $endMonth = Carbon::now()->startOfMonth();
        $reportingRangeStart = $startMonth->copy()->startOfMonth();
        $reportingRangeEnd = $endMonth->copy()->endOfMonth()->endOfDay();
        $monthKeyExpressionForCreatedAt = DatabaseDateExpressions::monthKey('created_at', $scopedTickets);
        $monthKeyExpressionForCompletedAt = DatabaseDateExpressions::monthKey('COALESCE(resolved_at, closed_at)', $scopedTickets);
        $monthKeyExpressionForCancelledAt = DatabaseDateExpressions::monthKey('cancelled_at', $scopedTickets);

        $receivedByMonth = (clone $scopedTickets)
            ->whereBetween('created_at', [$reportingRangeStart, $reportingRangeEnd])
            ->selectRaw("{$monthKeyExpressionForCreatedAt} as month_key, COUNT(*) as count")
            ->groupBy('month_key')
            ->pluck('count', 'month_key');
        // Cancelled tickets were withdrawn, so they stay in "received" but are left out of the resolution
        // rate's base, and they stop counting as open backlog from the month they were cancelled.
        $cancelledByCreatedMonth = (clone $scopedTickets)
            ->whereBetween('created_at', [$reportingRangeStart, $reportingRangeEnd])
            ->whereIn('status', Ticket::CANCELLED_STATUSES)
            ->selectRaw("{$monthKeyExpressionForCreatedAt} as month_key, COUNT(*) as count")
            ->groupBy('month_key')
            ->pluck('count', 'month_key');
        $cancelledInPeriodByMonth = (clone $scopedTickets)
            ->whereIn('status', Ticket::CANCELLED_STATUSES)
            ->whereNotNull('cancelled_at')
            ->whereBetween('cancelled_at', [$reportingRangeStart, $reportingRangeEnd])
            ->selectRaw("{$monthKeyExpressionForCancelledAt} as month_key, COUNT(*) as count")
            ->groupBy('month_key')
            ->pluck('count', 'month_key');
        $completedByCreatedMonth = (clone $scopedTickets)
            ->whereBetween('created_at', [$reportingRangeStart, $reportingRangeEnd])
            ->whereIn('status', Ticket::CLOSED_STATUSES)
            ->selectRaw("{$monthKeyExpressionForCreatedAt} as month_key, COUNT(DISTINCT id) as count")
            ->groupBy('month_key')
            ->pluck('count', 'month_key');

        $completedInPeriodByMonth = (clone $scopedTickets)
            ->whereIn('status', Ticket::CLOSED_STATUSES)
            ->where(function ($query) {
                $query->whereNotNull('resolved_at')
                    ->orWhereNotNull('closed_at');
            })
            ->whereRaw('COALESCE(resolved_at, closed_at) BETWEEN ? AND ?', [$reportingRangeStart, $reportingRangeEnd])
            ->selectRaw("{$monthKeyExpressionForCompletedAt} as month_key, COUNT(DISTINCT id) as count")
            ->groupBy('month_key')
            ->pluck('count', 'month_key');

        $monthDefinitions = collect();
        for ($cursor = $startMonth->copy(); $cursor->lte($endMonth); $cursor->addMonth()) {
            $monthDefinitions->push([
                'key' => $cursor->format('Y-m'),
                'label' => $cursor->format('M Y'),
                'start' => $cursor->copy()->startOfMonth(),
                'end' => $cursor->copy()->endOfMonth(),
            ]);
        }

        $rangeBacklogCutoff = $reportingRangeStart->copy()->subSecond();
        $openBeforeReportingRange = $this->countOpenTicketsAtCutoff(clone $scopedTickets, $rangeBacklogCutoff);
        $runningOpenCount = $openBeforeReportingRange;

        $reportRows = $monthDefinitions->map(function (array $row) use (
            &$runningOpenCount,
            $receivedByMonth,
            $completedByCreatedMonth,
            $completedInPeriodByMonth,
            $cancelledByCreatedMonth,
            $cancelledInPeriodByMonth
        ) {
            $monthKey = (string) $row['key'];
            $receivedCount = (int) ($receivedByMonth[$monthKey] ?? 0);
            $completedInPeriodCount = (int) ($completedInPeriodByMonth[$monthKey] ?? 0);
            $completedFromCreatedCount = (int) ($completedByCreatedMonth[$monthKey] ?? 0);
            $cancelledFromCreatedCount = (int) ($cancelledByCreatedMonth[$monthKey] ?? 0);
            $cancelledInPeriodCount = (int) ($cancelledInPeriodByMonth[$monthKey] ?? 0);
            $resolvableCount = max($receivedCount - $cancelledFromCreatedCount, 0);
            $runningOpenCount += $receivedCount - $completedInPeriodCount - $cancelledInPeriodCount;
            if ($runningOpenCount < 0) {
                $runningOpenCount = 0;
            }

            return [
                'month_key' => $monthKey,
                'month_label' => (string) $row['label'],
                'month_start' => $row['start']->toDateString(),
                'month_end' => $row['end']->toDateString(),
                'received' => $receivedCount,
                'resolved' => $completedFromCreatedCount,
                'completed_in_period' => $completedInPeriodCount,
                'open_end_of_month' => $runningOpenCount,
                'resolution_rate' => $resolvableCount > 0
                    ? round(($completedFromCreatedCount / $resolvableCount) * 100, 1)
                    : 0.0,
            ];
        });

        $graphPoints = $reportRows->map(fn (array $row) => [
            'key' => $row['month_key'],
            'month_label' => $row['month_label'],
            'label' => Carbon::createFromFormat('!Y-m', $row['month_key'])->format('M Y'),
            'received' => $row['received'],
            'resolved' => $row['resolved'],
            'completed_in_period' => $row['completed_in_period'],
            'resolution_rate' => $row['resolution_rate'],
        ]);

        return [$reportRows, $graphPoints];
    }

    public function countOpenTicketsAtCutoff(Builder $scopedTickets, Carbon $cutoff): int
    {
        $cacheKey = $this->queryScopeSignature($scopedTickets).'|'.$cutoff->toIso8601String();
        if (array_key_exists($cacheKey, $this->openTicketCountCache)) {
            return $this->openTicketCountCache[$cacheKey];
        }

        $count = (clone $scopedTickets)
            ->where('created_at', '<=', $cutoff)
            ->where(function ($query) use ($cutoff) {
                $query->whereNull('resolved_at')
                    ->orWhere('resolved_at', '>', $cutoff);
            })
            ->where(function ($query) use ($cutoff) {
                $query->whereNull('closed_at')
                    ->orWhere('closed_at', '>', $cutoff);
            })
            // A ticket cancelled on or before the cutoff was no longer part of the open backlog.
            ->where(function ($query) use ($cutoff) {
                $query->whereNotIn('status', Ticket::CANCELLED_STATUSES)
                    ->orWhere('cancelled_at', '>', $cutoff);
            })
            ->count();

        $this->openTicketCountCache[$cacheKey] = (int) $count;

        return (int) $count;
    }
}
