<?php

namespace App\Http\Requests\Admin\Tickets;

use App\Enums\RootCauseCategory;
use App\Http\Requests\Concerns\NormalizesAssignedToInput;
use App\Http\Requests\Concerns\RequiresCloseReasonWhenClosing;
use App\Http\Requests\Concerns\ResolvesAssignableAgentRule;
use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuickUpdateTicketRequest extends FormRequest
{
    use NormalizesAssignedToInput;
    use RequiresCloseReasonWhenClosing;
    use ResolvesAssignableAgentRule;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'assigned_to' => ['nullable', 'array'],
            'assigned_to.*' => [$this->assignableAgentRule()],
            'status' => ['required', Rule::in(Ticket::STATUSES)],
            'priority' => ['nullable', Rule::in(Ticket::PRIORITIES)],
            'ticket_type' => [
                Rule::prohibitedIf(! $this->user()?->canManageTicketType()),
                'nullable',
                Rule::in(Ticket::TYPES),
            ],
            'close_reason' => $this->closeReasonRule(fn () => in_array($this->string('status')->toString(), ['closed', 'cancelled'], true)),
            'root_cause_category' => ['nullable', Rule::in(RootCauseCategory::values())],
            'root_cause_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeAssignedToInput();

        if ($this->has('priority')) {
            $this->merge([
                'priority' => Ticket::normalizePriorityValue($this->input('priority')),
            ]);
        }

        if ($this->has('ticket_type')) {
            $this->merge([
                'ticket_type' => Ticket::normalizeTicketTypeValue($this->input('ticket_type')),
            ]);
        }
    }
}
