<?php

namespace App\Support;

/**
 * What the audit log keeps of the text typed in the sign-in box after a failed attempt.
 *
 * People sometimes type or paste their password into the username box, so a failed attempt keeps the text
 * only when it names a real account or is shaped like an email address (a mistyped address is not a secret).
 * Anything else is kept in a masked form: the first two characters, asterisks, and how long the text was.
 */
final class LoggedLoginText
{
    private const MAX_ASTERISKS = 8;

    public static function forLog(string $typed, bool $namesAnAccount): string
    {
        $typed = trim($typed);

        if ($typed === '' || $namesAnAccount || filter_var($typed, FILTER_VALIDATE_EMAIL) !== false) {
            return $typed;
        }

        return self::mask($typed);
    }

    public static function mask(string $typed): string
    {
        $length = mb_strlen($typed);
        $shown = $length > 2 ? mb_substr($typed, 0, 2) : '';

        return $shown.str_repeat('*', min(self::MAX_ASTERISKS, $length - mb_strlen($shown))).' ('.$length.($length === 1 ? ' char)' : ' chars)');
    }

    /**
     * True only for text that mask() could have produced, so running a clean-up twice changes nothing.
     */
    public static function isMasked(string $text): bool
    {
        if (preg_match('/^(.{2})?(\*{1,'.self::MAX_ASTERISKS.'}) \((\d+) chars?\)$/su', $text, $parts) !== 1) {
            return false;
        }

        $length = (int) $parts[3];
        $prefix = $parts[1];

        return $length >= 1 && $text === self::mask($prefix.str_repeat('x', $length - mb_strlen($prefix)));
    }
}
