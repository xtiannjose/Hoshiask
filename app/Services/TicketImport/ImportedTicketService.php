<?php

namespace App\Services\TicketImport;

use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketUserState;
use App\Models\User;
use App\Support\SubjectDepartment;
use Illuminate\Support\Carbon;

class ImportedTicketService
{
    private bool $superUserIdResolved = false;

    private ?int $cachedSuperUserId = null;

    /** @var list<string>|null */
    private ?array $departmentNames = null;

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function applyImportMetadata(array $attributes, ?string $requesterDepartment = null): array
    {
        $attributes['is_imported'] = true;
        $attributes['creation_source'] = Ticket::CREATION_SOURCE_IMPORTED;
        $attributes['created_by_user_id'] = null;
        $attributes['department'] = $this->departmentFor((string) ($attributes['subject'] ?? ''), $requesterDepartment);

        return $this->keepDatesInOrder($attributes);
    }

    /**
     * Old tracker sheets contain typos (a close date in 2015 on a ticket logged in 2025, or a close
     * time before the log time on the same day). A ticket cannot be resolved, closed or last updated
     * before it was logged, and the admin date editor refuses that order too, so such a date becomes
     * the logged date. Tracker imports keep the original dates as text in the description.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function keepDatesInOrder(array $attributes): array
    {
        $createdAt = $attributes['created_at'] ?? null;
        if (! $createdAt instanceof Carbon) {
            return $attributes;
        }

        foreach (['resolved_at', 'closed_at', 'updated_at'] as $column) {
            if (($attributes[$column] ?? null) instanceof Carbon && $attributes[$column]->lt($createdAt)) {
                $attributes[$column] = $createdAt->copy();
            }
        }

        return $attributes;
    }

    /**
     * Legacy rows are all filed under one placeholder account in the support department, so their
     * department is read from the start of the subject ("Alder - Printer jammed"). A requester with a
     * real department of their own keeps it. Null means "no better answer than the requester's".
     */
    private function departmentFor(string $subject, ?string $requesterDepartment): ?string
    {
        $own = trim((string) $requesterDepartment);

        if ($own !== '' && strcasecmp($own, User::supportDepartment()) !== 0) {
            return $own;
        }

        $this->departmentNames ??= Department::query()->pluck('name')->all();

        return SubjectDepartment::match($subject, $this->departmentNames) ?? ($own !== '' ? $own : null);
    }

    public function syncImportedReviewState(Ticket $ticket): void
    {
        $assignedAt = $ticket->assigned_at instanceof Carbon
            ? $ticket->assigned_at
            : ($ticket->assigned_at ? Carbon::parse($ticket->assigned_at) : null);

        if (! $assignedAt instanceof Carbon) {
            return;
        }

        $superUserId = $this->firstSuperUserId();

        if (! $superUserId) {
            return;
        }

        TicketUserState::markAcknowledged($ticket, (int) $superUserId, $assignedAt, true);
    }

    private function firstSuperUserId(): ?int
    {
        if ($this->superUserIdResolved) {
            return $this->cachedSuperUserId;
        }

        $this->cachedSuperUserId = User::query()
            ->where('role', User::ROLE_SUPER_USER)
            ->orderBy('id')
            ->value('id');
        $this->superUserIdResolved = true;

        return $this->cachedSuperUserId;
    }
}
