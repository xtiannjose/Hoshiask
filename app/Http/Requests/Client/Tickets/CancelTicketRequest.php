<?php

namespace App\Http\Requests\Client\Tickets;

use Illuminate\Foundation\Http\FormRequest;

class CancelTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cancel_reason' => ['required', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'cancel_reason.required' => 'Please tell us why you want to cancel this ticket.',
        ];
    }
}
