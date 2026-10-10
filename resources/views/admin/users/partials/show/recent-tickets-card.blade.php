<div class="mt-6 bg-white shadow sm:rounded-lg">
    <div class="px-4 py-5 sm:px-6">
        <h2 class="text-lg leading-6 font-medium text-slate-900">Recent Tickets</h2>
        <p class="mt-1 max-w-2xl text-sm text-slate-500">Latest tickets created or assigned to this user</p>
    </div>
    <div class="border-t border-slate-200">
        <ul class="divide-y divide-slate-200">
            @forelse($recentTickets as $ticket)
                <li class="px-4 py-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center min-w-0">
                            <div class="flex-shrink-0">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $ticket->status_color }}">
                                    {{ ucfirst(str_replace('_', ' ', $ticket->status)) }}
                                </span>
                            </div>
                            <div class="ml-4 min-w-0 flex-1">
                                <div class="text-sm font-medium text-slate-900 truncate">
                                    <a href="{{ route('admin.tickets.show', $ticket) }}" class="hover:text-hoshishi-blue-700">
                                        {{ $ticket->subject }}
                                    </a>
                                </div>
                                <div class="text-sm text-slate-500">
                                    {{ $ticket->ticket_number }}
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center">
                            <span class="mr-2 inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $ticket->priority_color }}">
                                {{ $ticket->priority_label }}
                            </span>
                            <div class="text-sm text-slate-500">
                                {{ $ticket->created_at->diffForHumans() }}
                            </div>
                        </div>
                    </div>
                </li>
            @empty
                <li class="px-4 py-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-slate-900">No tickets</h3>
                    <p class="mt-1 text-sm text-slate-500">This user hasn't created any tickets yet.</p>
                </li>
            @endforelse
        </ul>
    </div>
</div>
