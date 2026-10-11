<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-200 px-4 py-4 sm:px-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Conversation</h2>
                <p class="mt-1 text-sm text-slate-500">Message thread between client and support.</p>
            </div>
            @php
                $hiddenReplyCount = (int) ($hiddenReplyCount ?? 0);
                $messageTotal = $ticket->replies->count() + $hiddenReplyCount + 1;
            @endphp
            <span id="admin-message-count" class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                {{ $messageTotal }} {{ \Illuminate\Support\Str::plural('message', $messageTotal) }}
            </span>
        </div>
    </div>

    @if($hiddenReplyCount > 0)
        <div class="border-b border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-800 sm:px-6" role="status">
            The {{ $hiddenReplyCount }} oldest {{ \Illuminate\Support\Str::plural('message', $hiddenReplyCount) }} {{ $hiddenReplyCount === 1 ? 'is' : 'are' }} not shown.
            <a href="{{ request()->fullUrlWithQuery(['all_messages' => 1]) }}" class="font-semibold underline">Show all messages</a>
        </div>
    @endif

    <div id="admin-conversation-live-region" class="sr-only" aria-live="polite" role="status"></div>

    <div id="admin-conversation-thread" class="max-h-[60vh] min-h-56 space-y-4 sm:max-h-[560px] overflow-y-auto bg-gradient-to-b from-slate-50/80 to-white px-4 py-5 sm:px-6" data-ticket-id="{{ (int) $ticket->id }}" data-replies-url="{{ route('admin.tickets.replies.feed', $ticket) }}" data-seen-url="{{ route('admin.notifications.seen', $ticket) }}" data-replies-cursor="{{ $replyFeedCursor }}">
        <div class="js-time-separator py-1 text-center text-xs font-semibold uppercase tracking-wide text-slate-500" data-time="{{ $ticket->created_at->toIso8601String() }}">
            {{ $ticket->created_at->greaterThan(now()->subDay()) ? $ticket->created_at->format('g:i A') : $ticket->created_at->format('M j, Y') }}
        </div>
        <div class="js-chat-row flex justify-start" data-created-at="{{ $ticket->created_at->toIso8601String() }}">
            <div class="flex w-full max-w-3xl items-start gap-2">
                <div class="mt-1 flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full border border-slate-200 bg-white">
                    <img src="{{ $clientCompanyLogo }}" alt="Client company logo" class="avatar-logo">
                </div>
                <div class="w-fit max-w-[82%] rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                    <p class="js-message-text whitespace-pre-wrap text-sm leading-6 text-slate-800">{!! nl2br(e($ticket->description)) !!}</p>

                    <x-ticket-thread.attachment-list :attachments="$ticket->attachments" />
                </div>
            </div>
        </div>

        @php
            $lastTimestamp = $ticket->created_at;
        @endphp
        @foreach($ticket->replies->sortBy('created_at') as $reply)
            @php
                $isInternal = (bool) $reply->is_internal;
                $fromSupport = $reply->user->canAccessAdminTickets();
                $isDeleted = (bool) $reply->deleted_at;
                $showEditedBadge = (bool) $reply->edited_at && ! $isDeleted;
                $showTimestamp = $reply->created_at->diffInMinutes($lastTimestamp) >= 15;
                $canManageReply = (int) ($reply->user_id === auth()->id());
                $lastTimestamp = $reply->created_at;
                $avatarLogo = $departmentLogo(data_get($reply, 'user.department'), $fromSupport);
            @endphp
            @if($showTimestamp)
                <div class="js-time-separator py-1 text-center text-xs font-semibold uppercase tracking-wide text-slate-500" data-time="{{ $reply->created_at->toIso8601String() }}">
                    {{ $reply->created_at->greaterThan(now()->subDay()) ? $reply->created_at->format('g:i A') : $reply->created_at->format('M j, Y') }}
                </div>
            @endif
            <div class="js-chat-row flex {{ $fromSupport ? 'justify-end' : 'justify-start' }}" data-created-at="{{ $reply->created_at->toIso8601String() }}" data-reply-id="{{ $reply->id }}" data-can-manage="{{ $canManageReply }}" data-is-internal="{{ $isInternal ? '1' : '0' }}">
                <div class="flex w-full max-w-3xl items-start gap-2 {{ $fromSupport ? 'justify-end' : '' }}">
                    <div class="mt-1 flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full border border-slate-200 bg-white {{ $fromSupport ? 'order-2' : '' }}">
                        <img src="{{ $avatarLogo }}" alt="{{ $fromSupport ? 'Support' : 'Client' }} company logo" class="avatar-logo">
                    </div>
                    <div class="js-chat-bubble relative group w-fit max-w-[82%] rounded-2xl border px-4 py-3 shadow-sm {{ $fromSupport ? 'order-1 border-hoshishi-blue-200 bg-hoshishi-blue-50' : 'border-slate-200 bg-white' }} {{ $isDeleted ? 'chat-bubble-deleted' : '' }}" data-message="{{ e($reply->message) }}" data-deleted="{{ $reply->deleted_at ? '1' : '0' }}" data-edited="{{ $reply->edited_at ? '1' : '0' }}">
                        <div class="js-state-row mb-1 flex items-center gap-2 {{ $showEditedBadge || $isDeleted ? '' : 'hidden' }}">
                            <span class="js-edited-badge chat-meta-badge {{ $showEditedBadge ? '' : 'hidden' }}">Edited</span>
                            <span class="js-deleted-badge chat-meta-badge chat-meta-badge--deleted {{ $isDeleted ? '' : 'hidden' }}">Deleted</span>
                        </div>
                        <div class="mb-1 flex items-center gap-2 {{ $isInternal ? '' : 'hidden' }}">
                            <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold uppercase tracking-wide text-amber-800">
                                Internal
                            </span>
                        </div>
                        <div class="js-reply-reference mb-2 {{ $reply->replyTo ? '' : 'hidden' }}">
                            <p class="mb-1 flex items-center gap-1 text-xs font-semibold text-slate-500">
                                <x-ui.icon name="reply" class="h-3.5 w-3.5" />
                                {{ $fromSupport ? 'Support replied' : 'Client replied' }}
                            </p>
                            <div class="rounded-full bg-slate-100 px-3 py-1.5 text-xs text-slate-700">
                                {{ $reply->replyTo ? \Illuminate\Support\Str::limit($reply->replyTo->message, 120) : '' }}
                            </div>
                        </div>
                        <p class="js-message-text whitespace-pre-wrap text-sm leading-6 {{ $reply->deleted_at ? 'italic text-slate-500' : 'text-slate-800' }}">{!! nl2br(e($reply->message)) !!}</p>

                        @if(! $reply->deleted_at)
                            <x-ticket-thread.attachment-list :attachments="$reply->attachments" />
                        @endif

                        <div class="js-message-actions absolute {{ $fromSupport ? '-left-[4.75rem]' : '-right-10' }} top-1.5 flex items-center gap-1 rounded-full border border-slate-200 bg-white/95 p-1 shadow-sm opacity-0 transition group-hover:opacity-100 group-focus-within:opacity-100">
                            @if($canManageReply)
                                <div class="relative">
                                    <button type="button" class="js-more-btn inline-flex h-7 w-7 items-center justify-center rounded-full bg-hoshishi-accent text-white hover:bg-hoshishi-accent-dark" aria-label="More actions">
                                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M10 6a1.5 1.5 0 110-3 1.5 1.5 0 010 3zm0 5.5a1.5 1.5 0 110-3 1.5 1.5 0 010 3zm0 5.5a1.5 1.5 0 110-3 1.5 1.5 0 010 3z"/>
                                        </svg>
                                    </button>
                                    <div class="js-more-menu absolute {{ $fromSupport ? 'left-0' : 'right-0' }} z-20 mt-1 hidden min-w-[110px] overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg">
                                        <button type="button" class="js-edit-msg block w-full px-3 py-2 text-left text-xs font-medium text-slate-700 hover:bg-slate-50 {{ $reply->deleted_at ? 'hidden' : '' }}">Edit</button>
                                        <button type="button" class="js-delete-msg block w-full px-3 py-2 text-left text-xs font-medium text-red-600 hover:bg-red-50 {{ $reply->deleted_at ? 'hidden' : '' }}">Delete</button>
                                    </div>
                                </div>
                            @endif
                            @if($canReplyToTicket)
                                <button type="button" class="js-reply-msg inline-flex h-7 w-7 items-center justify-center rounded-full bg-hoshishi-accent text-white hover:bg-hoshishi-accent-dark" aria-label="Reply to this message">
                                    <x-ui.icon name="reply" class="h-4 w-4" />
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @if($canReplyToTicket)
        @php
            $notesOnly = ! $ticket->acceptsClientReplies();
        @endphp
        <div class="border-t border-slate-200 px-4 pb-4 pt-2 sm:px-6">
            @if($notesOnly)
                <div class="mb-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800" data-notes-only-notice>
                    This ticket is {{ $ticket->isCancelled() ? 'cancelled' : 'closed' }}. Anything you post here is saved as an internal note and is not shown to the client.
                </div>
            @endif
            <form id="admin-ticket-reply-form" action="{{ route('admin.tickets.reply', $ticket) }}" method="POST" enctype="multipart/form-data" class="space-y-3" data-ticket-id="{{ (int) $ticket->id }}" data-client-logo="{{ $clientCompanyLogo }}" data-support-logo="{{ $supportCompanyLogo }}" data-replies-url="{{ route('admin.tickets.replies.feed', $ticket) }}" data-seen-url="{{ route('admin.notifications.seen', $ticket) }}" data-replies-cursor="{{ $replyFeedCursor }}" data-update-url-template="{{ route('admin.tickets.replies.update', ['ticket' => $ticket, 'reply' => '__REPLY__']) }}" data-delete-url-template="{{ route('admin.tickets.replies.delete', ['ticket' => $ticket, 'reply' => '__REPLY__']) }}" @if(config('helpdesk.features.canned_replies')) data-canned-responses-url="{{ route('admin.canned-responses.list') }}" @endif>
                @csrf
                <p id="admin-reply-error" class="hidden text-xs font-medium text-red-600"></p>
                <input type="hidden" id="admin_reply_to_id" name="reply_to_id" value="">
                <div id="admin-reply-target-banner" class="hidden items-center justify-between rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-600 shadow-sm">
                    <span id="admin-reply-target-text" class="truncate pr-3 font-medium"></span>
                    <button type="button" id="admin-clear-reply-target" class="rounded-lg px-2 py-0.5 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700">Cancel</button>
                </div>
                <div id="admin-edit-target-banner" class="hidden items-center justify-between rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 shadow-sm">
                    <span id="admin-edit-target-text" class="truncate pr-3 font-medium"></span>
                    <button type="button" id="admin-cancel-edit-target" class="rounded-lg px-2 py-0.5 text-amber-700 transition hover:bg-amber-100 hover:text-amber-900">Cancel edit</button>
                </div>
                <div class="px-0 py-0">
                    <textarea name="message" id="message" rows="1" aria-label="Your reply" class="block max-h-48 min-h-[44px] w-full resize-none overflow-hidden rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 placeholder-slate-500 focus:border-hoshishi-primary focus:outline-none focus:ring-2 focus:ring-hoshishi-primary/20" placeholder="Write your reply..."></textarea>
                </div>

                @if(config('helpdesk.features.canned_replies'))
                    <div id="canned-response-picker" class="hidden rounded-xl border border-slate-200 bg-white shadow-lg">
                        <div class="border-b border-slate-200 px-3 py-2.5">
                            <input type="text" id="canned-response-search" aria-label="Search canned responses" placeholder="Search canned responses..." class="block w-full rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-800 placeholder-slate-500 focus:border-hoshishi-primary focus:outline-none focus:ring-2 focus:ring-[#2c3d63]/20" autocomplete="off">
                        </div>
                        <ul id="canned-response-list" class="max-h-52 overflow-y-auto divide-y divide-slate-100 text-sm"></ul>
                        <p id="canned-response-empty" class="hidden px-4 py-3 text-xs text-slate-500">No matches found.</p>
                    </div>
                @endif

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <label for="attachments" class="inline-flex cursor-pointer items-center rounded-full border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-slate-600 transition hover:bg-slate-100">Attach</label>
                        <input type="file" name="attachments[]" id="attachments" multiple class="hidden" accept=".jpg,.jpeg,.png,.pdf,.docx,.txt,.xlsx">
                        <span id="admin-attachment-count" class="text-xs text-slate-500">No files selected</span>
                        @if(config('helpdesk.features.canned_replies'))
                            <button type="button" id="canned-response-btn" class="inline-flex items-center gap-1 rounded-full border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-slate-600 transition hover:bg-slate-100" aria-label="Insert canned response">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h8M8 14h5m-9 7h16a2 2 0 002-2V7a2 2 0 00-2-2H7L3 9v10a2 2 0 002 2z"/></svg>
                                Canned
                            </button>
                        @endif
                    </div>
                    <div class="flex items-center gap-4">
                        <label for="is_internal" class="inline-flex items-center gap-2 text-sm text-slate-900">
                            <input type="checkbox" name="is_internal" id="is_internal" value="1" class="h-4 w-4 rounded border-slate-300 text-hoshishi-blue-700 focus:ring-hoshishi-primary" @checked($notesOnly) @disabled($notesOnly)>
                            Internal note (hidden from client)
                        </label>
                        @if($notesOnly)
                            {{-- A disabled checkbox is not submitted, so send the value explicitly. --}}
                            <input type="hidden" name="is_internal" value="1">
                        @endif
                        <button id="admin-send-reply-btn" type="submit" class="inline-flex items-center rounded-full bg-hoshishi-dark px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[#111a2e] disabled:cursor-not-allowed disabled:opacity-70">
                            Send
                        </button>
                    </div>
                </div>
                <p class="text-xs text-slate-500">Press Enter to send, or Shift+Enter for a new line. You can also attach files (max {{ \App\Support\UploadLimits::perFileLabel() }} each).</p>
            </form>
        </div>
    @else
        <div class="border-t border-slate-200 px-4 py-4 sm:px-6">
            <p class="text-sm text-slate-600">
                You are viewing this ticket read-only, so there is no reply box. Only the assigned staff and supervisors can post here.
            </p>
        </div>
    @endif
</div>
