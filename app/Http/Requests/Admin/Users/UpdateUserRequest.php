<?php

namespace App\Http\Requests\Admin\Users;

use App\Models\User;
use App\Rules\NotACommonPassword;
use App\Rules\PhoneNumber;
use App\Rules\UniqueUserEmail;
use App\Services\Admin\ManagedUserAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! auth()->check()) {
            return false;
        }

        /** @var User|null $targetUser */
        $targetUser = $this->route('user');
        if (! $targetUser instanceof User) {
            return false;
        }

        return app(ManagedUserAccessService::class)->updateError(auth()->user(), $targetUser) === null;
    }

    protected function failedAuthorization(): never
    {
        /** @var User|null $targetUser */
        $targetUser = $this->route('user');
        $message = ($targetUser instanceof User && auth()->check())
            ? app(ManagedUserAccessService::class)->updateError(auth()->user(), $targetUser)
            : null;

        throw new HttpResponseException(
            redirect()->route('admin.users.index')
                ->with('error', $message ?? 'You do not have permission to edit this user.')
        );
    }

    public function rules(): array
    {
        $currentUser = auth()->user();
        /** @var User $targetUser */
        $targetUser = $this->route('user');
        $canManageTarget = $currentUser->can('update', $targetUser);
        $availableRoles = $canManageTarget
            ? $currentUser->manageableUserRoleOptions($targetUser)
            : [User::ROLE_CLIENT, User::ROLE_SHADOW, User::ROLE_ADMIN, User::ROLE_SUPER_USER, User::ROLE_TECHNICAL];
        $canEditPassword = $currentUser->canEditManagedUserPassword($targetUser);
        $requestedRole = User::normalizeRole($this->string('role')->toString());
        $willBeClientRole = $requestedRole === User::ROLE_CLIENT;
        $canManageClientNotes = $currentUser->isShadow() && $willBeClientRole;

        $rules = [
            'username' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($targetUser->id)],
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'nullable',
                'email',
                new UniqueUserEmail($targetUser->id),
                function (string $attribute, mixed $value, \Closure $fail) use ($canManageTarget): void {
                    if ($canManageTarget && User::emailUsesReservedSystemDomain((string) $value)) {
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
            'password' => $canEditPassword
                ? ['nullable', 'string', 'min:8', 'confirmed', new NotACommonPassword]
                : ['prohibited'],
            'is_active' => ['boolean'],
            'is_profile_locked' => ['boolean'],
        ];

        if (! $canEditPassword) {
            $rules['password_confirmation'] = ['prohibited'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'username.regex' => 'Usernames can only use lowercase letters, numbers, dots, dashes and underscores (no spaces or capitals).',
        ];
    }

    protected function prepareForValidation(): void
    {
        /** @var User|null $targetUser */
        $targetUser = $this->route('user');
        $rawName = trim((string) $this->input('name'));
        $username = trim(mb_strtolower((string) $this->input('username')));

        if ($username === '' && $targetUser instanceof User) {
            $username = trim(mb_strtolower((string) $targetUser->username));
        }

        if ($username === '' && $rawName !== '') {
            $username = User::generateAvailableUsername($rawName, $targetUser?->id);
        }

        $this->merge([
            'username' => $username !== '' ? $username : null,
            'name' => $rawName,
            'email' => ($email = trim((string) $this->input('email'))) !== '' ? $email : null,
            'phone' => ($phone = trim((string) $this->input('phone'))) !== '' ? $phone : null,
        ]);
    }
}
