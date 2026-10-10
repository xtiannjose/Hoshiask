@extends('layouts.app')

@section('title', 'Account Settings - ' . config('app.name'))

@section('content')
@php
    $normalizedRole = $user->normalizedRole();
    $displayRole = $user->publicRole();
    $isSuperAdmin = in_array($normalizedRole, [\App\Models\User::ROLE_SHADOW, \App\Models\User::ROLE_ADMIN], true);
    $isDepartmentLocked = !$isSuperAdmin;
    $isEmailLocked = !$isSuperAdmin;
    $isUsernameLocked = in_array($normalizedRole, [\App\Models\User::ROLE_SUPER_USER, \App\Models\User::ROLE_TECHNICAL], true);
    $isProfileLocked = (bool) $user->is_profile_locked;
    // A forced password change must always be possible, even on a locked profile, or the user is stuck.
    $mustChangePassword = $user->mustChangePassword();
    $isPasswordLocked = $isProfileLocked && ! $mustChangePassword;
    $backRoute = $user->canAccessAdminTickets()
        ? route('admin.dashboard')
        : route('client.dashboard');
@endphp

<div class="mx-auto max-w-[1460px] px-4 sm:px-6 lg:px-8" data-account-settings-page>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-display text-3xl font-semibold text-slate-900">Account Settings</h1>
        </div>
        <a href="{{ $backRoute }}" class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
            Back to Dashboard
        </a>
    </div>

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Role</p>
            <p class="mt-2 text-sm font-semibold text-slate-800">{{ \App\Models\User::publicRoleLabel($displayRole) }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Account Status</p>
            <p class="mt-2 text-sm font-semibold {{ $user->is_active ? 'text-emerald-700' : 'text-red-700' }}">
                {{ $user->is_active ? 'Active' : 'Inactive' }}
            </p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Profile Edit Lock</p>
            <p class="mt-2 text-sm font-semibold {{ $isProfileLocked ? 'text-red-700' : 'text-emerald-700' }}">
                {{ $isProfileLocked ? 'Locked' : 'Unlocked' }}
            </p>
        </div>
    </div>

    <div class="mb-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="font-display text-lg font-semibold text-slate-900">Appearance</h2>
            <p class="mt-1 text-sm text-slate-500">Choose how the console looks on this device.</p>
        </div>
        <div class="flex flex-wrap gap-3 px-5 py-5">
            <button
                type="button"
                @click="setThemeMode('light')"
                :class="themeMode === 'light' ? 'border-hoshishi-primary bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
                class="app-pressable inline-flex items-center gap-2 rounded-xl border px-4 py-2.5 text-sm font-semibold transition"
                :aria-pressed="(themeMode === 'light').toString()"
            >
                <x-ui.icon name="sun" class="h-5 w-5" />
                Light
            </button>
            <button
                type="button"
                @click="setThemeMode('dark')"
                :class="themeMode === 'dark' ? 'border-hoshishi-primary bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
                class="app-pressable inline-flex items-center gap-2 rounded-xl border px-4 py-2.5 text-sm font-semibold transition"
                :aria-pressed="(themeMode === 'dark').toString()"
            >
                <x-ui.icon name="moon" class="h-5 w-5" />
                Dark
            </button>
            <button
                type="button"
                @click="setThemeMode('system')"
                :class="themeMode === 'system' ? 'border-hoshishi-primary bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
                class="app-pressable inline-flex items-center gap-2 rounded-xl border px-4 py-2.5 text-sm font-semibold transition"
                :aria-pressed="(themeMode === 'system').toString()"
            >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <rect x="3" y="4" width="18" height="13" rx="2" stroke-width="2"></rect>
                    <path stroke-linecap="round" stroke-width="2" d="M8 21h8M12 17v4"></path>
                </svg>
                System
            </button>
        </div>
    </div>

    @if($isProfileLocked)
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            @if($mustChangePassword)
                Your profile details are locked by an administrator, but you can still set your new password below.
            @else
                Your profile editing is locked by an administrator. Contact an admin to unlock this account before making changes.
            @endif
        </div>
    @endif

    @if($mustChangePassword)
        <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            This account is using a temporary password. Set a new password now to continue using the admin portal.
        </div>
    @endif

    <form action="{{ route('account.settings.update') }}" method="POST" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" data-submit-feedback>
        @csrf
        @method('PUT')

        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="font-display text-lg font-semibold text-slate-900">Profile Information</h2>
        </div>

        <div class="grid grid-cols-1 gap-5 px-5 py-5 sm:grid-cols-2">
            <div class="sm:col-span-1">
                <label for="username" class="form-label">
                    Username <span class="text-red-500">*</span>
                </label>
                <input
                    type="text"
                    name="username"
                    id="username"
                    value="{{ old('username', $user->username) }}"
                    autocomplete="username" autocapitalize="none" autocorrect="off" spellcheck="false" inputmode="text"
                    {{ $isProfileLocked ? 'readonly aria-readonly=true' : ($isUsernameLocked ? 'readonly aria-readonly=true' : 'required') }}
                    class="form-input @error('username') border-red-300 focus:border-red-400 focus:ring-red-200 @enderror {{ ($isUsernameLocked || $isProfileLocked) ? 'bg-slate-100 text-slate-500 cursor-not-allowed' : '' }}"
                    placeholder="Enter username"
                >
                @if($isUsernameLocked)
                    <p class="mt-1 px-3.5 text-xs text-slate-500">Only admins can change sign-in usernames for this account role.</p>
                @endif
                @error('username')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="sm:col-span-1">
                <label for="name" class="form-label">
                    Display Name <span class="text-red-500">*</span>
                </label>
                <input
                    type="text"
                    name="name"
                    id="name"
                    autocomplete="name"
                    value="{{ old('name', $user->name) }}"
                    {{ $isProfileLocked ? 'readonly aria-readonly=true' : 'required' }}
                    class="form-input @error('name') border-red-300 focus:border-red-400 focus:ring-red-200 @enderror {{ $isProfileLocked ? 'bg-slate-100 text-slate-500 cursor-not-allowed' : '' }}"
                    placeholder="Enter display name"
                >
                @error('name')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="sm:col-span-1">
                <label for="email" class="form-label">Email Address <span class="text-slate-500">(Optional)</span></label>
                <input
                    type="email"
                    name="email"
                    id="email"
                    autocomplete="email"
                    value="{{ old('email', $user->email) }}"
                    {{ ($isProfileLocked || $isEmailLocked) ? 'readonly aria-readonly=true' : '' }}
                    class="form-input @error('email') border-red-300 focus:border-red-400 focus:ring-red-200 @enderror {{ ($isEmailLocked || $isProfileLocked) ? 'bg-slate-100 text-slate-500 cursor-not-allowed' : '' }}"
                    placeholder="you@example.com"
                >
                @if($isEmailLocked)
                    <p class="mt-1 px-3.5 text-xs text-slate-500">Only admins can change account email addresses.</p>
                @endif
                @error('email')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="sm:col-span-1">
                <label for="department" class="form-label">Department</label>
                @if($isSuperAdmin)
                    <select
                        name="department"
                        id="department"
                        {{ $isProfileLocked ? 'disabled' : '' }}
                        class="form-input @error('department') border-red-300 focus:border-red-400 focus:ring-red-200 @enderror"
                    >
                        @foreach($departmentOptions as $departmentOption)
                            <option value="{{ $departmentOption }}" {{ old('department', $user->department) === $departmentOption ? 'selected' : '' }}>
                                {{ $departmentOption }}
                            </option>
                        @endforeach
                    </select>
                @else
                    <input
                        type="text"
                        name="department"
                        id="department"
                        value="{{ old('department', $user->department) }}"
                        class="form-input @error('department') border-red-300 focus:border-red-400 focus:ring-red-200 @enderror {{ ($isDepartmentLocked || $isProfileLocked) ? 'bg-slate-100 text-slate-500 cursor-not-allowed' : '' }}"
                        placeholder="{{ ($isDepartmentLocked || $isProfileLocked) ? '' : 'Optional' }}"
                        {{ ($isDepartmentLocked || $isProfileLocked) ? 'readonly aria-readonly=true' : '' }}
                    >
                @endif
                @if($isDepartmentLocked)
                    <p class="mt-1 text-xs text-slate-500">Only admins can change your department assignment.</p>
                @endif
                @error('department')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="sm:col-span-1">
                <label for="phone" class="form-label">Phone Number</label>
                <input
                    type="tel"
                    name="phone"
                    id="phone"
                    inputmode="tel"
                    autocomplete="tel"
                    value="{{ old('phone', $user->phone) }}"
                    {{ $isProfileLocked ? 'readonly aria-readonly=true' : '' }}
                    class="form-input @error('phone') border-red-300 focus:border-red-400 focus:ring-red-200 @enderror"
                    placeholder="Optional"
                >
                @error('phone')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="border-y border-slate-200 px-5 py-4">
            <h2 class="font-display text-lg font-semibold text-slate-900">Security</h2>
            <p class="mt-1 text-sm text-slate-500">Update your password if needed.</p>
        </div>

        <div class="grid grid-cols-1 gap-5 px-5 py-5 sm:grid-cols-2">
            <div class="sm:col-span-1">
                <label for="current_password" class="form-label">Current Password</label>
                <input
                    type="password"
                    name="current_password"
                    id="current_password"
                    autocomplete="current-password"
                    {{ $isPasswordLocked ? 'disabled' : '' }}
                    class="form-input @error('current_password') border-red-300 focus:border-red-400 focus:ring-red-200 @enderror"
                >
                @error('current_password')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="form-label">New Password</label>
                <div class="relative">
                    <input
                        type="password"
                        name="password"
                        id="password"
                        autocomplete="new-password"
                        aria-describedby="password-help"
                        {{ $isPasswordLocked ? 'disabled' : '' }}
                        class="form-input pr-11 @error('password') border-red-300 focus:border-red-400 focus:ring-red-200 @enderror"
                    >
                    <button
                        type="button"
                        class="absolute inset-y-0 right-0 inline-flex items-center justify-center px-3 text-slate-500 transition hover:text-slate-700"
                        data-password-peek="#password"
                        aria-label="Show password briefly"
                    >
                        <x-ui.icon name="eye" class="h-5 w-5" />
                    </button>
                </div>
                <p id="password-help" class="mt-1 text-xs text-slate-500">{{ \App\Rules\NotACommonPassword::HINT }}</p>
                @error('password')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password_confirmation" class="form-label">Confirm New Password</label>
                <div class="relative">
                    <input
                        type="password"
                        name="password_confirmation"
                        id="password_confirmation"
                        autocomplete="new-password"
                        {{ $isPasswordLocked ? 'disabled' : '' }}
                        class="form-input pr-11"
                    >
                    <button
                        type="button"
                        class="absolute inset-y-0 right-0 inline-flex items-center justify-center px-3 text-slate-500 transition hover:text-slate-700"
                        data-password-peek="#password_confirmation"
                        aria-label="Show password briefly"
                    >
                        <x-ui.icon name="eye" class="h-5 w-5" />
                    </button>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4">
            <a href="{{ $backRoute }}" class="btn-secondary">Cancel</a>
            <button type="submit" class="btn-primary" data-loading-text="Saving..." {{ $isPasswordLocked ? 'disabled' : '' }}>
                {{ $isPasswordLocked ? 'Profile Locked' : ($isProfileLocked ? 'Set New Password' : 'Save Changes') }}
            </button>
        </div>
    </form>
</div>
@endsection
