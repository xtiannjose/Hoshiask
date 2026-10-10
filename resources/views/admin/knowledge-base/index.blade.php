@extends('layouts.app')

@section('title', 'Knowledge Base - ' . config('app.name'))

@section('content')
<div class="mx-auto max-w-[1460px] px-4 sm:px-6 lg:px-8" data-knowledge-base-page>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-display text-3xl font-semibold text-slate-900">Knowledge Base</h1>
            <p class="mt-1 text-sm text-slate-600">FAQs, policies, and reference articles for staff and field offices.</p>
        </div>
        @if(auth()->user()->canManageTickets())
            <a href="{{ route('admin.knowledge-base.create') }}" class="btn-primary">New Article</a>
        @endif
    </div>

    <form method="GET" action="{{ route('admin.knowledge-base.index') }}" class="mb-5 flex flex-wrap items-end gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div>
            <label for="search" class="form-label">Search</label>
            <input type="text" id="search" name="search" class="form-input" value="{{ request('search') }}" placeholder="Title or content">
        </div>
        <div class="w-44">
            <label for="category" class="form-label">Category</label>
            <select id="category" name="category" class="form-input">
                <option value="all" {{ request('category', 'all') === 'all' ? 'selected' : '' }}>All</option>
                @foreach($categories as $category)
                    <option value="{{ $category->value }}" {{ request('category') === $category->value ? 'selected' : '' }}>{{ $category->label() }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn-primary">Apply</button>
        <a href="{{ route('admin.knowledge-base.index') }}" class="btn-secondary">Reset</a>
    </form>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="divide-y divide-slate-100">
            @forelse($articles as $article)
                {{-- The title link is stretched over the whole row (after:absolute), so clicking anywhere on the row opens the article; Edit and Delete sit above it. --}}
                <div class="relative flex items-start justify-between gap-3 px-5 py-4 transition hover:bg-slate-50">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <a href="{{ route('admin.knowledge-base.show', $article) }}" class="text-sm font-semibold text-slate-900 after:absolute after:inset-0 hover:underline" data-kb-article="kb-article-{{ $article->id }}">{{ $article->title }}</a>
                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">{{ $article->category_label }}</span>
                            @unless($article->is_published)
                                <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">Draft</span>
                            @endunless
                        </div>
                        <p class="mt-1 text-xs text-slate-500">By {{ optional($article->createdBy)->name ?? 'Unknown' }} &middot; {{ $article->created_at->format('M j, Y') }}</p>
                    </div>
                    @if(auth()->user()->canManageTickets())
                        <div class="relative z-10 flex shrink-0 items-center gap-1">
                            <a href="{{ route('admin.knowledge-base.edit', $article) }}" class="rounded-lg px-2.5 py-1 text-xs font-medium text-hoshishi-blue-700 hover:bg-hoshishi-blue-50">Edit</a>
                            <form action="{{ route('admin.knowledge-base.destroy', $article) }}" method="POST" data-submit-feedback data-confirm="Delete this article? This cannot be undone.">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded-lg px-2.5 py-1 text-xs font-medium text-red-600 hover:bg-red-50">Delete</button>
                            </form>
                        </div>
                    @endif
                </div>
            @empty
                <div class="px-5 py-10 text-center text-sm text-slate-500" data-empty-state>
                    @if(request()->filled('search') || request('category', 'all') !== 'all')
                        <p>No articles match those filters.</p>
                        <a href="{{ route('admin.knowledge-base.index') }}" class="mt-3 inline-flex items-center py-1 font-semibold text-hoshishi-blue-700 underline">Show all articles</a>
                    @elseif(auth()->user()->canManageTickets())
                        <p>No articles yet. Articles you publish here are shared with field offices.</p>
                        <a href="{{ route('admin.knowledge-base.create') }}" class="btn-primary mt-4">Write the first article</a>
                    @else
                        <p>No articles have been published yet.</p>
                    @endif
                </div>
            @endforelse
        </div>
        <div class="border-t border-slate-200 px-5 py-3">
            {{ $articles->links() }}
        </div>
    </div>
</div>

<x-knowledge-base.article-modal :articles="$articles" staff :can-edit="auth()->user()->canManageTickets()" />
@endsection
