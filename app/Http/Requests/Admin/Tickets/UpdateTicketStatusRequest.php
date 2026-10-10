<?php

namespace App\Http\Requests\Admin\Tickets;

use App\Enums\RootCauseCategory;
use App\Http\Requests\Concerns\RequiresCloseReasonWhenClosing;
use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTicketStatusRequest extends FormRequest
{
    use RequiresCloseReasonWhenClosing;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(Ticket::STATUSES)],
            'close_reason' => $this->closeReasonRule(fn () => in_array($this->string('status')->toString(), ['closed', 'cancelled'], true)),
            'root_cause_category' => ['nullable', Rule::in(RootCauseCategory::values())],
            'root_cause_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
