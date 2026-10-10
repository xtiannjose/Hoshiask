<?php

namespace App\Http\Requests\Notifications;

use Illuminate\Foundation\Http\FormRequest;

class DismissNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'ticket_id' => ['required', 'integer', 'exists:tickets,id'],
            'activity_at' => ['required', 'date'],
        ];
    }
}
