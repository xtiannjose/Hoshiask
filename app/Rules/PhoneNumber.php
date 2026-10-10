<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A number staff can actually dial: digits with optional spaces, dashes, dots, brackets and a
 * leading +, at least 7 digits. Accepts "09171234567", "+63 917 123 4567" and "(02) 8123-4567".
 */
class PhoneNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $text = trim((string) $value);

        if ($text === '') {
            return;
        }

        $digits = preg_replace('/\D/', '', $text) ?? '';

        if (! preg_match('/^\+?[0-9()\-\s.]+$/', $text) || strlen($digits) < 7 || strlen($digits) > 15) {
            $fail('Enter a phone number using digits only, with optional spaces, dashes or a leading +. For example 0917 123 4567.');
        }
    }
}
