<?php

namespace App\Support;

use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;

/**
 * The report "category buckets" (Hardware, Software, ...), defined once.
 *
 * Reports bucket categories in PHP, while the ticket list filters by bucket in SQL.
 * Both use the rules below, so a report count and its drill-down list always agree:
 * a category belongs to the FIRST bucket with a keyword in its name, otherwise "Other".
 */
final class TicketCategoryBuckets
{
    public const OTHER = 'Other';

    private const OTHER_COLOR = '#64748b';

    /**
     * @var array<string, array{keywords: list<string>, color: string}>
     */
    private const BUCKETS = [
        'Hardware' => ['keywords' => ['hardware'], 'color' => '#0ea5e9'],
        'Software' => ['keywords' => ['software', 'application'], 'color' => '#8b5cf6'],
        'Network' => ['keywords' => ['network', 'connect'], 'color' => '#14b8a6'],
        'Access / Permissions' => ['keywords' => ['access', 'permission', 'account'], 'color' => '#f59e0b'],
    ];

    /**
     * Old filter keys that must keep working (saved views, bookmarks). The Security
     * category was retired and its tickets moved to Other.
     */
    private const LEGACY_FILTER_KEYS = [
        'security' => self::OTHER,
    ];

    /**
     * @return list<string>
     */
    public static function labels(): array
    {
        return [...array_keys(self::BUCKETS), self::OTHER];
    }

    public static function forCategoryName(?string $categoryName): string
    {
        $name = strtolower(trim((string) $categoryName));

        foreach (self::BUCKETS as $label => $bucket) {
            foreach ($bucket['keywords'] as $keyword) {
                if (str_contains($name, $keyword)) {
                    return $label;
                }
            }
        }

        return self::OTHER;
    }

    public static function color(string $label): string
    {
        return self::BUCKETS[$label]['color'] ?? self::OTHER_COLOR;
    }

    /**
     * URL filter key for a bucket label, e.g. "Access / Permissions" => "access_permissions".
     */
    public static function filterKey(string $label): string
    {
        return strtolower(str_replace([' / ', '/', ' '], '_', $label));
    }

    public static function labelForFilterKey(string $rawKey): ?string
    {
        $key = self::filterKey(trim($rawKey));
        if ($key === '') {
            return null;
        }

        foreach (self::labels() as $label) {
            if (self::filterKey($label) === $key) {
                return $label;
            }
        }

        return self::LEGACY_FILTER_KEYS[$key] ?? null;
    }

    /**
     * Limit a category query to the given bucket using the same first-match rule as
     * forCategoryName(): earlier buckets' keywords are excluded.
     *
     * @param  Builder<Category>  $categoryQuery
     */
    public static function constrainCategoryQuery(Builder $categoryQuery, string $label): void
    {
        foreach (self::BUCKETS as $bucketLabel => $bucket) {
            if ($bucketLabel === $label) {
                $categoryQuery->where(function (Builder $query) use ($bucket): void {
                    foreach ($bucket['keywords'] as $keyword) {
                        $query->orWhereRaw('LOWER(name) LIKE ?', ['%'.$keyword.'%']);
                    }
                });

                return;
            }

            foreach ($bucket['keywords'] as $keyword) {
                $categoryQuery->whereRaw('LOWER(name) NOT LIKE ?', ['%'.$keyword.'%']);
            }
        }
    }
}
