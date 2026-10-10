<div id="ticket-actions" class="bg-white shadow sm:rounded-lg">
    <div class="px-4 py-5 sm:px-6">
        <h2 class="text-lg leading-6 font-medium text-slate-900">Actions</h2>
    </div>
    <div class="border-t border-slate-200 px-4 py-5 sm:px-6 space-y-4">
        @if($ticket->status === 'resolved' && !$ticket->satisfaction_rating)
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <div class="mb-3">
                    <h3 class="text-sm font-semibold text-slate-900">Thanks for confirming. How did we do?</h3>
                    <p class="mt-1 text-sm text-slate-600">Your rating helps us improve. A comment is optional unless you give 1 or 2 stars.</p>
                </div>
                <form action="{{ route('client.tickets.rate', $ticket) }}" method="POST" data-rating-form>
                    @csrf
                    <div class="space-y-3">
                        <div>
                            <x-ui.star-rating id-prefix="sidebar-rating" :value="old('rating')" :invalid="$errors->has('rating')" />
                            @error('rating')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="sidebar-comment" class="form-label">Comment <span class="text-slate-500" data-rating-comment-marker>(optional)</span></label>
                            <textarea name="comment" id="sidebar-comment" rows="3" class="form-input" placeholder="Anything you would like to tell us?" data-rating-comment aria-describedby="sidebar-comment-hint">{{ old('comment') }}</textarea>
                            <p id="sidebar-comment-hint" class="mt-1 text-xs font-medium text-amber-700" data-rating-comment-hint hidden>Please tell us what went wrong so we can put it right.</p>
                            @error('comment')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <button type="submit" class="btn-success w-full">Submit Rating</button>
                    </div>
                </form>
            </div>
        @elseif($ticket->satisfaction_rating)
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <h3 class="text-sm font-semibold text-slate-900 mb-2">Your Rating</h3>
                <div class="flex items-center mb-2">
                    @for($i = 1; $i <= 5; $i++)
                        <svg class="w-4 h-4 {{ $i <= $ticket->satisfaction_rating ? 'text-yellow-400' : 'text-slate-300' }}" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                        </svg>
                    @endfor
                </div>
                @if($ticket->satisfaction_comment)
                    <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500">Your comment</p>
                    <p class="text-sm text-slate-600">{{ $ticket->satisfaction_comment }}</p>
                @endif
            </div>
        @endif

        @if($ticket->isCancelled())
            <p class="text-sm text-slate-600">This ticket was cancelled and can no longer be updated.</p>
        @else
            @if(!in_array($ticket->status, ['resolved', 'closed']))
                <button type="button" id="open-resolve-ticket-modal" class="btn-success w-full">
                    Mark as Resolved
                </button>
            @else
                <button type="button" id="open-reopen-ticket-modal" class="btn-secondary w-full">
                    Reopen Ticket
                </button>
            @endif

            @if($ticket->isCancellable())
                <button type="button" id="open-cancel-ticket-modal" class="btn-danger w-full">
                    Cancel Ticket
                </button>
            @endif
        @endif
    </div>
</div>
