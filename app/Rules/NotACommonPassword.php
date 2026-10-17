<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Refuses the passwords attackers try first: well-known ones ("password", "12345678", "qwerty123"),
 * those built from a well-known word plus digits or symbols ("Password2026!"), and trivial patterns
 * (one repeated character, a run of consecutive digits). It never sees or stores the password beyond this
 * check, and existing passwords are unaffected. It cannot tell a hard password from an easy one; it only
 * stops the worst.
 */
class NotACommonPassword implements ValidationRule
{
    public const HINT = 'At least 8 characters. Very common passwords such as "password" or "12345678" are not accepted.';

    /**
     * Whole passwords, lowercase, that are refused outright.
     *
     * @var array<int, string>
     */
    private const COMMON = [
        '12345678', '123456789', '1234567890', '11111111', '00000000', '87654321', '987654321', '0987654321',
        '12341234', '12121212', '11223344', '1q2w3e4r', '1qaz2wsx', '1q2w3e4r5t', 'q1w2e3r4', 'zaq12wsx',
        'qwertyui', 'qwertyuiop', 'asdfghjk', 'asdfghjkl', 'zxcvbnm1', 'qazwsxedc', 'abcd1234', 'abc12345',
        'abcdefgh', 'iloveyou', 'sunshine', 'princess', 'football', 'baseball', 'superman', 'trustno1',
        'letmein1', 'welcome1', 'monkey12', 'dragon12', 'master12', 'shadow12', 'michael1', 'jennifer',
        'starwars', 'whatever', 'computer', 'internet', 'mustang1', 'passw0rd', 'p@ssw0rd', 'p@ssword',
        'pa$$word', 'admin123', 'admin1234', 'administrator', 'changeme', 'changeme1', 'default1', 'test1234',
        'testing1', 'guest123', 'user1234', 'login123', 'root1234', 'hoshiask', 'hoshiask1', 'hoshiask123',
    ];

    /**
     * Well-known words. A password that is one of these followed only by digits and symbols is refused.
     *
     * @var array<int, string>
     */
    private const COMMON_WORDS = [
        'password', 'passw0rd', 'p@ssword', 'p@ssw0rd', 'pa$$word', 'qwerty', 'qwertyuiop', 'letmein', 'welcome',
        'admin', 'administrator', 'iloveyou', 'monkey', 'dragon', 'master', 'shadow', 'sunshine', 'princess',
        'football', 'baseball', 'superman', 'trustno', 'changeme', 'default', 'hoshiask', 'temp', 'temporary',
        'abc', 'abcd', 'abcde', 'abcdef', 'secret', 'login', 'guest', 'test', 'user',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $password = (string) $value;

        // Length is enforced by the min rule; this only judges what is long enough to be checked.
        if ($password === '' || ! self::isCommon($password)) {
            return;
        }

        $fail('That password is too easy to guess. Choose something less common, for example a short phrase of several words.');
    }

    public static function isCommon(string $password): bool
    {
        $lower = mb_strtolower($password);

        if (in_array($lower, self::COMMON, true)) {
            return true;
        }

        // A single repeated character: aaaaaaaa, 11111111.
        if (preg_match('/^(.)\1+$/us', $password) === 1) {
            return true;
        }

        // A run of consecutive digits (up or down): 12345678, 23456789, 98765432.
        if (preg_match('/^[0-9]+$/', $password) === 1 && self::isDigitRun($password)) {
            return true;
        }

        // A well-known word with only digits or symbols after it: Password1, Welcome2026!, Qwerty#123.
        $stem = rtrim($lower, '0123456789!@#$%^&*()-_=+.,?');
        if ($stem !== $lower && in_array($stem, self::COMMON_WORDS, true)) {
            return true;
        }

        return in_array($lower, self::COMMON_WORDS, true) && mb_strlen($lower) >= 8;
    }

    private static function isDigitRun(string $digits): bool
    {
        $length = strlen($digits);
        if ($length < 2) {
            return false;
        }

        $step = (int) $digits[1] - (int) $digits[0];
        if (abs($step) !== 1) {
            return false;
        }

        for ($i = 1; $i < $length; $i++) {
            if ((int) $digits[$i] - (int) $digits[$i - 1] !== $step) {
                return false;
            }
        }

        return true;
    }
}
