<?php

namespace App\Http\Requests\Client\Tickets;

use Illuminate\Foundation\Http\FormRequest;

class ResolveTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'resolve_confirmation' => ['accepted'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000', 'required_if:rating,1,2'],
        ];
    }

    public function messages(): array
    {
        return [
            'resolve_confirmation.accepted' => 'Please confirm the ticket is resolved before continuing.',
            'rating.required' => 'Please tap a star to rate our support.',
            'comment.required_if' => 'You gave 1 or 2 stars. Please tell us what went wrong so we can put it right.',
        ];
    }
}
