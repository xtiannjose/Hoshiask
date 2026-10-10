<?php

namespace App\Services\Admin;

use App\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class TicketIndexSnapshotService
{
    public function buildTicketListSnapshotToken(Builder $query): string
    {
        $qualifiedUpdatedAtColumn = $query->getModel()->qualifyColumn('updated_at');
        $snapshot = (clone $query)
            ->toBase()
            ->selectRaw("COUNT(*) as total_tickets, MAX({$qualifiedUpdatedAtColumn}) as latest_updated_at")
            ->first();
        $latestUpdatedTimestamp = $snapshot?->latest_updated_at
            ? strtotime((string) $snapshot->latest_updated_at)
            : 0;
        $totalTickets = (int) ($snapshot->total_tickets ?? 0);

        return sha1(json_encode([
            'latest_updated_at' => $latestUpdatedTimestamp,
            'total_tickets' => $totalTickets,
        ]));
    }

    public function buildTicketListPageSnapshotToken(Builder $orderedQuery, int $page, int $perPage): string
    {
        $idColumn = $orderedQuery->getModel()->qualifyColumn('id');
        $updatedAtColumn = $orderedQuery->getModel()->qualifyColumn('updated_at');
        $pageRows = (clone $orderedQuery)
            ->forPage($page, $perPage)
            ->get([$idColumn, $updatedAtColumn]);

        return $this->buildTicketListPageSnapshotTokenForTickets($pageRows);
    }

    public function buildTicketListPageSnapshotTokenForTickets(LengthAwarePaginator|Collection $tickets): string
    {
        $ticketItems = $tickets instanceof LengthAwarePaginator
            ? $tickets->getCollection()
            : $tickets;

        return sha1(json_encode(
            $ticketItems
                ->map(fn (Ticket $ticket): array => [
                    'id' => (int) $ticket->id,
                    'updated_at' => optional($ticket->updated_at)->getTimestamp() ?? 0,
                ])
                ->values()
                ->all()
        ));
    }
}
