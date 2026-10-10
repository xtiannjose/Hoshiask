<?php

namespace App\Services\Admin;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class TicketIndexService
{
    public function __construct(
        private TicketIndexFilterService $filters,
        private TicketIndexOptionService $options,
        private TicketIndexScopeService $scopes,
        private TicketIndexSnapshotService $snapshots,
    ) {}

    public function resolveActiveTab(string $requestedTab, ?User $user = null): string
    {
        return $this->scopes->resolveActiveTab($requestedTab, $user);
    }

    public function resolveSelectedStatus(string $requestedStatus, string $activeTab): string
    {
        return $this->scopes->resolveSelectedStatus($requestedStatus, $activeTab);
    }

    public function resolveCreatedDateRange(Request $request): ?array
    {
        return $this->filters->resolveCreatedDateRange($request);
    }

    public function scopedTicketQueryFor(?User $user, ?string $activeTab = null): Builder
    {
        return $this->scopes->scopedTicketQueryFor($user, $activeTab);
    }

    public function applyTabScope(Builder $query, string $activeTab): void
    {
        $this->scopes->applyTabScope($query, $activeTab);
    }

    public function applyFilters(
        Builder $query,
        Request $request,
        string $selectedStatus,
        ?array $createdDateRange = null,
    ): void {
        $this->filters->applyFilters($query, $request, $selectedStatus, $createdDateRange);
    }

    public function applyFiltersExcept(
        Builder $query,
        Request $request,
        string $selectedStatus,
        ?array $createdDateRange = null,
        array $excludedFilters = [],
    ): void {
        $this->filters->applyFiltersExcept($query, $request, $selectedStatus, $createdDateRange, $excludedFilters);
    }

    public function buildTicketListSnapshotToken(Builder $query): string
    {
        return $this->snapshots->buildTicketListSnapshotToken($query);
    }

    public function buildTicketListPageSnapshotToken(Builder $orderedQuery, int $page, int $perPage): string
    {
        return $this->snapshots->buildTicketListPageSnapshotToken($orderedQuery, $page, $perPage);
    }

    public function buildTicketListPageSnapshotTokenForTickets(LengthAwarePaginator|Collection $tickets): string
    {
        return $this->snapshots->buildTicketListPageSnapshotTokenForTickets($tickets);
    }

    public function distinctTicketColumnOptions(string $column, ?Builder $scopedBaseQuery = null): Collection
    {
        return $this->options->distinctTicketColumnOptions($column, $scopedBaseQuery);
    }

    public function accountOptionsFor(Builder $scopedTickets): Collection
    {
        return $this->options->accountOptionsFor($scopedTickets);
    }

    public function categoryOptionsFor(Builder $scopedTickets): Collection
    {
        return $this->options->categoryOptionsFor($scopedTickets);
    }

    public function assignedAgentOptionsFor(Builder $scopedTickets): Collection
    {
        return $this->options->assignedAgentOptionsFor($scopedTickets);
    }

    public function monthOptionsFor(Builder $scopedTickets): Collection
    {
        return $this->options->monthOptionsFor($scopedTickets);
    }

    public function activeAssignableAgents(): Collection
    {
        return $this->options->activeAssignableAgents();
    }
}
