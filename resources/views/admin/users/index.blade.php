@extends('layouts.app')

@section('title', 'User Management - ' . config('app.name'))

@section('content')
<div class="mx-auto max-w-[1460px]" data-admin-users-page data-users-base-url="{{ route('admin.users.index', absolute: false) }}">
    @php
        $listReturnTo = request()->getRequestUri();
    @endphp
    <div class="mb-6">
        <div class="md:flex md:items-center md:justify-between">
            <div class="flex-1 min-w-0">
                <h1 class="text-2xl font-semibold text-slate-900">{{ $segmentTitle ?? 'User Management' }}</h1>
            </div>
            <div class="mt-4 flex md:mt-0 md:ml-4">
                <a href="{{ route('admin.users.create') }}" class="btn-primary">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    Create User
                </a>
            </div>
        </div>
    </div>

    @include('admin.users.partials.index.segment-toggle')
    @include('admin.users.partials.index.filter-form')
    @include('admin.users.partials.index.table')
</div>

@include('admin.users.partials.account-modals')

@endsection

