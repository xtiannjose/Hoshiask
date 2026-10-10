<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Carbon;

trait ValidatesTicketLifecycleDates
{
    protected function validateTicketLifecycleDateOrder(Validator $validator): void
    {
        $parseIfValid = fn (string $field) => $this->filled($field) && ! $validator->errors()->has($field)
            ? Carbon::parse($this->input($field))
            : null;

        $loggedAt = $parseIfValid('created_at');
        $resolvedAt = $parseIfValid('resolved_at');
        $closedAt = $parseIfValid('closed_at');

        if ($loggedAt && $resolvedAt && $resolvedAt->lt($loggedAt)) {
            $validator->errors()->add('resolved_at', 'The resolved date cannot be earlier than the logged date.');
        }

        if ($loggedAt && $closedAt && $closedAt->lt($loggedAt)) {
            $validator->errors()->add('closed_at', 'The closed date cannot be earlier than the logged date.');
        }

        if ($resolvedAt && $closedAt && $closedAt->lt($resolvedAt)) {
            $validator->errors()->add('closed_at', 'The closed date cannot be earlier than the resolved date.');
        }
    }
}
