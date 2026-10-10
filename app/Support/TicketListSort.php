<?php

namespace App\Support;

use App\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Column sorting and page size for the staff ticket list. Anything unrecognised in the
 * query string falls back to the defaults (newest first, 15 per page), so a hand-edited
 * URL can never break the list or reach an arbitrary ORDER BY.
 */
final class TicketListSort
{
    public const SORTS = ['created', 'updated', 'severity', 'status'];

    public const PER_PAGE_OPTIONS = [15, 50, 100];

    public const DEFAULT_PER_PAGE = 15;

    /** Most urgent first: open work before finished work. */
    private const STATUS_ORDER_SQL = "CASE status WHEN 'open' THEN 1 WHEN 'in_progress' THEN 2 WHEN 'pending' THEN 3 WHEN 'resolved' THEN 4 WHEN 'closed' THEN 5 ELSE 6 END";

    /**
     * @return array{sort: string, direction: string}
     */
    public static function resolve(Request $request): array
    {
        $sort = $request->query('sort');
        $sort = is_string($sort) && in_array($sort, self::SORTS, true) ? $sort : 'created';

        $direction = $request->query('direction');
        $direction = is_string($direction) ? strtolower($direction) : '';
        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = self::defaultDirection($sort);
        }

        return ['sort' => $sort, 'direction' => $direction];
    }

    public static function defaultDirection(string $sort): string
    {
        // Dates read newest-first; severity and status read most-urgent-first.
        return in_array($sort, ['severity', 'status'], true) ? 'asc' : 'desc';
    }

    public static function perPage(Request $request): int
    {
        $perPage = $request->integer('per_page', self::DEFAULT_PER_PAGE);

        return in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : self::DEFAULT_PER_PAGE;
    }

    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public static function apply(Builder $query, string $sort, string $direction): Builder
    {
        $direction = $direction === 'asc' ? 'asc' : 'desc';

        match ($sort) {
            'severity' => $query
                ->orderByRaw('CASE WHEN priority IS NULL THEN 1 ELSE 0 END') // unrated tickets always last
                ->orderBy('priority', $direction),
            'status' => $query->orderByRaw(self::STATUS_ORDER_SQL.' '.$direction),
            'updated' => $query->orderBy('updated_at', $direction),
            default => $query->orderBy('created_at', $direction),
        };

        // A stable tie-break keeps pages from repeating or skipping rows.
        return $query->orderBy('created_at', 'desc')->orderBy('id', 'desc');
    }
}
