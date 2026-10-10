<?php

namespace App\Services\Client;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ClientDashboardSummaryService
{
    public function __construct(
        private ClientTicketIndexService $ticketIndex,
    ) {}

    /**
     * @return array{stats: array{total_tickets: int, open_tickets: int, in_progress_tickets: int}, live_snapshot_token: string}
     */
    public function summaryFor(User $user): array
    {
        $ticketQuery = $this->ticketIndex->scopedTicketQueryFor($user);
        $summary = $this->ticketIndex->buildSummarySnapshot(clone $ticketQuery);

        return [
            'stats' => [
                'total_tickets' => (int) ($summary['total_tickets'] ?? 0),
                'open_tickets' => (int) ($summary['open_tickets'] ?? 0),
                'in_progress_tickets' => (int) ($summary['in_progress_tickets'] ?? 0),
            ],
            'live_snapshot_token' => $this->ticketIndex->buildSnapshotTokenFromSummary($summary),
        ];
    }

    /**
     * @return Builder<Ticket>
     */
    public function recentTicketsQueryFor(User $user): Builder
    {
        return $this->ticketIndex->scopedTicketQueryFor($user);
    }
}
