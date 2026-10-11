@extends('layouts.app')

@section('title', 'Assignment Rules - ' . config('app.name'))

@section('content')
<div class="mx-auto max-w-[1460px] px-4 sm:px-6 lg:px-8">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Ticket Assignment Rules</h1>
            <p class="mt-1 text-sm text-slate-500">
                Automatically assign new, unassigned tickets to a technician based on their category, using the weights below.
                Weights are relative shares within a category &mdash; they don't need to add up to 100.
            </p>
        </div>
    </div>

    <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="mb-3 text-lg font-semibold text-slate-900">Distribution Method</h2>
        <form action="{{ route('admin.assignment-rules.distribution-method') }}" method="POST" class="flex flex-wrap items-center gap-4" data-submit-feedback>
            @csrf
            @method('PUT')
            <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                <input type="radio" name="distribution_method" value="weighted_random" class="h-4 w-4" {{ $distributionMethod === 'weighted_random' ? 'checked' : '' }}>
                <span>
                    <span class="font-semibold">Weighted random</span>
                    &mdash; each new ticket independently rolls against the weights.
                </span>
            </label>
            <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                <input type="radio" name="distribution_method" value="rotating_counter" class="h-4 w-4" {{ $distributionMethod === 'rotating_counter' ? 'checked' : '' }}>
                <span>
                    <span class="font-semibold">Rotating counter</span>
                    &mdash; always gives the next ticket to whoever is furthest below their target share.
                </span>
            </label>
            <button type="submit" class="btn-secondary py-2" data-loading-text="Saving...">Save</button>
        </form>
    </div>

    <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-900">Availability</h2>
        <p class="mt-1 text-sm text-slate-500">
            Someone who is away is skipped by the rules above until the day they are back, so they are not given tickets
            they cannot answer. Staff can still assign them by hand. Leave the day empty to make them available now.
        </p>
        <div class="mt-4 divide-y divide-slate-200">
            @forelse($technicians as $technician)
                @php($away = $technician->assignment_paused_until && $technician->assignment_paused_until->isFuture())
                <form action="{{ route('admin.assignment-rules.availability', $technician) }}" method="POST" class="flex flex-wrap items-center gap-3 py-3" data-submit-feedback>
                    @csrf
                    @method('PUT')
                    <div class="min-w-[12rem] flex-1">
                        <p class="text-sm font-semibold text-slate-900">{{ $technician->name }}</p>
                        <p class="text-sm {{ $away ? 'text-amber-700' : 'text-slate-500' }}">
                            {{ $away ? 'Away until '.$technician->assignment_paused_until->format('M j, Y') : 'Available' }}
                        </p>
                    </div>
                    <label for="back_on_{{ $technician->id }}" class="text-sm text-slate-600">Back on</label>
                    <input type="date" name="back_on" id="back_on_{{ $technician->id }}" class="form-input w-auto"
                           value="{{ $away ? $technician->assignment_paused_until->format('Y-m-d') : '' }}"
                           min="{{ now()->addDay()->format('Y-m-d') }}">
                    <button type="submit" class="btn-secondary py-2" data-loading-text="Saving...">Save</button>
                </form>
            @empty
                <p class="py-3 text-sm text-slate-500">No active technicians yet.</p>
            @endforelse
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[380px_minmax(0,1fr)]">
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-lg font-semibold text-slate-900">Add Rule</h2>
            </div>

            <form action="{{ route('admin.assignment-rules.store') }}" method="POST" class="space-y-5 px-5 py-5" data-submit-feedback>
                @csrf

                <div>
                    <label for="category_id" class="form-label">Category <span class="text-red-500">*</span></label>
                    <div class="mt-1">
                        <select name="category_id" id="category_id" required class="form-input @error('category_id') border-red-300 focus:border-red-400 focus:ring-red-200 @enderror">
                            <option value="">Select a category</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" {{ (string) old('category_id') === (string) $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @error('category_id')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="user_id" class="form-label">Technician <span class="text-red-500">*</span></label>
                    <div class="mt-1">
                        <select name="user_id" id="user_id" required class="form-input @error('user_id') border-red-300 focus:border-red-400 focus:ring-red-200 @enderror">
                            <option value="">Select a technician</option>
                            @foreach($technicians as $technician)
                                <option value="{{ $technician->id }}" {{ (string) old('user_id') === (string) $technician->id ? 'selected' : '' }}>{{ $technician->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @error('user_id')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="weight" class="form-label">Weight (1-100) <span class="text-red-500">*</span></label>
                    <div class="mt-1">
                        <input type="number" name="weight" id="weight" min="1" max="100" value="{{ old('weight', 50) }}" required class="form-input @error('weight') border-red-300 focus:border-red-400 focus:ring-red-200 @enderror">
                    </div>
                    @error('weight')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="pt-2">
                    <button type="submit" class="btn-primary" data-loading-text="Creating...">Create Rule</button>
                </div>
            </form>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-lg font-semibold text-slate-900">Rules by Category</h2>
            </div>

            <div class="max-h-[70vh] divide-y divide-slate-200 overflow-auto">
                @forelse($categories as $category)
                    @php($categoryRules = $rulesByCategory->get($category->id, collect()))
                    <div class="px-5 py-4">
                        <p class="mb-2 text-sm font-semibold text-slate-900">{{ $category->name }}</p>
                        @forelse($categoryRules as $rule)
                            <div class="mb-2 flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                                <span class="text-slate-700">{{ optional($rule->technician)->name ?? 'Unknown technician' }}</span>
                                <div class="flex items-center gap-3">
                                    <span class="font-semibold text-slate-900">{{ $rule->weight }}%</span>
                                    <form action="{{ route('admin.assignment-rules.destroy', $rule) }}" method="POST" data-submit-feedback>
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 transition hover:text-red-800" aria-label="Remove rule for {{ optional($rule->technician)->name }}">
                                            Remove
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500">No rules configured for this category.</p>
                        @endforelse
                    </div>
                @empty
                    <p class="px-5 py-4 text-sm text-slate-500">No active categories yet.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
