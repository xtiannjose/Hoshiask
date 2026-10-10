<?php

namespace App\Http\Requests\Admin\Tickets;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTicketDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->canManageTickets();
    }

    public function rules(): array
    {
        return [
            'department' => ['required', 'string', Rule::in(User::allowedDepartments())],
        ];
    }
}
