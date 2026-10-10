<?php

namespace App\Http\Requests\Admin\Tickets;

use App\Http\Requests\Concerns\NormalizesAssignedToInput;
use App\Http\Requests\Concerns\RequiresCloseReasonWhenClosing;
use App\Http\Requests\Concerns\ResolvesAssignableAgentRule;
use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkTicketActionRequest extends FormRequest
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
            'action' => ['required', Rule::in(['delete', 'assign', 'status', 'priority', 'merge'])],
            'selected_ids' => ['required', 'array', 'min:1', 'max:200'],
            'selected_ids.*' => ['integer', 'exists:tickets,id'],
            'assigned_to' => ['nullable', 'array'],
            'assigned_to.*' => [$this->assignableAgentRule()],
            'status' => ['nullable', Rule::in(Ticket::STATUSES)],
            'priority' => ['nullable', Rule::in(Ticket::PRIORITIES)],
            'close_reason' => $this->closeReasonRule(
                fn () => $this->string('action')->toString() === 'status'
                    && in_array($this->string('status')->toString(), ['closed', 'cancelled'], true)
            ),
            'confirm_cross_owner_merge' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'selected_ids.max' => 'You can change at most 200 tickets at once. Narrow the list with a filter first.',
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
    }
}
