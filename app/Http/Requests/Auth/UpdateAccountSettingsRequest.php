<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Rules\NotACommonPassword;
use App\Rules\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && ! auth()->user()->isClient();
    }

    public function rules(): array
    {
        $user = auth()->user();
        $normalizedRole = $user->normalizedRole();
        $isSuperAdmin = in_array($normalizedRole, [User::ROLE_SHADOW, User::ROLE_ADMIN], true);
        $username = $isSuperAdmin && $this->has('username')
            ? $this->string('username')->toString()
            : (string) $user->username;
        $email = $isSuperAdmin && $this->has('email')
            ? $this->input('email')
            : $user->email;
        $requiresCurrentPassword = $user->mustChangePassword()
            || ($isSuperAdmin && $username !== (string) $user->username)
            || ($isSuperAdmin && $email !== $user->email)
            || $this->filled('password');

        $passwordRules = [
            'password' => $user->mustChangePassword()
                ? ['required', 'string', 'min:8', 'confirmed', 'different:current_password', new NotACommonPassword]
                : ['nullable', 'string', 'min:8', 'confirmed', 'different:current_password', new NotACommonPassword],
            'current_password' => $requiresCurrentPassword
                ? ['required', 'current_password']
                : ['nullable'],
        ];

        // A locked profile may still change its password (and must, when the password is temporary),
        // so its locked details are neither submitted nor validated.
        if ($user->is_profile_locked) {
            return $passwordRules;
        }

        $rules = [
            'username' => $isSuperAdmin
                ? ['required', 'string', 'max:255', 'regex:/^[a-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($user->id)]
                : ['nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => $isSuperAdmin
                ? ['nullable', 'email', Rule::unique('users', 'email')->ignore($user->id)]
                : ['nullable'],
            'phone' => ['nullable', 'string', 'max:20', new PhoneNumber],
            'department' => $isSuperAdmin
                ? ['required', Rule::in(User::allowedDepartments())]
                : ['nullable'],
            ...$passwordRules,
        ];

        return $rules;
    }

    public function messages(): array
    {
        return [
            'password.different' => 'Choose a new password that is different from your current one.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $currentUser = auth()->user();
        $username = trim(mb_strtolower((string) $this->input('username')));

        if ($username === '' && $currentUser) {
            $username = trim(mb_strtolower((string) $currentUser->username));
        }

        $this->merge([
            'username' => $username !== '' ? $username : null,
            'name' => trim((string) $this->input('name')),
            'email' => ($email = trim((string) $this->input('email'))) !== '' ? $email : null,
            'phone' => trim((string) $this->input('phone')),
        ]);
    }
}
