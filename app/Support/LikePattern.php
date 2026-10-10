<?php

namespace App\Support;

/**
 * Builds "contains" patterns for user-typed search boxes so that % and _ in the search text
 * match themselves instead of acting as wildcards. Pair the pattern with
 * `LIKE ? ESCAPE '!'` (LIKE_ESCAPE): SQLite has no default escape character, so it must be explicit.
 */
final class LikePattern
{
    public const LIKE_ESCAPE = "ESCAPE '!'";

    public static function contains(string $term): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';
    }
}
