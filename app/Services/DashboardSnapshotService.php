<?php

namespace App\Services;

use App\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class DashboardSnapshotService
{
    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function recentWindow(int $days = 10): array
    {
        return [now()->subDays($days)->startOfDay(), now()->endOfDay()];
    }

    /**
     * @param  array<string, mixed>  $extraSignals
     */
    public function buildToken(?string $latestUpdatedAt, array $extraSignals = []): string
    {
        $latestUpdatedTimestamp = $latestUpdatedAt ? strtotime($latestUpdatedAt) : 0;

        return sha1(json_encode([
            'latest_updated_at' => $latestUpdatedTimestamp,
            ...$extraSignals,
        ]));
    }

    /**
     * The token of a ticket list: it changes when a ticket is added, removed or updated.
     *
     * @param  Builder<Ticket>  $query
     */
    public function buildTokenForQuery(Builder $query): string
    {
        $updatedAtColumn = $query->getModel()->qualifyColumn('updated_at');
        $snapshot = (clone $query)
            ->toBase()
            ->selectRaw("COUNT(*) as total_tickets, MAX({$updatedAtColumn}) as latest_updated_at")
            ->first();

        return $this->buildToken(
            $snapshot?->latest_updated_at ? (string) $snapshot->latest_updated_at : null,
            ['total_tickets' => (int) ($snapshot->total_tickets ?? 0)]
        );
    }
}
