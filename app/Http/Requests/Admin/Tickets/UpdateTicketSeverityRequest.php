<?php

namespace App\Http\Requests\Admin\Tickets;

use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTicketSeverityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'severity' => ['required', Rule::in(Ticket::PRIORITIES)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'severity' => Ticket::normalizePriorityValue($this->input('severity', $this->input('priority'))),
        ]);
    }
}
