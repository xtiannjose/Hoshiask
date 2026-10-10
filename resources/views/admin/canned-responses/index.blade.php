@extends('layouts.app')

@section('title', 'Canned Responses - ' . config('app.name'))

@section('content')
<div class="mx-auto max-w-[1460px] px-4 sm:px-6 lg:px-8">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Canned Responses</h1>
            <p class="mt-1 text-sm text-slate-500">Reusable reply templates that staff can insert into ticket replies.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[420px_minmax(0,1fr)]">

        {{-- Create form --}}
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-lg font-semibold text-slate-900">Add Canned Response</h2>
            </div>

            <form action="{{ route('admin.canned-responses.store') }}" method="POST" class="space-y-4 px-5 py-5" data-submit-feedback>
                @csrf

                <div>
                    <label for="title" class="form-label">Title <span class="text-red-500">*</span></label>
                    <div class="mt-1">
                        <input
                            type="text"
                            name="title"
                            id="title"
                            value="{{ old('title') }}"
                            required
                            maxlength="255"
                            placeholder="e.g. Greeting - Thank you for contacting support"
                            class="form-input @error('title') border-rose-300 focus:border-rose-400 focus:ring-rose-200 @enderror"
                        >
                    </div>
                    @error('title')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="body" class="form-label">Body <span class="text-red-500">*</span></label>
                    <div class="mt-1">
                        <textarea
                            name="body"
                            id="body"
                            rows="5"
                            required
                            maxlength="5000"
                            placeholder="Write the reply template text here..."
                            class="form-input resize-y @error('body') border-rose-300 focus:border-rose-400 focus:ring-rose-200 @enderror"
                        >{{ old('body') }}</textarea>
                    </div>
                    @error('body')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="pt-1">
                    <button type="submit" class="btn-primary" data-loading-text="Creating...">Create Response</button>
                </div>
            </form>
        </div>

        {{-- List --}}
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
                <h2 class="text-lg font-semibold text-slate-900">All Responses <span class="ml-1 text-sm font-normal text-slate-500">({{ $responses->total() }})</span></h2>
                <form action="{{ route('admin.canned-responses.index') }}" method="GET" class="flex items-center gap-2">
                    <input
                        type="text"
                        name="search"
                        value="{{ request('search') }}"
                        placeholder="Search..."
                        class="form-input !py-1.5 !text-xs w-44"
                    >
                    <button type="submit" class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-200">Search</button>
                    @if(request('search'))
                        <a href="{{ route('admin.canned-responses.index') }}" class="text-xs text-slate-500 hover:text-slate-700">Clear</a>
                    @endif
                </form>
            </div>

            @if($responses->isEmpty())
                <div class="px-5 py-10 text-center text-sm text-slate-500">No canned responses yet. Create one using the form on the left.</div>
            @else
                <div class="divide-y divide-slate-100">
                    @foreach($responses as $response)
                        <div class="group px-5 py-4" x-data="{ editing: false }">
                            <div x-show="!editing">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-slate-900">{{ $response->title }}</p>
                                        <p class="mt-0.5 text-xs text-slate-500">By {{ optional($response->createdBy)->name ?? 'Unknown' }} &middot; {{ $response->created_at->format('M j, Y') }}</p>
                                        <p class="mt-2 line-clamp-2 text-sm text-slate-700">{{ $response->body }}</p>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-1 opacity-0 transition group-hover:opacity-100">
                                        <button type="button" @click="editing = true" class="rounded-lg px-2.5 py-1 text-xs font-medium text-hoshishi-blue-700 hover:bg-hoshishi-blue-50">Edit</button>
                                        <form action="{{ route('admin.canned-responses.destroy', $response) }}" method="POST" data-submit-feedback>
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-lg px-2.5 py-1 text-xs font-medium text-red-600 hover:bg-red-50">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <div x-show="editing" x-cloak>
                                <form action="{{ route('admin.canned-responses.update', $response) }}" method="POST" class="space-y-3" data-submit-feedback>
                                    @csrf
                                    @method('PUT')
                                    <input type="text" name="title" value="{{ $response->title }}" required maxlength="255" class="form-input !text-sm" placeholder="Title">
                                    <textarea name="body" rows="4" required maxlength="5000" class="form-input resize-y !text-sm" placeholder="Body">{{ $response->body }}</textarea>
                                    <div class="flex items-center gap-2">
                                        <button type="submit" class="rounded-lg bg-hoshishi-dark px-3 py-1.5 text-xs font-semibold text-white hover:bg-[#111a2e]" data-loading-text="Saving...">Save</button>
                                        <button type="button" @click="editing = false" class="rounded-lg px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-100">Cancel</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="border-t border-slate-200 px-5 py-3">
                    {{ $responses->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
