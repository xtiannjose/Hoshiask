<?php

namespace App\Http\Requests\Admin\SystemLogs;

use Illuminate\Foundation\Http\FormRequest;

class UnlockSystemLogsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'password' => ['required', 'string'],
        ];
    }
}
