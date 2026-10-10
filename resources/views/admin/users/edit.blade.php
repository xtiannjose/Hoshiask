@extends('layouts.app')

@section('title', 'Edit User - ' . config('app.name'))

@section('content')
<div class="mx-auto max-w-[1460px] px-4 sm:px-6 lg:px-8" data-admin-users-edit-page data-support-department="{{ \App\Models\User::supportDepartment() }}">
    <div class="mb-8">
        <div class="flex items-center">
            <a href="{{ $returnTo ?? route('admin.users.index', absolute: false) }}" class="text-slate-500 hover:text-slate-700 mr-4" aria-label="Back to users">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
            </a>
            <div>
                <h1 class="text-2xl font-semibold text-slate-900">Edit User</h1>
            </div>
        </div>
    </div>

    <div class="bg-white shadow sm:rounded-lg">
        <form action="{{ route('admin.users.update', $user) }}" method="POST" class="space-y-6" data-submit-feedback>
            @csrf
            @method('PUT')
            <input type="hidden" name="stay_on_edit" value="1">
            <input type="hidden" name="return_to" value="{{ $returnTo ?? route('admin.users.index', absolute: false) }}">
            <div class="px-4 py-5 sm:p-6">
                @include('admin.users.partials.edit.form-fields')
            </div>

            <div class="px-4 py-3 bg-slate-50 text-right sm:px-6 space-x-3">
                <a href="{{ $returnTo ?? route('admin.users.index', absolute: false) }}" class="btn-secondary">Cancel</a>
                <button type="submit" class="btn-primary" data-loading-text="Saving...">Update User</button>
            </div>
        </form>
    </div>
</div>
@endsection
