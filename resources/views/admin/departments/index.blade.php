@extends('layouts.app')

@section('title', 'Departments - ' . config('app.name'))

@section('content')
<div class="mx-auto max-w-[1460px] px-4 sm:px-6 lg:px-8">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Departments</h1>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[380px_minmax(0,1fr)]">
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-lg font-semibold text-slate-900">Add Department</h2>
            </div>

            <form action="{{ route('admin.departments.store') }}" method="POST" enctype="multipart/form-data" class="space-y-5 px-5 py-5" data-submit-feedback>
                @csrf

                <div>
                    <label for="name" class="form-label">
                        Department Name <span class="text-red-500">*</span>
                    </label>
                    <div class="mt-1">
                        <input
                            type="text"
                            name="name"
                            id="name"
                            value="{{ old('name') }}"
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
                        Department Logo <span class="text-red-500">*</span>
                    </label>
                    <div class="mt-1">
                        <input
                            type="file"
                            name="logo"
                            id="logo"
                            accept=".png,.jpg,.jpeg,.webp,.bmp,.gif"
                            required
                            class="block w-full text-sm text-slate-700 file:mr-4 file:rounded-lg file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:font-semibold file:text-slate-700 hover:file:bg-slate-200 @error('logo') text-red-600 @enderror"
                        >
                    </div>
                    @error('logo')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="pt-2">
                    <button type="submit" class="btn-primary" data-loading-text="Creating...">Create Department</button>
                </div>
            </form>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-lg font-semibold text-slate-900">Department Directory</h2>
            </div>

            <div class="max-h-[70vh] overflow-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="sticky top-0 z-10 bg-slate-50">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Department</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Users</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Tickets</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        @foreach($departments as $department)
                            <tr>
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-12 w-12 items-center justify-center overflow-hidden rounded-full border border-slate-200 bg-white">
                                            <img src="{{ $department->logo_url }}" alt="{{ $department->name }} logo" class="avatar-logo">
                                        </div>
                                        <div>
                                            <p class="text-sm font-semibold text-slate-900">{{ $department->name }}</p>
                                            <p class="text-xs text-slate-500">{{ $department->slug }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-4 text-sm text-slate-700">{{ $department->user_count }}</td>
                                <td class="px-5 py-4 text-sm text-slate-700">{{ $department->ticket_count }}</td>
                                <td class="px-5 py-4 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <a href="{{ route('admin.departments.edit', $department) }}" class="inline-flex items-center rounded-lg px-2.5 py-1 text-sm font-medium text-hoshishi-blue-700 transition hover:bg-hoshishi-blue-50 hover:text-hoshishi-blue-900">
                                            Edit
                                        </a>
                                        <x-ui.delete-button
                                            :action="route('admin.departments.destroy', $department)"
                                            title="Delete Department"
                                            :name="$department->name"
                                            note="Its logo is removed too. Nothing uses this department."
                                            :blocked="$department->delete_blocked_reason" />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<x-ui.delete-confirm-modal />
@endsection
