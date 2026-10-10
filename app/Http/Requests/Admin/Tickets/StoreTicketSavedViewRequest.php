<?php

namespace App\Http\Requests\Admin\Tickets;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketSavedViewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->canAccessAdminTickets();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'filters' => ['nullable', 'array'],
        ];
    }
}
