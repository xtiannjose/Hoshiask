<div class="border-t border-slate-200 px-4 pb-4 pt-2 sm:px-6">
    @if($ticket->acceptsClientReplies())
        <form
            id="ticket-reply-form"
            action="{{ route('client.tickets.reply', $ticket) }}"
            method="POST"
            enctype="multipart/form-data"
            class="space-y-3"
            data-ticket-id="{{ (int) $ticket->id }}"
            data-client-logo="{{ $clientCompanyLogo }}"
            data-support-logo="{{ $supportCompanyLogo }}"
            data-replies-url="{{ route('client.tickets.replies.feed', $ticket) }}"
            data-seen-url="{{ route('client.notifications.seen', $ticket) }}"
            data-replies-cursor="{{ $replyFeedCursor }}"
            data-update-url-template="{{ route('client.tickets.replies.update', ['ticket' => $ticket, 'reply' => '__REPLY__']) }}"
            data-delete-url-template="{{ route('client.tickets.replies.delete', ['ticket' => $ticket, 'reply' => '__REPLY__']) }}"
            data-edit-window-ms="{{ \App\Models\TicketReply::editWindowMinutes() * 60 * 1000 }}"
            data-edit-window-label="{{ \App\Models\TicketReply::editWindowLabel() }}"
        >
            @csrf
            <p id="reply-error" class="hidden text-xs font-medium text-red-600"></p>
            <input type="hidden" id="reply_to_id" name="reply_to_id" value="">
            <div id="reply-target-banner" class="hidden items-center justify-between rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-600 shadow-sm">
                <span id="reply-target-text" class="truncate pr-3 font-medium"></span>
                <button type="button" id="clear-reply-target" class="rounded-lg px-2 py-0.5 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700">Cancel</button>
            </div>
            <div id="edit-target-banner" class="hidden items-center justify-between rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 shadow-sm">
                <span id="edit-target-text" class="truncate pr-3 font-medium"></span>
                <button type="button" id="cancel-edit-target" class="rounded-lg px-2 py-0.5 text-amber-700 transition hover:bg-amber-100 hover:text-amber-900">Cancel edit</button>
            </div>
            <div class="px-0 py-0">
                <textarea
                    name="message"
                    id="message"
                    aria-label="Your message"
                    rows="1"
                    class="block max-h-48 min-h-[44px] w-full resize-none overflow-hidden rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 placeholder-slate-500 focus:border-hoshishi-primary focus:outline-none focus:ring-2 focus:ring-hoshishi-primary/20"
                    placeholder="Write your message..."
                ></textarea>

                <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <label for="attachments" class="inline-flex cursor-pointer items-center rounded-full border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-slate-600 transition hover:bg-slate-100">Attach</label>
                        <input
                            type="file"
                            name="attachments[]"
                            id="attachments"
                            multiple
                            class="hidden"
                            accept=".jpg,.jpeg,.png,.pdf,.docx,.txt,.xlsx"
                        >
                        <p id="attachment-count" class="text-xs text-slate-500">No files selected</p>
                    </div>
                    <button id="send-reply-btn" type="submit" class="inline-flex items-center rounded-full bg-hoshishi-dark px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[#111a2e] disabled:cursor-not-allowed disabled:opacity-70">
                        Send
                    </button>
                </div>
                <p class="mt-2 text-xs text-slate-500">Press Enter to send, or Shift+Enter for a new line. You can also attach files (max {{ \App\Support\UploadLimits::perFileLabel() }} each).</p>
            </div>
        </form>
    @else
        <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600">
            This ticket is {{ $ticket->isCancelled() ? 'cancelled' : 'closed' }}. Reply is disabled.
        </div>
    @endif
</div>
