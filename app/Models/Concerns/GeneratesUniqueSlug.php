<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

trait GeneratesUniqueSlug
{
    /**
     * Slugifies $source and appends "-2", "-3", etc. until the slug is free on this table.
     */
    protected static function uniqueSlugFor(string $source, string $fallback, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($source);
        if ($baseSlug === '') {
            $baseSlug = $fallback;
        }

        $candidate = $baseSlug;
        $suffix = 1;

        while (self::query()
            ->when($ignoreId !== null, fn (Builder $query) => $query->where('id', '!=', $ignoreId))
            ->where('slug', $candidate)
            ->exists()
        ) {
            $suffix++;
            $candidate = $baseSlug.'-'.$suffix;
        }

        return $candidate;
    }
}
