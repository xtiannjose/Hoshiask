<?php

namespace App\Http\Requests\Admin\Tickets;

use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTicketTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->canManageTicketType();
    }

    public function rules(): array
    {
        return [
            'ticket_type' => ['required', Rule::in(Ticket::TYPES)],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('ticket_type')) {
            $this->merge([
                'ticket_type' => Ticket::normalizeTicketTypeValue($this->input('ticket_type')),
            ]);
        }
    }
}
