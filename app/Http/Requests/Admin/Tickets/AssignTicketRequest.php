<?php

namespace App\Http\Requests\Admin\Tickets;

use App\Http\Requests\Concerns\NormalizesAssignedToInput;
use App\Http\Requests\Concerns\ResolvesAssignableAgentRule;
use Illuminate\Foundation\Http\FormRequest;

class AssignTicketRequest extends FormRequest
{
    use NormalizesAssignedToInput;
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
        ];
    }
}
