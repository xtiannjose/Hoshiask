<?php

namespace App\Rules;

use App\Models\Department;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Department names are unique regardless of letter case. The database unique index is case-sensitive,
 * so without this "glade" could be created next to "Glade", giving users two look-alike departments.
 */
class UniqueDepartmentName implements ValidationRule
{
    public function __construct(private readonly ?int $ignoreDepartmentId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $name = mb_strtolower(trim((string) $value));

        $exists = Department::query()
            ->when($this->ignoreDepartmentId !== null, fn ($query) => $query->whereKeyNot($this->ignoreDepartmentId))
            ->whereRaw('LOWER(name) = ?', [$name])
            ->exists();

        if ($exists) {
            $fail('validation.unique')->translate();
        }
    }
}
