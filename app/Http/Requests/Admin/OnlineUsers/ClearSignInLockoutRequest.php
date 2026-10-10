<?php

namespace App\Http\Requests\Admin\OnlineUsers;

use Illuminate\Foundation\Http\FormRequest;

class ClearSignInLockoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:255'],
        ];
    }
}
