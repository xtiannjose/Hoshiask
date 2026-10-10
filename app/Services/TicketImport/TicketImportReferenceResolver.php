<?php

namespace App\Services\TicketImport;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use RuntimeException;

class TicketImportReferenceResolver
{
    /**
     * Status words found in legacy files => ticket status (underscores read as spaces).
     *
     * @var array<string, string>
     */
    private const STATUS_ALIASES = [
        'new' => 'open',
        'open' => 'open',
        'in progress' => 'in_progress',
        'ongoing' => 'in_progress',
        'pending' => 'pending',
        'resolved' => 'resolved',
        'done' => 'resolved',
        'closed' => 'closed',
    ];

    public function __construct(
        private readonly ImportEntityLookupCache $lookupCache,
    ) {}

    /**
     * Severity value for a legacy priority word (e.g. "high", "Severity 2"), or null if unknown.
     */
    public function priorityValue(mixed $value): ?string
    {
        $normalized = strtolower(trim((string) $value));

        return Ticket::normalizePriorityValue(match ($normalized) {
            'normal' => 'medium',
            'critical' => 'severity_1',
            default => $normalized,
        });
    }

    /**
     * Ticket status for a legacy status word (e.g. "done", "In Progress"), or null if unknown.
     */
    public function statusValue(mixed $value): ?string
    {
        return self::STATUS_ALIASES[strtolower(str_replace('_', ' ', trim((string) $value)))] ?? null;
    }

    public function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    public function normalizeLookupKey(mixed $value): ?string
    {
        $value = $this->nullableString($value);
        if ($value === null) {
            return null;
        }

        return strtolower(trim((string) preg_replace('/\s+/u', ' ', $value)));
    }

    public function resolveUserReference(?string $reference, int $row, string $context, string $rowLabel = 'Row'): User
    {
        if ($reference === null) {
            throw new RuntimeException(sprintf('%s %d could not resolve a %s user.', $rowLabel, $row, $context));
        }

        if (ctype_digit($reference)) {
            $user = $this->lookupCache->userById((int) $reference);
            if ($user instanceof User) {
                return $user;
            }
        }

        $user = $this->lookupCache->userByEmail($reference) ?? $this->lookupCache->userByUsername($reference);
        if ($user instanceof User) {
            return $user;
        }

        throw new RuntimeException(sprintf('%s %d could not resolve %s "%s".', $rowLabel, $row, $context, $reference));
    }

    public function resolveOptionalUser(
        int $row,
        mixed $id,
        mixed $email,
        mixed $username,
        string $label,
        string $rowLabel = 'Row'
    ): ?User {
        $idValue = $this->nullableString($id);
        if ($idValue !== null) {
            $user = $this->lookupCache->userById((int) $idValue);
            if ($user instanceof User) {
                return $user;
            }

            throw new RuntimeException(sprintf('%s %d references an unknown %s user_id: %s', $rowLabel, $row, $label, $idValue));
        }

        $emailValue = $this->nullableString($email);
        if ($emailValue !== null) {
            $user = $this->lookupCache->userByEmail($emailValue);
            if ($user instanceof User) {
                return $user;
            }

            throw new RuntimeException(sprintf('%s %d references an unknown %s user_email: %s', $rowLabel, $row, $label, $emailValue));
        }

        $usernameValue = $this->nullableString($username);
        if ($usernameValue !== null) {
            $user = $this->lookupCache->userByUsername($usernameValue);
            if ($user instanceof User) {
                return $user;
            }

            throw new RuntimeException(sprintf('%s %d references an unknown %s user_username: %s', $rowLabel, $row, $label, $usernameValue));
        }

        return null;
    }

    public function resolveCategoryReference(?string $reference, int $row, string $rowLabel = 'Row'): Category
    {
        if ($reference === null) {
            throw new RuntimeException(sprintf('%s %d could not resolve a category.', $rowLabel, $row));
        }

        if (ctype_digit($reference)) {
            $category = $this->lookupCache->categoryById((int) $reference);
            if ($category instanceof Category) {
                return $category;
            }
        }

        $category = $this->lookupCache->categoryByName($reference);
        if ($category instanceof Category) {
            return $category;
        }

        throw new RuntimeException(sprintf('%s %d could not resolve category "%s".', $rowLabel, $row, $reference));
    }
}
