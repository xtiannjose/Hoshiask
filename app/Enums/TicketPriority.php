<?php

namespace App\Enums;

/**
 * Ticket severities (stored in tickets.priority): the one place that defines each
 * severity's value, label and colours. A ticket without a severity is "Severity not set".
 * Severity 1 is the most urgent.
 */
enum TicketPriority: string
{
    case Severity1 = 'severity_1';
    case Severity2 = 'severity_2';
    case Severity3 = 'severity_3';

    public const SEVERITY_NOT_SET_LABEL = 'Severity not set';

    public const SEVERITY_NOT_SET_CHART_COLOR = '#64748b';

    public static function labelFor(?string $value): string
    {
        return self::tryFromValue($value)?->label() ?? self::SEVERITY_NOT_SET_LABEL;
    }

    public static function tryFromValue(?string $value): ?self
    {
        return self::tryFrom(strtolower(trim((string) $value)));
    }

    public function label(): string
    {
        return match ($this) {
            self::Severity1 => 'Severity 1',
            self::Severity2 => 'Severity 2',
            self::Severity3 => 'Severity 3',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Severity1 => 'bg-red-100 text-red-800',
            self::Severity2 => 'bg-amber-100 text-amber-800',
            self::Severity3 => 'bg-emerald-100 text-emerald-800',
        };
    }

    /**
     * Colour for this severity in report charts (same meaning as the badges: red = most urgent).
     */
    public function chartColor(): string
    {
        return match ($this) {
            self::Severity1 => '#ef4444',
            self::Severity2 => '#f59e0b',
            self::Severity3 => '#10b981',
        };
    }
}
