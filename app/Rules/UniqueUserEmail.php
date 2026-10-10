<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Email addresses are unique regardless of letter case. Sign-in matches them in lower case while the database
 * unique index is case-sensitive, so without this "Ana@office.gov.ph" could sit next to "ana@office.gov.ph" and
 * email sign-in would pick one of the two accounts at random.
 */
class UniqueUserEmail implements ValidationRule
{
    public function __construct(private readonly ?int $ignoreUserId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $exists = User::query()
            ->when($this->ignoreUserId !== null, fn ($query) => $query->whereKeyNot($this->ignoreUserId))
            ->whereRaw('LOWER(email) = ?', [mb_strtolower(trim((string) $value))])
            ->exists();

        if ($exists) {
            $fail('validation.unique')->translate();
        }
    }
}
