{{-- The popup that shows an article on top of the list. The page stays visible behind it; clicking outside, the X or
     Escape closes it. Each article's content sits in an inert <template> and is copied in when its row is clicked,
     so opening is instant. Without JavaScript the rows are ordinary links to the full article page. --}}
@props([
    'articles',
    'staff' => false,
    'canEdit' => false,
])
<dialog id="kb-article-modal" class="app-modal-root" aria-labelledby="kb-modal-title">
    <div class="app-modal-overlay absolute inset-0 bg-slate-900/35 backdrop-blur-[1px]" data-modal-overlay></div>
    <div class="pointer-events-none relative z-10 flex min-h-dvh items-center justify-center p-4">
        <div class="app-modal-panel pointer-events-auto relative flex max-h-[85vh] w-full max-w-2xl flex-col rounded-2xl border border-slate-200 bg-white shadow-xl">
            <button type="button" class="app-pressable absolute right-3 top-3 inline-flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-700" data-modal-close aria-label="Close article">
                <x-ui.icon name="x" class="h-5 w-5" />
            </button>
            <div class="overflow-y-auto overscroll-contain p-6" data-kb-modal-content tabindex="-1"></div>
        </div>
    </div>
</dialog>
<div hidden>
    @foreach($articles as $article)
        <template id="kb-article-{{ $article->id }}">
            <x-knowledge-base.article :article="$article" heading="h2" heading-id="kb-modal-title" :staff="$staff" :can-edit="$canEdit" />
        </template>
    @endforeach
</div>
