@extends('layouts.app')

@section('title', 'Edit Department - ' . config('app.name'))

@section('content')
<div class="mx-auto max-w-[980px] px-4 sm:px-6 lg:px-8">
    <div class="mb-8">
        <div class="flex items-center">
            <a href="{{ route('admin.departments.index') }}" class="text-slate-500 hover:text-slate-700 mr-4" aria-label="Back to departments">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
            </a>
            <div>
                <h1 class="text-2xl font-semibold text-slate-900">Edit Department</h1>
                <p class="mt-1 text-sm text-slate-600">Update the department name and logo.</p>
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <form action="{{ route('admin.departments.update', $department) }}" method="POST" enctype="multipart/form-data" class="space-y-6 px-5 py-5" data-submit-feedback>
            @csrf
            @method('PUT')

            <div class="flex flex-wrap items-center gap-4">
                <div class="flex h-20 w-20 items-center justify-center overflow-hidden rounded-full border border-slate-200 bg-white">
                    <img src="{{ $department->logo_url }}" alt="{{ $department->name }} logo" class="avatar-logo">
                </div>
                <div>
                    <p class="text-sm font-semibold text-slate-900">{{ $department->name }}</p>
                    <p class="text-xs text-slate-500">{{ $department->slug }}</p>
                </div>
            </div>

            <div>
                <label for="name" class="form-label">
                    Department Name <span class="text-red-500">*</span>
                </label>
                <div class="mt-1">
                    <input
                        type="text"
                        name="name"
                        id="name"
                        value="{{ old('name', $department->name) }}"
                        required
                        class="form-input @error('name') border-red-300 focus:border-red-400 focus:ring-red-200 @enderror"
                    >
                </div>
                @error('name')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="logo" class="form-label">
                    Replace Logo
                </label>
                <div class="mt-1">
                    <input
                        type="file"
                        name="logo"
                        id="logo"
                        accept=".png,.jpg,.jpeg,.webp,.bmp,.gif"
                        class="block w-full text-sm text-slate-700 file:mr-4 file:rounded-lg file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:font-semibold file:text-slate-700 hover:file:bg-slate-200 @error('logo') text-red-600 @enderror"
                    >
                </div>
                <p class="mt-2 text-xs text-slate-500">Leave blank to keep the current logo.</p>
                @error('logo')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-slate-200 pt-4">
                <a href="{{ route('admin.departments.index') }}" class="btn-secondary">Cancel</a>
                <button type="submit" class="btn-primary" data-loading-text="Saving...">Save Department</button>
            </div>
        </form>
    </div>
</div>
@endsection
