<?php

namespace App\Services\Admin\Reports;

use App\Models\Ticket;
use App\Support\SlaSettings;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * "Resolution Time Compliance" on the Reports page: how the completed tickets created in the period split
 * into resolution-time bands. Cancelled tickets were withdrawn, so they are not measured.
 */
class SlaReportService
{
    /**
     * @param  Builder<Ticket>  $scopedTickets
     * @return array{label: string, resolution_buckets: list<array{label: string, count: int, rate: float}>}
     */
    public function build(Builder $scopedTickets, Carbon $start, Carbon $end, string $label): array
    {
        $resolutionMinutes = (clone $scopedTickets)
            ->whereBetween('tickets.created_at', [$start, $end])
            ->whereNotIn('tickets.status', Ticket::CANCELLED_STATUSES)
            ->get(['tickets.id', 'tickets.created_at', 'tickets.resolved_at', 'tickets.closed_at'])
            ->map(static fn (Ticket $ticket): ?int => $ticket->slaResolutionMinutes())
            ->reject(static fn (?int $minutes): bool => $minutes === null);
        $completedCount = $resolutionMinutes->count();

        ['one_hour' => $oneHour, 'four_hours' => $fourHours, 'one_day' => $oneDay] = SlaSettings::resolutionBandMinutes();
        $bands = [
            'Under 1 Hour' => static fn (int $minutes): bool => $minutes < $oneHour,
            'Under 4 Hours' => static fn (int $minutes): bool => $minutes >= $oneHour && $minutes < $fourHours,
            'Under 24 Hours' => static fn (int $minutes): bool => $minutes >= $fourHours && $minutes < $oneDay,
            'Above 24 Hours' => static fn (int $minutes): bool => $minutes >= $oneDay,
        ];

        $buckets = [];
        foreach ($bands as $bandLabel => $inBand) {
            $count = $resolutionMinutes->filter($inBand)->count();
            $buckets[] = [
                'label' => $bandLabel,
                'count' => $count,
                'rate' => $completedCount > 0 ? round(($count / $completedCount) * 100, 1) : 0.0,
            ];
        }

        return [
            'label' => $label,
            'resolution_buckets' => $buckets,
        ];
    }
}
