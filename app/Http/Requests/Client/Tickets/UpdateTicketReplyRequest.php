<?php

namespace App\Http\Requests\Client\Tickets;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTicketReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:10000'],
        ];
    }
}
