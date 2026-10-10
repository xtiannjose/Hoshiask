@props(['attachments', 'borderClass' => 'border-slate-200'])

@if($attachments && $attachments->count() > 0)
    <div class="js-attachments-wrap mt-4 flex flex-wrap gap-2">
        @foreach($attachments as $attachment)
            @if(str_starts_with((string) $attachment->mime_type, 'image/'))
                <a href="{{ $attachment->download_url }}"
                   class="js-attachment-link block w-[240px] max-w-full overflow-hidden rounded-xl border {{ $borderClass }} bg-white p-2 text-sm transition hover:bg-slate-50"
                   data-file-url="{{ $attachment->preview_url }}"
                   data-file-name="{{ $attachment->original_filename }}"
                   data-file-mime="{{ $attachment->mime_type }}">
                    <img src="{{ $attachment->preview_url }}" alt="{{ $attachment->original_filename }}" class="h-36 w-full rounded-lg object-cover">
                    <span class="mt-2 block truncate text-xs text-slate-600">{{ $attachment->original_filename }}</span>
                </a>
            @else
                <a href="{{ $attachment->download_url }}"
                   class="{{ $attachment->preview_url ? 'js-attachment-link ' : '' }}flex max-w-full items-center rounded-xl border {{ $borderClass }} bg-white px-3 py-2 text-sm transition hover:bg-slate-50"
                   @if($attachment->preview_url)
                       data-file-url="{{ $attachment->preview_url }}"
                       data-file-name="{{ $attachment->original_filename }}"
                       data-file-mime="{{ $attachment->mime_type }}"
                   @endif>
                    <svg class="mr-2 h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                    </svg>
                    <span class="truncate">{{ $attachment->original_filename }}</span>
                </a>
            @endif
        @endforeach
    </div>
@endif
