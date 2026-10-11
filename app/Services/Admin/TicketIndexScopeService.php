<?php

namespace App\Services\Admin;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class TicketIndexScopeService
{
    public function resolveActiveTab(string $requestedTab, ?User $user = null): string
    {
        $activeTab = in_array($requestedTab, ['all', 'tickets', 'attention', 'breached', 'history'], true)
            ? $requestedTab
            : 'tickets';

        if ($activeTab === 'attention' && $user?->isTechnician()) {
            return 'tickets';
        }

        return $activeTab;
    }

    public function resolveSelectedStatus(string $requestedStatus, string $activeTab): string
    {
        $selectedStatus = trim($requestedStatus);
        if ($selectedStatus === '') {
            $selectedStatus = 'all';
        }

        $allowedStatuses = $this->allowedStatusesForTab($activeTab);

        return in_array($selectedStatus, $allowedStatuses, true)
            ? $selectedStatus
            : 'all';
    }

    public function scopedTicketQueryFor(?User $user, ?string $activeTab = null): Builder
    {
        $query = Ticket::query();

        if ($user && $user->isTechnician()) {
            $query->where(function (Builder $builder) use ($user, $activeTab) {
                Ticket::applyAssignedToConstraint($builder, (int) $user->id);

                if ($activeTab === 'history') {
                    $builder->orWhere(function (Builder $requesterBuilder) use ($user) {
                        Ticket::applyClosedInternalRequesterConstraint($requesterBuilder, (int) $user->id);
                    });
                }
            });
        }

        return $query;
    }

    public function applyTabScope(Builder $query, string $activeTab): void
    {
        if ($activeTab === 'all') {
            return;
        }

        if ($activeTab === 'history') {
            $query->whereIn('status', [...Ticket::CLOSED_STATUSES, ...Ticket::CANCELLED_STATUSES]);

            return;
        }

        if ($activeTab === 'attention') {
            $query->whereIn('status', Ticket::SLA_CLOCK_STATUSES)
                ->where('created_at', '<=', now()->subHours(Ticket::NEEDS_ATTENTION_AFTER_HOURS));

            return;
        }

        if ($activeTab === 'breached') {
            $targetMinutes = Ticket::resolutionTargetMinutes();
            $query->whereIn('status', Ticket::SLA_CLOCK_STATUSES)
                ->where('created_at', '<=', now()->subMinutes($targetMinutes));

            return;
        }

        $query->whereIn('status', Ticket::OPEN_STATUSES);
    }

    private function allowedStatusesForTab(string $activeTab): array
    {
        return $activeTab === 'history'
            ? array_merge(['all'], Ticket::CLOSED_STATUSES, Ticket::CANCELLED_STATUSES)
            : ($activeTab === 'all'
                ? array_merge(['all'], Ticket::OPEN_STATUSES, Ticket::CLOSED_STATUSES, Ticket::CANCELLED_STATUSES)
                : array_merge(['all'], Ticket::OPEN_STATUSES));
    }
}
