<?php

namespace App\Http\Requests\Client\Tickets;

use Illuminate\Foundation\Http\FormRequest;

class ReopenTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reopen_reason' => ['required', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'reopen_reason.required' => 'Please tell us why you need to reopen this ticket.',
        ];
    }
}
