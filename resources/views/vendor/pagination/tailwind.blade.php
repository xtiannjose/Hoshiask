@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="app-pagination">
        <div class="flex flex-col gap-3 sm:hidden">
            <p class="text-sm text-slate-600">
                {!! __('Showing') !!}
                @if ($paginator->firstItem())
                    <span class="font-semibold text-slate-900">{{ $paginator->firstItem() }}</span>
                    {!! __('to') !!}
                    <span class="font-semibold text-slate-900">{{ $paginator->lastItem() }}</span>
                @else
                    {{ $paginator->count() }}
                @endif
                {!! __('of') !!}
                <span class="font-semibold text-slate-900">{{ $paginator->total() }}</span>
                {!! __('results') !!}
            </p>

            <div class="grid grid-cols-2 gap-2">
                @if ($paginator->onFirstPage())
                    <span class="app-pagination-mobile app-pagination-mobile--disabled">
                        {!! __('pagination.previous') !!}
                    </span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="app-pagination-mobile">
                        {!! __('pagination.previous') !!}
                    </a>
                @endif

                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="app-pagination-mobile">
                        {!! __('pagination.next') !!}
                    </a>
                @else
                    <span class="app-pagination-mobile app-pagination-mobile--disabled">
                        {!! __('pagination.next') !!}
                    </span>
                @endif
            </div>
        </div>

        <div class="hidden items-center justify-between gap-4 sm:flex">
            <p class="text-sm text-slate-600">
                {!! __('Showing') !!}
                @if ($paginator->firstItem())
                    <span class="font-semibold text-slate-900">{{ $paginator->firstItem() }}</span>
                    {!! __('to') !!}
                    <span class="font-semibold text-slate-900">{{ $paginator->lastItem() }}</span>
                @else
                    {{ $paginator->count() }}
                @endif
                {!! __('of') !!}
                <span class="font-semibold text-slate-900">{{ $paginator->total() }}</span>
                {!! __('results') !!}
            </p>

            <div class="app-pagination-track">
                @if ($paginator->onFirstPage())
                    <span role="link" aria-disabled="true" aria-label="{{ __('pagination.previous') }}" class="app-pagination-arrow app-pagination-arrow--disabled">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('pagination.previous') }}" class="app-pagination-arrow">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </a>
                @endif

                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span aria-disabled="true" class="app-pagination-page app-pagination-page--ellipsis">{{ $element }}</span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span aria-current="page" class="app-pagination-page app-pagination-page--current">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" aria-label="{{ __('Go to page :page', ['page' => $page]) }}" class="app-pagination-page">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('pagination.next') }}" class="app-pagination-arrow">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                @else
                    <span role="link" aria-disabled="true" aria-label="{{ __('pagination.next') }}" class="app-pagination-arrow app-pagination-arrow--disabled">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </span>
                @endif
            </div>
        </div>
    </nav>
@endif
