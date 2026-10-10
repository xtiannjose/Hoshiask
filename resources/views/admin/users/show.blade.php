@extends('layouts.app')

@section('title', 'User Details - ' . config('app.name'))

@section('content')
<div class="mx-auto max-w-[1460px] px-4 sm:px-6 lg:px-8" data-admin-users-page data-users-base-url="{{ route('admin.users.index', absolute: false) }}">
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <a href="{{ route('admin.users.index') }}" class="text-slate-500 hover:text-slate-700 mr-4" aria-label="Back to users">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                </a>
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900">User Details</h1>
                    <p class="mt-1 text-sm text-slate-600">View user information and activity</p>
                </div>
            </div>
            <div class="flex space-x-3">
                @can('update', $user)
                    <a href="{{ route('admin.users.edit', $user) }}" class="btn-secondary">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Edit
                    </a>
                @endcan
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
        <div class="lg:col-span-8">
            @include('admin.users.partials.show.information-card')
            @if(auth()->user()->isShadow())
                @include('admin.users.partials.show.sign-in-activity-card')
            @endif
            @include('admin.users.partials.show.recent-tickets-card')
        </div>

        @include('admin.users.partials.show.sidebar')
    </div>
</div>

@include('admin.users.partials.account-modals', ['deleteActionLabel' => 'Delete'])

@endsection


