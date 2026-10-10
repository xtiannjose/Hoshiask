{{-- The revert dialog posts a status change, so only people who can manage the ticket get it. --}}
@if($canManageTicket ?? false)
<dialog id="revert-ticket-modal" class="app-modal-root">
    <div class="app-modal-overlay absolute inset-0 bg-slate-900/35 backdrop-blur-[1px]" data-modal-overlay="revert"></div>
    <div class="relative z-10 flex min-h-dvh items-center justify-center p-4">
        <div class="app-modal-panel w-full max-w-md rounded-2xl border border-slate-200 bg-white shadow-xl">
            <div class="border-b border-slate-200 px-5 py-4">
                <h3 class="text-base font-semibold text-slate-900">Confirm Revert</h3>
            </div>
            <form id="revert-ticket-form" action="{{ route('admin.tickets.status', $ticket) }}" method="POST" class="space-y-4 px-5 py-4">
                @csrf
                <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
                <input type="hidden" name="status" value="in_progress">
                <p class="text-sm text-slate-600">This will move ticket <strong>#{{ $ticket->ticket_number }}</strong> back to <strong>In Progress</strong>.</p>
                <label for="revert_confirm" class="flex cursor-pointer items-start gap-2 text-sm text-slate-700">
                    <input id="revert_confirm" type="checkbox" class="ticket-checkbox mt-0.5" required>
                    <span>I confirm that this ticket should be reverted.</span>
                </label>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn-secondary" data-modal-close="revert">Cancel</button>
                    <button id="revert_submit" type="submit" class="btn-primary disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:brightness-100" disabled>Confirm Revert</button>
                </div>
            </form>
        </div>
    </div>
</dialog>
@endif

<dialog id="attachment-modal" class="app-modal-root">
    <div class="app-modal-overlay absolute inset-0 bg-slate-900/35 backdrop-blur-[1px]" data-modal-close="true"></div>
    <div class="relative z-10 min-h-dvh flex items-center justify-center p-4 pointer-events-none">
        <div class="app-modal-panel pointer-events-auto w-full max-w-5xl rounded-2xl border border-slate-200 bg-white shadow-xl">
            <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                <h3 id="attachment-modal-title" class="text-sm font-medium text-slate-900">Attachment Preview</h3>
                <button type="button" id="attachment-modal-close" aria-label="Close" class="text-xl leading-none text-slate-500 hover:text-slate-700">&times;</button>
            </div>
            <div class="bg-slate-50 p-4">
                <img id="attachment-modal-image" class="hidden w-auto max-w-full h-auto max-h-[75vh] mx-auto" alt="Attachment preview">
                <iframe id="attachment-modal-frame" class="hidden w-full h-[75vh] border-0 bg-white"></iframe>
            </div>
        </div>
    </div>
</dialog>

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
