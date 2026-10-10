@extends('layouts.app')

@section('title', 'Online Users - ' . config('app.name'))

@section('content')
<div
    class="mx-auto max-w-[1460px] px-4 sm:px-6 lg:px-8"
    data-admin-online-users-page
    data-live-url="{{ route('admin.online-users.live', absolute: false) }}"
>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-display text-3xl font-semibold text-slate-900">Online Users</h1>
            <p class="mt-1 text-sm text-slate-500">
                Accounts with activity in the last {{ $onlineWindowMinutes }} minutes, and recent failed sign-ins. Refreshes every 20 seconds.
            </p>
        </div>
        <form method="POST" action="{{ route('admin.system-logs.lock') }}" data-submit-feedback>
            @csrf
            <button type="submit" class="btn-secondary" data-loading-text="Locking...">Lock</button>
        </form>
    </div>

    <div class="js-online-users-live">
        @include('admin.online-users.partials.live')
    </div>
</div>

<dialog id="onlineUserSignOutModal" class="app-modal-root" aria-labelledby="onlineUserSignOutTitle">
    <div class="app-modal-overlay absolute inset-0 bg-slate-900/35 backdrop-blur-[1px]" data-modal-overlay></div>
    <div class="relative z-10 flex min-h-dvh items-center justify-center p-4">
        <div class="app-modal-panel relative mx-auto w-full max-w-md rounded-2xl border border-slate-200 bg-white p-5 shadow-xl">
            <div class="text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-red-100">
                    <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                </div>
                <h3 id="onlineUserSignOutTitle" class="mt-2 text-lg font-medium text-slate-900">Sign out of all devices?</h3>
                <p class="mt-2 px-4 text-sm text-slate-500">
                    <strong class="js-sign-out-user-name"></strong> will be signed out everywhere, including browsers where they chose "Remember me".
                </p>
                <p class="mt-2 px-4 text-xs text-slate-500">
                    They can still sign in again with their password. To keep them out, deactivate the account or reset its password.
                </p>
            </div>
            <form method="POST" action="" class="js-sign-out-form mt-5 space-y-3" data-submit-feedback>
                @csrf
                <button type="submit" class="btn-danger w-full" data-loading-text="Signing out...">Sign out</button>
                <button type="button" class="btn-secondary w-full" data-modal-close>Cancel</button>
            </form>
        </div>
    </div>
</dialog>
@endsection
