<div class="bg-white shadow sm:rounded-lg">
    <div class="px-4 py-5 sm:px-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <h1 class="wrap-anywhere text-2xl font-semibold text-slate-900">{{ $ticket->subject }}</h1>
                <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-slate-500">
                    <span class="font-medium">{{ $ticket->ticket_number }}</span>
                    <span class="hidden text-slate-300 sm:inline">&bull;</span>
                    <span>Created {{ $ticket->created_at->format('M j, Y \a\t g:i A') }}</span>
                    @if($ticket->assigned_user_ids !== [])
                        <span class="hidden text-slate-300 sm:inline">&bull;</span>
                        <span>Assigned to {{ $ticket->assigned_users_label }}</span>
                    @endif
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $ticket->status_color }}">
                    {{ ucfirst(str_replace('_', ' ', $ticket->status)) }}
                </span>
                @if($ticket->isEscalated())
                    <span class="inline-flex items-center rounded-full bg-orange-100 px-3 py-1 text-sm font-medium text-orange-800">
                        Escalated for further review
                    </span>
                @endif
            </div>
        </div>
    </div>
</div>
