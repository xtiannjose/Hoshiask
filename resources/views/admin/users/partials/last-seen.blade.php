@if($isOnline)
    <span class="inline-flex items-center gap-1.5 text-sm font-medium text-emerald-700">
        <span class="h-2 w-2 rounded-full bg-emerald-500" aria-hidden="true"></span>
        Online now
    </span>
@elseif($user->last_seen_at)
    <span class="text-sm text-slate-700" title="{{ $user->last_seen_at->format('M j, Y g:i A') }}">
        {{ $user->last_seen_at->diffForHumans() }}
    </span>
@else
    <span class="text-sm text-slate-500">Never</span>
@endif
