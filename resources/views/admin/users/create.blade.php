@extends('layouts.app')

@section('title', 'Create User - ' . config('app.name'))

@section('content')
<div class="mx-auto max-w-[1460px] px-4 sm:px-6 lg:px-8" data-admin-users-create-page data-support-department="{{ \App\Models\User::supportDepartment() }}">
    <div class="mb-8">
        <div class="flex items-center">
            <a href="{{ route('admin.users.index') }}" class="text-slate-500 hover:text-slate-700 mr-4" aria-label="Back to users">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
            </a>
            <div>
                <h1 class="text-2xl font-semibold text-slate-900">Create New User</h1>
            </div>
        </div>
    </div>

    <div class="bg-white shadow sm:rounded-lg">
        <form action="{{ route('admin.users.store') }}" method="POST" class="space-y-6" data-submit-feedback>
            @csrf
            <div class="px-4 py-5 sm:p-6">
                <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-2 xl:grid-cols-3">
                    <!-- Username -->
                    <div class="sm:col-span-1">
                        <label for="username" class="form-label">
                            Username <span class="text-red-500">*</span>
                        </label>
                        <div class="mt-1">
                            <input type="text" name="username" id="username" value="{{ old('username') }}" required
                                autocomplete="username" autocapitalize="none" autocorrect="off" spellcheck="false" inputmode="text"
                                class="form-input @error('username') border-red-300 focus:border-red-400 focus:ring-red-200 @enderror"
                                aria-describedby="username-help">
                        </div>
                        <p id="username-help" class="mt-1 text-xs text-slate-500">Lowercase letters, numbers, dots, dashes and underscores only. No spaces.</p>
                        @error('username')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Display Name -->
                    <div class="sm:col-span-1">
                        <label for="name" class="form-label">
                            Display Name <span class="text-red-500">*</span>
                        </label>
                        <div class="mt-1">
                            <input type="text" name="name" id="name" value="{{ old('name') }}" required
                                class="form-input @error('name') border-red-300 focus:border-red-400 focus:ring-red-200 @enderror">
                        </div>
                        @error('name')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Email -->
                    <div class="sm:col-span-1">
                        <label for="email" class="form-label">
                            Email Address (Optional)
                        </label>
                        <div class="mt-1">
                            <input type="email" name="email" id="email" value="{{ old('email') }}"
                                class="form-input @error('email') border-red-300 focus:border-red-400 focus:ring-red-200 @enderror">
                        </div>
                        @error('email')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Phone -->
                    <div class="sm:col-span-1">
                        <label for="phone" class="form-label">
                            Phone Number
                        </label>
                        <div class="mt-1">
                            <input type="tel" name="phone" id="phone" inputmode="tel" value="{{ old('phone') }}"
                                class="form-input @error('phone') border-red-300 focus:border-red-400 focus:ring-red-200 @enderror">
                        </div>
                        @error('phone')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Department -->
                    <div class="sm:col-span-1">
                        <label for="department" class="form-label">
                            Department <span class="text-red-500">*</span>
                        </label>
                        <div class="mt-1">
                            <select name="department" id="department" required
                                class="form-input @error('department') border-red-300 focus:border-red-400 focus:ring-red-200 @enderror">
                                <option value="">Select department</option>
                                @foreach(\App\Models\User::allowedDepartments() as $departmentOption)
                                    <option value="{{ $departmentOption }}" {{ old('department') === $departmentOption ? 'selected' : '' }}>
                                        {{ $departmentOption }}
                                    </option>
                                @endforeach
                            </select>
                            <input type="hidden" id="department_hidden" name="department" value="" disabled>
                        </div>
                        @error('department')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Role -->
                    <div class="sm:col-span-1">
                        <label for="role" class="form-label">
                            Role <span class="text-red-500">*</span>
                        </label>
                        <div class="mt-1">
                            <select name="role" id="role" required
                                class="form-input @error('role') border-red-300 focus:border-red-400 focus:ring-red-200 @enderror">
                                <option value="">Select a role</option>
                                @foreach($availableRoles as $role)
                                    <option value="{{ $role }}" {{ old('role') === $role ? 'selected' : '' }}>
                                        {{ ucfirst(str_replace('_', ' ', $role)) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @error('role')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        @unless(auth()->user()->isSuperAdmin())
                            <p class="mt-2 text-sm text-slate-500">
                                You can create client users only.
                            </p>
                        @endunless
                    </div>

                    <!-- Password -->
                    <div class="sm:col-span-1">
                        <label for="password" class="form-label">
                            Password <span class="text-red-500">*</span>
                        </label>
                        <div class="mt-1">
                            <input type="password" name="password" id="password" required autocomplete="new-password"
                                aria-describedby="password-help"
                                class="form-input @error('password') border-red-300 focus:border-red-400 focus:ring-red-200 @enderror">
                        </div>
                        <p id="password-help" class="mt-1 text-xs text-slate-500">{{ \App\Rules\NotACommonPassword::HINT }} Staff are asked to choose their own password when they first sign in.</p>
                        @error('password')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Password Confirmation -->
                    <div class="sm:col-span-1">
                        <label for="password_confirmation" class="form-label">
                            Confirm Password <span class="text-red-500">*</span>
                        </label>
                        <div class="mt-1">
                            <input type="password" name="password_confirmation" id="password_confirmation" required
                                class="form-input">
                        </div>
                    </div>

                    @if(auth()->user()->isShadow())
                        <div id="client-notes-wrap" class="hidden sm:col-span-2 xl:col-span-3">
                            <label for="client_notes" class="form-label">
                                Client Notes
                            </label>
                            <div class="mt-1">
                                <textarea
                                    name="client_notes"
                                    id="client_notes"
                                    rows="4"
                                    class="form-input @error('client_notes') border-red-300 focus:border-red-400 focus:ring-red-200 @enderror"
                                    placeholder="Optional internal notes for this client account"
                                    disabled
                                >{{ old('client_notes') }}</textarea>
                            </div>
                            <p class="mt-2 text-sm text-slate-500">Shadow-only note shown for client accounts.</p>
                            @error('client_notes')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    @endif
                </div>
            </div>

            <div class="px-4 py-3 bg-slate-50 text-right sm:px-6 space-x-3">
                <a href="{{ route('admin.users.index') }}" class="btn-secondary">Cancel</a>
                <button type="submit" class="btn-primary" data-loading-text="Creating...">Create User</button>
            </div>
        </form>
    </div>
</div>
@endsection
