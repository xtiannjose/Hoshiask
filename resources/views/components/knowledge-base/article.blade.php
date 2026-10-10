{{-- One knowledge base article: used by the full article page and by the popup on the list, so they always match. --}}
@props([
    'article',
    'heading' => 'h1',
    'headingId' => null,
    // Staff view: also shows the Draft badge, the author line and (when allowed) the Edit button.
    'staff' => false,
    'canEdit' => false,
])
@php
    $headingClass = $heading === 'h1'
        ? 'mt-2 font-display text-2xl font-semibold text-slate-900'
        : 'mt-2 pr-10 font-display text-xl font-semibold text-slate-900';
@endphp
<div class="flex items-center gap-2">
    <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">{{ $article->category_label }}</span>
    @if($staff && ! $article->is_published)
        <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">Draft</span>
    @endif
</div>
<{{ $heading }} @if($headingId) id="{{ $headingId }}" @endif class="{{ $headingClass }}">{{ $article->title }}</{{ $heading }}>
@if($staff)
    <p class="mt-1 text-xs text-slate-500">By {{ optional($article->createdBy)->name ?? 'Unknown' }} &middot; {{ $article->created_at->format('M j, Y') }}</p>
@endif
<div class="prose mt-5 max-w-none whitespace-pre-wrap text-sm text-slate-700">{{ $article->body }}</div>
@if($staff && $canEdit)
    <div class="mt-6 border-t border-slate-100 pt-4">
        <a href="{{ route('admin.knowledge-base.edit', $article) }}" class="btn-secondary">Edit Article</a>
    </div>
@endif
