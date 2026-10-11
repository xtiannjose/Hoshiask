<?php

namespace App\Services\Admin\Reports;

use App\Models\Ticket;
use App\Support\SlaSettings;
use App\Support\TicketSla;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * "Resolution Time Compliance" on the Reports page: how the completed tickets created in the period split
 * into resolution-time bands. Cancelled tickets were withdrawn, so they are not measured.
 */
class SlaReportService
{
    /** Tickets read per round trip. Memory stays flat however long the period is (the all-time view). */
    private const CHUNK_SIZE = 2000;

    /**
     * @param  Builder<Ticket>  $scopedTickets
     * @return array{label: string, resolution_buckets: list<array{label: string, count: int, rate: float}>}
     */
    public function build(Builder $scopedTickets, Carbon $start, Carbon $end, string $label): array
    {
        ['one_hour' => $oneHour, 'four_hours' => $fourHours, 'one_day' => $oneDay] = SlaSettings::resolutionBandMinutes();
        $bands = [
            'Under 1 Hour' => static fn (int $minutes): bool => $minutes < $oneHour,
            'Under 4 Hours' => static fn (int $minutes): bool => $minutes >= $oneHour && $minutes < $fourHours,
            'Under 24 Hours' => static fn (int $minutes): bool => $minutes >= $fourHours && $minutes < $oneDay,
            'Above 24 Hours' => static fn (int $minutes): bool => $minutes >= $oneDay,
        ];
        $counts = array_fill_keys(array_keys($bands), 0);
        $completedCount = 0;
        $stamp = static fn (?string $date): ?int => $date === null ? null : (strtotime($date) ?: null);

        // Raw rows, not models: with tens of thousands of tickets the model and date objects cost seconds.
        (clone $scopedTickets)
            ->whereBetween('tickets.created_at', [$start, $end])
            ->whereNotIn('tickets.status', Ticket::CANCELLED_STATUSES)
            ->select(['tickets.id', 'tickets.created_at', 'tickets.resolved_at', 'tickets.closed_at'])
            ->toBase()
            ->chunkById(self::CHUNK_SIZE, function ($rows) use ($bands, $stamp, &$counts, &$completedCount): void {
                foreach ($rows as $row) {
                    $minutes = TicketSla::minutesToCompletion($stamp($row->created_at), $stamp($row->resolved_at), $stamp($row->closed_at));
                    if ($minutes === null) {
                        continue;
                    }

                    $completedCount++;
                    foreach ($bands as $bandLabel => $inBand) {
                        if ($inBand($minutes)) {
                            $counts[$bandLabel]++;
                        }
                    }
                }
            }, 'tickets.id', 'id');

        $buckets = [];
        foreach ($counts as $bandLabel => $count) {
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
