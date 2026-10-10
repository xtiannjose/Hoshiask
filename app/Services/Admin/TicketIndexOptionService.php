<?php

namespace App\Services\Admin;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use App\Support\DatabaseDateExpressions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TicketIndexOptionService
{
    private ?Collection $activeAssignableAgentsCache = null;

    public function distinctTicketColumnOptions(string $column, ?Builder $scopedBaseQuery = null): Collection
    {
        $this->assertSupportedLocationColumn($column);

        $query = $scopedBaseQuery ? clone $scopedBaseQuery : Ticket::query();

        return $query
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->select($column)
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->values();
    }

    public function accountOptionsFor(Builder $scopedTickets): Collection
    {
        $visibleClientIds = (clone $scopedTickets)
            ->whereNotNull('user_id')
            ->select('user_id')
            ->distinct()
            ->pluck('user_id');

        return User::query()
            ->where('role', User::ROLE_CLIENT)
            ->where('is_active', true)
            ->whereIn('id', $visibleClientIds)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->values();
    }

    public function monthOptionsFor(Builder $scopedTickets): Collection
    {
        $monthKeyExpression = DatabaseDateExpressions::monthKey('created_at', $scopedTickets);

        return (clone $scopedTickets)
            ->whereNotNull('created_at')
            ->selectRaw("{$monthKeyExpression} as month_key")
            ->distinct()
            ->orderByDesc('month_key')
            ->pluck('month_key')
            ->map(function (mixed $monthKey): array {
                $month = Carbon::createFromFormat('!Y-m', (string) $monthKey)->startOfMonth();

                return [
                    'value' => $month->format('Y-m'),
                    'label' => $month->format('F Y'),
                ];
            })
            ->unique('value')
            ->values();
    }

    public function categoryOptionsFor(Builder $scopedTickets): Collection
    {
        $visibleCategoryIds = (clone $scopedTickets)
            ->whereNotNull('category_id')
            ->select('category_id')
            ->distinct()
            ->pluck('category_id');

        return Category::active()
            ->whereIn('id', $visibleCategoryIds)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->values();
    }

    public function assignedAgentOptionsFor(Builder $scopedTickets): Collection
    {
        $visiblePrimaryAssigneeIds = (clone $scopedTickets)
            ->whereNotNull('assigned_to')
            ->select('assigned_to')
            ->distinct()
            ->pluck('assigned_to');

        $visibleAssignmentIds = DB::table('ticket_assignments')
            ->whereIn('ticket_id', (clone $scopedTickets)->select('tickets.id'))
            ->distinct()
            ->pluck('user_id');

        $visibleAssigneeIds = $visiblePrimaryAssigneeIds
            ->merge($visibleAssignmentIds)
            ->map(fn (mixed $id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        if ($visibleAssigneeIds->isEmpty()) {
            return collect();
        }

        return User::query()
            ->whereIn('role', User::TICKET_ASSIGNABLE_ROLES)
            ->where('is_active', true)
            ->whereIn('id', $visibleAssigneeIds)
            ->orderBy('name')
            ->get(['id', 'name', 'role'])
            ->values();
    }

    public function activeAssignableAgents(): Collection
    {
        if ($this->activeAssignableAgentsCache instanceof Collection) {
            return $this->activeAssignableAgentsCache;
        }

        return $this->activeAssignableAgentsCache = User::query()
            ->whereIn('role', User::TICKET_ASSIGNABLE_ROLES)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'role']);
    }

    private function assertSupportedLocationColumn(string $column): void
    {
        if (! in_array($column, ['province', 'municipality'], true)) {
            throw new \InvalidArgumentException('Unsupported ticket location column.');
        }
    }
}
