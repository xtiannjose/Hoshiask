<?php

namespace App\Http\Requests\Concerns;

use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

trait ResolvesAssignableAgentRule
{
    protected function assignableAgentRule(): Exists
    {
        return Rule::exists('users', 'id')->where(function ($query) {
            $query->whereIn('role', User::TICKET_ASSIGNABLE_ROLES)
                ->where('is_active', true);
        });
    }
}
