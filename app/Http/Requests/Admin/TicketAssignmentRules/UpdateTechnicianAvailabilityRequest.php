<?php

namespace App\Http\Requests\Admin\TicketAssignmentRules;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTechnicianAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            // The first day the person is assigned tickets again. Blank means available now.
            'back_on' => ['nullable', 'date_format:Y-m-d', 'after:today', 'before:'.now()->addYear()->addDay()->toDateString()],
        ];
    }

    public function messages(): array
    {
        return [
            'back_on.after' => 'Choose a day after today, or leave it empty to make the person available now.',
            'back_on.before' => 'Choose a day within the next year.',
        ];
    }
}
