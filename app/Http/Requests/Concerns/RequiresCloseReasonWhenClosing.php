<?php

namespace App\Http\Requests\Concerns;

use Closure;
use Illuminate\Validation\Rule;

trait RequiresCloseReasonWhenClosing
{
    /**
     * @return array<int, mixed>
     */
    protected function closeReasonRule(Closure $isClosing): array
    {
        return [
            Rule::requiredIf($isClosing),
            'nullable',
            'string',
            'max:1000',
        ];
    }
}
