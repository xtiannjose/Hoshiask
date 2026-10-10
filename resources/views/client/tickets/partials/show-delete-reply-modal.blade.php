<dialog id="delete-reply-modal" class="app-modal-root">
    <div class="app-modal-overlay absolute inset-0 bg-slate-900/35 backdrop-blur-[1px]" data-modal-overlay="delete-reply"></div>
    <div class="relative z-10 flex min-h-dvh items-center justify-center p-4">
        <div class="app-modal-panel w-full max-w-md rounded-2xl border border-slate-200 bg-white shadow-xl">
            <div class="border-b border-slate-200 px-5 py-4">
                <h3 class="text-base font-semibold text-slate-900">Delete Message</h3>
                <p class="mt-1 text-sm text-slate-500">This will mark the selected message as deleted.</p>
            </div>
            <div class="space-y-4 px-5 py-4">
                <label for="delete-reply-confirm" class="flex cursor-pointer items-start gap-2 text-sm text-slate-700">
                    <input id="delete-reply-confirm" type="checkbox" class="ticket-checkbox mt-0.5">
                    <span>I confirm that this message should be deleted.</span>
                </label>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn-secondary" data-modal-close="delete-reply">Cancel</button>
                    <button id="delete-reply-submit" type="button" class="btn-danger disabled:cursor-not-allowed disabled:opacity-60" disabled>Delete Message</button>
                </div>
            </div>
        </div>
    </div>
</dialog>
