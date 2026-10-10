<?php

namespace App\Http\Requests\Admin\Tickets;

use App\Http\Requests\Concerns\ValidatesTicketLifecycleDates;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTicketDatesRequest extends FormRequest
{
    use ValidatesTicketLifecycleDates;

    public function authorize(): bool
    {
        return (bool) $this->user()?->canSetManualTicketDates();
    }

    public function rules(): array
    {
        return [
            'created_at' => ['required', 'date', 'before_or_equal:now'],
            'resolved_at' => ['nullable', 'date', 'before_or_equal:now'],
            'closed_at' => ['nullable', 'date', 'before_or_equal:now'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->validateTicketLifecycleDateOrder($validator));
    }
}
