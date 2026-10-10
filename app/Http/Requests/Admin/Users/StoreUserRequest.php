<?php

namespace App\Http\Requests\Admin\Users;

use App\Models\User;
use App\Rules\NotACommonPassword;
use App\Rules\PhoneNumber;
use App\Rules\UniqueUserEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('create', User::class) ?? false;
    }

    public function rules(): array
    {
        $user = auth()->user();
        $availableRoles = $user->manageableUserRoleOptions();
        $requestedRole = User::normalizeRole($this->string('role')->toString());
        $canManageClientNotes = $user->isShadow() && $requestedRole === User::ROLE_CLIENT;

        return [
            'username' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9._-]+$/', 'unique:users,username'],
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'nullable',
                'email',
                new UniqueUserEmail,
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (User::emailUsesReservedSystemDomain((string) $value)) {
                        $fail('Email addresses ending in @system.local are reserved for system archive accounts.');
                    }
                },
            ],
            'phone' => ['nullable', 'string', 'max:20', new PhoneNumber],
            'department' => ['required', Rule::in(User::allowedDepartments())],
            'role' => ['required', Rule::in($availableRoles)],
            'client_notes' => $canManageClientNotes
                ? ['nullable', 'string', 'max:2000']
                : ['prohibited'],
            'password' => ['required', 'string', 'min:8', 'confirmed', new NotACommonPassword],
        ];
    }

    public function messages(): array
    {
        return [
            'username.regex' => 'Usernames can only use lowercase letters, numbers, dots, dashes and underscores (no spaces or capitals).',
        ];
    }

    protected function prepareForValidation(): void
    {
        $rawName = trim((string) $this->input('name'));
        $username = trim(mb_strtolower((string) $this->input('username')));

        if ($username === '' && $rawName !== '') {
            $username = User::generateAvailableUsername($rawName);
        }

        $this->merge([
            'username' => $username !== '' ? $username : null,
            'name' => $rawName,
            'email' => ($email = trim((string) $this->input('email'))) !== '' ? $email : null,
            'phone' => ($phone = trim((string) $this->input('phone'))) !== '' ? $phone : null,
        ]);
    }
}
