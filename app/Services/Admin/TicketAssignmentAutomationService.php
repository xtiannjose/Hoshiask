<?php

namespace App\Services\Admin;

use App\Models\Ticket;
use App\Models\TicketAssignmentRule;
use App\Models\TicketAssignmentSetting;
use App\Services\SystemLogService;
use Illuminate\Support\Collection;

class TicketAssignmentAutomationService
{
    public function __construct(
        private TicketAssignmentService $ticketAssignments,
        private SystemLogService $systemLogs,
    ) {}

    public function autoAssignIfUnassigned(Ticket $ticket): void
    {
        if ($ticket->assigned_to !== null || $ticket->category_id === null) {
            return;
        }

        $rules = TicketAssignmentRule::query()
            ->where('category_id', $ticket->category_id)
            // Someone away (assignment_paused_until in the future) is skipped; a day-off does not need a rule change.
            ->whereHas('technician', fn ($query) => $query
                ->where('is_active', true)
                ->where(fn ($away) => $away->whereNull('assignment_paused_until')->orWhere('assignment_paused_until', '<=', now())))
            ->get();

        if ($rules->isEmpty()) {
            return;
        }

        $distributionMethod = TicketAssignmentSetting::current()->distribution_method;
        $technicianId = match ($distributionMethod) {
            TicketAssignmentSetting::METHOD_ROTATING_COUNTER => $this->pickByRotatingCounter($ticket, $rules),
            TicketAssignmentSetting::METHOD_LEAST_WORKLOAD => $this->pickByLeastWorkload($rules),
            default => $this->pickByWeightedRandom($rules),
        };

        if ($technicianId === null) {
            return;
        }

        $ticket->update([
            'assigned_to' => $technicianId,
            'assigned_at' => now(),
        ]);
        $this->ticketAssignments->syncAssignmentState($ticket, [], [$technicianId], notifyNewAssignees: true);

        $this->systemLogs->record(
            'ticket.assignment.automated',
            'Automatically assigned ticket using a category assignment rule.',
            [
                'category' => 'ticket',
                'target_type' => Ticket::class,
                'target_id' => $ticket->id,
                'metadata' => [
                    'ticket_number' => $ticket->ticket_number,
                    'category_id' => $ticket->category_id,
                    'assigned_to' => $technicianId,
                    'distribution_method' => $distributionMethod,
                ],
            ]
        );
    }

    /**
     * @param  Collection<int, TicketAssignmentRule>  $rules
     */
    private function pickByWeightedRandom(Collection $rules): ?int
    {
        $totalWeight = (int) $rules->sum('weight');
        if ($totalWeight <= 0) {
            return null;
        }

        $roll = random_int(1, $totalWeight);
        $cumulative = 0;

        foreach ($rules as $rule) {
            $cumulative += (int) $rule->weight;
            if ($roll <= $cumulative) {
                return (int) $rule->user_id;
            }
        }

        return (int) $rules->last()->user_id;
    }

    /**
     * The technician with the least work for their weight: Open and In Progress tickets they hold (as the primary or
     * as one of several assignees, in any category) divided by the rule's weight, so a weight of 60 carries twice
     * the work of a weight of 30. Pending tickets wait on someone else and imported ones are old records, so
     * neither counts. A tie goes to the bigger weight, then to the lower user id, so the choice is repeatable.
     *
     * @param  Collection<int, TicketAssignmentRule>  $rules
     */
    private function pickByLeastWorkload(Collection $rules): ?int
    {
        $best = null;

        foreach ($rules as $rule) {
            $weight = (int) $rule->weight;
            if ($weight <= 0) {
                continue;
            }

            $userId = (int) $rule->user_id;
            $load = Ticket::applyAssignedToConstraint(
                Ticket::query()
                    ->whereIn('status', Ticket::SLA_CLOCK_STATUSES)
                    ->where('is_imported', false),
                $userId
            )->count();

            // load/weight < bestLoad/bestWeight, compared without dividing.
            $better = $best === null
                || $load * $best['weight'] < $best['load'] * $weight
                || ($load * $best['weight'] === $best['load'] * $weight
                    && ($weight > $best['weight'] || ($weight === $best['weight'] && $userId < $best['user_id'])));

            if ($better) {
                $best = ['user_id' => $userId, 'load' => $load, 'weight' => $weight];
            }
        }

        return $best['user_id'] ?? null;
    }

    /**
     * @param  Collection<int, TicketAssignmentRule>  $rules
     */
    private function pickByRotatingCounter(Ticket $ticket, Collection $rules): ?int
    {
        $totalWeight = (int) $rules->sum('weight');
        if ($totalWeight <= 0) {
            return null;
        }

        $technicianIds = $rules->pluck('user_id')->map(fn (mixed $id): int => (int) $id)->all();
        $actualCounts = Ticket::query()
            ->where('category_id', $ticket->category_id)
            ->whereIn('assigned_to', $technicianIds)
            // Recent assignments only: over all time a technician added later would get every new ticket until
            // the lifetime shares matched.
            ->whereRaw('COALESCE(assigned_at, created_at) >= ?', [now()->subDays((int) config('hoshiask.assignment.rotation_window_days', 30))])
            ->selectRaw('assigned_to, COUNT(*) as aggregate')
            ->groupBy('assigned_to')
            ->pluck('aggregate', 'assigned_to');
        $totalAssigned = (int) $actualCounts->sum();

        $bestUserId = null;
        $bestDeficit = null;

        foreach ($rules as $rule) {
            $userId = (int) $rule->user_id;
            $targetShare = $rule->weight / $totalWeight;
            $actualShare = $totalAssigned > 0 ? (int) ($actualCounts[$userId] ?? 0) / $totalAssigned : 0.0;
            $deficit = $targetShare - $actualShare;

            if ($bestDeficit === null || $deficit > $bestDeficit) {
                $bestDeficit = $deficit;
                $bestUserId = $userId;
            }
        }

        return $bestUserId;
    }
}
