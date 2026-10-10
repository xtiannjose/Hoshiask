<?php

namespace App\Http\Requests\Admin\TicketAssignmentRules;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketAssignmentRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(function ($query) {
                    $query->whereIn('role', User::TICKET_ASSIGNABLE_ROLES)->where('is_active', true);
                }),
                Rule::unique('ticket_assignment_rules', 'user_id')->where(function ($query) {
                    $query->where('category_id', $this->input('category_id'));
                }),
            ],
            'weight' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.unique' => 'This technician already has a rule for the selected category.',
        ];
    }
}
