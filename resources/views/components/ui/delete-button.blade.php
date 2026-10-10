{{--
    A trash-icon button that asks "are you sure?" before anything is deleted.
    Usage: <x-ui.delete-button :action="route('...destroy', $item)" title="Delete Article" :name="$item->title" note="..." />
    Pair it with <x-ui.delete-confirm-modal /> once on the page; resources/js/lib/delete-confirm.js opens the dialog.
    Pass `blocked` (the reason) for something that cannot be deleted: the icon is greyed out and the reason is its tooltip.
--}}
@props(['action', 'title', 'name', 'note' => null, 'blocked' => null])

<button type="button"
    data-delete-confirm
    data-delete-action="{{ $action }}"
    data-delete-title="{{ $title }}"
    data-delete-name="{{ $name }}"
    @if(filled($note)) data-delete-note="{{ $note }}" @endif
    @if(filled($blocked)) aria-disabled="true" @endif
    aria-label="{{ $title }}: {{ $name }}{{ filled($blocked) ? '. '.$blocked : '' }}"
    title="{{ filled($blocked) ? $blocked : $title }}"
    {{ $attributes->merge(['class' => 'inline-flex h-8 w-8 items-center justify-center rounded-lg text-red-600 transition hover:bg-red-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-300 aria-disabled:cursor-not-allowed aria-disabled:text-slate-300 aria-disabled:hover:bg-transparent']) }}>
    <x-ui.icon name="trash" class="h-4 w-4" />
</button>
