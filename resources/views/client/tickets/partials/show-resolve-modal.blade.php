<dialog id="resolve-ticket-modal" class="app-modal-root">
    <div class="app-modal-overlay absolute inset-0 bg-slate-900/35 backdrop-blur-[1px]" data-resolve-ticket-overlay="true"></div>
    <div class="relative z-10 min-h-dvh flex items-center justify-center p-4">
        <div class="app-modal-panel w-full max-w-lg rounded-2xl border border-slate-200 bg-white shadow-xl">
            <div class="px-5 py-4 border-b border-slate-200">
                <h3 class="text-lg font-semibold text-slate-900">Mark Ticket as Resolved</h3>
                <p class="mt-1 text-sm text-slate-600">Thanks for confirming. How did we do?</p>
            </div>
            <form action="{{ route('client.tickets.resolve', $ticket) }}" method="POST" class="space-y-4 p-5" data-submit-feedback data-rating-form>
                @csrf
                @if($hasResolveValidationErrors)
                    <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        Please check the highlighted fields before resolving this ticket.
                    </div>
                @endif
                <div class="space-y-3 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div>
                        <x-ui.star-rating id-prefix="resolve-rating" :value="old('rating')" :invalid="$errors->has('rating')" />
                        @error('rating')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="resolve_comment" class="form-label">Comment <span class="text-slate-500" data-rating-comment-marker>(optional)</span></label>
                        <textarea id="resolve_comment" name="comment" rows="3" class="form-input" placeholder="Anything you would like to tell us?" data-rating-comment aria-describedby="resolve-comment-hint" aria-invalid="{{ $errors->has('comment') ? 'true' : 'false' }}">{{ old('comment') }}</textarea>
                        <p id="resolve-comment-hint" class="mt-1 text-xs font-medium text-amber-700" data-rating-comment-hint hidden>Please tell us what went wrong so we can put it right.</p>
                        @error('comment')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
                <label class="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700">
                    <input id="resolve_confirm_checkbox" name="resolve_confirmation" type="checkbox" value="1" required {{ old('resolve_confirmation') ? 'checked' : '' }} class="ticket-checkbox mt-0.5">
                    <span class="leading-5">I confirm this ticket has been resolved and can be marked as closed for support follow-up.</span>
                </label>
                @error('resolve_confirmation')
                    <p class="-mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
                <div class="flex flex-col-reverse gap-2.5 sm:flex-row sm:justify-end">
                    <button type="button" id="resolve-ticket-cancel" class="btn-secondary sm:min-w-[110px]">Cancel</button>
                    <button id="resolve-confirm-submit" type="submit" class="btn-success sm:min-w-[220px] disabled:cursor-not-allowed disabled:opacity-60" data-loading-text="Resolving..." disabled>Confirm Resolved</button>
                </div>
            </form>
        </div>
    </div>
</dialog>
