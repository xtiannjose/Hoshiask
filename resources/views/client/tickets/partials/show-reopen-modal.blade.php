<dialog id="reopen-ticket-modal" class="app-modal-root">
    <div class="app-modal-overlay absolute inset-0 bg-slate-900/35 backdrop-blur-[1px]" data-reopen-ticket-overlay="true"></div>
    <div class="relative z-10 min-h-dvh flex items-center justify-center p-4">
        <div class="app-modal-panel w-full max-w-lg rounded-2xl border border-slate-200 bg-white shadow-xl">
            <div class="px-5 py-4 border-b border-slate-200">
                <h3 class="text-lg font-semibold text-slate-900">Reopen Ticket</h3>
                <p class="mt-1 text-sm text-slate-600">Let us know why this ticket needs more work, and we'll pick it back up.</p>
            </div>
            <form action="{{ route('client.tickets.reopen', $ticket) }}" method="POST" class="space-y-4 p-5" data-submit-feedback>
                @csrf
                @if($hasReopenValidationErrors)
                    <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        Please provide a reason before reopening this ticket.
                    </div>
                @endif
                <div>
                    <label for="reopen_reason" class="form-label">Reason<span class="ml-1 text-red-500">*</span></label>
                    <textarea id="reopen_reason" name="reopen_reason" rows="4" class="form-input" placeholder="e.g. The issue came back after it was marked resolved..." required aria-invalid="{{ $errors->has('reopen_reason') ? 'true' : 'false' }}">{{ old('reopen_reason') }}</textarea>
                    @error('reopen_reason')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex flex-col-reverse gap-2.5 sm:flex-row sm:justify-end">
                    <button type="button" id="reopen-ticket-cancel" class="btn-secondary sm:min-w-[110px]">Cancel</button>
                    <button type="submit" class="btn-primary sm:min-w-[160px]" data-loading-text="Reopening...">Reopen Ticket</button>
                </div>
            </form>
        </div>
    </div>
</dialog>
