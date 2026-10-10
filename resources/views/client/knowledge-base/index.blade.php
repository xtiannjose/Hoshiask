@extends('layouts.app')

@section('title', 'Knowledge Base - ' . config('app.name'))

@section('content')
<div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8" data-knowledge-base-page>
    <div class="mb-6">
        <h1 class="font-display text-3xl font-semibold text-slate-900">Knowledge Base</h1>
        <p class="mt-1 text-sm text-slate-600">FAQs, policies, and reference articles. Check here before filing a ticket.</p>
    </div>

    <form method="GET" action="{{ route('client.knowledge-base.index') }}" class="mb-5 flex flex-wrap items-end gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div>
            <label for="search" class="form-label">Search</label>
            <input type="text" id="search" name="search" class="form-input" value="{{ request('search') }}" placeholder="Title or content">
        </div>
        <div>
            <label for="category" class="form-label">Category</label>
            <select id="category" name="category" class="form-input">
                <option value="all" {{ request('category', 'all') === 'all' ? 'selected' : '' }}>All</option>
                @foreach($categories as $category)
                    <option value="{{ $category->value }}" {{ request('category') === $category->value ? 'selected' : '' }}>{{ $category->label() }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn-primary">Apply</button>
    </form>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="divide-y divide-slate-100">
            @forelse($articles as $article)
                <a href="{{ route('client.knowledge-base.show', $article) }}" class="block px-5 py-4 transition hover:bg-slate-50" data-kb-article="kb-article-{{ $article->id }}">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">{{ $article->category_label }}</span>
                    </div>
                    <p class="mt-1 text-sm font-semibold text-slate-900">{{ $article->title }}</p>
                </a>
            @empty
                <div class="px-5 py-10 text-center text-sm text-slate-500" data-empty-state>
                    @if(request()->filled('search') || request('category', 'all') !== 'all')
                        <p>No articles match those filters.</p>
                        <a href="{{ route('client.knowledge-base.index') }}" class="mt-3 inline-flex items-center py-1 font-semibold text-hoshishi-blue-700 underline">Show all articles</a>
                    @else
                        <p>No articles have been published yet. If you need help now, you can send us a ticket.</p>
                        <a href="{{ route('client.tickets.create') }}" class="btn-primary mt-4">Create a ticket</a>
                    @endif
                </div>
            @endforelse
        </div>
        <div class="border-t border-slate-200 px-5 py-3">
            {{ $articles->links() }}
        </div>
    </div>
</div>

<x-knowledge-base.article-modal :articles="$articles" />
@endsection
