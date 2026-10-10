<?php

namespace App\Support;

/**
 * Works out which department an old, imported ticket came from by reading the start of its subject.
 *
 * The legacy tracker filed every row under one placeholder account, so the only record of the
 * client is the prefix of the subject: "Alder - Printer jammed", "Juniper R3 LEOSAT - No signal".
 */
final class SubjectDepartment
{
    /**
     * The department named first in the subject's prefix, or null when the subject has no prefix
     * or names none of them. Names match as whole words ignoring case, so "Juniper" is found in
     * "PARTNER_Juniper R1-2" but "Orb" is not found in "Orbit 5".
     *
     * @param  iterable<string>  $departmentNames
     */
    public static function match(string $subject, iterable $departmentNames): ?string
    {
        $prefix = self::prefix($subject);
        if ($prefix === null) {
            return null;
        }

        $best = null;
        $bestPosition = PHP_INT_MAX;

        foreach ($departmentNames as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }

            $pattern = '/(?<![\p{L}\p{N}])'.preg_quote($name, '/').'(?![\p{L}\p{N}])/iu';
            if (preg_match($pattern, $prefix, $found, PREG_OFFSET_CAPTURE) !== 1) {
                continue;
            }

            // The earliest mention wins; on a tie the longer (more specific) name does.
            $position = $found[0][1];
            if ($position < $bestPosition || ($position === $bestPosition && mb_strlen($name) > mb_strlen((string) $best))) {
                $best = $name;
                $bestPosition = $position;
            }
        }

        return $best;
    }

    /**
     * The text before the first " - " (hyphen, en dash or em dash) in the subject, or null when
     * the subject has no such separator.
     */
    public static function prefix(string $subject): ?string
    {
        $parts = preg_split('/\s+[-–—]\s+/u', trim($subject), 2);

        // false means the text is not valid UTF-8 (old sheets can hold stray bytes): no readable prefix.
        if ($parts === false || count($parts) !== 2) {
            return null;
        }

        $prefix = trim($parts[0]);

        return $prefix !== '' ? $prefix : null;
    }
}
