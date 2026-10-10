<?php

namespace App\Services\Admin;

use App\Enums\TicketPriority;
use App\Support\TicketCategoryBuckets;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReportBreakdownService
{
    public function buildCategoryBreakdownForScope(Builder $scopedTickets, Carbon $start, Carbon $end): Collection
    {
        $categoryCounts = $this->categoryCountsForScope(clone $scopedTickets, $start, $end);
        $total = max(1, (int) $categoryCounts->sum(fn (object $row) => (int) $row->count));

        return $categoryCounts
            ->map(function (object $row) use ($total): array {
                $count = (int) $row->count;

                return [
                    'name' => (string) $row->category_name,
                    'count' => $count,
                    'share' => round(($count / $total) * 100, 1),
                ];
            })
            ->values();
    }

    /**
     * @param  iterable<object{category_name: string|null, count: int|string}>  $categoryCounts
     * @return list<array{name: string, count: int}>
     */
    public function bucketCategoryCounts(iterable $categoryCounts): array
    {
        $bucketCounts = array_fill_keys(TicketCategoryBuckets::labels(), 0);

        foreach ($categoryCounts as $row) {
            $bucketCounts[TicketCategoryBuckets::forCategoryName((string) $row->category_name)] += (int) $row->count;
        }

        return collect($bucketCounts)->map(fn (int $count, string $name) => [
            'name' => $name,
            'count' => $count,
        ])->values()->all();
    }

    /**
     * @param  Collection<string, int|string>  $priorityCounts  counts keyed by priority value
     * @return list<array{name: string, value: string|null, count: int}>
     */
    public function bucketPriorityCounts(Collection $priorityCounts): array
    {
        $buckets = [[
            'name' => TicketPriority::SEVERITY_NOT_SET_LABEL,
            'value' => null,
            'count' => (int) ($priorityCounts[''] ?? $priorityCounts[null] ?? 0),
        ]];

        foreach (TicketPriority::cases() as $priority) {
            $buckets[] = [
                'name' => $priority->label(),
                'value' => $priority->value,
                'count' => (int) ($priorityCounts[$priority->value] ?? 0),
            ];
        }

        return $buckets;
    }

    public function categoryCountsForScope(
        Builder $scopedTickets,
        ?Carbon $start = null,
        ?Carbon $end = null
    ): Collection {
        $query = (clone $scopedTickets)
            ->leftJoin('categories', 'tickets.category_id', '=', 'categories.id')
            ->reorder();

        if ($start && $end) {
            $query->whereBetween('tickets.created_at', [$start, $end]);
        }

        return $query
            ->selectRaw("COALESCE(categories.name, 'Uncategorized') as category_name, COUNT(*) as count")
            ->groupBy('categories.name')
            ->orderByDesc('count')
            ->get();
    }
}
