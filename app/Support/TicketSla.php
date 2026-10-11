<?php

namespace App\Support;

use App\Models\Ticket;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * The SLA state of one ticket: how long it took or has taken, whether it breached the resolution target or is
 * close to it, and the badge text and colour the lists show. It only reads the ticket's dates and status, so it
 * can be tested with an unsaved ticket. "Done" means the first of resolved, closed or cancelled that is set, and
 * the reports use the same rule through minutesToCompletion(). Minutes are counted by BusinessHours: around the
 * clock unless the working-hours setting is on.
 */
final class TicketSla
{
    public function __construct(private readonly Ticket $ticket) {}

    public function completedAt(): ?Carbon
    {
        foreach (['resolved_at', 'closed_at', 'cancelled_at'] as $column) {
            if ($this->ticket->{$column} instanceof Carbon) {
                return $this->ticket->{$column};
            }
        }

        return null;
    }

    /** Minutes from logging to done, or to now (or $asOf) while the ticket is still going. Null without a logged date. */
    public function elapsedMinutes(?CarbonInterface $asOf = null): ?int
    {
        $createdAt = $this->ticket->created_at;
        if (! $createdAt instanceof Carbon) {
            return null;
        }

        $endAt = $this->completedAt() ?? ($asOf ?? now());

        return BusinessHours::elapsedMinutes($createdAt->getTimestamp(), $endAt->getTimestamp());
    }

    /** Minutes to resolution, closure or cancellation, or null while the ticket is still open. */
    public function resolutionMinutes(): ?int
    {
        $stamp = static fn (mixed $date): ?int => $date instanceof Carbon ? $date->getTimestamp() : null;

        return self::minutesToCompletion(
            $stamp($this->ticket->created_at),
            $stamp($this->ticket->resolved_at),
            $stamp($this->ticket->closed_at),
            $stamp($this->ticket->cancelled_at),
        );
    }

    /**
     * The rule behind resolutionMinutes() on plain unix timestamps: "done" is the first of resolved, closed and
     * cancelled that is set. A report over thousands of tickets reads raw rows and calls this, instead of building
     * a model and two Carbon dates for each, so both read "done" the same way.
     */
    public static function minutesToCompletion(?int $createdAt, ?int $resolvedAt, ?int $closedAt, ?int $cancelledAt = null): ?int
    {
        $completedAt = $resolvedAt ?? $closedAt ?? $cancelledAt;
        if ($createdAt === null || $completedAt === null) {
            return null;
        }

        return BusinessHours::elapsedMinutes($createdAt, $completedAt);
    }

    public function hasBreachedResolutionTarget(?CarbonInterface $asOf = null): bool
    {
        $elapsedMinutes = $this->elapsedMinutes($asOf);

        return $elapsedMinutes !== null && $elapsedMinutes >= SlaSettings::resolutionTargetMinutes();
    }

    public function isNearingResolutionTarget(): bool
    {
        $elapsedMinutes = $this->elapsedMinutes();

        return $elapsedMinutes !== null && $elapsedMinutes >= SlaSettings::assignmentReminderMinutes();
    }

    public function badgeLabel(): ?string
    {
        $elapsedLabel = $this->elapsedLabel();

        return $elapsedLabel === null ? null : $this->badgeText().' - '.$elapsedLabel;
    }

    public function badgeClass(): string
    {
        if ($this->ticket->isCancelled()) {
            return 'border-slate-200 bg-slate-50 text-slate-600';
        }

        if ($this->ticket->status === 'pending') {
            return 'border-amber-200 bg-amber-50 text-amber-700';
        }

        if (in_array($this->ticket->status, Ticket::CLOSED_STATUSES, true)) {
            return $this->hasBreachedResolutionTarget()
                ? 'border-red-200 bg-red-50 text-red-700'
                : 'border-emerald-200 bg-emerald-50 text-emerald-700';
        }

        if ($this->hasBreachedResolutionTarget()) {
            return 'border-red-200 bg-red-50 text-red-700';
        }

        if ($this->isNearingResolutionTarget()) {
            return 'border-orange-200 bg-orange-50 text-orange-700';
        }

        return 'border-sky-200 bg-sky-50 text-sky-700';
    }

    private function badgeText(): string
    {
        if ($this->ticket->isCancelled()) {
            return 'Cancelled';
        }

        if ($this->ticket->status === 'pending') {
            return 'Awaiting client';
        }

        if (in_array($this->ticket->status, Ticket::CLOSED_STATUSES, true)) {
            return $this->hasBreachedResolutionTarget() ? 'Breached' : 'Completed';
        }

        if ($this->hasBreachedResolutionTarget()) {
            return 'Breached';
        }

        if ($this->isNearingResolutionTarget()) {
            return 'SLA risk';
        }

        return 'On track';
    }

    private function elapsedLabel(): ?string
    {
        $minutes = $this->elapsedMinutes();
        if ($minutes === null) {
            return null;
        }

        if ($minutes < 60) {
            return $minutes.'m';
        }

        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        return $remainingMinutes > 0
            ? "{$hours}h {$remainingMinutes}m"
            : "{$hours}h";
    }
}
