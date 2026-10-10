<?php

namespace App\Http\Requests\Admin\Tickets;

use App\Http\Requests\Concerns\ResolvesAssignableAgentRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EscalateTicketRequest extends FormRequest
{
    use ResolvesAssignableAgentRule;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'escalated_to' => [
                'required',
                $this->assignableAgentRule(),
                Rule::notIn([$this->user()?->id]),
            ],
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'escalated_to.not_in' => 'You cannot escalate a ticket to yourself.',
        ];
    }
}
