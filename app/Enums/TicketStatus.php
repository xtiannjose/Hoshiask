<?php

namespace App\Enums;

/**
 * Ticket lifecycle statuses: the one place that defines each status's stored value,
 * label and colours. Ticket::STATUSES / OPEN_STATUSES / CLOSED_STATUSES are built from it.
 */
enum TicketStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Pending = 'pending';
    case Resolved = 'resolved';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::InProgress => 'In Progress',
            self::Pending => 'Pending',
            self::Resolved => 'Resolved',
            self::Closed => 'Closed',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Solid badge used in the ticket lists. White text on the 400/500 shades read at only 1.7 to 2.9
     * to 1 against the 4.5 to 1 that small text needs, so every shade here is a 700 (600 for grey).
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Open => 'bg-blue-700 text-white',
            self::InProgress => 'bg-violet-700 text-white',
            self::Pending => 'bg-amber-700 text-white',
            self::Resolved => 'bg-emerald-700 text-white',
            self::Closed => 'bg-slate-600 text-white',
            self::Cancelled => 'bg-rose-700 text-white',
        };
    }

    /**
     * Tinted badge used on dashboards, ticket headers and profile cards.
     */
    public function softBadgeClass(): string
    {
        return match ($this) {
            self::Open => 'bg-blue-100 text-blue-800',
            self::InProgress => 'bg-violet-100 text-violet-800',
            self::Pending => 'bg-amber-100 text-amber-800',
            self::Resolved => 'bg-emerald-100 text-emerald-800',
            self::Closed => 'bg-slate-100 text-slate-700',
            self::Cancelled => 'bg-rose-100 text-rose-800',
        };
    }

    /**
     * Small activity dot shown next to a ticket in the lists.
     */
    public function dotClass(): string
    {
        return match ($this) {
            self::Open => 'bg-blue-500',
            self::InProgress => 'bg-violet-500',
            self::Pending => 'bg-amber-500',
            self::Resolved => 'bg-emerald-500',
            self::Closed => 'bg-slate-400',
            self::Cancelled => 'bg-rose-500',
        };
    }

    /**
     * Colour for this status in report charts.
     */
    public function chartColor(): string
    {
        return match ($this) {
            self::Open => '#3b82f6',
            self::InProgress => '#8b5cf6',
            self::Pending => '#f59e0b',
            self::Resolved => '#10b981',
            self::Closed => '#64748b',
            self::Cancelled => '#f43f5e',
        };
    }
}
