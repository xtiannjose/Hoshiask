<?php

namespace App\Support;

use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * SQL snippets for grouping by month/week, per database driver
 * (PostgreSQL in production, SQLite in tests, MySQL/MariaDB supported).
 */
final class DatabaseDateExpressions
{
    /**
     * "YYYY-MM" for the given column or SQL expression.
     *
     * @param  Builder<Model>  $query
     */
    public static function monthKey(string $column, Builder $query): string
    {
        return match (self::driver($query)) {
            'pgsql' => "TO_CHAR({$column}, 'YYYY-MM')",
            'sqlite' => "strftime('%Y-%m', {$column})",
            default => "DATE_FORMAT({$column}, '%Y-%m')",
        };
    }

    /**
     * "YYYY-MM-DD" of the Monday starting the column's week.
     *
     * @param  Builder<Model>  $query
     */
    public static function weekStartDate(string $column, Builder $query): string
    {
        return match (self::driver($query)) {
            'pgsql' => "TO_CHAR(DATE_TRUNC('week', {$column})::date, 'YYYY-MM-DD')",
            'sqlite' => "date({$column}, '-' || ((CAST(strftime('%w', {$column}) AS integer) + 6) % 7) || ' days')",
            default => "DATE_FORMAT(DATE_SUB(DATE({$column}), INTERVAL WEEKDAY({$column}) DAY), '%Y-%m-%d')",
        };
    }

    /**
     * @param  Builder<Model>  $query
     */
    private static function driver(Builder $query): string
    {
        /** @var Connection $connection */
        $connection = $query->getQuery()->getConnection();

        return $connection->getDriverName();
    }
}
