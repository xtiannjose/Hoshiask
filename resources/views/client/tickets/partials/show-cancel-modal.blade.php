<dialog id="cancel-ticket-modal" class="app-modal-root">
    <div class="app-modal-overlay absolute inset-0 bg-slate-900/35 backdrop-blur-[1px]" data-cancel-ticket-overlay="true"></div>
    <div class="relative z-10 min-h-dvh flex items-center justify-center p-4">
        <div class="app-modal-panel w-full max-w-lg rounded-2xl border border-slate-200 bg-white shadow-xl">
            <div class="px-5 py-4 border-b border-slate-200">
                <h3 class="text-lg font-semibold text-slate-900">Cancel Ticket</h3>
                <p class="mt-1 text-sm text-slate-600">Let us know why you no longer need this ticket. This cannot be undone.</p>
            </div>
            <form action="{{ route('client.tickets.cancel', $ticket) }}" method="POST" class="space-y-4 p-5" data-submit-feedback>
                @csrf
                @if($hasCancelValidationErrors)
                    <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        Please provide a reason before cancelling this ticket.
                    </div>
                @endif
                <div>
                    <label for="cancel_reason" class="form-label">Reason<span class="ml-1 text-red-500">*</span></label>
                    <textarea id="cancel_reason" name="cancel_reason" rows="4" class="form-input" placeholder="e.g. This is no longer needed..." required aria-invalid="{{ $errors->has('cancel_reason') ? 'true' : 'false' }}">{{ old('cancel_reason') }}</textarea>
                    @error('cancel_reason')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex flex-col-reverse gap-2.5 sm:flex-row sm:justify-end">
                    <button type="button" id="cancel-ticket-dismiss" class="btn-secondary sm:min-w-[110px]">Never mind</button>
                    <button type="submit" class="btn-danger sm:min-w-[160px]" data-loading-text="Cancelling...">Cancel Ticket</button>
                </div>
            </form>
        </div>
    </div>
</dialog>
