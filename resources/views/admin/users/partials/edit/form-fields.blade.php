<div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-2 xl:grid-cols-3">
    <div class="sm:col-span-1">
        <label for="username" class="form-label">
            Username <span class="text-red-500">*</span>
        </label>
        <div class="mt-1">
            <input type="text" name="username" id="username" value="{{ old('username', $user->username) }}" required
                autocomplete="username" autocapitalize="none" autocorrect="off" spellcheck="false" inputmode="text"
                data-profile-edit-lockable
                class="form-input @error('username') border-red-300 focus:border-red-400 focus:ring-red-200 @enderror"
                aria-describedby="username-help">
        </div>
        <p id="username-help" class="mt-1 text-xs text-slate-500">Lowercase letters, numbers, dots, dashes and underscores only. No spaces.</p>
        @error('username')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="sm:col-span-1">
        <label for="name" class="form-label">
            Display Name <span class="text-red-500">*</span>
        </label>
        <div class="mt-1">
            <input type="text" name="name" id="name" value="{{ old('name', $user->name) }}" required
                data-profile-edit-lockable
                class="form-input @error('name') border-red-300 focus:border-red-400 focus:ring-red-200 @enderror">
        </div>
        @error('name')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="sm:col-span-1">
        <label for="email" class="form-label">
            Email Address (Optional)
        </label>
        <div class="mt-1">
            <input type="email" name="email" id="email" value="{{ old('email', $user->email) }}"
                data-profile-edit-lockable
                class="form-input @error('email') border-red-300 focus:border-red-400 focus:ring-red-200 @enderror">
        </div>
        @error('email')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="sm:col-span-1">
        <label for="phone" class="form-label">
            Phone Number
        </label>
        <div class="mt-1">
            <input type="tel" name="phone" id="phone" inputmode="tel" value="{{ old('phone', $user->phone) }}"
                data-profile-edit-lockable
                class="form-input @error('phone') border-red-300 focus:border-red-400 focus:ring-red-200 @enderror">
        </div>
        @error('phone')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="sm:col-span-1">
        <label for="department" class="form-label">
            Department <span class="text-red-500">*</span>
        </label>
        <div class="mt-1">
            <select name="department" id="department" required
                data-profile-edit-lockable
                class="form-input @error('department') border-red-300 focus:border-red-400 focus:ring-red-200 @enderror">
                <option value="">Select department</option>
                @foreach(\App\Models\User::allowedDepartments() as $departmentOption)
                    <option value="{{ $departmentOption }}" {{ old('department', $user->department) === $departmentOption ? 'selected' : '' }}>
                        {{ $departmentOption }}
                    </option>
                @endforeach
            </select>
            <input type="hidden" id="department_hidden" name="department" value="" disabled>
        </div>
        <p class="mt-2 text-xs text-slate-500">Tickets this account has already filed stay with the department they were filed under.</p>
        @error('department')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="sm:col-span-1">
        <label for="role" class="form-label">
            Role <span class="text-red-500">*</span>
        </label>
        <div class="mt-1">
            <select name="role" id="role" required
                data-profile-edit-lockable
                class="form-input @error('role') border-red-300 focus:border-red-400 focus:ring-red-200 @enderror">
                <option value="">Select a role</option>
                @foreach($availableRoles as $role)
                    <option value="{{ $role }}" {{ old('role', $user->normalizedRole()) === $role ? 'selected' : '' }}>
                        {{ ucfirst(str_replace('_', ' ', $role)) }}
                    </option>
                @endforeach
            </select>
        </div>
        @error('role')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="sm:col-span-1">
        <label class="form-label">
            Account Access
        </label>
        @php
            $isActiveValue = old('is_active', $user->is_active ? '1' : '0');
        @endphp
        <fieldset class="mt-1 space-y-2">
            <legend class="sr-only">Lock or unlock this account</legend>
            <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700">
                <input
                    type="radio"
                    name="is_active"
                    value="1"
                    data-profile-edit-lockable
                    class="h-4 w-4 border-slate-300 text-hoshishi-blue-700 focus:ring-hoshishi-primary"
                    {{ (string) $isActiveValue === '1' ? 'checked' : '' }}
                >
                <span>Unlock account (Active)</span>
            </label>
            <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700">
                <input
                    type="radio"
                    name="is_active"
                    value="0"
                    data-profile-edit-lockable
                    class="h-4 w-4 border-slate-300 text-hoshishi-blue-700 focus:ring-hoshishi-primary"
                    {{ (string) $isActiveValue === '0' ? 'checked' : '' }}
                >
                <span>Lock account (Inactive)</span>
            </label>
        </fieldset>
        @error('is_active')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="sm:col-span-1">
        <label class="form-label">
            Profile Edit Lock
        </label>
        @php
            $isProfileLockedValue = old('is_profile_locked', $user->is_profile_locked ? '1' : '0');
        @endphp
        <input type="hidden" name="is_profile_locked" id="is_profile_locked" value="{{ $isProfileLockedValue }}">
        <button
            type="button"
            id="profile-lock-toggle"
            class="mt-1 inline-flex w-full items-center justify-between rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-hoshishi-primary"
            aria-pressed="{{ (string) $isProfileLockedValue === '1' ? 'true' : 'false' }}"
        >
            <span class="inline-flex items-center gap-2">
                <span id="profile-lock-icon-locked" class="hidden text-red-600" aria-hidden="true">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9" d="M8 11V8a4 4 0 118 0v3m-9 0h10a2 2 0 012 2v6a2 2 0 01-2 2H7a2 2 0 01-2-2v-6a2 2 0 012-2z"/>
                    </svg>
                </span>
                <span id="profile-lock-icon-unlocked" class="hidden text-emerald-600" aria-hidden="true">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9" d="M16 11V8a4 4 0 00-7.5-2m-1.5 5h10a2 2 0 012 2v6a2 2 0 01-2 2H7a2 2 0 01-2-2v-6a2 2 0 012-2z"/>
                    </svg>
                </span>
                <span id="profile-lock-state-label">Unlock profile editing</span>
            </span>
            <span class="text-xs text-slate-500">Toggle</span>
        </button>
        <p id="profile-edit-locked-banner" class="mt-2 hidden rounded border border-slate-300 bg-slate-50 px-2 py-1 text-xs text-slate-700">
            Profile editing is locked. Unlock first to edit this account.
        </p>
        @error('is_profile_locked')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    @if($canEditPassword ?? false)
        <div class="sm:col-span-1">
            <label for="password" class="form-label">
                New Password
            </label>
            <div class="mt-1 relative">
                <input type="password" name="password" id="password"
                    data-profile-edit-lockable
                    class="form-input pr-24 @error('password') border-red-300 focus:border-red-400 focus:ring-red-200 @enderror">
                <button
                    type="button"
                    data-peek-password-for="password"
                    class="absolute inset-y-0 right-0 inline-flex items-center justify-center px-3 text-hoshishi-blue-700 transition hover:text-hoshishi-blue-900 focus:outline-none focus:ring-2 focus:ring-hoshishi-primary"
                    aria-label="Show password briefly"
                    title="Show password for 0.5 seconds"
                >
                    <x-ui.icon name="eye" class="h-5 w-5" />
                </button>
            </div>
            <p class="mt-2 text-sm text-slate-500">Leave blank to keep current password. {{ \App\Rules\NotACommonPassword::HINT }}</p>
            @error('password')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="sm:col-span-1">
            <label for="password_confirmation" class="form-label">
                Confirm New Password
            </label>
            <div class="mt-1 relative">
                <input type="password" name="password_confirmation" id="password_confirmation"
                    data-profile-edit-lockable
                    class="form-input pr-24">
                <button
                    type="button"
                    data-peek-password-for="password_confirmation"
                    class="absolute inset-y-0 right-0 inline-flex items-center justify-center px-3 text-hoshishi-blue-700 transition hover:text-hoshishi-blue-900 focus:outline-none focus:ring-2 focus:ring-hoshishi-primary"
                    aria-label="Show password briefly"
                    title="Show password for 0.5 seconds"
                >
                    <x-ui.icon name="eye" class="h-5 w-5" />
                </button>
            </div>
        </div>
    @else
        <div class="sm:col-span-2">
            <p class="mt-2 text-sm text-slate-500">
                Password changes for client accounts are restricted to admins.
            </p>
        </div>
    @endif

    @if($user->isClient() && auth()->user()->isShadow())
        <div class="sm:col-span-2 xl:col-span-3">
            <label for="client_notes" class="form-label">
                Client Notes
            </label>
            <div class="mt-1">
                <textarea
                    name="client_notes"
                    id="client_notes"
                    rows="4"
                    data-profile-edit-lockable
                    class="form-input @error('client_notes') border-red-300 focus:border-red-400 focus:ring-red-200 @enderror"
                    placeholder="Internal notes for this client account"
                >{{ old('client_notes', $user->client_notes) }}</textarea>
            </div>
            <p class="mt-2 text-sm text-slate-500">Shadow-only note visible on the client's User Details page.</p>
            @error('client_notes')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
    @endif
</div>
